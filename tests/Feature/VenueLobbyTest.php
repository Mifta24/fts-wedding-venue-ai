<?php

namespace Tests\Feature;

use App\Models\Hall;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VenueLobbyTest extends TestCase
{
    use RefreshDatabase;

    private function venue(array $attributes = []): Venue
    {
        return Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', ...$attributes]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function hall(Venue $venue, string $name, array $attributes = []): Hall
    {
        return $venue->halls()->create([
            'name' => $name, 'slug' => Str::slug($name), 'base_price' => 50000000, 'min_guests' => 100, 'max_guests' => 300,
            'is_active' => true, 'sort_order' => 0, ...$attributes,
        ]);
    }

    public function test_opening_screen_links_to_the_only_published_venue(): void
    {
        $this->venue();
        Venue::create(['name' => 'Draft', 'slug' => 'draft']);

        $this->get('/?lang=en')
            ->assertOk()
            ->assertSee('Wedding Venue AI')
            ->assertSee('Enter Demo')
            ->assertSee('href="'.route('venue.halls', ['venueSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('href="'.route('venue.services', ['venueSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('href="'.route('venue.reservation', ['venueSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('href="'.route('venue.staff', ['venueSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertDontSee('Draft');
    }

    public function test_opening_screen_lists_every_published_venue_when_there_are_several(): void
    {
        Venue::create(['name' => 'First Venue', 'slug' => 'first', 'public_status' => 'published']);
        Venue::create(['name' => 'Second Venue', 'slug' => 'second', 'public_status' => 'published']);
        Venue::create(['name' => 'Hidden Venue', 'slug' => 'hidden']);

        $this->get('/')->assertOk()->assertSee('First Venue')->assertSee('Second Venue')->assertDontSee('Hidden Venue');
    }

    public function test_opening_screen_explains_when_no_venue_is_published(): void
    {
        $this->get('/?lang=en')->assertOk()->assertSee('The venue is being prepared');
    }

    public function test_chat_has_separate_open_and_close_controls_without_submitting_the_composer(): void
    {
        $this->venue();

        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('data-chat-launcher', false)
            ->assertSee('data-chat-composer', false)
            ->assertSee('type="button" data-chat-close', false)
            ->assertSee('aria-controls="concierge-chat-log"', false);
    }

    public function test_chat_exposes_consistent_status_draft_and_loading_copy_for_each_locale(): void
    {
        $this->venue();

        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('data-label-status-sent="Message sent"', false)
            ->assertSee('data-label-status-waiting="Waiting for the team to reply"', false)
            ->assertSee('data-label-status-replied="The team has replied"', false)
            ->assertSee('data-label-draft-title="Unsaved message"', false)
            ->assertSee('data-label-draft-keep="Keep typing"', false)
            ->assertSee('data-label-draft-discard="Discard draft"', false)
            ->assertSee('data-thinking-indicator', false)
            ->assertSee('data-chat-status', false)
            ->assertSee('data-draft-dialog', false);

        $this->get('/demo?lang=ja')
            ->assertOk()
            ->assertSee('data-label-status-sent="メッセージを送信しました"', false)
            ->assertSee('data-label-status-waiting="チームの返信を待っています"', false)
            ->assertSee('data-label-status-replied="チームが返信しました"', false)
            ->assertSee('data-label-draft-title="未送信のメッセージ"', false)
            ->assertSee('data-label-draft-keep="入力を続ける"', false)
            ->assertSee('data-label-draft-discard="下書きを破棄"', false);

        $this->get('/demo?lang=id')
            ->assertOk()
            ->assertSee('data-label-status-sent="Pesan terkirim"', false)
            ->assertSee('data-label-status-waiting="Menunggu balasan tim"', false)
            ->assertSee('data-label-status-replied="Tim telah membalas"', false)
            ->assertSee('data-label-draft-title="Pesan belum dikirim"', false)
            ->assertSee('data-label-draft-keep="Lanjut mengetik"', false)
            ->assertSee('data-label-draft-discard="Buang draft"', false);
    }

    public function test_the_services_page_lists_only_active_services_of_the_current_venue(): void
    {
        $venue = $this->venue();
        $venue->knowledgeItems()->create(['category' => 'services', 'title' => 'Garden decoration', 'body' => 'Fresh flowers.', 'is_active' => true, 'image_url' => 'https://example.test/decor.jpg']);
        $venue->knowledgeItems()->create(['category' => 'catering', 'title' => 'Buffet menu', 'body' => 'Halal buffet.', 'is_active' => true]);
        $venue->knowledgeItems()->create(['category' => 'services', 'title' => 'Hidden service', 'body' => 'Private.', 'is_active' => false]);
        $other = Venue::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $other->knowledgeItems()->create(['category' => 'services', 'title' => 'Other decoration', 'body' => 'Other venue.', 'is_active' => true]);

        $this->get('/demo/services')
            ->assertOk()
            ->assertSee('Garden decoration')
            ->assertSee('Buffet menu')
            ->assertDontSee('Hidden service')
            ->assertDontSee('Other decoration')
            ->assertSee('class="service-card"', false)
            ->assertSee('https://example.test/decor.jpg', false)
            ->assertSee('class="lobby-content stage-panel-right', false);

        $this->get('/demo?lang=en')->assertOk()->assertSee('Welcome to')->assertSee('your wedding day');
        $this->get('/demo?lang=ja')->assertOk()->assertSee('ふたりの特別な一日へ');
    }

    public function test_unpublished_lobby_is_not_accessible(): void
    {
        Venue::create(['name' => 'Draft', 'slug' => 'draft']);

        $this->get('/draft')->assertNotFound();
    }

    public function test_the_halls_page_lists_only_active_halls_of_the_current_venue(): void
    {
        $venue = $this->venue();
        $first = $this->hall($venue, 'Garden Pavilion', ['setting' => 'outdoor', 'view_type' => 'garden']);
        $first->images()->create(['image_url' => 'https://example.test/one.jpg', 'alt_text' => 'Aisle', 'sort_order' => 0]);
        $this->hall($venue, 'Grand Ballroom', ['sort_order' => 1]);
        $this->hall($venue, 'Retired Hall', ['is_active' => false]);
        $other = Venue::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $this->hall($other, 'Foreign Hall');

        $this->get('/demo/halls?lang=en')
            ->assertOk()
            ->assertSee('Garden Pavilion')
            ->assertSee('Grand Ballroom')
            ->assertSee('narrator-on-stage', false)
            ->assertSee('class="hall-nav-thumb"', false)
            ->assertSee('href="'.route('venue.hall', ['venueSlug' => 'demo', 'hallSlug' => 'garden-pavilion', 'lang' => 'en']).'"', false)
            ->assertDontSee('Retired Hall')
            ->assertDontSee('Foreign Hall');
    }

    public function test_a_hall_page_shows_its_details_gallery_and_neighbouring_halls(): void
    {
        $venue = $this->venue(['weekday_discount_percent' => 15, 'deposit_percent' => 30]);
        $first = $this->hall($venue, 'Garden Pavilion', [
            'size_sqm' => 1400, 'setting' => 'outdoor', 'view_type' => 'garden', 'seating_styles' => ['banquet', 'theatre'],
            'amenities' => ['sound_system', 'bridal_room'], 'extra_hour_available' => true, 'extra_hour_price' => 3000000,
        ]);
        $first->images()->create(['image_url' => 'https://example.test/one.jpg', 'alt_text' => 'Aisle at dusk', 'sort_order' => 0]);
        $first->images()->create(['image_url' => 'https://example.test/two.jpg', 'alt_text' => 'Reception tables', 'sort_order' => 1]);
        $this->hall($venue, 'Grand Ballroom', ['sort_order' => 1, 'max_guests' => 800]);

        $ballroomUrl = route('venue.hall', ['venueSlug' => 'demo', 'hallSlug' => 'grand-ballroom', 'lang' => 'en']);

        $this->get('/demo/halls/garden-pavilion?lang=en')
            ->assertOk()
            ->assertSee('Hall 01 / 02')
            ->assertSee('Aisle at dusk')
            ->assertSee('Garden view')
            ->assertSee('100–300 guests')
            ->assertSee('Round-table banquet, Theatre rows')
            ->assertSee('Sound system')
            ->assertSee('Bridal suite')
            ->assertSee('1400 m²')
            ->assertSee('15% off events from Monday to Thursday')
            ->assertSee('30% down payment secures your date')
            ->assertSee('https://example.test/two.jpg', false)
            ->assertSee('Final availability and rates are confirmed by the wedding team.')
            ->assertSee('rel="prev"', false)
            ->assertSee($ballroomUrl, false);

        $this->get('/demo/halls/retired-or-unknown')->assertNotFound();
        $this->get('/demo/halls/garden-pavilion?lang=id')->assertOk()->assertSee('Pemandangan taman');
    }

    public function test_hall_pages_follow_the_venue_publication_and_hall_state(): void
    {
        $venue = $this->venue();
        $this->hall($venue, 'Hidden', ['is_active' => false]);
        Venue::create(['name' => 'Draft', 'slug' => 'draft']);

        $this->get('/demo/halls?lang=en')->assertOk()->assertSee('Hall information will be available soon.');
        $this->get('/demo/halls/hidden')->assertNotFound();
        $this->get('/draft/halls')->assertNotFound();
    }

    public function test_the_lobby_walks_the_client_through_every_chapter(): void
    {
        $venue = $this->venue();
        $this->hall($venue, 'Garden Pavilion');

        $this->get('/demo?lang=id')
            ->assertOk()
            ->assertSee('href="'.route('venue.halls', ['venueSlug' => 'demo', 'lang' => 'id']).'"', false)
            ->assertSee('data-tour-line="Membuka galeri hall."', false)
            ->assertSee('href="'.route('venue.services', ['venueSlug' => 'demo', 'lang' => 'id']).'"', false)
            ->assertSee('data-tour-line="Menuju layanan pernikahan."', false);

        $this->get('/demo/halls?lang=id')
            ->assertOk()
            ->assertSee('data-tour-line="Kembali ke sambutan."', false)
            ->assertSee('data-scene="halls"', false)
            ->assertSee('data-chapter="II"', false);
    }

    public function test_reservation_wizard_and_staff_channels_render_from_venue_data(): void
    {
        $venue = $this->venue(['whatsapp' => '+62 812-0000-1111', 'phone' => '+62 361 000', 'email' => 'wedding@demo.test']);
        $this->hall($venue, 'Garden Pavilion');

        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('href="'.route('venue.reservation', ['venueSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('data-tour-line="Opening the date request to hold your day."', false)
            ->assertSee('href="'.route('venue.staff', ['venueSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('data-tour-line="Stepping over to the wedding team."', false);

        $this->get('/demo/reservation?lang=en')
            ->assertOk()
            ->assertSee('data-scene="reservation"', false)
            ->assertSee('data-tour-line="Returning to the welcome."', false)
            ->assertSee('data-step="5"', false)
            ->assertSee('name="event_date"', false)
            ->assertSee('value="akad_reception"', false)
            ->assertSee('value="garden-pavilion"', false)
            ->assertSee('Step :current of :total', false)
            ->assertSee(route('reservation.store', 'demo'), false);

        $this->get('/demo/reservation?lang=id')->assertOk()->assertSee('Langkah :current dari :total', false);

        $this->get('/demo/reservation?lang=en&hall=garden-pavilion')
            ->assertOk()
            ->assertSee('data-preselect-hall="garden-pavilion"', false);

        $this->get('/demo/halls/garden-pavilion?lang=en')
            ->assertOk()
            ->assertSee('href="'.e(route('venue.reservation', ['venueSlug' => 'demo', 'lang' => 'en', 'hall' => 'garden-pavilion'])).'"', false);

        $this->get('/demo/staff?lang=en')
            ->assertOk()
            ->assertSee('data-scene="staff"', false)
            ->assertSee('data-tour-line="Returning to the welcome."', false)
            ->assertSee('https://wa.me/6281200001111?text=', false)
            ->assertSee('tel:+62361000', false)
            ->assertSee('mailto:wedding@demo.test', false);

        $this->get('/draft/staff')->assertNotFound();
        $this->get('/draft/reservation')->assertNotFound();
    }

    public function test_every_chapter_shows_the_order_of_the_day_with_its_own_key_lit(): void
    {
        $this->venue();

        foreach (['' => 'I', '/halls' => 'II', '/services' => 'III', '/info' => 'IV', '/reservation' => 'V', '/staff' => 'VI'] as $path => $chapter) {
            $page = $this->get('/demo'.$path.'?lang=en')
                ->assertOk()
                ->assertSee('class="journey-panel"', false)
                ->assertSee('data-chapter="'.$chapter.'"', false)
                ->assertSee('<span class="chapter-display-code" data-chapter-code>'.$chapter.'</span>', false)
                ->getContent();

            $panel = Str::between($page, 'class="journey-panel"', '</nav>');
            $this->assertSame(1, substr_count($panel, 'aria-current="page"'));
            $this->assertMatchesRegularExpression('/aria-current="page"\s*>\s*<span class="journey-key" aria-hidden="true">'.$chapter.'<\/span>/', $panel);
        }
    }

    public function test_venue_information_and_team_chapters_use_narrow_sheets(): void
    {
        $this->venue();

        $this->get('/demo/staff?lang=en')->assertOk()->assertSee('class="lobby-content staff-panel', false);
        $this->get('/demo/info?lang=en')->assertOk()->assertSee('class="lobby-content info-panel', false);
    }

    public function test_every_stage_scene_uses_the_same_chat_controls_and_dock_contract(): void
    {
        $venue = $this->venue();
        $this->hall($venue, 'Garden Pavilion');
        $venue->knowledgeItems()->create(['category' => 'services', 'title' => 'Decoration', 'body' => 'Fresh flowers.', 'is_active' => true]);

        foreach (['/demo', '/demo/info', '/demo/halls', '/demo/services', '/demo/reservation', '/demo/staff'] as $path) {
            $this->get($path.'?lang=en')
                ->assertOk()
                ->assertSee('data-chat-dock="', false)
                ->assertSee('data-chat-launcher', false)
                ->assertSee('data-chat-composer', false)
                ->assertSee('aria-controls="concierge-chat-log"', false);
        }
    }

    public function test_each_service_has_its_own_page_with_neighbours_and_an_index(): void
    {
        $venue = $this->venue();
        $decor = $venue->knowledgeItems()->create(['category' => 'services', 'title' => 'Garden decoration', 'body' => 'Fresh flowers all day.', 'is_active' => true, 'sort_order' => 0]);
        $photo = $venue->knowledgeItems()->create(['category' => 'services', 'title' => 'Photo and video', 'body' => 'Two photographers.', 'is_active' => true, 'sort_order' => 1]);
        $hidden = $venue->knowledgeItems()->create(['category' => 'services', 'title' => 'Hidden service', 'body' => 'Private.', 'is_active' => false]);

        $url = fn ($item) => route('venue.service', ['venueSlug' => 'demo', 'serviceId' => $item->id, 'lang' => 'en']);

        $this->get('/demo/services?lang=en')
            ->assertOk()
            ->assertSee('data-scene="services"', false)
            ->assertSee($url($decor), false)
            ->assertSee($url($photo), false)
            ->assertDontSee($url($hidden), false);

        $this->get($url($decor))
            ->assertOk()
            ->assertSee('Service 1 / 2')
            ->assertSee('Fresh flowers all day.')
            ->assertSee('Ask about this service')
            ->assertSee('rel="prev"', false)
            ->assertSee($url($photo), false);

        $this->get($url($hidden))->assertNotFound();
        $this->get('/demo/services/999999')->assertNotFound();
        $this->get('/draft/services')->assertNotFound();
        $this->get('/draft/info')->assertNotFound();
    }

    public function test_the_venue_information_scene_lists_only_approved_about_policy_access_and_faq_entries(): void
    {
        $venue = $this->venue([
            'address' => 'Jl. Taman 8', 'city' => 'Bintaro', 'country' => 'Indonesia',
            'event_start_time' => '08:00', 'event_end_time' => '22:00', 'description' => 'A garden wedding venue.', 'latitude' => -6.27, 'longitude' => 106.73,
        ]);
        $venue->knowledgeItems()->create(['category' => 'policies', 'title' => 'Cancellation', 'body' => 'Free date change once, 90 days ahead.', 'is_active' => true]);
        $venue->knowledgeItems()->create(['category' => 'access', 'title' => 'Parking', 'body' => 'Space for 350 cars.', 'is_active' => true]);
        $venue->knowledgeItems()->create(['category' => 'faq', 'title' => 'Can we visit first?', 'body' => 'Yes, every day by appointment.', 'is_active' => true]);
        $venue->knowledgeItems()->create(['category' => 'policies', 'title' => 'Draft policy', 'body' => 'Not approved.', 'is_active' => false]);
        $venue->knowledgeItems()->create(['category' => 'services', 'title' => 'Decoration', 'body' => 'Belongs to services.', 'is_active' => true]);

        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('href="'.route('venue.info', ['venueSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('data-tour-line="Turning to the venue information."', false)
            ->assertDontSee('Free date change once, 90 days ahead.');

        $this->get('/demo/info?lang=en')
            ->assertOk()
            ->assertSee('data-scene="info"', false)
            ->assertSee('data-tour-line="Returning to the welcome."', false)
            ->assertSee('Free date change once, 90 days ahead.')
            ->assertSee('Space for 350 cars.')
            ->assertSee('Can we visit first?')
            ->assertSee('class="info-hours-time"', false)
            ->assertSeeInOrder(['08:00', '22:00'])
            ->assertSee('https://www.google.com/maps?q=-6.2700000,106.7300000', false)
            ->assertDontSee('Not approved.');
    }

    public function test_the_lobby_shows_a_localised_loader_and_the_opening_screen_too(): void
    {
        $this->venue();

        $this->get('/demo?lang=id')->assertOk()->assertSee('data-stage-loader', false)->assertSee('Membuka tirai…');
        $this->get('/?lang=en')->assertOk()->assertSee('data-stage-exit', false)->assertSee('Drawing the curtains…');
    }

    public function test_the_concierge_introduces_halls_using_only_stored_venue_data(): void
    {
        $venue = $this->venue(['weekday_discount_percent' => 15]);
        $this->hall($venue, 'Garden Pavilion', [
            'description' => 'A calm hall under the trees', 'base_price' => 55000000, 'size_sqm' => 1200, 'setting' => 'outdoor',
            'min_guests' => 150, 'max_guests' => 500, 'view_type' => 'garden',
        ]);
        $this->hall($venue, 'Salon', ['base_price' => 28000000, 'setting' => 'indoor', 'min_guests' => 40, 'max_guests' => 120, 'sort_order' => 1]);

        $this->get('/demo/halls?lang=en')
            ->assertOk()
            ->assertSee('data-narrator', false)
            ->assertSee('We have 2 halls, from IDR 28.000.000 per event.');

        $this->get('/demo/halls/garden-pavilion?lang=en')
            ->assertOk()
            ->assertSee('Garden Pavilion. A calm hall under the trees. An open-air setting. It seats 150 to 500 guests across 1200 m². Garden view. Rental starts from IDR 55.000.000 per event. Final availability and rates are confirmed by our wedding team. Events from Monday to Thursday get 15% off.');

        $this->get('/demo/halls/salon?lang=en')
            ->assertOk()
            ->assertSee('Salon. An air-conditioned indoor hall. It seats 40 to 120 guests. Rental starts from IDR 28.000.000 per event.');

        $this->get('/demo/halls?lang=id')->assertOk()->assertSee('Ada 2 hall, mulai dari IDR 28.000.000 per acara.');
    }

    public function test_no_hall_narration_is_shown_when_the_venue_has_no_halls(): void
    {
        $this->venue();

        $this->get('/demo/halls?lang=en')->assertOk()->assertDontSee('data-key="halls"', false)->assertDontSee('Welcome to the hall gallery');
    }

    public function test_the_concierge_introduces_services_and_venue_information_from_stored_data(): void
    {
        $venue = $this->venue(['city' => 'Bintaro', 'country' => 'Indonesia', 'event_start_time' => '08:00', 'event_end_time' => '22:00']);
        $venue->knowledgeItems()->create(['category' => 'services', 'title' => 'Garden decoration', 'body' => "Fresh flowers.\nAisle lights included.", 'is_active' => true, 'sort_order' => 0]);
        $venue->knowledgeItems()->create(['category' => 'services', 'title' => 'Photo and video', 'body' => 'Two photographers.', 'is_active' => true, 'sort_order' => 1]);
        $venue->knowledgeItems()->create(['category' => 'services', 'title' => 'Hidden service', 'body' => 'Private.', 'is_active' => false, 'sort_order' => 2]);

        $decor = $venue->knowledgeItems()->where('title', 'Garden decoration')->firstOrFail();

        $this->get('/demo/services?lang=en')
            ->assertOk()
            ->assertSee('Your day is supported by 2 wedding services, including Garden decoration; Photo and video.')
            ->assertDontSee('including Garden decoration; Photo and video; Hidden service');

        $this->get(route('venue.service', ['venueSlug' => 'demo', 'serviceId' => $decor->id, 'lang' => 'en']))
            ->assertOk()
            ->assertSee('data-key="service-', false)
            ->assertSee('data-text="Fresh flowers.', false);

        $this->get('/demo/info?lang=en')
            ->assertOk()
            ->assertSee('Welcome to Demo. The venue is in Bintaro, Indonesia. Events run from 08:00 to 22:00.');

        $this->get('/demo/info?lang=id')->assertOk()->assertSee('Selamat datang di Demo. Gedung kami berada di Bintaro, Indonesia.');
    }

    public function test_venue_information_narration_skips_missing_data(): void
    {
        Venue::create(['name' => 'Bare', 'slug' => 'bare', 'public_status' => 'published']);

        $this->get('/bare/info?lang=en')
            ->assertOk()
            ->assertSee('Welcome to Bare. Events run from 08:00 to 22:00. Below you will find the address')
            ->assertDontSee('The venue is in');
    }

    public function test_the_sound_toggle_is_available_on_the_opening_screen_and_the_lobby_in_every_language(): void
    {
        $this->venue();

        $this->get('/?lang=en')->assertOk()->assertSee('data-sound-toggle', false)->assertSee('Sound on')->assertSee('Sound off');
        $this->get('/?lang=id')->assertOk()->assertSee('Suara aktif')->assertSee('Suara mati');
        $this->get('/demo?lang=en')->assertOk()->assertSee('data-sound-toggle', false)->assertSee('data-label-off="Sound off"', false);
        $this->get('/demo/info?lang=ja')
            ->assertOk()
            ->assertSee('サウンドオン')
            ->assertSee('data-lang="ja-JP"', false)
            ->assertSee('data-voice-preference="female"', false);
    }

    public function test_the_hall_chapter_shows_a_directory_of_halls(): void
    {
        $venue = $this->venue();
        $this->hall($venue, 'Garden Pavilion', ['base_price' => 55000000]);
        $this->hall($venue, 'Grand Ballroom', ['base_price' => 85000000, 'sort_order' => 1]);
        $this->hall($venue, 'Retired Hall', ['is_active' => false]);

        $this->get('/demo/halls/grand-ballroom?lang=id')
            ->assertOk()
            ->assertSee('class="hall-directory"', false)
            ->assertSee('class="hall-nav"', false)
            ->assertSee('mulai dari IDR&nbsp;55.000.000 / acara', false)
            ->assertDontSee('Retired Hall')
            ->assertSee('href="'.route('venue.show', ['venueSlug' => 'demo', 'lang' => 'id']).'" class="journey-button"', false);

        $index = $this->get('/demo/halls/grand-ballroom?lang=id')->getContent();
        $navigation = Str::between($index, '<nav class="hall-nav">', '</nav>');
        $this->assertSame(1, substr_count($navigation, 'aria-current'));

        $this->get('/demo?lang=id')
            ->assertOk()
            ->assertSee('href="'.route('venue.reservation', ['venueSlug' => 'demo', 'lang' => 'id']).'"', false)
            ->assertDontSee('class="hall-nav"', false);
    }

    public function test_each_chapter_opens_onto_its_own_photograph_with_the_concierge_posed_in_front(): void
    {
        $venue = $this->venue();
        $this->hall($venue, 'Garden Pavilion');

        $this->get('/demo')
            ->assertOk()
            ->assertSee('photo-1670529776180-60e4132ab90c', false)
            ->assertSee('class="stage-character" data-pose="standing"', false);
        $this->get('/demo/halls')
            ->assertOk()
            ->assertSee('photo-1519167758481-83f550bb49b3', false)
            ->assertSee('class="stage-character" data-pose="presenting"', false);
        $this->get('/demo/info')
            ->assertOk()
            ->assertSee('photo-1505944357431-27579db47558', false)
            ->assertSee('class="stage-character" data-pose="standing"', false)
            ->assertSee('images/character/character avatar.jpg', false);
        $this->get('/demo/staff')->assertOk()->assertSee('class="stage-character" data-pose="consulting"', false);
        $this->get('/demo/reservation')->assertOk()->assertSee('class="stage-character" data-pose="consulting"', false);

        $this->get('/demo/halls/garden-pavilion')
            ->assertOk()
            ->assertSee('photo-1519167758481-83f550bb49b3', false)
            ->assertSee('images/character/character avatar.jpg', false)
            ->assertDontSee('class="stage-character"', false);
    }

    public function test_service_icons_follow_what_the_entry_is_about(): void
    {
        $venue = $this->venue();
        $icon = fn (string $title, array $tags = []) => $venue->knowledgeItems()->make(['category' => 'services', 'title' => $title, 'body' => '-', 'tags' => $tags])->iconName();

        $this->assertSame('decor', $icon('Dekorasi dan bunga'));
        $this->assertSame('catering', $icon('Paket katering prasmanan'));
        $this->assertSame('photo', $icon('Foto, video, dan drone'));
        $this->assertSame('makeup', $icon('Rias pengantin'));
        $this->assertSame('music', $icon('Hiburan dan band'));
        $this->assertSame('planner', $icon('Wedding organizer'));
        $this->assertSame('parking', $icon('Parkir dan valet'));
        $this->assertSame('transport', $icon('Antar-jemput bandara'));
        $this->assertSame('payment', $icon('Uang muka', ['deposit']));
        $this->assertSame('wifi', $icon('Internet', ['wifi']));
        $this->assertSame('transit', $icon('Lokasi dan stasiun'));
        $this->assertSame('star', $icon('Kids corner'));
    }

    public function test_every_journey_button_leads_to_its_chapter_with_a_topic_for_the_concierge(): void
    {
        $this->venue();

        $scenes = [
            'venue.halls' => 'Saya ingin melihat pilihan hall dan kapasitasnya.',
            'venue.services' => 'Layanan dan vendor pernikahan apa saja yang tersedia?',
            'venue.info' => 'Bagaimana ketentuan uang muka, pembatalan, dan aturan gedung?',
            'venue.reservation' => 'Saya ingin mengamankan tanggal pernikahan. Bantu saya cek ketersediaan.',
            'venue.staff' => 'Saya ingin bicara dengan tim wedding.',
        ];

        $response = $this->get('/demo?lang=id')->assertOk();

        foreach ($scenes as $route => $topic) {
            $url = route($route, ['venueSlug' => 'demo', 'lang' => 'id']);

            $response->assertSee('href="'.$url.'"', false)
                ->assertSeeInOrder(['class="journey-panel"', 'href="'.$url.'"', 'data-stage-exit', 'data-topic="'.$topic.'"'], false);
            $this->get($url)->assertOk();
        }
    }

    public function test_lobby_offers_the_halls_and_a_date_request_as_the_two_main_actions_in_the_client_language(): void
    {
        $venue = $this->venue();
        $this->hall($venue, 'Garden Pavilion');

        $this->get('/demo?lang=id')
            ->assertOk()
            ->assertSee('class="lobby-action lobby-action-signal" data-stage-exit', false)
            ->assertSee('href="'.route('venue.halls', ['venueSlug' => 'demo', 'lang' => 'id']).'"', false)
            ->assertSee('Lihat hall')
            ->assertSee('href="'.route('venue.reservation', ['venueSlug' => 'demo', 'lang' => 'id']).'"', false)
            ->assertSee('Amankan tanggal saya');

        $this->get('/demo?lang=en')->assertOk()->assertSee('See the halls')->assertSee('Hold my date');
        $this->get('/demo?lang=ja')->assertOk()->assertSee('ホールを見る');
    }

    public function test_lobby_without_any_hall_shows_no_actions_that_lead_nowhere(): void
    {
        $this->venue();

        $this->get('/demo?lang=en')->assertOk()->assertDontSee('lobby-cta', false)->assertDontSee('See the halls');
    }

    public function test_lobby_stats_show_the_price_from_the_capacity_and_the_weekday_saving(): void
    {
        $venue = $this->venue(['weekday_discount_percent' => 15]);
        $this->hall($venue, 'Garden Pavilion', ['base_price' => 28000000, 'max_guests' => 1000]);

        $this->get('/demo?lang=en')->assertOk()->assertSee('IDR 28.000.000')->assertSee('1.000 guests')->assertSee('15% off');
    }
}
