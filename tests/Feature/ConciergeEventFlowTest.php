<?php

namespace Tests\Feature;

use App\Models\HallInventory;
use App\Models\Venue;
use App\Services\Concierge\EventParser;
use App\Services\Concierge\VenueConciergeTools;
use Carbon\CarbonImmutable;
use Database\Seeders\JsAculSeeder;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The couple asks for plans, a date or the options in plain words (typed or
 * spoken) and the cards appear by themselves, whatever the local model does.
 */
class ConciergeEventFlowTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.local_llm.base_url' => 'http://llm.test',
            'services.local_llm.api_key' => 'test-key',
            'services.local_llm.model' => 'test-model',
        ]);

        $seeder = new JsAculSeeder;
        $seeder->setContainer(app())->setCommand(new Command)->__invoke();
        $this->venue = Venue::where('slug', 'jsacul')->firstOrFail();

        // The model never calls a tool here: it only talks.
        Http::fake(['llm.test/*' => Http::response(['choices' => [['message' => ['content' => 'Happy to help.'], 'finish_reason' => 'stop']]])]);
    }

    private function token(): string
    {
        return $this->postJson('/jsacul/concierge/start', ['locale' => 'en'])->assertOk()->json('client_token');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function say(string $token, string $text): array
    {
        return $this->postJson('/jsacul/concierge/message', ['client_token' => $token, 'message' => $text])
            ->assertOk()->json('message.ui_payload') ?? [];
    }

    public function test_what_plans_do_you_have_shows_every_plan_card_with_a_starting_price(): void
    {
        $ui = $this->say($this->token(), 'what plans do you have?');

        $this->assertSame('hall_results', $ui[0]['type']);
        $this->assertCount(21, $ui[0]['halls']);
        $this->assertGreaterThan(0, $ui[0]['halls'][0]['total_price']);
        $this->assertNull($ui[0]['event_date']);
    }

    public function test_options_shows_the_ten_service_cards_with_photos(): void
    {
        $ui = $this->say($this->token(), 'what options do you have?');

        $this->assertSame('service_results', $ui[0]['type']);
        $this->assertCount(10, $ui[0]['services']);
        foreach ($ui[0]['services'] as $service) {
            $this->assertStringStartsWith('/images/jsacul/opt-', $service['image_url']);
            $this->assertStringContainsString('/jsacul/services/', $service['url']);
        }
    }

    public function test_a_date_typed_in_separate_messages_goes_straight_to_the_plan_search(): void
    {
        $date = CarbonImmutable::now('Asia/Tokyo')->addDays(40)->next(CarbonImmutable::WEDNESDAY);
        $token = $this->token();

        $this->assertSame([], $this->say($token, 'hello'));
        $ui = $this->say($token, $date->format('n/j'));

        $this->assertSame('hall_results', $ui[0]['type']);
        $this->assertSame($date->toDateString(), $ui[0]['event_date']);
        $this->assertNotEmpty($ui[0]['halls']);
    }

    public function test_a_date_and_guest_count_in_one_message_work_too(): void
    {
        $date = CarbonImmutable::now('Asia/Tokyo')->addDays(40)->next(CarbonImmutable::THURSDAY);
        $ui = $this->say($this->token(), 'we want '.$date->format('F j').' for 2 guests');

        $this->assertSame('hall_results', $ui[0]['type']);
        $this->assertSame($date->toDateString(), $ui[0]['event_date']);
        $this->assertSame(2, $ui[0]['guests']);
    }

    public function test_a_closed_day_is_told_honestly_instead_of_showing_cards(): void
    {
        $tuesday = CarbonImmutable::now('Asia/Tokyo')->addDays(40)->next(CarbonImmutable::TUESDAY);
        $ui = $this->say($this->token(), $tuesday->format('n/j'));

        $this->assertSame([], $ui);
        Http::assertSent(fn ($request) => str_contains(json_encode($request['messages']), 'No hall is free'));
    }

    public function test_the_latest_date_wins_when_the_couple_changes_their_mind(): void
    {
        $first = CarbonImmutable::now('Asia/Tokyo')->addDays(40)->next(CarbonImmutable::WEDNESDAY);
        $second = $first->addDays(8);
        $token = $this->token();

        $this->say($token, $first->format('n/j'));
        $ui = $this->say($token, 'actually '.$second->format('n/j'));

        $this->assertSame($second->toDateString(), $ui[0]['event_date']);
    }

    public function test_a_named_plan_still_goes_to_its_own_detail_and_not_the_list(): void
    {
        $ui = $this->say($this->token(), '鶴ヶ城 和装フォトウエディングプラン について教えて');

        $this->assertSame('hall_detail', $ui[0]['type']);
    }

    public function test_the_parser_reads_dates_and_guests_in_three_languages(): void
    {
        $today = CarbonImmutable::parse('2026-11-18');

        $this->assertSame(['date' => '2026-12-20', 'guests' => 2], EventParser::fromMessages(['12/20', '2 guests'], $today, 'en'));
        $this->assertSame('2026-12-21', EventParser::fromMessages(['21 desember untuk 2 orang'], $today, 'id')['date']);
        $this->assertSame('2026-12-22', EventParser::fromMessages(['12月22日にしたい'], $today, 'ja')['date']);
        $this->assertSame(['date' => null, 'guests' => null], EventParser::fromMessages(['hello there'], $today, 'en'));
    }

    public function test_inventory_exists_for_the_dates_these_tests_use(): void
    {
        $this->assertGreaterThan(60, HallInventory::count() / 21);
    }

    public function test_a_venue_question_is_answered_from_the_knowledge_base_even_when_the_model_calls_no_tool(): void
    {
        $token = $this->token();
        $this->say($token, 'How much is the helicopter?');

        // The model's second request carries the real helicopter entry, so it cannot invent a price.
        Http::assertSent(fn ($request) => str_contains($request->body(), '242,000') && str_contains($request->body(), '828,000'));
    }

    public function test_a_japanese_sentence_finds_the_helicopter_entry_and_not_everything(): void
    {
        $token = $this->token();
        $this->say($token, 'ヘリコプターの料金はいくらですか');

        Http::assertSent(fn ($request) => str_contains($request->body(), '242,000'));
    }

    public function test_knowledge_terms_drop_filler_and_split_japanese_particles(): void
    {
        $this->assertSame(['ヘリコプター', '料金'], VenueConciergeTools::knowledgeTerms('ヘリコプターの料金はいくらですか'));
        $this->assertSame(['tokyo'], VenueConciergeTools::knowledgeTerms('Can we come from Tokyo?'));
        $this->assertSame([], VenueConciergeTools::knowledgeTerms('12/23'));
    }
}
