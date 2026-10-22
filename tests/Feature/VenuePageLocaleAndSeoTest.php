<?php

namespace Tests\Feature;

use App\Models\Venue;
use App\Models\VenueKnowledgeItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenuePageLocaleAndSeoTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->venue = Venue::create([
            'name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'default_locale' => 'id',
            'description' => 'Gedung pernikahan di tepi danau.',
            'translations' => ['en' => ['description' => 'A lakeside wedding venue.']],
        ]);
        $this->venue->halls()->create([
            'name' => 'Aula Taman', 'slug' => 'aula-taman', 'base_price' => 1000000, 'min_guests' => 10, 'max_guests' => 100, 'is_active' => true,
            'description' => 'Aula terbuka.', 'translations' => ['en' => ['name' => 'Garden Hall', 'description' => 'Open air hall.'], 'ja' => ['name' => 'ガーデンホール']],
        ]);
        $this->venue->knowledgeItems()->create([
            'category' => VenueKnowledgeItem::CATEGORY_SERVICES, 'title' => 'Bunga segar', 'body' => 'Dekorasi bunga.', 'is_active' => true,
            'translations' => ['en' => ['title' => 'Fresh flowers', 'body' => 'Floral decor.']],
        ]);
    }

    public function test_the_page_follows_the_lang_query_and_sets_the_html_language(): void
    {
        $this->get('/demo?lang=en')->assertOk()->assertSee('<html lang="en">', false)->assertSee('Welcome to');
        $this->get('/demo?lang=ja')->assertOk()->assertSee('<html lang="ja">', false)->assertSee('ようこそ');
        $this->get('/demo?lang=id')->assertOk()->assertSee('<html lang="id">', false)->assertSee('Selamat datang di');
    }

    public function test_an_unsupported_language_falls_back_to_the_venues_default(): void
    {
        $this->get('/demo?lang=fr')->assertOk()->assertSee('<html lang="id">', false);

        $this->venue->update(['default_locale' => 'en']);
        $this->get('/demo')->assertOk()->assertSee('<html lang="en">', false);
    }

    public function test_hall_and_service_pages_use_the_translated_content_or_fall_back_to_the_original(): void
    {
        $this->get('/demo/halls/aula-taman?lang=en')->assertOk()->assertSee('Garden Hall')->assertSee('Open air hall.');
        $this->get('/demo/halls/aula-taman?lang=ja')->assertOk()->assertSee('ガーデンホール')->assertSee('Aula terbuka.');
        $this->get('/demo/halls/aula-taman?lang=id')->assertOk()->assertSee('Aula Taman');

        $service = $this->venue->knowledgeItems()->firstOrFail();
        $this->get("/demo/services/{$service->id}?lang=en")->assertOk()->assertSee('Fresh flowers');
        $this->get("/demo/services/{$service->id}?lang=ja")->assertOk()->assertSee('Bunga segar');
        $this->get('/demo/halls?lang=en')->assertOk()->assertSee('Garden Hall');
    }

    public function test_every_chapter_renders_in_each_language(): void
    {
        foreach (['', '/halls', '/services', '/info', '/staff', '/reservation'] as $path) {
            foreach (['id', 'en', 'ja'] as $lang) {
                $this->get("/demo{$path}?lang={$lang}")->assertOk()->assertSee("<html lang=\"{$lang}\">", false);
            }
        }
    }

    public function test_pages_carry_a_description_open_graph_tags_and_language_alternates(): void
    {
        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('<meta name="description" content="A lakeside wedding venue.">', false)
            ->assertSee('<meta property="og:title"', false)
            ->assertSee('<meta property="og:image" content="https://', false)
            ->assertSee('<meta property="og:locale" content="en_US">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
            ->assertSee('<link rel="canonical" href="'.url('/demo').'?lang=en">', false)
            ->assertSee('hreflang="ja"', false);
    }

    public function test_a_hall_page_describes_itself_and_uses_its_own_photo_for_sharing(): void
    {
        $hall = $this->venue->halls()->firstOrFail();
        $hall->images()->create(['image_url' => 'https://example.test/aisle.jpg', 'alt_text' => '', 'sort_order' => 0]);

        $this->get('/demo/halls/aula-taman?lang=en')
            ->assertOk()
            ->assertSee('<meta name="description" content="Open air hall.">', false)
            ->assertSee('<meta property="og:image" content="https://example.test/aisle.jpg">', false)
            ->assertSee('alt="Garden Hall"', false);
    }

    public function test_the_opening_screen_has_share_tags(): void
    {
        $this->get('/?lang=en')->assertOk()->assertSee('<meta property="og:title" content="Wedding Venue AI">', false)->assertSee('<meta name="description"', false);
    }

    public function test_the_venue_sitemap_lists_public_pages_in_every_language_and_skips_hidden_content(): void
    {
        $this->venue->halls()->create(['name' => 'Hidden', 'slug' => 'hidden', 'base_price' => 1, 'is_active' => false]);

        $response = $this->get('/demo/sitemap.xml')->assertOk();

        $this->assertStringContainsString('application/xml', $response->headers->get('Content-Type'));
        $response->assertSee('<?xml version="1.0" encoding="UTF-8"?>', false)
            ->assertSee(url('/demo/halls/aula-taman').'?lang=id', false)
            ->assertSee('hreflang="ja" href="'.url('/demo/halls/aula-taman').'?lang=ja"', false)
            ->assertSee(url('/demo/reservation'), false)
            ->assertDontSee('hidden');
    }

    public function test_the_sitemap_follows_content_changes_and_hides_unpublished_venues(): void
    {
        $this->get('/demo/sitemap.xml')->assertDontSee('/demo/halls/new-hall');

        $this->venue->halls()->create(['name' => 'New Hall', 'slug' => 'new-hall', 'base_price' => 1, 'is_active' => true]);
        $this->get('/demo/sitemap.xml')->assertSee('/demo/halls/new-hall', false);

        $this->venue->update(['public_status' => 'draft']);
        $this->get('/demo/sitemap.xml')->assertNotFound();
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('/demo/sitemap.xml');
    }

    public function test_the_sitemap_index_lists_published_venues(): void
    {
        Venue::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        Venue::create(['name' => 'Draft', 'slug' => 'draft-venue', 'public_status' => 'draft']);

        $this->get('/sitemap.xml')->assertOk()->assertSee(url('/demo/sitemap.xml'), false)->assertSee(url('/other/sitemap.xml'), false)->assertDontSee('draft-venue');
    }
}
