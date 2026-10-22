<?php

namespace Tests\Feature;

use App\Models\Hall;
use App\Models\HallInventory;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    private CarbonImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'timezone' => 'Asia/Makassar']);
        $this->today = CarbonImmutable::now('Asia/Makassar')->startOfDay();
    }

    private function hall(string $slug, bool $active = true): Hall
    {
        return $this->venue->halls()->create(['name' => $slug, 'slug' => $slug, 'base_price' => 500000, 'is_active' => $active]);
    }

    private function day(Hall $hall, int $offset, int $total = 1, int $booked = 0): void
    {
        HallInventory::create(['hall_id' => $hall->id, 'event_date' => $this->today->addDays($offset)->toDateString(), 'total_slots' => $total, 'booked_slots' => $booked, 'price' => 500000]);
    }

    /**
     * @return list<string>
     */
    private function openDates(): array
    {
        return $this->getJson('/demo/reservation/availability')->assertOk()->json('dates');
    }

    public function test_a_date_is_open_while_any_active_hall_has_a_free_slot(): void
    {
        $studio = $this->hall('studio');
        $suite = $this->hall('suite');
        $this->day($studio, 1, total: 1, booked: 1);
        $this->day($suite, 1, total: 2, booked: 1);

        $this->assertSame([$this->today->addDay()->toDateString()], $this->openDates());
    }

    public function test_a_date_is_closed_when_every_hall_is_booked_or_nothing_is_open(): void
    {
        $studio = $this->hall('studio');
        $this->day($studio, 1, total: 1, booked: 1);
        $this->day($studio, 3, total: 0);

        $this->assertSame([], $this->openDates());
    }

    public function test_inactive_halls_and_past_dates_do_not_open_a_date(): void
    {
        $hidden = $this->hall('hidden', active: false);
        $studio = $this->hall('studio');
        $this->day($hidden, 2);
        $this->day($studio, -1);

        $this->assertSame([], $this->openDates());
    }

    public function test_dates_are_listed_once_and_in_date_order_with_the_venue_time_zone_today(): void
    {
        $studio = $this->hall('studio');
        $suite = $this->hall('suite');
        $this->day($studio, 5);
        $this->day($suite, 5);
        $this->day($studio, 2);

        $response = $this->getJson('/demo/reservation/availability')->assertOk();

        $this->assertSame([$this->today->addDays(2)->toDateString(), $this->today->addDays(5)->toDateString()], $response->json('dates'));
        $this->assertSame($this->today->toDateString(), $response->json('today'));
    }

    public function test_dates_beyond_the_look_ahead_window_are_left_out(): void
    {
        $studio = $this->hall('studio');
        $this->day($studio, 730);
        $this->day($studio, 731);

        $this->assertSame([$this->today->addDays(730)->toDateString()], $this->openDates());
    }

    public function test_another_venues_inventory_is_never_listed(): void
    {
        $other = Venue::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $foreign = $other->halls()->create(['name' => 'Foreign', 'slug' => 'foreign', 'base_price' => 1, 'is_active' => true]);
        $this->day($foreign, 2);

        $this->assertSame([], $this->openDates());
    }

    public function test_an_unpublished_or_unknown_venue_returns_404(): void
    {
        Venue::create(['name' => 'Draft', 'slug' => 'draft']);

        $this->getJson('/draft/reservation/availability')->assertNotFound();
        $this->getJson('/nowhere/reservation/availability')->assertNotFound();
    }
}
