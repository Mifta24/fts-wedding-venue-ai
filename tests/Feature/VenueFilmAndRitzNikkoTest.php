<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venue;
use Database\Seeders\DemoVenueSeeder;
use Database\Seeders\RitzNikkoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenueFilmAndRitzNikkoTest extends TestCase
{
    use RefreshDatabase;

    public function test_video_id_is_taken_only_from_youtube_links(): void
    {
        $venue = new Venue;

        foreach ([
            'https://www.youtube.com/watch?v=rShzTN66Auw' => 'rShzTN66Auw',
            'https://youtu.be/rShzTN66Auw?t=5' => 'rShzTN66Auw',
            'https://www.youtube.com/watch?feature=share&v=rShzTN66Auw' => 'rShzTN66Auw',
            'https://www.youtube.com/embed/rShzTN66Auw' => 'rShzTN66Auw',
            'https://evil.test/watch?v=rShzTN66Auw' => null,
            'https://www.youtube.com.evil.test/watch?v=rShzTN66Auw' => null,
            'javascript:alert(1)' => null,
            '' => null,
        ] as $url => $expected) {
            $venue->video_url = $url;
            $this->assertSame($expected, $venue->videoId(), $url);
        }
    }

    public function test_the_film_button_shows_only_when_a_video_is_set_and_never_leaks_the_url(): void
    {
        $this->seed(DemoVenueSeeder::class);
        $demo = Venue::where('name', 'FTS Wedding Venue AI')->firstOrFail();

        $this->get(route('venue.show', $demo->slug))->assertOk()->assertDontSee('data-video-open', false);

        $demo->update(['video_url' => 'https://www.youtube.com/watch?v=rShzTN66Auw']);

        foreach (['id' => 'Tonton videonya', 'en' => 'Watch the film', 'ja' => '映像を見る', 'th' => 'ชมวิดีโอ', 'zh' => '觀看影片'] as $lang => $label) {
            $this->get(route('venue.show', [$demo->slug, 'lang' => $lang]))
                ->assertOk()
                ->assertSee('data-video-open', false)
                ->assertSee('data-video-id="rShzTN66Auw"', false)
                ->assertSee($label)
                ->assertDontSee('youtube.com/watch', false)
                ->assertDontSee('<iframe', false);
        }
    }

    public function test_admin_can_set_a_youtube_link_but_not_another_site(): void
    {
        $this->seed(DemoVenueSeeder::class);
        $owner = User::where('email', 'owner@ftswedding.test')->firstOrFail();
        $venue = Venue::where('name', 'FTS Wedding Venue AI')->firstOrFail();

        $payload = fn (array $extra) => [
            'name' => $venue->name, 'timezone' => $venue->timezone, 'default_locale' => 'id',
            'event_start_time' => '08:00', 'event_end_time' => '22:00',
            'weekday_discount_percent' => 15, 'deposit_percent' => 30, 'public_status' => 'published',
            ...$extra,
        ];

        $this->actingAs($owner)->put(route('admin.settings.update'), $payload(['video_url' => 'https://evil.test/x']))
            ->assertSessionHasErrors('video_url');

        $this->actingAs($owner)->put(route('admin.settings.update'), $payload(['video_url' => 'https://youtu.be/rShzTN66Auw']))
            ->assertSessionHasNoErrors();

        $this->assertSame('rShzTN66Auw', $venue->fresh()->videoId());
    }

    public function test_ritz_nikko_is_reachable_by_link_but_unlisted_and_noindex(): void
    {
        $this->seed(DemoVenueSeeder::class);
        $this->seed(RitzNikkoSeeder::class);

        $nikko = Venue::where('slug', RitzNikkoSeeder::SLUG)->firstOrFail();
        $this->assertSame('unlisted', $nikko->public_status);
        $this->assertSame('rShzTN66Auw', $nikko->videoId());

        $this->get(route('venue.show', $nikko->slug))
            ->assertOk()
            ->assertSee('noindex', false)
            ->assertSee('data-video-open', false)
            ->assertSee('/images/ritz-nikko/exterior.webp', false);

        // the test suite has no exchange rates, so every language falls back to the venue's own yen
        $this->get(route('venue.halls', [$nikko->slug, 'lang' => 'th']))->assertOk()->assertSee('¥');
        $this->get(route('venue.hall', [$nikko->slug, 'bar-and-lake-suite', 'lang' => 'zh']))->assertOk();

        // not in the opening list, nor the sitemap; the original demo is untouched
        $this->get('/')->assertOk()->assertDontSee('Ritz-Carlton')->assertSee('FTS Wedding Venue AI');
        $this->get(route('sitemap'))->assertOk()->assertDontSee(RitzNikkoSeeder::SLUG);

        $demo = Venue::where('name', 'FTS Wedding Venue AI')->firstOrFail();
        $this->get(route('venue.show', $demo->slug))->assertOk()->assertDontSee('noindex', false);
    }

    public function test_reseeding_ritz_nikko_does_not_duplicate_anything(): void
    {
        $this->seed(RitzNikkoSeeder::class);
        $nikko = Venue::where('slug', RitzNikkoSeeder::SLUG)->firstOrFail();
        $counts = [$nikko->halls()->count(), $nikko->knowledgeItems()->count(), Venue::count()];

        $this->seed(RitzNikkoSeeder::class);

        $this->assertSame($counts, [$nikko->halls()->count(), $nikko->knowledgeItems()->count(), Venue::count()]);
    }
}
