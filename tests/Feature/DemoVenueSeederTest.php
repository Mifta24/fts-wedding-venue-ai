<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Database\Seeders\DemoVenueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoVenueSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_seeds_sample_bookings_in_every_status(): void
    {
        $this->seed(DemoVenueSeeder::class);

        $this->assertSame(
            [Booking::STATUS_CANCELLED, Booking::STATUS_CONFIRMED, Booking::STATUS_PENDING],
            Booking::query()->distinct()->orderBy('status')->pluck('status')->all()
        );
    }

    public function test_reseeding_does_not_duplicate_the_sample_bookings(): void
    {
        $this->seed(DemoVenueSeeder::class);
        $count = Booking::count();

        $this->seed(DemoVenueSeeder::class);

        $this->assertGreaterThan(0, $count);
        $this->assertSame($count, Booking::count());
    }

    public function test_the_admin_bookings_list_shows_the_sample_bookings_in_indonesian(): void
    {
        $this->seed(DemoVenueSeeder::class);

        $this->actingAs(User::where('email', 'owner@ftswedding.test')->firstOrFail())
            ->get(route('admin.bookings.index'))
            ->assertOk()
            ->assertSee('Aditya & Salsabila')
            ->assertSee('Dikonfirmasi')
            ->assertSee('Menunggu')
            ->assertSee('Akad &amp; Resepsi', false);
    }

    public function test_the_admin_hall_list_shows_each_hall_photo(): void
    {
        $this->seed(DemoVenueSeeder::class);

        $this->actingAs(User::where('email', 'owner@ftswedding.test')->firstOrFail())
            ->get(route('admin.halls.index'))
            ->assertOk()
            ->assertSee('Grand Ballroom Aurora')
            ->assertSee('photo-1519167758481-83f550bb49b3', false);
    }
}
