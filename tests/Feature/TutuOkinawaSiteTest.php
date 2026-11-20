<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Venue;
use App\Services\Concierge\ConciergeService;
use App\Services\Concierge\VenueConciergeTools;
use Database\Seeders\JsAculSeeder;
use Database\Seeders\TutuOkinawaSeeder;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * TUTU Okinawa is a bridal salon that introduces partner venues: a "guide" site. No venue price may show
 * anywhere, and a booking request asks for a consultation day, not a wedding date.
 */
class TutuOkinawaSiteTest extends TestCase
{
    use RefreshDatabase;

    private function seedSite(?string $code = null): Venue
    {
        $seeder = new TutuOkinawaSeeder;
        $seeder->accessCode = $code;
        $seeder->setContainer(app())->setCommand(new Command)->__invoke();

        return Venue::where('slug', 'tutu-okinawa')->firstOrFail();
    }

    private function tools(Venue $venue, string $locale = 'en'): VenueConciergeTools
    {
        $conversation = Conversation::create(['venue_id' => $venue->id, 'client_token' => (string) Str::uuid(), 'locale' => $locale]);

        return new VenueConciergeTools($venue, $conversation, $locale);
    }

    public function test_seed_has_21_partner_venues_in_three_areas_and_a_guide_catalog(): void
    {
        $venue = $this->seedSite();
        $counts = $venue->halls()->where('is_active', true)->get()->groupBy('group_key')->map->count()->all();

        ksort($counts);
        $this->assertSame(['central' => 2, 'naha' => 14, 'north' => 5], $counts);
        $this->assertTrue($venue->isPlanCatalog());
        $this->assertFalse($venue->showsPrices());
        $this->assertSame('tutu', $venue->theme);
        $this->assertNotEmpty($venue->knowledgeItems()->count());
        foreach ($venue->halls()->with('images')->get() as $hall) {
            $this->assertGreaterThanOrEqual(1, $hall->images->count());
            foreach ($hall->images as $image) {
                $this->assertFileExists(public_path($image->image_url), $image->image_url);
            }
        }
        $this->assertFileExists(public_path($venue->cover_path));
    }

    public function test_the_site_waits_unlisted_until_the_owner_agrees(): void
    {
        $this->seedSite();

        $this->get('/tutu-okinawa?lang=en')->assertOk()->assertSee('noindex', false);
        $this->get('/sitemap.xml')->assertDontSee('tutu-okinawa');
    }

    public function test_no_page_shows_a_venue_price_in_any_language(): void
    {
        $venue = $this->seedSite();
        $slug = $venue->halls()->orderBy('sort_order')->value('slug');

        foreach (['ja', 'en', 'id', 'th', 'zh'] as $lang) {
            foreach (['', '/halls', "/halls/{$slug}", '/services', '/info', '/reservation'] as $path) {
                $html = $this->get("/tutu-okinawa{$path}?lang={$lang}")->assertOk()->getContent();
                $text = strip_tags(preg_replace('#<(script|style)\b.*?</\1>#s', '', $html));

                $this->assertDoesNotMatchRegularExpression('/(?:rp\.?|idr|usd|¥|￥)\s?\d{2,}/iu', $text, "{$lang} {$path}");
                $this->assertStringNotContainsString('wizard-total', $html, "{$lang} {$path}");
            }
        }
    }

    public function test_the_booking_wizard_asks_for_a_consultation_without_money(): void
    {
        $this->seedSite();

        $html = $this->get('/tutu-okinawa/reservation?lang=en')->assertOk()->getContent();
        $this->assertStringContainsString('data-prices="0"', $html);
        $this->assertStringNotContainsString('data-summary-total', $html);
        $this->assertStringNotContainsString('data-summary-deposit', $html);
    }

    public function test_a_consultation_quote_and_request_carry_no_amount(): void
    {
        $venue = $this->seedSite();
        $hall = $venue->halls()->orderBy('sort_order')->firstOrFail();
        $day = now($venue->timezone)->addDays(12);
        while ($day->isWednesday()) {
            $day = $day->addDay();
        }
        $body = ['hall_slug' => $hall->slug, 'event_date' => $day->toDateString(), 'event_type' => 'consultation', 'guests' => 2];

        $quote = $this->postJson('/tutu-okinawa/reservation/quote', $body);
        if ($quote->status() === 200) {
            $this->assertArrayNotHasKey('grand_total', $quote->json());
            $this->assertArrayNotHasKey('deposit_total', $quote->json());
        }
        $this->assertNotSame(500, $quote->status());
        $this->postJson('/tutu-okinawa/reservation/quote', [...$body, 'guests' => 99])->assertStatus(422);
    }

    public function test_ai_tools_never_hand_a_price_to_the_model_or_the_cards(): void
    {
        $venue = $this->seedSite();
        $tools = $this->tools($venue);
        $slug = $venue->halls()->orderBy('sort_order')->value('slug');

        foreach ([
            $tools->dispatch('show_plans', []),
            $tools->dispatch('search_halls', ['event_date' => now($venue->timezone)->addDays(12)->toDateString(), 'guests' => 2]),
            $tools->dispatch('get_hall_detail', ['hall_slug' => $slug]),
        ] as $result) {
            $json = json_encode($result, JSON_UNESCAPED_UNICODE);
            foreach (['total_price', 'deposit_total', 'starting_price', 'discount_percent', 'min_guests', 'max_guests'] as $key) {
                $this->assertStringNotContainsString("\"{$key}\"", $json, $key);
            }
        }
    }

    public function test_the_system_prompt_forbids_prices_and_holding_a_date(): void
    {
        $venue = $this->seedSite();
        $conversation = Conversation::create(['venue_id' => $venue->id, 'client_token' => (string) Str::uuid(), 'locale' => 'en']);
        $method = new \ReflectionMethod(ConciergeService::class, 'buildSystemPrompt');
        $prompt = $method->invoke(app(ConciergeService::class), $venue, $conversation);

        $this->assertStringContainsString('NO PRICE FOR A VENUE IS PUBLISHED', $prompt);
        $this->assertStringContainsString('FREE CONSULTATION', $prompt);
        $this->assertStringNotContainsString('deposit', strtolower(str_replace('there is no deposit', '', $prompt)));
    }

    public function test_a_chat_reply_that_invents_a_venue_price_is_replaced(): void
    {
        config(['services.local_llm.base_url' => 'http://llm.test', 'services.local_llm.api_key' => 'k', 'services.local_llm.model' => 'm']);
        $this->seedSite();
        Http::fake(['llm.test/*' => Http::response(['choices' => [['message' => ['content' => 'A wedding at Southgate usually costs about ¥3,000,000.'], 'finish_reason' => 'stop']]])]);

        $token = $this->postJson('/tutu-okinawa/concierge/start', ['locale' => 'en'])->assertOk()->json('client_token');
        $reply = $this->postJson('/tutu-okinawa/concierge/message', ['client_token' => $token, 'message' => 'How much is a wedding at Southgate?'])
            ->assertOk()->json('message.content');

        $this->assertStringNotContainsString('3,000,000', $reply);
    }

    public function test_a_photo_wedding_price_that_was_looked_up_in_the_knowledge_base_is_kept(): void
    {
        config(['services.local_llm.base_url' => 'http://llm.test', 'services.local_llm.api_key' => 'k', 'services.local_llm.model' => 'm']);
        $this->seedSite();
        Http::fake(['llm.test/*' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => null, 'tool_calls' => [['id' => 'c1', 'type' => 'function', 'function' => ['name' => 'search_knowledge', 'arguments' => json_encode(['query' => 'photo wedding price'])]]]], 'finish_reason' => 'tool_calls']]])
            ->push(['choices' => [['message' => ['content' => 'Photo weddings for Okinawa residents start from ¥110,000.'], 'finish_reason' => 'stop']]]),
        ]);

        $token = $this->postJson('/tutu-okinawa/concierge/start', ['locale' => 'en'])->assertOk()->json('client_token');
        $reply = $this->postJson('/tutu-okinawa/concierge/message', ['client_token' => $token, 'message' => 'How much is a photo wedding for residents?'])
            ->assertOk()->json('message.content');

        $this->assertStringContainsString('110,000', $reply);
    }

    public function test_the_lobby_is_a_consultation_lounge_with_the_concierge_seated_and_photos_on_the_side(): void
    {
        $this->seedSite();

        $html = $this->get('/tutu-okinawa?lang=en')->assertOk()->getContent();
        $this->assertStringContainsString('data-pose="consulting"', $html);
        $this->assertStringContainsString('data-lounge-slides', $html);
        $this->assertStringContainsString('theme-tutu', $html);
        // The lobby sits in front of the bright chapel photo, not a plain white wall.
        $this->assertStringContainsString('/images/tutu/lounge.webp', $html);
    }

    public function test_every_chapter_keeps_the_bright_concept_photo_and_the_lobby_has_no_desk_bar(): void
    {
        $this->seedSite();

        foreach (['halls' => 'scene-halls', 'services' => 'scene-services', 'info' => 'scene-info', 'staff' => 'scene-staff', 'reservation' => 'scene-reservation'] as $path => $photo) {
            $this->assertStringContainsString("/images/tutu/{$photo}.webp", $this->get("/tutu-okinawa/{$path}?lang=en")->assertOk()->getContent(), $path);
            $this->assertFileExists(public_path("images/tutu/{$photo}.webp"));
        }
        $this->assertStringNotContainsString('lounge-desk', $this->get('/tutu-okinawa?lang=en')->getContent());
        $this->assertStringContainsString('lounge-media', $this->get('/tutu-okinawa?lang=en')->getContent());
    }

    public function test_the_other_chapters_and_the_other_sites_keep_the_stage_layout(): void
    {
        $this->seedSite();
        $jsacul = (new JsAculSeeder);
        $jsacul->setContainer(app())->setCommand(new Command)->__invoke();

        $this->assertStringNotContainsString('lounge-media', $this->get('/tutu-okinawa/halls?lang=en')->assertOk()->getContent());
        $this->assertStringNotContainsString('lounge-media', $this->get('/jsacul?lang=en')->assertOk()->getContent());
        $this->assertStringNotContainsString('tutu/lounge.webp', $this->get('/tutu-okinawa/halls?lang=en')->assertOk()->getContent());
    }

    public function test_the_other_sites_still_show_their_prices(): void
    {
        $this->assertTrue(Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published'])->showsPrices());
    }
}
