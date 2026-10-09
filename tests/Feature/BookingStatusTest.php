<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Hall;
use App\Models\HallInventory;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\BookingStatusChanged;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BookingStatusTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    private Hall $hall;

    private User $staff;

    private CarbonImmutable $eventDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $this->hall = $this->venue->halls()->create(['name' => 'Studio', 'slug' => 'studio', 'base_price' => 500000, 'is_active' => true]);

        $this->staff = User::factory()->create();
        $this->venue->users()->attach($this->staff->id, ['role' => 'owner', 'status' => 'active']);

        $this->eventDate = CarbonImmutable::now()->addDays(5)->startOfDay();
        HallInventory::create(['hall_id' => $this->hall->id, 'event_date' => $this->eventDate->toDateString(), 'total_slots' => 2, 'booked_slots' => 0, 'price' => 500000]);
    }

    private function holdDate(): Booking
    {
        return app(ReservationService::class)->createRequest($this->venue, $this->hall, [
            'event_date' => $this->eventDate, 'event_type' => 'reception', 'guest_count' => 100,
            'client_name' => 'Ayu', 'client_phone' => '+62 811 111 222', 'contact_type' => 'whatsapp',
        ]);
    }

    /**
     * @return list<int>
     */
    private function bookedSlots(): array
    {
        return HallInventory::orderBy('event_date')->pluck('booked_slots')->all();
    }

    private function setStatus(Booking $booking, string $status)
    {
        return $this->actingAs($this->staff)->patch(route('admin.bookings.status', $booking), ['status' => $status]);
    }

    public function test_confirming_a_pending_booking_keeps_its_date_held(): void
    {
        $booking = $this->holdDate();

        $this->setStatus($booking, Booking::STATUS_CONFIRMED)->assertRedirect()->assertSessionHas('status');

        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->fresh()->status);
        $this->assertSame([1], $this->bookedSlots());
    }

    public function test_cancelling_a_confirmed_booking_frees_its_date_again(): void
    {
        $booking = $this->holdDate();
        $this->setStatus($booking, Booking::STATUS_CONFIRMED);

        $this->setStatus($booking, Booking::STATUS_CANCELLED)->assertRedirect()->assertSessionHas('status');

        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
        $this->assertSame([0], $this->bookedSlots());
    }

    public function test_a_cancelled_booking_cannot_be_confirmed_again(): void
    {
        $booking = $this->holdDate();
        $this->setStatus($booking, Booking::STATUS_CANCELLED);

        $this->setStatus($booking, Booking::STATUS_CONFIRMED)->assertRedirect()->assertSessionHas('error');

        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
        $this->assertSame([0], $this->bookedSlots());
    }

    public function test_cancelling_twice_releases_the_date_only_once(): void
    {
        $first = $this->holdDate();
        $this->holdDate();

        $this->setStatus($first, Booking::STATUS_CANCELLED);
        $this->setStatus($first, Booking::STATUS_CANCELLED)->assertSessionHas('error');

        $this->assertSame([1], $this->bookedSlots());
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $booking = $this->holdDate();

        $this->setStatus($booking, Booking::STATUS_PENDING)->assertSessionHasErrors('status');

        $this->assertSame(Booking::STATUS_PENDING, $booking->fresh()->status);
    }

    public function test_the_booking_list_offers_only_the_moves_a_booking_can_make(): void
    {
        $booking = $this->holdDate();
        $this->setStatus($booking, Booking::STATUS_CONFIRMED);

        $this->actingAs($this->staff)->get(route('admin.bookings.index'))
            ->assertOk()
            ->assertDontSee('>Confirm</button>', false)
            ->assertSee('>Cancel</button>', false);
    }

    public function test_a_hall_with_open_bookings_cannot_be_deleted(): void
    {
        $this->holdDate();

        $this->actingAs($this->staff)->delete(route('admin.halls.destroy', $this->hall))->assertSessionHas('error');

        $this->assertNotSoftDeleted($this->hall);
    }

    public function test_a_hall_whose_bookings_are_cancelled_can_be_deleted_and_the_booking_list_still_works(): void
    {
        $booking = $this->holdDate();
        $this->setStatus($booking, Booking::STATUS_CANCELLED);

        $this->actingAs($this->staff)->delete(route('admin.halls.destroy', $this->hall))->assertSessionHas('status');

        $this->assertSoftDeleted($this->hall);
        $this->actingAs($this->staff)->get(route('admin.bookings.index'))->assertOk()->assertSee($booking->reference)->assertSee('Studio');
    }

    public function test_a_hall_whose_open_bookings_ended_in_the_past_can_be_deleted(): void
    {
        $booking = $this->holdDate();
        $booking->update(['event_date' => now()->subDays(10)]);

        $this->actingAs($this->staff)->delete(route('admin.halls.destroy', $this->hall))->assertSessionHas('status');

        $this->assertSoftDeleted($this->hall);
    }

    public function test_clients_with_an_email_are_told_when_their_booking_is_confirmed_or_cancelled(): void
    {
        Notification::fake();
        $booking = $this->holdDate();
        $booking->update(['client_email' => 'ayu@example.test']);

        $this->setStatus($booking, Booking::STATUS_CONFIRMED);
        $this->setStatus($booking, Booking::STATUS_CANCELLED);
        $this->setStatus($booking, Booking::STATUS_CONFIRMED);

        Notification::assertSentOnDemandTimes(BookingStatusChanged::class, 2);
    }
}
