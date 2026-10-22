<?php

namespace Tests\Feature;

use App\Models\Hall;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueKnowledgeItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VenueContentCacheTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();

        // The array store keeps objects as they are; production stores serialize and refuse to rebuild objects.
        config(['cache.stores.array.serialize' => true]);
        Cache::purge('array');

        $this->venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
    }

    private function hall(Venue $venue, string $name, string $slug): Hall
    {
        return $venue->halls()->create(['name' => $name, 'slug' => $slug, 'base_price' => 1000000, 'min_guests' => 10, 'max_guests' => 100, 'is_active' => true]);
    }

    private function queryCount(string $url): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query')->filter(fn ($sql) => str_contains($sql, 'from "halls"') || str_contains($sql, 'from "venue_knowledge_items"'));
        DB::disableQueryLog();

        return $queries->count();
    }

    public function test_a_second_visit_reads_halls_and_services_from_the_cache(): void
    {
        $this->hall($this->venue, 'Garden', 'garden');

        $first = $this->queryCount('/demo/halls?lang=en');
        $second = $this->queryCount('/demo/halls?lang=en');

        $this->assertGreaterThan(0, $first);
        $this->assertLessThan($first, $second);
    }

    public function test_adding_editing_hiding_and_deleting_a_hall_shows_up_on_the_next_visit(): void
    {
        $this->get('/demo/halls?lang=en')->assertOk()->assertDontSee('Garden Hall');

        $hall = $this->hall($this->venue, 'Garden Hall', 'garden-hall');
        $this->get('/demo/halls?lang=en')->assertSee('Garden Hall');

        $hall->update(['name' => 'Lakeside Hall']);
        $this->get('/demo/halls?lang=en')->assertSee('Lakeside Hall')->assertDontSee('Garden Hall');

        $hall->update(['is_active' => false]);
        $this->get('/demo/halls?lang=en')->assertDontSee('Lakeside Hall');

        $hall->update(['is_active' => true]);
        $this->get('/demo/halls?lang=en')->assertSee('Lakeside Hall');

        $hall->delete();
        $this->get('/demo/halls?lang=en')->assertDontSee('Lakeside Hall');
    }

    public function test_adding_and_removing_a_hall_photo_shows_up_on_the_hall_page(): void
    {
        $hall = $this->hall($this->venue, 'Garden Hall', 'garden-hall');
        $this->get('/demo/halls/garden-hall?lang=en')->assertOk()->assertDontSee('https://example.test/aisle.jpg');

        $image = $hall->images()->create(['image_url' => 'https://example.test/aisle.jpg', 'alt_text' => 'Aisle', 'sort_order' => 0]);
        $this->get('/demo/halls/garden-hall?lang=en')->assertSee('https://example.test/aisle.jpg');

        $image->delete();
        $this->get('/demo/halls/garden-hall?lang=en')->assertDontSee('https://example.test/aisle.jpg');
    }

    public function test_services_and_information_follow_changes_to_the_knowledge_base(): void
    {
        $this->get('/demo/services?lang=en')->assertOk()->assertDontSee('Fresh flowers');
        $this->get('/demo/info?lang=en')->assertOk()->assertDontSee('Valet parking');

        $service = $this->venue->knowledgeItems()->create(['category' => VenueKnowledgeItem::CATEGORY_SERVICES, 'title' => 'Fresh flowers', 'body' => 'Weekly.', 'is_active' => true]);
        $this->venue->knowledgeItems()->create(['category' => VenueKnowledgeItem::CATEGORY_ACCESS, 'title' => 'Valet parking', 'body' => 'At the gate.', 'is_active' => true]);

        $this->get('/demo/services?lang=en')->assertSee('Fresh flowers');
        $this->get('/demo/info?lang=en')->assertSee('Valet parking');

        $service->delete();
        $this->get('/demo/services?lang=en')->assertDontSee('Fresh flowers');
    }

    public function test_an_admin_edit_is_visible_to_visitors_straight_away(): void
    {
        $staff = User::factory()->create();
        $this->venue->users()->attach($staff->id, ['role' => 'owner', 'status' => 'active']);
        $hall = $this->hall($this->venue, 'Garden Hall', 'garden-hall');
        $this->get('/demo/halls?lang=en')->assertSee('Garden Hall');

        $this->actingAs($staff)->put(route('admin.halls.update', $hall), [
            'name' => 'Rose Hall', 'setting' => 'indoor', 'min_guests' => 10, 'max_guests' => 100, 'base_price' => 1000000, 'is_active' => 1,
        ])->assertRedirect();

        $this->get('/demo/halls?lang=en')->assertSee('Rose Hall')->assertDontSee('Garden Hall');
    }

    public function test_changing_one_venue_does_not_flush_another_venues_cache(): void
    {
        $other = Venue::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $this->hall($this->venue, 'Garden Hall', 'garden-hall');
        $this->hall($other, 'Beach Hall', 'beach-hall');

        $this->get('/demo/halls?lang=en')->assertOk();
        $warm = $this->queryCount('/demo/halls?lang=en');

        $this->hall($other, 'Another Hall', 'another-hall');

        $this->assertSame($warm, $this->queryCount('/demo/halls?lang=en'));
        $this->get('/other/halls?lang=en')->assertSee('Another Hall');
    }
}
