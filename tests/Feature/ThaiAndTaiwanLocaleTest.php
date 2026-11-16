<?php

namespace Tests\Feature;

use App\Models\Venue;
use App\Services\Concierge\ContentGuard;
use App\Services\CurrencyConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ThaiAndTaiwanLocaleTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['services.fx.feed' => 'https://fx.test/latest', 'services.fx.fallback_feed' => 'https://fx2.test/{base}']);
        Http::fake([
            'fx.test/*' => Http::response(['rates' => ['JPY' => 0.0092, 'USD' => 0.00006, 'THB' => 0.0019]]),
            'fx2.test/*' => Http::response(['rates' => ['TWD' => 0.0018, 'THB' => 9]]),
        ]);

        $this->venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'default_locale' => 'id']);
        $hall = $this->venue->halls()->create([
            'name' => 'Aurora', 'slug' => 'aurora', 'base_price' => 40000000, 'min_guests' => 100, 'max_guests' => 300,
            'setting' => 'indoor', 'is_active' => true, 'sort_order' => 0,
            'translations' => ['th' => ['name' => 'ออโรร่า'], 'zh' => ['name' => '極光']],
        ]);
        $hall->images()->create(['image_url' => 'https://img.test/a.jpg?w=800', 'alt_text' => 'x', 'tags' => ['interior'], 'sort_order' => 0]);
    }

    public function test_every_page_renders_in_thai_and_taiwan_chinese_with_their_own_currency(): void
    {
        foreach (['/demo', '/demo/halls', '/demo/halls/aurora', '/demo/services', '/demo/info', '/demo/staff', '/demo/reservation'] as $path) {
            $this->get("{$path}?lang=th")->assertOk()->assertSee('<html lang="th">', false);
            $this->get("{$path}?lang=zh")->assertOk()->assertSee('<html lang="zh-TW">', false);
        }

        $this->get('/demo/halls?lang=th')->assertSee('฿76,000', false)->assertSee('ออโรร่า');
        $this->get('/demo/halls?lang=zh')->assertSee('NT$72,000', false)->assertSee('極光');
    }

    public function test_the_language_switcher_lists_all_five_languages(): void
    {
        $this->get('/demo/halls?lang=en')->assertOk()
            ->assertSee('?lang=th', false)->assertSee('?lang=zh', false)
            ->assertSee('ภาษาไทย')->assertSee('繁體中文');
    }

    public function test_the_ai_replies_in_the_language_the_client_writes_in(): void
    {
        $guard = new ContentGuard;

        $this->assertSame('th', $guard->detectLocale('อยากดูห้องจัดเลี้ยงค่ะ', 'en'));
        $this->assertSame('zh', $guard->detectLocale('我想看看宴會廳', 'en'));
        $this->assertSame('ja', $guard->detectLocale('ホールを見たいです', 'en'));
    }

    public function test_thai_and_taiwan_dollar_rates_come_from_the_feeds(): void
    {
        $fx = new CurrencyConverter;

        $this->assertSame('THB', $fx->currencyFor($this->venue, 'th'));
        $this->assertSame('TWD', $fx->currencyFor($this->venue, 'zh'));
        $this->assertSame('฿76,000', $fx->display(40000000, $this->venue, 'THB'));
        $this->assertSame('NT$72,000', $fx->display(40000000, $this->venue, 'TWD'));
    }
}
