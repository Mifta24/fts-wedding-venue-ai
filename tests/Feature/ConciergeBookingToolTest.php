<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\HallInventory;
use App\Models\Venue;
use App\Services\Concierge\VenueConciergeTools;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConciergeBookingToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_booking_tool_creates_a_reference_holds_the_date_and_refuses_a_second_request(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'deposit_percent' => 30]);
        $hall = $venue->halls()->create(['name' => 'Garden Pavilion', 'slug' => 'garden-pavilion', 'base_price' => 50000000, 'min_guests' => 100, 'max_guests' => 400, 'is_active' => true]);
        $conversation = Conversation::create(['venue_id' => $venue->id, 'client_token' => (string) Str::uuid(), 'locale' => 'en']);

        $date = $this->saturdayAhead();
        HallInventory::create(['hall_id' => $hall->id, 'event_date' => $date->toDateString(), 'total_slots' => 1, 'booked_slots' => 0, 'price' => 50000000]);

        $tools = new VenueConciergeTools($venue, $conversation, 'en');
        $input = [
            'hall_slug' => 'garden-pavilion', 'event_date' => $date->toDateString(), 'event_type' => 'akad_reception',
            'guests' => 250, 'client_name' => 'Ayu & Raka', 'client_phone' => '+62 811 111 222',
        ];

        $quote = $tools->dispatch('check_availability', $input);
        $this->assertSame(50000000.0, $quote['ui']['quote']['grand_total']);
        $this->assertSame(15000000.0, $quote['ui']['quote']['deposit_total']);

        $result = $tools->dispatch('create_booking_request', $input);

        $booking = Booking::firstOrFail();
        $this->assertSame($booking->reference, $result['ui']['booking']['booking_reference']);
        $this->assertSame(250, $booking->guest_count);
        $this->assertSame('akad_reception', $booking->event_type);
        $this->assertSame(50000000.0, (float) $booking->total_price);
        $this->assertSame(15000000.0, (float) $booking->deposit_amount);
        $this->assertSame([1], HallInventory::pluck('booked_slots')->all());

        $taken = $tools->dispatch('create_booking_request', $input);
        $this->assertNull($taken['ui']);
        $this->assertSame(1, Booking::count());
    }

    public function test_ai_tools_apply_the_weekday_discount_and_filter_halls_by_guest_count_and_setting(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'weekday_discount_percent' => 20]);
        $salon = $venue->halls()->create(['name' => 'Salon', 'slug' => 'salon', 'base_price' => 20000000, 'setting' => 'indoor', 'min_guests' => 40, 'max_guests' => 120, 'is_active' => true]);
        $garden = $venue->halls()->create(['name' => 'Garden', 'slug' => 'garden', 'base_price' => 40000000, 'setting' => 'outdoor', 'min_guests' => 150, 'max_guests' => 500, 'view_type' => 'garden', 'is_active' => true]);
        $conversation = Conversation::create(['venue_id' => $venue->id, 'client_token' => (string) Str::uuid(), 'locale' => 'en']);

        $tuesday = CarbonImmutable::now()->addDays(10)->startOfWeek()->addWeeks(2)->addDay();
        $saturday = $tuesday->addDays(4);
        foreach ([$salon, $garden] as $hall) {
            foreach ([$tuesday, $saturday] as $date) {
                HallInventory::create(['hall_id' => $hall->id, 'event_date' => $date->toDateString(), 'total_slots' => 1, 'booked_slots' => 0, 'price' => $hall->base_price]);
            }
        }

        $tools = new VenueConciergeTools($venue, $conversation, 'en');

        $weekday = $tools->dispatch('check_availability', ['hall_slug' => 'garden', 'event_date' => $tuesday->toDateString()])['ui']['quote'];
        $this->assertSame(20, $weekday['discount_percent']);
        $this->assertSame(40000000.0, $weekday['subtotal']);
        $this->assertSame(32000000.0, $weekday['grand_total']);

        $weekend = $tools->dispatch('check_availability', ['hall_slug' => 'garden', 'event_date' => $saturday->toDateString()])['ui']['quote'];
        $this->assertSame(0, $weekend['discount_percent']);
        $this->assertSame(40000000.0, $weekend['grand_total']);

        $search = $tools->dispatch('search_halls', ['event_date' => $tuesday->toDateString(), 'guests' => 100])['ui']['halls'];
        $this->assertSame(['salon', 'garden'], array_column($search, 'hall_slug'));
        $this->assertSame(16000000.0, $search[0]['total_price']);

        $outdoor = $tools->dispatch('search_halls', ['event_date' => $tuesday->toDateString(), 'guests' => 100, 'setting' => 'outdoor'])['ui']['halls'];
        $this->assertSame(['garden'], array_column($outdoor, 'hall_slug'));

        $big = $tools->dispatch('search_halls', ['event_date' => $tuesday->toDateString(), 'guests' => 300])['ui']['halls'];
        $this->assertSame(['garden'], array_column($big, 'hall_slug'));

        $detail = json_decode($tools->dispatch('get_hall_detail', ['hall_slug' => 'salon'])['text'], true);
        $this->assertSame(120, $detail['max_guests']);
        $this->assertSame(20, $detail['weekday_discount_percent']);
    }

    private function saturdayAhead(): CarbonImmutable
    {
        return CarbonImmutable::now()->addDays(20)->startOfWeek()->addDays(5);
    }
}
