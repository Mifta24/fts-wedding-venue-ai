<?php

namespace Tests\Feature;

use App\Models\Venue;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\JsAculSeeder;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JsAculSiteTest extends TestCase
{
    use RefreshDatabase;

    private function seedSite(?string $code = null): Venue
    {
        $seeder = new JsAculSeeder;
        $seeder->accessCode = $code;
        $seeder->setContainer(app())->setCommand(new Command)->__invoke();

        return Venue::where('slug', 'jsacul')->firstOrFail();
    }

    public function test_seed_has_21_plans_in_three_areas_with_six_photos_and_a_cover(): void
    {
        $venue = $this->seedSite();
        $counts = $venue->halls()->where('is_active', true)->get()->groupBy('group_key')->map->count()->all();

        ksort($counts);
        $this->assertSame(['aizu' => 10, 'nasu' => 4, 'nikko' => 7], $counts);
        $this->assertTrue($venue->isPlanCatalog());
        $this->assertSame('jsacul', $venue->theme);
        $this->assertSame('published', $venue->public_status);
        $this->assertFalse($venue->isGated());
        $this->assertNotEmpty($venue->knowledgeItems()->count());
        foreach ($venue->halls()->with('images')->get() as $hall) {
            $this->assertCount(4, $hall->price_options);
            $this->assertGreaterThanOrEqual(5, $hall->images->count());
        }
    }

    public function test_every_photo_file_exists_on_disk(): void
    {
        $venue = $this->seedSite();
        foreach ($venue->halls()->with('images')->get() as $hall) {
            foreach ($hall->images as $image) {
                $this->assertFileExists(public_path($image->image_url), $image->image_url);
            }
        }
        $this->assertFileExists(public_path($venue->cover_path));
    }

    public function test_without_a_code_the_site_is_public_indexable_and_in_the_sitemap(): void
    {
        $this->seedSite();

        $this->get('/jsacul?lang=ja')->assertOk()->assertDontSee('noindex', false);
        $this->get('/sitemap.xml')->assertSee('jsacul');
    }

    public function test_with_a_code_visitors_are_sent_to_the_gate_and_cannot_use_the_api(): void
    {
        $this->seedSite('team-secret');

        $this->get('/jsacul/halls?lang=ja')->assertRedirect('/jsacul/access?lang=ja');
        $this->get('/jsacul/access?lang=ja')->assertOk()->assertDontSee('team-secret');
        $this->postJson('/jsacul/concierge/start')->assertForbidden();
    }

    public function test_wrong_code_is_refused_and_right_code_unlocks_for_the_session(): void
    {
        $this->seedSite('team-secret');

        $this->from('/jsacul/access')->post('/jsacul/access', ['code' => 'nope'])->assertRedirect('/jsacul/access');
        $this->get('/jsacul/halls')->assertRedirect();

        $this->post('/jsacul/access', ['code' => 'team-secret'])->assertRedirect();
        $this->get('/jsacul/halls?lang=ja')->assertOk();
    }

    public function test_code_makes_it_private_and_is_stored_hashed_and_reseeding_without_it_opens_the_site(): void
    {
        $venue = $this->seedSite('team-secret');
        $this->assertNotSame('team-secret', $venue->access_code_hash);
        $this->assertSame('unlisted', $venue->public_status);
        $this->get('/jsacul?lang=ja')->assertRedirect();

        $venue = $this->seedSite(null);
        $this->assertNull($venue->access_code_hash);
        $this->assertSame('published', $venue->public_status);
        $this->get('/jsacul?lang=ja')->assertOk();
    }

    public function test_area_board_defaults_to_aizu_and_only_the_hall_board_has_area_tabs(): void
    {
        $this->seedSite();

        $html = $this->get('/jsacul/halls?lang=ja')->assertOk()->getContent();
        $this->assertSame(3, substr_count($html, 'data-area-tab="'));
        $this->assertMatchesRegularExpression('/data-area-tab="aizu"[^>]*aria-selected="true"/', $html);
        $this->assertSame(1, substr_count($html, 'data-area-board'));

        $this->get('/jsacul?lang=ja')->assertOk()->assertDontSee('data-area-tab=', false);
    }

    public function test_plan_page_shows_prices_per_outfit_and_never_talks_about_capacity(): void
    {
        $this->seedSite();

        $this->get('/jsacul/halls/plan-03?lang=ja')->assertOk()
            ->assertSee('¥400,000', false)->assertSee('¥620,000', false)
            ->assertDontSee('収容人数', false)->assertDontSee('2–10', false);
        $this->get('/jsacul/halls/plan-03?lang=en')->assertOk()->assertSee('Base price', false);
    }

    public function test_other_venues_keep_the_plain_hall_board_without_area_tabs(): void
    {
        $this->seed(DatabaseSeeder::class);
        $slug = Venue::where('slug', '!=', 'jsacul')->value('slug');

        $this->get("/$slug/halls")->assertOk()->assertDontSee('data-area-tab=', false);
    }

    public function test_lobby_shows_three_film_icons_and_files_exist_and_services_chapter_is_called_options(): void
    {
        $venue = $this->seedSite();

        $this->assertCount(3, $venue->filmsFor('ja'));
        foreach ($venue->filmsFor('ja') as $film) {
            $this->assertFileExists(public_path(ltrim($film['src'], '/')));
            $this->assertFileExists(public_path(ltrim((string) $film['poster'], '/')));
        }

        $this->get(route('venue.show', ['venueSlug' => 'jsacul', 'lang' => 'ja']))
            ->assertOk()->assertSee('data-film-open', false)->assertSee('/videos/jsacul/helicopter.mp4', false)->assertSee('/videos/jsacul/rickshaw.mp4', false)->assertSee('オプション');
        $this->get(route('venue.services', ['venueSlug' => 'jsacul', 'lang' => 'ja']))->assertOk()->assertSee('オプション');
    }

    public function test_every_option_card_has_a_photo_that_exists(): void
    {
        $venue = $this->seedSite();
        $services = $venue->knowledgeItems()->where('category', 'services')->get();

        $this->assertCount(10, $services);
        foreach ($services as $service) {
            $this->assertStringStartsWith('/images/jsacul/', (string) $service->image_url, $service->title);
            $this->assertFileExists(public_path(ltrim($service->image_url, '/')));
        }

        $this->get(route('venue.services', ['venueSlug' => 'jsacul', 'lang' => 'ja']))
            ->assertOk()->assertSee('/images/jsacul/opt-09.webp', false)->assertSee('人力車フォトウエディング');
    }

    public function test_films_never_point_outside_this_site_and_other_venues_have_none(): void
    {
        $venue = $this->seedSite();
        $venue->films = [['src' => 'https://evil.test/x.mp4', 'titles' => ['en' => 'x']], ['src' => '/videos/jsacul/castle.mp4', 'titles' => ['en' => 'ok']]];

        $this->assertSame(['/videos/jsacul/castle.mp4'], array_column($venue->filmsFor('en'), 'src'));
        $this->assertSame([], (new Venue)->filmsFor('ja'));
    }

    public function test_lobby_has_five_photo_icons_that_point_to_real_plans(): void
    {
        $venue = $this->seedSite();

        $photos = $venue->lobbyPhotosFor('ja');
        $this->assertCount(5, $photos);
        foreach ($photos as $photo) {
            $this->assertFileExists(public_path(ltrim($photo['src'], '/')));
            $this->assertFileExists(public_path(ltrim($photo['thumb'], '/')));
            $this->assertNotSame('', $photo['title']);
            $this->get($photo['url'])->assertOk();
        }

        $this->get(route('venue.show', ['venueSlug' => 'jsacul', 'lang' => 'ja']))
            ->assertOk()->assertSee('data-photo-open="4"', false)->assertSee('data-photo-modal', false);
    }

    public function test_lobby_photos_ignore_outside_links_and_other_venues_have_none(): void
    {
        $venue = $this->seedSite();
        $venue->lobby_photos = [['src' => 'https://evil.test/a.jpg', 'thumb' => '/images/jsacul/lobby-18.webp', 'hall' => 'plan-18']];

        $this->assertSame([], $venue->lobbyPhotosFor('en'));
        $this->assertSame([], (new Venue)->lobbyPhotosFor('ja'));
    }
}
