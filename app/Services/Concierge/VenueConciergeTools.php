<?php

namespace App\Services\Concierge;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Hall;
use App\Models\HandoverRequest;
use App\Models\Venue;
use App\Models\VenueKnowledgeItem;
use App\Notifications\NewHandoverRequest;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Executes the AI Concierge's tools against this venue's controlled data.
 * Every venue fact, hall, price and availability answer must pass through
 * here — the model itself is never trusted to hold any of it.
 */
class VenueConciergeTools
{
    public function __construct(
        private readonly Venue $venue,
        private readonly Conversation $conversation,
        private readonly string $locale,
        private readonly ReservationService $reservations = new ReservationService,
    ) {}

    /**
     * OpenAI-compatible function-calling schema (used by the local LM Studio
     * / Ollama endpoint via ConciergeService — see topic in that class).
     */
    public static function definitions(): array
    {
        return array_map(
            fn (array $tool) => ['type' => 'function', 'function' => $tool],
            [
                [
                    'name' => 'search_knowledge',
                    'description' => 'Search the wedding venue\'s approved knowledge base: wedding services and vendors (decoration, makeup, photo and video, entertainment, wedding organizer), catering and menus, payment and cancellation policies, parking and access to the venue, house rules, and FAQs. Always use this instead of answering venue-fact questions from memory. Returns up to 5 matching entries.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Keywords from the couple\'s question, e.g. "catering", "down payment" or "parking"'],
                            'category' => [
                                'type' => 'string',
                                'enum' => ['general', 'services', 'policies', 'catering', 'access', 'faq'],
                                'description' => 'Optional category filter',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
                [
                    'name' => 'search_halls',
                    'description' => 'Search the venue\'s halls that are free on the couple\'s wedding date and fit their guest count, with the total price — weekday discount already applied — checked. Use this whenever a couple describes what they want (date, number of guests, indoor or outdoor, budget) rather than naming one hall by name.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'event_date' => ['type' => 'string', 'description' => 'Wedding date, YYYY-MM-DD'],
                            'guests' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Expected number of invited guests'],
                            'setting' => ['type' => 'string', 'enum' => Hall::SETTINGS, 'description' => 'Optional preference: indoor, outdoor or semi_outdoor'],
                            'view_type' => ['type' => 'string', 'description' => 'e.g. garden, lake, skyline — optional preference'],
                        ],
                        'required' => ['event_date', 'guests'],
                    ],
                ],
                [
                    'name' => 'get_hall_detail',
                    'description' => 'Get full details and photos for one hall by its slug (from a previous search_halls result): size, capacity, seating styles, setting, what is included, and extra-hour pricing. Use this when the couple asks to see more about, or see photos of, a specific hall.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'hall_slug' => ['type' => 'string'],
                            'image_tag' => ['type' => 'string', 'description' => 'Optional filter, e.g. "ceremony", "reception", "decor", "bridal_room", "exterior"'],
                        ],
                        'required' => ['hall_slug'],
                    ],
                ],
                [
                    'name' => 'check_availability',
                    'description' => 'Get the current, real-time availability and exact total price for one hall on a specific date, including any weekday discount and the down payment. ALWAYS call this before confirming a price or telling a couple a date is open — never state a price or availability from memory.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'hall_slug' => ['type' => 'string'],
                            'event_date' => ['type' => 'string', 'description' => 'Wedding date, YYYY-MM-DD'],
                            'extra_hours' => ['type' => 'integer', 'minimum' => 0, 'maximum' => Hall::MAX_EXTRA_HOURS, 'description' => 'Optional overtime hours beyond the standard event window'],
                        ],
                        'required' => ['hall_slug', 'event_date'],
                    ],
                ],
                [
                    'name' => 'create_booking_request',
                    'description' => 'Create a date request after the couple confirms the hall and date and you have their name and phone. This re-checks availability before holding the date. It does not charge payment or sign a contract — it creates a pending request for the wedding team to confirm and to collect the down payment.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'hall_slug' => ['type' => 'string'],
                            'event_date' => ['type' => 'string', 'description' => 'Wedding date, YYYY-MM-DD'],
                            'event_type' => ['type' => 'string', 'enum' => Booking::EVENT_TYPES, 'description' => 'akad, reception, akad_reception (both on the same day) or engagement'],
                            'guests' => ['type' => 'integer', 'minimum' => 1],
                            'extra_hours' => ['type' => 'integer', 'minimum' => 0, 'maximum' => Hall::MAX_EXTRA_HOURS],
                            'client_name' => ['type' => 'string'],
                            'client_email' => ['type' => 'string'],
                            'client_phone' => ['type' => 'string'],
                            'notes' => ['type' => 'string'],
                        ],
                        'required' => ['hall_slug', 'event_date', 'guests', 'client_name', 'client_phone'],
                    ],
                ],
                [
                    'name' => 'request_human_handover',
                    'description' => 'Hand this conversation over to a human member of the wedding team. Use this for special requests (custom decoration, outside vendors, religious or cultural ceremonies), complaints, custom packages, negotiated rates, rescheduling or unusual cancellations, payment problems, guest counts above the online limit, or any question you cannot answer confidently from the available tools. Always write a clear summary so the team does not need to ask the couple to repeat themselves.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'reason' => [
                                'type' => 'string',
                                'enum' => ['special_request', 'complaint', 'custom_package', 'negotiated_rate', 'reschedule', 'payment_issue', 'low_confidence'],
                            ],
                            'summary' => ['type' => 'string', 'description' => 'What the couple wants and the relevant context gathered so far, written for a team member who has not seen this conversation'],
                        ],
                        'required' => ['reason', 'summary'],
                    ],
                ],
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{text: string, ui: array|null}
     */
    public function dispatch(string $name, array $input): array
    {
        return match ($name) {
            'search_knowledge' => $this->searchKnowledge($input),
            'search_halls' => $this->searchHalls($input),
            'get_hall_detail' => $this->getHallDetail($input),
            'check_availability' => $this->checkAvailability($input),
            'create_booking_request' => $this->createBookingRequest($input),
            'request_human_handover' => $this->requestHumanHandover($input),
            default => ['text' => "Unknown tool: {$name}", 'ui' => null],
        };
    }

    private function searchKnowledge(array $input): array
    {
        $query = trim((string) ($input['query'] ?? ''));
        $category = $input['category'] ?? null;
        $words = collect(preg_split('/\s+/', Str::lower($query)))->filter();

        $items = $this->venue->knowledgeItems()
            ->where('is_active', true)
            ->when($category, fn ($q) => $q->where('category', $category))
            ->get()
            ->filter(function (VenueKnowledgeItem $item) use ($query, $words) {
                if ($query === '') {
                    return true;
                }

                $haystack = Str::lower($item->title.' '.$item->body.' '.implode(' ', $item->tags ?? []));

                return Str::contains($haystack, Str::lower($query))
                    || $words->contains(fn ($word) => Str::contains($haystack, $word));
            })
            ->take(5);

        if ($items->isEmpty()) {
            return [
                'text' => 'No matching knowledge base entry was found. Do not guess the answer — tell the couple you will check with the team, or call request_human_handover.',
                'ui' => null,
            ];
        }

        $results = $items->map(fn (VenueKnowledgeItem $item) => [
            'category' => $item->category,
            'title' => $item->translatedTitle($this->locale),
            'body' => $item->translatedBody($this->locale),
        ])->values()->all();

        return ['text' => json_encode($results, JSON_UNESCAPED_UNICODE), 'ui' => null];
    }

    private function searchHalls(array $input): array
    {
        $eventDate = $this->parseDate($input['event_date']);
        $guests = (int) $input['guests'];
        $setting = $input['setting'] ?? null;
        $viewType = $input['view_type'] ?? null;

        $candidates = $this->venue->halls()
            ->where('is_active', true)
            ->get()
            ->filter(fn (Hall $hall) => $hall->fitsGuests($guests))
            ->when($setting, fn ($c) => $c->filter(fn (Hall $hall) => $hall->setting === $setting))
            ->when($viewType, fn ($c) => $c->filter(fn (Hall $hall) => $hall->view_type === $viewType));

        $matches = [];
        foreach ($candidates as $hall) {
            $quote = $this->reservations->quote($hall, $eventDate);
            if (! $quote) {
                continue;
            }

            $thumbnail = $hall->images->first();

            $matches[] = [
                'hall_slug' => $hall->slug,
                'name' => $hall->translatedName($this->locale),
                'size_sqm' => $hall->size_sqm,
                'setting' => $hall->setting,
                'view_type' => $hall->view_type,
                'min_guests' => $hall->min_guests,
                'max_guests' => $hall->max_guests,
                'catering_included' => $hall->catering_included,
                'event_date' => $eventDate->toDateString(),
                'discount_percent' => $quote['discount_percent'],
                'total_price' => $quote['grand_total'],
                'deposit_total' => $quote['deposit_total'],
                'currency' => $this->venue->currency,
                'thumbnail_url' => $thumbnail?->image_source,
            ];
        }

        if (empty($matches)) {
            return [
                'text' => 'No hall is free on that date for that many guests. Tell the couple honestly and offer to check a different date, a nearby weekday or another setting, or call request_human_handover if they want to join the waiting list or need a custom arrangement.',
                'ui' => null,
            ];
        }

        usort($matches, fn ($a, $b) => $a['total_price'] <=> $b['total_price']);

        return [
            'text' => json_encode($matches, JSON_UNESCAPED_UNICODE),
            'ui' => [
                'type' => 'hall_results',
                'event_date' => $eventDate->toDateString(),
                'guests' => $guests,
                'halls' => $matches,
            ],
        ];
    }

    private function getHallDetail(array $input): array
    {
        $hall = $this->findHall($input['hall_slug']);
        if (! $hall) {
            return ['text' => 'Hall not found.', 'ui' => null];
        }

        $images = $hall->images;
        $tag = $input['image_tag'] ?? null;
        if ($tag) {
            $images = $images->filter(fn ($img) => $img->hasTag($tag));
        }

        $detail = [
            'hall_slug' => $hall->slug,
            'name' => $hall->translatedName($this->locale),
            'description' => $hall->translatedDescription($this->locale),
            'size_sqm' => $hall->size_sqm,
            'setting' => $hall->setting,
            'min_guests' => $hall->min_guests,
            'max_guests' => $hall->max_guests,
            'seating_styles' => $hall->seating_styles,
            'view_type' => $hall->view_type,
            'catering_included' => $hall->catering_included,
            'extra_hour_available' => $hall->extra_hour_available,
            'extra_hour_price' => $hall->extra_hour_price,
            'amenities' => $hall->amenities,
            'starting_price' => $hall->base_price,
            'currency' => $this->venue->currency,
            'event_window' => [
                'from' => substr((string) $this->venue->event_start_time, 0, 5),
                'until' => substr((string) $this->venue->event_end_time, 0, 5),
            ],
            'weekday_discount_percent' => $this->venue->weekday_discount_percent,
            'deposit_percent' => $this->venue->deposit_percent,
            'note' => 'starting_price is a per-event rental rate before any weekday discount and is indicative only — always call check_availability for the exact price on a real date.',
            'images' => $images->map(fn ($img) => [
                'url' => $img->image_source,
                'tags' => $img->tags,
                'alt' => $img->alt_text,
            ])->values()->all(),
        ];

        return [
            'text' => json_encode($detail, JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'hall_detail', 'hall' => $detail],
        ];
    }

    private function checkAvailability(array $input): array
    {
        $hall = $this->findHall($input['hall_slug']);
        if (! $hall) {
            return ['text' => 'Hall not found.', 'ui' => null];
        }

        $eventDate = $this->parseDate($input['event_date']);
        $quote = $this->reservations->quote($hall, $eventDate, (int) ($input['extra_hours'] ?? 0));

        if (! $quote) {
            return [
                'text' => json_encode([
                    'available' => false,
                    'hall_slug' => $hall->slug,
                    'message' => 'This hall is not open for booking on that date.',
                ], JSON_UNESCAPED_UNICODE),
                'ui' => ['type' => 'availability', 'available' => false, 'hall_slug' => $hall->slug],
            ];
        }

        $result = [
            'available' => true,
            'hall_slug' => $hall->slug,
            'name' => $hall->translatedName($this->locale),
            'currency' => $this->venue->currency,
            ...$quote,
        ];

        return [
            'text' => json_encode($result, JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'availability', 'available' => true, 'quote' => $result],
        ];
    }

    private function createBookingRequest(array $input): array
    {
        $hall = $this->findHall($input['hall_slug']);
        if (! $hall) {
            return ['text' => 'Hall not found.', 'ui' => null];
        }

        $eventDate = $this->parseDate($input['event_date']);
        $guests = (int) $input['guests'];

        if (! $hall->fitsGuests($guests) || $guests > ReservationService::MAX_GUESTS) {
            return ['text' => 'This hall cannot hold that many guests. Suggest a larger hall, or call request_human_handover with reason custom_package for a split-room arrangement.', 'ui' => null];
        }

        $booking = $this->reservations->createRequest($this->venue, $hall, [
            'event_date' => $eventDate,
            'event_type' => $input['event_type'] ?? null,
            'guest_count' => $guests,
            'extra_hours' => (int) ($input['extra_hours'] ?? 0),
            'client_name' => $input['client_name'],
            'client_email' => $input['client_email'] ?? null,
            'client_phone' => $input['client_phone'],
            'locale' => $this->locale,
            'notes' => $input['notes'] ?? null,
        ], $this->conversation);

        if (! $booking) {
            return [
                'text' => 'The hall is no longer available on that exact date — availability may have just changed. Call check_availability again or offer alternative dates.',
                'ui' => null,
            ];
        }

        $payload = [
            'booking_reference' => $booking->reference,
            'hall_slug' => $hall->slug,
            'name' => $hall->translatedName($this->locale),
            'event_date' => $eventDate->toDateString(),
            'event_type' => $booking->event_type,
            'guests' => $guests,
            'total_price' => (float) $booking->total_price,
            'deposit_amount' => (float) $booking->deposit_amount,
            'currency' => $this->venue->currency,
            'status' => 'pending_confirmation',
        ];

        return [
            'text' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'booking_confirmation', 'booking' => $payload],
        ];
    }

    private function requestHumanHandover(array $input): array
    {
        $reason = $input['reason'];
        $summary = $input['summary'];

        $handover = HandoverRequest::create([
            'conversation_id' => $this->conversation->id,
            'reason' => $reason,
            'summary' => $summary,
            'status' => HandoverRequest::STATUS_OPEN,
        ]);

        $this->venue->notifyStaff(new NewHandoverRequest($handover));

        $this->conversation->update([
            'status' => Conversation::STATUS_HANDED_OVER,
            'handover_summary' => $summary,
        ]);

        return [
            'text' => json_encode([
                'ok' => true,
                'message' => 'A member of the wedding team has been notified and will join this conversation shortly.',
            ], JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'handover', 'reason' => $reason, 'summary' => $summary],
        ];
    }

    // --- helpers ---

    private function findHall(string $slug): ?Hall
    {
        return $this->venue->halls()->where('slug', $slug)->first();
    }

    private function parseDate(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date)->startOfDay();
    }
}
