<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\HallInventory;
use App\Models\Venue;
use App\Services\CurrencyConverter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocaleCurrencyTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    private string $eventDate;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['services.fx.feed' => 'https://fx.test/latest']);
        Http::fake(['fx.test/*' => Http::response(['base' => 'IDR', 'rates' => ['JPY' => 0.0092, 'USD' => 0.00006]])]);

        $this->venue = Venue::create([
            'name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'timezone' => 'Asia/Makassar',
            'default_locale' => 'id', 'deposit_percent' => 30,
        ]);
        $hall = $this->venue->halls()->create([
            'name' => 'Garden Pavilion', 'slug' => 'garden-pavilion', 'base_price' => 40000000, 'min_guests' => 100, 'max_guests' => 300,
            'extra_hour_available' => true, 'extra_hour_price' => 3000000, 'is_active' => true, 'sort_order' => 0,
        ]);
        $this->eventDate = CarbonImmutable::now('Asia/Makassar')->addDays(20)->startOfWeek()->addDays(5)->toDateString();
        HallInventory::create(['hall_id' => $hall->id, 'event_date' => $this->eventDate, 'total_slots' => 1, 'booked_slots' => 0, 'price' => 40000000]);
    }

    private function event(string $locale, array $extra = []): array
    {
        return ['hall_slug' => 'garden-pavilion', 'event_date' => $this->eventDate, 'event_type' => 'akad_reception', 'guests' => 150, 'locale' => $locale, ...$extra];
    }

    public function test_each_language_shows_prices_in_its_own_currency(): void
    {
        $this->get('/demo/halls?lang=ja')->assertOk()->assertSee('¥368,000', false);
        $this->get('/demo/halls?lang=en')->assertOk()->assertSee('$2,400', false);
        $this->get('/demo/halls?lang=id')->assertOk()->assertSee("Rp\u{00A0}40.000.000", false);
    }

    public function test_the_quote_is_priced_and_rounded_in_the_language_currency(): void
    {
        $this->postJson('/demo/reservation/quote', $this->event('ja', ['extra_hours' => 2]))
            ->assertOk()
            ->assertJson(['currency' => 'JPY', 'hall_total' => 368000, 'extra_hours_total' => 56000, 'grand_total' => 424000, 'deposit_total' => 127000]);

        $this->postJson('/demo/reservation/quote', $this->event('en'))
            ->assertOk()
            ->assertJson(['currency' => 'USD', 'grand_total' => 2400, 'deposit_total' => 720]);

        $this->postJson('/demo/reservation/quote', $this->event('id'))
            ->assertOk()
            ->assertJson(['currency' => 'IDR', 'grand_total' => 40000000]);
    }

    public function test_a_booking_is_billed_in_the_currency_it_was_quoted_in(): void
    {
        $this->postJson('/demo/reservation', $this->event('ja', [
            'client_name' => 'Aki', 'contact_type' => 'email', 'contact_value' => 'aki@example.test',
        ]))->assertCreated();

        $booking = Booking::firstOrFail();
        $this->assertSame('JPY', $booking->currency);
        $this->assertEquals(368000, $booking->total_price);
        $this->assertEquals(110000, $booking->deposit_amount);
        $this->assertEqualsWithDelta(40000000, $booking->totalInVenueCurrency(), 200000);
    }

    public function test_the_venue_currency_is_used_when_no_rate_is_available(): void
    {
        Cache::flush();
        config(['services.fx.feed' => 'https://down.test/latest']);
        Http::fake(['down.test/*' => Http::response('down', 500)]);

        $this->postJson('/demo/reservation/quote', $this->event('ja'))
            ->assertOk()
            ->assertJson(['currency' => 'IDR', 'grand_total' => 40000000]);
    }

    public function test_formatting_follows_each_currency(): void
    {
        $fx = new CurrencyConverter;

        $this->assertSame('Rp 1.500.000', $fx->format(1500000, 'IDR'));
        $this->assertSame('¥1,500,000', $fx->format(1500000, 'JPY'));
        $this->assertSame('$1,500', $fx->format(1500, 'USD'));
    }
}
