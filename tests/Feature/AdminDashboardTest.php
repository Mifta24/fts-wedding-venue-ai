<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Hall;
use App\Models\HallInventory;
use App\Models\HandoverRequest;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    private Hall $hall;

    private User $staff;

    private CarbonImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'timezone' => 'Asia/Jakarta']);
        $this->hall = $this->venue->halls()->create(['name' => 'Studio', 'slug' => 'studio', 'base_price' => 500000, 'is_active' => true]);
        $this->staff = User::factory()->create();
        $this->venue->users()->attach($this->staff->id, ['role' => 'staff', 'status' => 'active']);
        $this->today = CarbonImmutable::now('Asia/Jakarta')->startOfDay();
    }

    private function booking(string $status, int $eventOffset, ?\DateTimeInterface $createdAt = null, ?Venue $venue = null, ?Hall $hall = null): Booking
    {
        $booking = Booking::create([
            'reference' => 'WD-'.Str::upper(Str::random(6)), 'venue_id' => ($venue ?? $this->venue)->id, 'hall_id' => ($hall ?? $this->hall)->id,
            'client_name' => 'Client '.Str::random(4), 'event_date' => $this->today->addDays($eventOffset), 'guest_count' => 100, 'total_price' => 1000000, 'deposit_amount' => 300000, 'status' => $status,
        ]);

        if ($createdAt) {
            $booking->forceFill(['created_at' => $createdAt])->save();
        }

        return $booking;
    }

    private function dashboard()
    {
        return $this->actingAs($this->staff)->get(route('admin.dashboard'));
    }

    public function test_pending_requests_older_than_a_day_are_flagged_as_waiting(): void
    {
        $this->freezeTime();
        $this->booking(Booking::STATUS_PENDING, 5);
        $this->booking(Booking::STATUS_PENDING, 6, now()->subHours(25));
        $this->booking(Booking::STATUS_CONFIRMED, 7, now()->subDays(3));

        $this->dashboard()->assertOk()->assertSee('1 waiting over 24h')->assertSee('border-amber-300', false);
    }

    public function test_nothing_is_flagged_when_every_pending_request_is_recent(): void
    {
        $this->booking(Booking::STATUS_PENDING, 5);

        $this->dashboard()->assertOk()->assertSee('None waiting over 24h')->assertDontSee('border-amber-300', false);
    }

    public function test_weddings_count_and_list_only_confirmed_bookings_in_the_next_thirty_days(): void
    {
        $soon = $this->booking(Booking::STATUS_CONFIRMED, 0);
        $this->booking(Booking::STATUS_CONFIRMED, 29);
        $this->booking(Booking::STATUS_CONFIRMED, 30);
        $this->booking(Booking::STATUS_CONFIRMED, -1);
        $this->booking(Booking::STATUS_PENDING, 2);
        $this->booking(Booking::STATUS_CANCELLED, 2);

        $response = $this->dashboard()->assertOk()->assertSee('Weddings · 30 days')->assertSee($soon->client_name);

        $this->assertCount(2, $response->viewData('upcoming'));
        $this->assertSame(2, $response->viewData('stats')['upcoming_events']);
    }

    public function test_dates_booked_is_booked_over_total_slots_for_the_next_thirty_days_of_active_halls(): void
    {
        $hidden = $this->venue->halls()->create(['name' => 'Hidden', 'slug' => 'hidden', 'base_price' => 1, 'is_active' => false]);
        foreach ([[$this->hall, 0, 10, 4], [$this->hall, 29, 10, 6], [$this->hall, 30, 10, 10], [$hidden, 1, 10, 10]] as [$hall, $offset, $total, $booked]) {
            HallInventory::create(['hall_id' => $hall->id, 'event_date' => $this->today->addDays($offset)->toDateString(), 'total_slots' => $total, 'booked_slots' => $booked, 'price' => 1]);
        }

        $response = $this->dashboard()->assertOk()->assertSee('Dates booked · 30 days')->assertSee('50%');

        $this->assertSame(50, $response->viewData('stats')['booked_percent']);
    }

    public function test_dates_booked_is_a_dash_until_dates_are_open(): void
    {
        $this->dashboard()->assertOk()->assertSee('No dates open yet');

        $this->assertNull($this->dashboard()->viewData('stats')['booked_percent']);
    }

    public function test_figures_never_include_another_venues_data(): void
    {
        $this->freezeTime();
        $other = Venue::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $foreign = $other->halls()->create(['name' => 'Foreign', 'slug' => 'foreign', 'base_price' => 1, 'is_active' => true]);
        $this->booking(Booking::STATUS_CONFIRMED, 1, null, $other, $foreign);
        $this->booking(Booking::STATUS_PENDING, 1, now()->subDays(2), $other, $foreign);
        HallInventory::create(['hall_id' => $foreign->id, 'event_date' => $this->today->toDateString(), 'total_slots' => 5, 'booked_slots' => 5, 'price' => 1]);
        $conversation = Conversation::create(['venue_id' => $other->id, 'client_token' => (string) Str::uuid(), 'locale' => 'en']);
        HandoverRequest::create(['conversation_id' => $conversation->id, 'reason' => 'complaint', 'summary' => 'Foreign complaint']);

        $stats = $this->dashboard()->assertOk()->viewData('stats');

        $this->assertSame(0, $stats['upcoming_events']);
        $this->assertSame(0, $stats['pending_bookings']);
        $this->assertSame(0, $stats['waiting_bookings']);
        $this->assertSame(0, $stats['open_handovers']);
        $this->assertNull($stats['booked_percent']);
    }

    public function test_every_admin_page_has_a_menu_for_phones_and_marks_the_current_page(): void
    {
        $this->dashboard()
            ->assertOk()
            ->assertSee('<details class="group">', false)
            ->assertSee('aria-current="page"', false);

        $this->actingAs($this->staff)->get(route('admin.bookings.index'))->assertOk()->assertSee('<details class="group">', false);
    }
}
