<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Hall;
use App\Models\HallInventory;
use App\Models\Venue;
use App\Services\Reservation\ReservationHandover;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReservationController extends Controller
{
    private const SUPPORTED_LOCALES = ['id', 'en', 'ja'];

    /** How far ahead the date picker looks for open dates: weddings are planned a year or two out. */
    private const AVAILABILITY_DAYS = 730;

    private const PHONE_PATTERN = '/^\+?[0-9\s\-().]{6,20}$/';

    /**
     * Client-facing validation messages, keyed by locale.
     *
     * @var array<string, array<string, string>>
     */
    public const MESSAGES = [
        'en' => [
            'invalid' => 'Please check this field.',
            'date_past' => 'The wedding date cannot be in the past.',
            'hall_unknown' => 'Please choose a hall.',
            'capacity' => 'This hall holds up to :max guests. Choose a larger hall or contact our wedding team.',
            'unavailable' => 'This hall is not available on that date.',
            'contact_email' => 'Please enter a valid email address.',
            'contact_phone' => 'Please enter a valid phone or WhatsApp number.',
        ],
        'id' => [
            'invalid' => 'Mohon periksa kolom ini.',
            'date_past' => 'Tanggal acara tidak boleh di masa lalu.',
            'hall_unknown' => 'Silakan pilih hall.',
            'capacity' => 'Hall ini menampung maksimal :max tamu. Pilih hall yang lebih besar atau hubungi tim wedding kami.',
            'unavailable' => 'Hall ini tidak tersedia pada tanggal tersebut.',
            'contact_email' => 'Masukkan alamat email yang valid.',
            'contact_phone' => 'Masukkan nomor telepon atau WhatsApp yang valid.',
        ],
        'ja' => [
            'invalid' => 'この項目をご確認ください。',
            'date_past' => '挙式日は過去にできません。',
            'hall_unknown' => '会場（ホール）を選択してください。',
            'capacity' => 'このホールは最大:max名様までです。広いホールを選ぶか、ウェディングチームにご相談ください。',
            'unavailable' => 'この日程では、このホールはご利用いただけません。',
            'contact_email' => '有効なメールアドレスを入力してください。',
            'contact_phone' => '有効な電話番号またはWhatsApp番号を入力してください。',
        ],
    ];

    public function __construct(
        private readonly ReservationService $reservations,
        private readonly ReservationHandover $handover,
    ) {}

    /**
     * The dates on which at least one hall is still free, so the date picker
     * can grey out the days that are fully booked or not yet opened.
     */
    public function availability(Request $request, string $venueSlug): JsonResponse
    {
        $venue = $this->publishedVenue($venueSlug);
        $today = CarbonImmutable::now($venue->timezone)->startOfDay();
        $until = $today->addDays(self::AVAILABILITY_DAYS);

        $halls = $venue->halls()->where('is_active', true)
            ->when($request->query('hall'), fn ($q, $slug) => $q->where('slug', $slug));

        $dates = HallInventory::whereIn('hall_id', $halls->select('id'))
            ->whereRaw('total_slots > booked_slots')
            ->whereDate('event_date', '>=', $today->toDateString())
            ->whereDate('event_date', '<=', $until->toDateString())
            ->orderBy('event_date')
            ->pluck('event_date')
            ->map(fn ($date) => CarbonImmutable::parse($date)->toDateString())
            ->unique()
            ->values();

        return response()->json([
            'today' => $today->toDateString(),
            'until' => $until->toDateString(),
            'dates' => $dates,
        ]);
    }

    public function quote(Request $request, string $venueSlug): JsonResponse
    {
        $venue = $this->publishedVenue($venueSlug);
        $locale = $this->locale($request, $venue);

        $data = $request->validate($this->eventRules($venue), $this->validationMessages($locale));
        [$hall, $eventDate, $extraHours] = $this->resolveEvent($venue, $data, $locale);

        $quote = $this->reservations->quote($hall, $eventDate, $extraHours);

        if (! $quote) {
            return $this->unavailable($venue, $hall, $data, $locale);
        }

        return response()->json([
            'available' => true,
            ...$quote,
            'currency' => $venue->currency,
        ]);
    }

    public function store(Request $request, string $venueSlug): JsonResponse
    {
        $venue = $this->publishedVenue($venueSlug);
        $locale = $this->locale($request, $venue);
        $messages = self::MESSAGES[$locale];

        $data = $request->validate([
            ...$this->eventRules($venue),
            'client_name' => ['required', 'string', 'max:100'],
            'contact_type' => ['required', Rule::in(['whatsapp', 'phone', 'email'])],
            'contact_value' => ['required', 'string', 'max:120'],
            'special_request' => ['nullable', 'string', 'max:500'],
            'client_token' => ['nullable', 'uuid'],
        ], $this->validationMessages($locale));

        $isEmail = $data['contact_type'] === 'email';

        if ($isEmail && ! filter_var($data['contact_value'], FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['contact_value' => $messages['contact_email']]);
        }

        if (! $isEmail && ! preg_match(self::PHONE_PATTERN, $data['contact_value'])) {
            throw ValidationException::withMessages(['contact_value' => $messages['contact_phone']]);
        }

        [$hall, $eventDate, $extraHours] = $this->resolveEvent($venue, $data, $locale);

        $conversation = isset($data['client_token'])
            ? Conversation::where('venue_id', $venue->id)->where('client_token', $data['client_token'])->first()
            : null;

        $booking = $this->reservations->createRequest($venue, $hall, [
            'event_date' => $eventDate,
            'event_type' => $data['event_type'],
            'guest_count' => (int) $data['guests'],
            'extra_hours' => $extraHours,
            'client_name' => $data['client_name'],
            'client_email' => $isEmail ? $data['contact_value'] : null,
            'client_phone' => $isEmail ? null : $data['contact_value'],
            'contact_type' => $data['contact_type'],
            'locale' => $locale,
            'notes' => $data['special_request'] ?? null,
        ], $conversation);

        if (! $booking) {
            return $this->unavailable($venue, $hall, $data, $locale);
        }

        return response()->json([
            'reference' => $booking->reference,
            'status' => $booking->status,
            'total' => (float) $booking->total_price,
            'deposit' => (float) $booking->deposit_amount,
            'currency' => $venue->currency,
            'handover' => $this->handover->forBooking($venue, $booking, $locale),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function eventRules(Venue $venue): array
    {
        $today = now($venue->timezone)->toDateString();

        return [
            'hall_slug' => ['required', 'string', 'max:120'],
            'event_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today],
            'event_type' => ['required', Rule::in(Booking::EVENT_TYPES)],
            'guests' => ['required', 'integer', 'min:1', 'max:'.ReservationService::MAX_GUESTS],
            'extra_hours' => ['nullable', 'integer', 'min:0', 'max:'.Hall::MAX_EXTRA_HOURS],
            'locale' => ['nullable', Rule::in(self::SUPPORTED_LOCALES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validationMessages(string $locale): array
    {
        $messages = self::MESSAGES[$locale];

        return [
            'required' => $messages['invalid'],
            'date_format' => $messages['invalid'],
            'integer' => $messages['invalid'],
            'min' => $messages['invalid'],
            'max' => $messages['invalid'],
            'in' => $messages['invalid'],
            'uuid' => $messages['invalid'],
            'after_or_equal' => $messages['date_past'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: Hall, 1: CarbonImmutable, 2: int}
     */
    private function resolveEvent(Venue $venue, array $data, string $locale): array
    {
        $messages = self::MESSAGES[$locale];

        $hall = $venue->halls()->where('is_active', true)->where('slug', $data['hall_slug'])->first();

        if (! $hall) {
            throw ValidationException::withMessages(['hall_slug' => $messages['hall_unknown']]);
        }

        if (! $hall->fitsGuests((int) $data['guests'])) {
            throw ValidationException::withMessages(['guests' => str_replace(':max', (string) $hall->max_guests, $messages['capacity'])]);
        }

        return [$hall, CarbonImmutable::parse($data['event_date'])->startOfDay(), (int) ($data['extra_hours'] ?? 0)];
    }

    /**
     * The hall cannot be booked on that date: say so, and offer the halls that
     * are free on the same date for the same number of guests.
     *
     * @param  array<string, mixed>  $data
     */
    private function unavailable(Venue $venue, Hall $requested, array $data, string $locale): JsonResponse
    {
        $eventDate = CarbonImmutable::parse($data['event_date'])->startOfDay();
        $guests = (int) $data['guests'];

        $alternatives = $venue->halls()
            ->where('is_active', true)
            ->whereKeyNot($requested->id)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Hall $hall) => $hall->fitsGuests($guests))
            ->map(function (Hall $hall) use ($eventDate, $locale) {
                $quote = $this->reservations->quote($hall, $eventDate);

                return $quote ? [
                    'slug' => $hall->slug,
                    'name' => $hall->translatedName($locale),
                    'total' => $quote['grand_total'],
                ] : null;
            })
            ->filter()
            ->values();

        $message = self::MESSAGES[$locale]['unavailable'];

        return response()->json([
            'message' => $message,
            'errors' => ['hall_slug' => [$message]],
            'alternatives' => $alternatives,
            'currency' => $venue->currency,
        ], 422);
    }

    private function locale(Request $request, Venue $venue): string
    {
        return in_array($request->input('locale'), self::SUPPORTED_LOCALES, true)
            ? $request->input('locale')
            : $venue->default_locale;
    }

    private function publishedVenue(string $venueSlug): Venue
    {
        $venue = Venue::where('slug', $venueSlug)->first();

        abort_if(! $venue || ! $venue->isPublished(), 404);

        return $venue;
    }
}
