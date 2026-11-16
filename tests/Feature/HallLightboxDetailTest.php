<?php

namespace Tests\Feature;

use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HallLightboxDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_lightbox_has_a_detail_panel_with_photo_tags_and_hall_facts_in_each_language(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'default_locale' => 'id']);
        $hall = $venue->halls()->create([
            'name' => 'Aurora', 'slug' => 'aurora', 'base_price' => 1000000, 'min_guests' => 100, 'max_guests' => 300,
            'size_sqm' => 900, 'setting' => 'indoor', 'is_active' => true, 'sort_order' => 0,
        ]);
        $hall->images()->create(['image_url' => 'https://img.test/a.jpg?w=800', 'alt_text' => 'Lampu kristal', 'tags' => ['interior', 'chandelier'], 'sort_order' => 0]);
        $hall->images()->create(['image_url' => 'https://img.test/b.jpg?w=800', 'alt_text' => 'Pelaminan', 'tags' => ['ceremony'], 'sort_order' => 1]);

        $this->get('/demo/halls/aurora?lang=ja')->assertOk()
            ->assertSee('data-lightbox-detail', false)
            ->assertSee('data-lightbox-info', false)
            ->assertSee('詳細')
            ->assertSee('data-tags="[&quot;内観&quot;,&quot;シャンデリア&quot;]"', false)
            ->assertSee('900 m²');

        $this->get('/demo/halls/aurora?lang=en')->assertOk()->assertSee('Photo information')->assertSee('data-tags="[&quot;Interior&quot;,&quot;Chandelier&quot;]"', false);
        $this->get('/demo/halls/aurora?lang=id')->assertOk()->assertSee('Informasi foto')->assertSee('data-tags="[&quot;Akad \/ pemberkatan&quot;]"', false);
    }
}
