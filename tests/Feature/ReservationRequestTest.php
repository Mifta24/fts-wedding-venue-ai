<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Hall;
use App\Models\HallInventory;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReservationRequestTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    private Hall $garden;

    private Hall $ballroom;

    private string $eventDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->venue = Venue::create([
            'name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'timezone' => 'Asia/Makassar',
            'default_locale' => 'en', 'whatsapp' => '+62 812-3456-7890', 'phone' => '+62 361 771234', 'email' => 'wedding@demo.test',
            'deposit_percent' => 30,
        ]);

        $this->garden = $this->venue->halls()->create([
            'name' => 'Garden Pavilion', 'slug' => 'garden-pavilion', 'base_price' => 40000000, 'min_guests' => 100, 'max_guests' => 300,
            'extra_hour_available' => true, 'extra_hour_price' => 3000000, 'is_active' => true, 'sort_order' => 0,
        ]);
        $this->ballroom = $this->venue->halls()->create([
            'name' => 'Grand Ballroom', 'slug' => 'grand-ballroom', 'base_price' => 80000000, 'min_guests' => 300, 'max_guests' => 800,
            'is_active' => true, 'sort_order' => 1,
        ]);

        $today = CarbonImmutable::now('Asia/Makassar')->startOfDay();
        // A Saturday, so no weekday discount muddies the numbers unless a test turns it on.
        $this->eventDate = $today->addDays(20)->startOfWeek()->addDays(5)->toDateString();

        foreach ([$this->garden->id => 40000000, $this->ballroom->id => 80000000] as $hallId => $price) {
            HallInventory::create(['hall_id' => $hallId, 'event_date' => $this->eventDate, 'total_slots' => 1, 'booked_slots' => 0, 'price' => $price]);
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function event(array $overrides = []): array
    {
        return [
            'hall_slug' => 'garden-pavilion', 'event_date' => $this->eventDate, 'event_type' => 'akad_reception',
            'guests' => 200, 'locale' => 'en',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function request(array $overrides = []): array
    {
        return $this->event([
            'client_name' => 'Rina & Dimas', 'contact_type' => 'whatsapp', 'contact_value' => '+62 812 0000 1111',
            'special_request' => 'Javanese decoration', ...$overrides,
        ]);
    }

    public function test_quote_prices_the_date_with_extra_hours_and_the_down_payment(): void
    {
        $this->postJson('/demo/reservation/quote', $this->event(['extra_hours' => 2]))
            ->assertOk()
            ->assertJson([
                'available' => true, 'hall_total' => 40000000, 'extra_hours' => 2, 'extra_hours_total' => 6000000,
                'subtotal' => 46000000, 'discount_percent' => 0, 'grand_total' => 46000000,
                'deposit_percent' => 30, 'deposit_total' => 13800000, 'currency' => 'IDR',
            ]);
    }

    public function test_extra_hours_are_ignored_for_halls_that_do_not_sell_them(): void
    {
        $this->postJson('/demo/reservation/quote', $this->event(['hall_slug' => 'grand-ballroom', 'guests' => 500, 'extra_hours' => 3]))
            ->assertOk()
            ->assertJsonPath('extra_hours', 0)
            ->assertJsonPath('grand_total', 80000000);
    }

    public function test_quote_rejects_a_past_date_in_the_client_language(): void
    {
        $yesterday = CarbonImmutable::now('Asia/Makassar')->subDay()->toDateString();

        $this->postJson('/demo/reservation/quote', $this->event(['event_date' => $yesterday, 'locale' => 'id']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.event_date.0', 'Tanggal acara tidak boleh di masa lalu.');
    }

    public function test_quote_rejects_a_guest_list_larger_than_the_hall_holds(): void
    {
        $this->postJson('/demo/reservation/quote', $this->event(['guests' => 350]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.guests.0', 'This hall holds up to 300 guests. Choose a larger hall or contact our wedding team.');

        $this->postJson('/demo/reservation/quote', $this->event(['guests' => 350, 'hall_slug' => 'grand-ballroom']))->assertOk();
    }

    public function test_quote_rejects_unknown_and_inactive_halls_and_unknown_event_types(): void
    {
        $this->postJson('/demo/reservation/quote', $this->event(['hall_slug' => 'nope']))->assertUnprocessable()->assertJsonValidationErrors('hall_slug');
        $this->postJson('/demo/reservation/quote', $this->event(['event_type' => 'birthday']))->assertUnprocessable()->assertJsonValidationErrors('event_type');

        $this->garden->update(['is_active' => false]);

        $this->postJson('/demo/reservation/quote', $this->event())->assertUnprocessable()->assertJsonValidationErrors('hall_slug');
    }

    public function test_a_taken_date_offers_the_halls_that_are_still_free_for_the_guest_list(): void
    {
        HallInventory::where('hall_id', $this->garden->id)->update(['booked_slots' => 1]);

        $this->postJson('/demo/reservation/quote', $this->event(['guests' => 250]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.hall_slug.0', 'This hall is not available on that date.')
            ->assertJsonPath('alternatives.0.slug', 'grand-ballroom')
            ->assertJsonPath('alternatives.0.total', 80000000);

        HallInventory::where('hall_id', $this->ballroom->id)->update(['booked_slots' => 1]);

        $this->postJson('/demo/reservation/quote', $this->event(['guests' => 250]))
            ->assertUnprocessable()
            ->assertJsonCount(0, 'alternatives');
    }

    public function test_a_date_the_hall_has_not_opened_is_unavailable(): void
    {
        $closed = CarbonImmutable::parse($this->eventDate)->addDay()->toDateString();

        $this->postJson('/demo/reservation/quote', $this->event(['event_date' => $closed]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.hall_slug.0', 'This hall is not available on that date.');
    }

    public function test_submitting_creates_a_pending_request_holds_the_date_and_returns_handover_links(): void
    {
        $conversation = Conversation::create(['venue_id' => $this->venue->id, 'client_token' => (string) Str::uuid(), 'locale' => 'en']);

        $response = $this->postJson('/demo/reservation', $this->request(['extra_hours' => 1, 'client_token' => $conversation->client_token]))
            ->assertCreated()
            ->assertJsonPath('status', Booking::STATUS_PENDING)
            ->assertJsonPath('total', 43000000)
            ->assertJsonPath('deposit', 12900000)
            ->assertJsonPath('handover.phone_url', 'tel:+62361771234');

        $booking = Booking::firstOrFail();
        $this->assertMatchesRegularExpression('/^WD-[A-Z0-9]{6}$/', $booking->reference);
        $response->assertJsonPath('reference', $booking->reference);
        $this->assertSame('akad_reception', $booking->event_type);
        $this->assertSame(200, $booking->guest_count);
        $this->assertSame(1, $booking->extra_hours);
        $this->assertSame('whatsapp', $booking->contact_type);
        $this->assertSame('+62 812 0000 1111', $booking->client_phone);
        $this->assertNull($booking->client_email);
        $this->assertSame('Javanese decoration', $booking->notes);
        $this->assertSame($conversation->id, $booking->conversation_id);
        $this->assertSame([1], HallInventory::where('hall_id', $this->garden->id)->pluck('booked_slots')->all());

        $whatsapp = $response->json('handover.whatsapp_url');
        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $whatsapp);
        $message = urldecode(substr($whatsapp, strlen('https://wa.me/6281234567890?text=')));
        $this->assertStringContainsString('Name: Rina & Dimas', $message);
        $this->assertStringContainsString('Event date: ', $message);
        $this->assertStringContainsString('Event: Ceremony & reception', $message);
        $this->assertStringContainsString('Guests: 200', $message);
        $this->assertStringContainsString('Hall: Garden Pavilion', $message);
        $this->assertStringContainsString('Special request: Javanese decoration', $message);
        $this->assertStringContainsString("Reference: {$booking->reference}", $message);
        $this->assertStringStartsWith('mailto:wedding@demo.test?subject=', $response->json('handover.email_url'));
    }

    public function test_email_contacts_are_stored_as_email_and_validated(): void
    {
        $this->postJson('/demo/reservation', $this->request(['contact_type' => 'email', 'contact_value' => 'not-an-email']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.contact_value.0', 'Please enter a valid email address.');

        $this->postJson('/demo/reservation', $this->request(['contact_type' => 'phone', 'contact_value' => 'call me']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact_value');

        $this->postJson('/demo/reservation', $this->request(['contact_type' => 'email', 'contact_value' => 'rina@example.com']))->assertCreated();

        $booking = Booking::firstOrFail();
        $this->assertSame('rina@example.com', $booking->client_email);
        $this->assertNull($booking->client_phone);
    }

    public function test_a_date_can_only_be_requested_once_per_hall(): void
    {
        $this->postJson('/demo/reservation', $this->request())->assertCreated();

        $this->postJson('/demo/reservation', $this->request(['client_name' => 'Late Couple']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.hall_slug.0', 'This hall is not available on that date.');

        $this->assertSame(1, Booking::count());

        $this->postJson('/demo/reservation', $this->request(['hall_slug' => 'grand-ballroom', 'client_name' => 'Other Hall']))->assertCreated();
        $this->assertSame(2, Booking::count());
    }

    public function test_reference_is_unique_per_request(): void
    {
        $this->postJson('/demo/reservation', $this->request())->assertCreated();
        $this->postJson('/demo/reservation', $this->request(['hall_slug' => 'grand-ballroom']))->assertCreated();

        $this->assertSame(2, Booking::distinct()->count('reference'));
    }

    public function test_unpublished_venues_reject_reservations(): void
    {
        Venue::create(['name' => 'Draft', 'slug' => 'draft']);

        $this->postJson('/draft/reservation', $this->request())->assertNotFound();
        $this->postJson('/draft/reservation/quote', $this->event())->assertNotFound();
    }

    public function test_reservation_requests_are_rate_limited(): void
    {
        foreach (range(1, 20) as $attempt) {
            $this->postJson('/demo/reservation/quote', $this->event())->assertOk();
        }

        $this->postJson('/demo/reservation/quote', $this->event())->assertTooManyRequests();
    }

    public function test_cancelling_a_booking_frees_the_date_again(): void
    {
        $this->postJson('/demo/reservation', $this->request())->assertCreated();
        $booking = Booking::firstOrFail();

        $user = User::factory()->create();
        $this->venue->users()->attach($user->id, ['role' => 'owner', 'status' => 'active']);

        $this->actingAs($user)
            ->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_CANCELLED])
            ->assertRedirect();

        $this->assertSame([0], HallInventory::where('hall_id', $this->garden->id)->pluck('booked_slots')->all());
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);

        $this->postJson('/demo/reservation/quote', $this->event())->assertOk();
    }

    public function test_weekday_events_are_quoted_with_the_weekday_discount(): void
    {
        $this->venue->update(['weekday_discount_percent' => 15]);

        $today = CarbonImmutable::now('Asia/Makassar')->startOfDay();
        $tuesday = $today->addDays(30)->startOfWeek()->addDay();
        $friday = $tuesday->addDays(3);
        foreach ([$tuesday, $friday] as $date) {
            HallInventory::create(['hall_id' => $this->garden->id, 'event_date' => $date->toDateString(), 'total_slots' => 1, 'booked_slots' => 0, 'price' => 40000000]);
        }

        $this->postJson('/demo/reservation/quote', $this->event(['event_date' => $tuesday->toDateString(), 'extra_hours' => 1]))
            ->assertOk()
            ->assertJsonPath('is_weekday', true)
            ->assertJsonPath('subtotal', 43000000)
            ->assertJsonPath('discount_percent', 15)
            ->assertJsonPath('discount_total', 6450000)
            ->assertJsonPath('grand_total', 36550000)
            ->assertJsonPath('deposit_total', 10965000);

        $this->postJson('/demo/reservation/quote', $this->event(['event_date' => $friday->toDateString()]))
            ->assertOk()
            ->assertJsonPath('is_weekday', false)
            ->assertJsonPath('discount_percent', 0)
            ->assertJsonPath('grand_total', 40000000);

        $this->postJson('/demo/reservation', [...$this->request(['event_date' => $tuesday->toDateString()])])
            ->assertCreated()
            ->assertJsonPath('total', 34000000);
    }
}
