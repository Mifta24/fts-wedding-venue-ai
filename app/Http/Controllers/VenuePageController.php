<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Hall;
use App\Models\Venue;
use App\Models\VenueKnowledgeItem;
use App\Services\Reservation\ReservationHandover;
use App\Services\Reservation\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class VenuePageController extends Controller
{
    private const SUPPORTED_LOCALES = ['id', 'en', 'ja'];

    /** The concierge's face for the chat and narrator avatars. */
    private const AVATAR_IMAGE = 'images/character/character avatar.jpg';

    /**
     * The concierge's poses, transparent cut-outs shown in front of the scene photo.
     *
     * @var array<string, string>
     */
    private const CHARACTER_POSES = [
        'standing' => 'images/character/character standing.png',
        'presenting' => 'images/character/character right hand.png',
        'consulting' => 'images/character/character grateful.png',
    ];

    /**
     * Which pose she takes in each chapter.
     *
     * @var array<string, string>
     */
    private const SCENE_POSES = [
        'halls' => 'presenting',
        'reservation' => 'consulting',
        'staff' => 'consulting',
    ];

    private const OPENING_PHOTO = 'https://images.unsplash.com/photo-1606490194859-07c18c9f0968?auto=format&fit=crop&w=2000&q=80';

    /**
     * Wedding photography behind each scene, so every chapter of the site
     * opens onto the venue itself.
     *
     * @var array<string, array{image: string, focus: string, focusMobile: string}>
     */
    private const SCENE_PHOTOS = [
        'lobby' => ['image' => 'https://images.unsplash.com/photo-1670529776180-60e4132ab90c?auto=format&fit=crop&w=2000&q=80', 'focus' => 'center 55%', 'focusMobile' => '50% 55%'],
        'halls' => ['image' => 'https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&w=2000&q=80', 'focus' => 'center 50%', 'focusMobile' => '40% 50%'],
        'services' => ['image' => 'https://images.unsplash.com/photo-1560117531-02eeab8e3593?auto=format&fit=crop&w=2000&q=80', 'focus' => 'center 45%', 'focusMobile' => '50% 45%'],
        'info' => ['image' => 'https://images.unsplash.com/photo-1505944357431-27579db47558?auto=format&fit=crop&w=2000&q=80', 'focus' => 'center 55%', 'focusMobile' => '50% 55%'],
        'staff' => ['image' => 'https://images.unsplash.com/photo-1606490208247-b65be3d94cd1?auto=format&fit=crop&w=2000&q=80', 'focus' => 'center 40%', 'focusMobile' => '45% 40%'],
        'reservation' => ['image' => 'https://images.unsplash.com/photo-1587271407850-8d438ca9fdf2?auto=format&fit=crop&w=2000&q=80', 'focus' => 'center 50%', 'focusMobile' => '50% 50%'],
    ];

    /**
     * Each section of the site is a chapter of the wedding day, reached from
     * the order-of-the-day panel. The numeral is what the panel and the
     * chapter display show.
     *
     * @var array<string, string>
     */
    public const CHAPTERS = [
        'lobby' => 'I',
        'halls' => 'II',
        'services' => 'III',
        'info' => 'IV',
        'reservation' => 'V',
        'staff' => 'VI',
    ];

    public function __construct(private readonly ReservationHandover $handover) {}

    /**
     * What the AI concierge says when the client steps into a chapter.
     * Every sentence is built from stored venue data, never generated.
     *
     * @var array<string, array<string, string>>
     */
    private const NARRATION = [
        'en' => [
            'halls' => 'Welcome to the hall gallery. We have :count halls, from :price per event. Open any of them and I will walk you through the atmosphere and capacity, or ask me anything.',
            'halls_one' => 'Welcome to the hall gallery. We have one hall, from :price per event. Open it and I will walk you through the atmosphere and capacity, or ask me anything.',
            'capacity' => 'It seats :min to :max guests across :size m².',
            'capacity_nosize' => 'It seats :min to :max guests.',
            'price' => 'Rental starts from :price per event. Final availability and rates are confirmed by our wedding team.',
            'weekday' => 'Events from Monday to Thursday get :percent% off.',
            'services' => 'Your day is supported by :count wedding services, including :examples. Choose one to read the details, or ask me anything.',
            'services_one' => 'Your day is supported by :examples. Open it to read the details, or ask me anything.',
            'info_welcome' => 'Welcome to :venue.',
            'info_place' => 'The venue is in :location.',
            'info_hours' => 'Events run from :in to :out.',
            'info_more' => 'Below you will find the address, our policies and frequently asked questions — or just ask me.',
            'tour_halls' => 'Opening the hall gallery.',
            'tour_services' => 'Turning to the wedding services.',
            'tour_info' => 'Turning to the venue information.',
            'tour_staff' => 'Stepping over to the wedding team.',
            'tour_reservation' => 'Opening the date request to hold your day.',
            'tour_lobby' => 'Returning to the welcome.',
            'listen' => 'Listen', 'stop' => 'Stop', 'skip' => 'Skip', 'speaks' => 'is speaking',
        ],
        'id' => [
            'halls' => 'Selamat datang di galeri hall. Ada :count hall, mulai dari :price per acara. Buka salah satunya, nanti saya jelaskan suasana dan kapasitasnya — atau tanyakan apa saja kepada saya.',
            'halls_one' => 'Selamat datang di galeri hall. Ada satu hall, mulai dari :price per acara. Bukalah, nanti saya jelaskan suasana dan kapasitasnya — atau tanyakan apa saja kepada saya.',
            'capacity' => 'Hall ini menampung :min sampai :max tamu dengan luas :size m².',
            'capacity_nosize' => 'Hall ini menampung :min sampai :max tamu.',
            'price' => 'Sewa mulai dari :price per acara. Ketersediaan dan tarif final dikonfirmasi oleh tim wedding kami.',
            'weekday' => 'Acara hari Senin sampai Kamis dapat potongan :percent%.',
            'services' => 'Hari bahagia Anda didukung :count layanan pernikahan, di antaranya :examples. Pilih salah satu untuk membaca detailnya, atau tanyakan apa saja kepada saya.',
            'services_one' => 'Hari bahagia Anda didukung :examples. Buka untuk membaca detailnya, atau tanyakan apa saja kepada saya.',
            'info_welcome' => 'Selamat datang di :venue.',
            'info_place' => 'Gedung kami berada di :location.',
            'info_hours' => 'Acara berlangsung pukul :in sampai :out.',
            'info_more' => 'Di bawah ini ada alamat, kebijakan kami, dan pertanyaan yang sering diajukan — atau tanyakan langsung kepada saya.',
            'tour_halls' => 'Membuka galeri hall.',
            'tour_services' => 'Menuju layanan pernikahan.',
            'tour_info' => 'Menuju informasi gedung.',
            'tour_staff' => 'Menuju tim wedding kami.',
            'tour_reservation' => 'Membuka pengajuan tanggal untuk mengamankan hari Anda.',
            'tour_lobby' => 'Kembali ke sambutan.',
            'listen' => 'Dengarkan', 'stop' => 'Berhenti', 'skip' => 'Lewati', 'speaks' => 'sedang berbicara',
        ],
        'ja' => [
            'halls' => 'ホールギャラリーへようこそ。:count 件のホールを、1挙式 :price からご用意しています。ホールを開いていただければ、雰囲気と収容人数をご案内します。ご質問もお気軽にどうぞ。',
            'halls_one' => 'ホールギャラリーへようこそ。1件のホールを、1挙式 :price からご用意しています。開いていただければ、雰囲気と収容人数をご案内します。ご質問もお気軽にどうぞ。',
            'capacity' => '広さは:size m²、:min〜:max名様までご着席いただけます。',
            'capacity_nosize' => ':min〜:max名様までご着席いただけます。',
            'price' => '会場費は1挙式 :price から。空き状況と料金は、ウェディングチームが最終確認いたします。',
            'weekday' => '月曜日から木曜日の挙式は:percent%オフです。',
            'services' => '素敵な一日を:count件のウェディングサービスがお支えします。:examples など。選ぶと詳細をご覧いただけます。',
            'services_one' => '素敵な一日を:examples がお支えします。詳細をご覧ください。',
            'info_welcome' => ':venue へようこそ。',
            'info_place' => '会場は:locationにございます。',
            'info_hours' => '挙式のお時間は:in〜:outです。',
            'info_more' => '以下に、所在地、ご利用規約、よくあるご質問をご案内しています。お気軽にお尋ねください。',
            'tour_halls' => 'ホールギャラリーをご案内します。',
            'tour_services' => 'ウェディングサービスへご案内します。',
            'tour_info' => '会場のご案内へ進みます。',
            'tour_staff' => 'ウェディングチームのもとへご案内します。',
            'tour_reservation' => '日程のご予約リクエストをご案内します。',
            'tour_lobby' => 'ウェルカムへ戻ります。',
            'listen' => '音声で聞く', 'stop' => '停止', 'skip' => 'スキップ', 'speaks' => '話しています',
        ],
    ];

    /**
     * Client-facing names for the coded hall values stored in the database.
     *
     * @var array<string, array{setting: array<string, string>, setting_sentence: array<string, string>, seating: array<string, string>, view: array<string, string>, amenity: array<string, string>}>
     */
    private const HALL_TERMS = [
        'en' => [
            'setting' => ['indoor' => 'Indoor', 'outdoor' => 'Outdoor', 'semi_outdoor' => 'Semi-outdoor'],
            'setting_sentence' => ['indoor' => 'An air-conditioned indoor hall', 'outdoor' => 'An open-air setting', 'semi_outdoor' => 'A semi-outdoor space under a roof'],
            'seating' => ['banquet' => 'Round-table banquet', 'theatre' => 'Theatre rows', 'cocktail' => 'Standing cocktail', 'long_table' => 'Long family tables', 'lounge' => 'Lounge seating'],
            'view' => ['garden' => 'Garden view', 'lake' => 'Lake view', 'skyline' => 'Skyline view', 'pool' => 'Pool view', 'city' => 'City view', 'mountain' => 'Mountain view'],
            'amenity' => ['air_conditioning' => 'Air conditioning', 'sound_system' => 'Sound system', 'led_screen' => 'LED screen', 'stage' => 'Stage', 'bridal_room' => 'Bridal suite', 'parking' => 'Guest parking', 'generator' => 'Backup generator', 'wifi' => 'Wi-Fi', 'catering_kitchen' => 'Catering kitchen', 'chandelier' => 'Chandeliers', 'aisle_runway' => 'Aisle runway', 'stage_lighting' => 'Stage lighting', 'valet' => 'Valet parking', 'prayer_room' => 'Prayer room', 'garden_lights' => 'Garden lights', 'rain_cover' => 'Rain cover'],
        ],
        'id' => [
            'setting' => ['indoor' => 'Indoor', 'outdoor' => 'Outdoor', 'semi_outdoor' => 'Semi-outdoor'],
            'setting_sentence' => ['indoor' => 'Hall tertutup ber-AC', 'outdoor' => 'Area terbuka', 'semi_outdoor' => 'Area semi-outdoor beratap'],
            'seating' => ['banquet' => 'Banquet meja bundar', 'theatre' => 'Tatanan teater', 'cocktail' => 'Cocktail berdiri', 'long_table' => 'Meja panjang keluarga', 'lounge' => 'Tempat duduk lounge'],
            'view' => ['garden' => 'Pemandangan taman', 'lake' => 'Pemandangan danau', 'skyline' => 'Pemandangan skyline', 'pool' => 'Pemandangan kolam', 'city' => 'Pemandangan kota', 'mountain' => 'Pemandangan gunung'],
            'amenity' => ['air_conditioning' => 'AC', 'sound_system' => 'Sound system', 'led_screen' => 'Layar LED', 'stage' => 'Panggung', 'bridal_room' => 'Ruang rias pengantin', 'parking' => 'Parkir tamu', 'generator' => 'Genset cadangan', 'wifi' => 'Wi-Fi', 'catering_kitchen' => 'Dapur katering', 'chandelier' => 'Lampu kristal', 'aisle_runway' => 'Lorong pengantin', 'stage_lighting' => 'Tata cahaya panggung', 'valet' => 'Parkir valet', 'prayer_room' => 'Mushola', 'garden_lights' => 'Lampu taman', 'rain_cover' => 'Atap pelindung hujan'],
        ],
        'ja' => [
            'setting' => ['indoor' => '屋内', 'outdoor' => '屋外', 'semi_outdoor' => '半屋外'],
            'setting_sentence' => ['indoor' => '空調の効いた屋内ホール', 'outdoor' => '開放的な屋外会場', 'semi_outdoor' => '屋根付きの半屋外スペース'],
            'seating' => ['banquet' => '円卓バンケット', 'theatre' => 'シアター形式', 'cocktail' => '立食カクテル', 'long_table' => 'ロングテーブル', 'lounge' => 'ラウンジ席'],
            'view' => ['garden' => 'ガーデンビュー', 'lake' => 'レイクビュー', 'skyline' => 'スカイラインビュー', 'pool' => 'プールビュー', 'city' => 'シティビュー', 'mountain' => 'マウンテンビュー'],
            'amenity' => ['air_conditioning' => '空調', 'sound_system' => '音響設備', 'led_screen' => 'LEDスクリーン', 'stage' => 'ステージ', 'bridal_room' => '新婦控室', 'parking' => 'ゲスト駐車場', 'generator' => '非常用発電機', 'wifi' => 'Wi-Fi', 'catering_kitchen' => 'ケータリングキッチン', 'chandelier' => 'シャンデリア', 'aisle_runway' => 'バージンロード', 'stage_lighting' => 'ステージ照明', 'valet' => 'バレーパーキング', 'prayer_room' => '礼拝室', 'garden_lights' => 'ガーデンライト', 'rain_cover' => '雨よけ屋根'],
        ],
    ];

    public function index(Request $request): View
    {
        $venues = Venue::where('public_status', 'published')->orderBy('name')->get();

        $locale = in_array($request->query('lang'), self::SUPPORTED_LOCALES, true)
            ? $request->query('lang')
            : ($venues->first()?->default_locale ?? 'id');

        return view('opening', [
            'venues' => $venues,
            'locale' => $locale,
            'supportedLocales' => self::SUPPORTED_LOCALES,
            'opening' => $this->openingLabels($locale),
            'openingImage' => self::OPENING_PHOTO,
            'chapters' => self::CHAPTERS,
        ]);
    }

    public function show(Request $request, string $venueSlug): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        return view('venue.show', $this->stageData($venue, $locale, 'lobby'));
    }

    public function halls(Request $request, string $venueSlug): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        return view('venue.halls-index', $this->stageData($venue, $locale, 'halls'));
    }

    public function hall(Request $request, string $venueSlug, string $hallSlug): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        $data = $this->stageData($venue, $locale, 'hall');
        $halls = $data['halls'];
        $index = $halls->search(fn (Hall $hall) => $hall->slug === $hallSlug);

        abort_if($index === false, 404);

        return view('venue.hall-detail', [
            ...$data,
            'hall' => $halls[$index],
            'hallIndex' => $index,
            'previousHall' => $halls[($index - 1 + $halls->count()) % $halls->count()],
            'nextHall' => $halls[($index + 1) % $halls->count()],
        ]);
    }

    public function services(Request $request, string $venueSlug): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        return view('venue.services-index', $this->stageData($venue, $locale, 'services'));
    }

    public function service(Request $request, string $venueSlug, int $serviceId): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        $data = $this->stageData($venue, $locale, 'service');
        $services = $data['services'];
        $index = $services->search(fn (VenueKnowledgeItem $item) => $item->id === $serviceId);

        abort_if($index === false, 404);

        return view('venue.service-detail', [
            ...$data,
            'service' => $services[$index],
            'serviceIndex' => $index,
            'previousService' => $services[($index - 1 + $services->count()) % $services->count()],
            'nextService' => $services[($index + 1) % $services->count()],
        ]);
    }

    public function info(Request $request, string $venueSlug): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        return view('venue.info-index', $this->stageData($venue, $locale, 'info'));
    }

    public function staff(Request $request, string $venueSlug): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        return view('venue.staff-index', $this->stageData($venue, $locale, 'staff'));
    }

    public function reservationScene(Request $request, string $venueSlug): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        return view('venue.reservation-index', [
            ...$this->stageData($venue, $locale, 'reservation'),
            'preselectedHall' => (string) $request->query('hall', ''),
        ]);
    }

    /**
     * @return array{0: Venue, 1: string}
     */
    private function resolveStage(Request $request, string $venueSlug): array
    {
        $venue = Venue::where('slug', $venueSlug)->firstOrFail();

        abort_if(! $venue->isPublished(), 404);

        $locale = in_array($request->query('lang'), self::SUPPORTED_LOCALES, true)
            ? $request->query('lang')
            : $venue->default_locale;

        return [$venue, $locale];
    }

    /**
     * Everything the shared stage shell needs, for whichever chapter the
     * client has stepped into.
     *
     * @return array<string, mixed>
     */
    private function stageData(Venue $venue, string $locale, string $scene): array
    {
        $halls = $venue->halls()
            ->where('is_active', true)
            ->with(['images' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get()
            ->values();

        $services = $venue->knowledgeItems()->where('is_active', true)
            ->whereIn('category', [VenueKnowledgeItem::CATEGORY_SERVICES, VenueKnowledgeItem::CATEGORY_CATERING])
            ->orderBy('sort_order')->get()->values();

        $labels = [
            ...$this->labels($locale),
            'setting_labels_json' => json_encode(self::HALL_TERMS[$locale]['setting'], JSON_UNESCAPED_UNICODE),
            'event_labels_json' => json_encode($this->eventTypeLabels($locale), JSON_UNESCAPED_UNICODE),
        ];
        $lobby = $this->lobbyLabels($locale);

        return [
            'venue' => $venue,
            'halls' => $halls,
            'locale' => $locale,
            'scene' => $scene,
            'chapter' => self::CHAPTERS[['hall' => 'halls', 'service' => 'services'][$scene] ?? $scene] ?? self::CHAPTERS['lobby'],
            'backdrop' => $this->sceneBackdrop($scene),
            'menuItems' => $this->stageMenu($venue, $locale, $labels, $lobby),
            'supportedLocales' => self::SUPPORTED_LOCALES,
            'labels' => $labels,
            'lobby' => $lobby,
            'hallTerms' => self::HALL_TERMS[$locale],
            'eventTypes' => $this->eventTypeLabels($locale),
            'wizard' => $this->wizardLabels($venue, $locale),
            'narration' => self::NARRATION[$locale],
            'hallNarrations' => $this->hallNarrations($venue, $halls, $locale),
            'staffLinks' => $this->handover->forStaff($venue, $locale),
            'today' => now($venue->timezone)->toDateString(),
            'infoItems' => $venue->knowledgeItems()->where('is_active', true)
                ->whereIn('category', [VenueKnowledgeItem::CATEGORY_GENERAL, VenueKnowledgeItem::CATEGORY_POLICIES, VenueKnowledgeItem::CATEGORY_ACCESS, VenueKnowledgeItem::CATEGORY_FAQ])
                ->orderBy('sort_order')->get()->groupBy('category'),
            'services' => $services,
            'sceneNarrations' => $this->sceneNarrations($venue, $services, $locale),
            'maxExtraHours' => Hall::MAX_EXTRA_HOURS,
            'maxGuests' => ReservationService::MAX_GUESTS,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function eventTypeLabels(string $locale): array
    {
        return collect(Booking::EVENT_TYPES)
            ->mapWithKeys(fn (string $type) => [$type => ReservationHandover::eventTypeLabel($type, $locale)])
            ->all();
    }

    /**
     * The photograph behind a scene, the concierge posed in front of it,
     * plus the crop of her face used by the chat and narrator avatars.
     *
     * @return array{image: string, focus: string, focusMobile: string, character: string, pose: string, avatarImage: string, avatarZoom: string, avatarFocus: string}
     */
    private function sceneBackdrop(string $scene): array
    {
        $photo = self::SCENE_PHOTOS[['hall' => 'halls', 'service' => 'services'][$scene] ?? $scene] ?? self::SCENE_PHOTOS['lobby'];

        $pose = self::SCENE_POSES[['hall' => 'halls'][$scene] ?? $scene] ?? 'standing';

        return [
            ...$photo,
            'character' => asset(self::CHARACTER_POSES[$pose]),
            'pose' => $pose,
            'avatarImage' => asset(self::AVATAR_IMAGE),
            'avatarZoom' => 'cover',
            'avatarFocus' => '50% 50%',
        ];
    }

    /**
     * The order-of-the-day panel. Every section is its own chapter, so
     * choosing one eases the stage out as the curtains close on it.
     *
     * @param  array<string, string>  $labels
     * @param  array<string, string>  $lobby
     * @return list<array{key: string, chapter: string, label: string, href: string, exit: bool, tour: ?string, topic: string}>
     */
    private function stageMenu(Venue $venue, string $locale, array $labels, array $lobby): array
    {
        $tour = self::NARRATION[$locale];
        $url = fn (string $name) => route("venue.{$name}", ['venueSlug' => $venue->slug, 'lang' => $locale]);

        return [
            ['key' => 'halls', 'chapter' => self::CHAPTERS['halls'], 'label' => $labels['halls_heading'], 'href' => $url('halls'), 'exit' => true, 'tour' => $tour['tour_halls'], 'topic' => $labels['menu_halls_q']],
            ['key' => 'services', 'chapter' => self::CHAPTERS['services'], 'label' => $labels['menu_services'], 'href' => $url('services'), 'exit' => true, 'tour' => $tour['tour_services'], 'topic' => $labels['menu_services_q']],
            ['key' => 'info', 'chapter' => self::CHAPTERS['info'], 'label' => $lobby['menu_info'], 'href' => $url('info'), 'exit' => true, 'tour' => $tour['tour_info'], 'topic' => $labels['menu_policies_q']],
            ['key' => 'reservation', 'chapter' => self::CHAPTERS['reservation'], 'label' => $lobby['reservation'], 'href' => $url('reservation'), 'exit' => true, 'tour' => $tour['tour_reservation'], 'topic' => $lobby['reservation_q']],
            ['key' => 'staff', 'chapter' => self::CHAPTERS['staff'], 'label' => $labels['menu_staff'], 'href' => $url('staff'), 'exit' => true, 'tour' => $tour['tour_staff'], 'topic' => $labels['menu_staff_q']],
        ];
    }

    /**
     * Intros for the services chapter and the venue information scene,
     * built only from stored venue data.
     *
     * @param  Collection<int, VenueKnowledgeItem>  $services
     * @return array{services: ?string, info: string}
     */
    private function sceneNarrations(Venue $venue, Collection $services, string $locale): array
    {
        $templates = self::NARRATION[$locale];
        $separator = $locale === 'ja' ? '、' : '; ';
        $time = fn (?string $value) => $value ? substr($value, 0, 5) : null;

        $intro = null;

        if ($services->isNotEmpty()) {
            $examples = $services->take(3)->map(fn ($item) => $item->translatedTitle($locale))->implode($separator);
            $intro = str_replace(
                [':count', ':examples'],
                [(string) $services->count(), $examples],
                $templates[$services->count() === 1 ? 'services_one' : 'services']
            );
        }

        $location = collect([$venue->city, $venue->country])->filter()->implode($locale === 'ja' ? '、' : ', ');
        $parts = [str_replace(':venue', $venue->name, $templates['info_welcome'])];

        if ($location !== '') {
            $parts[] = str_replace(':location', $location, $templates['info_place']);
        }

        if ($time($venue->event_start_time) && $time($venue->event_end_time)) {
            $parts[] = str_replace([':in', ':out'], [$time($venue->event_start_time), $time($venue->event_end_time)], $templates['info_hours']);
        }

        $parts[] = $templates['info_more'];

        return ['services' => $intro, 'info' => implode(' ', $parts)];
    }

    /**
     * @param  Collection<int, Hall>  $halls
     * @return array{halls: ?string, hall: array<string, string>}
     */
    private function hallNarrations(Venue $venue, Collection $halls, string $locale): array
    {
        $templates = self::NARRATION[$locale];
        $terms = self::HALL_TERMS[$locale];
        $money = fn ($value) => $venue->currency.' '.number_format((float) $value, 0, ',', '.');
        $sentence = fn (string $text) => preg_match('/[.!?。！？]$/u', $text) ? $text : $text.($locale === 'ja' ? '。' : '.');

        if ($halls->isEmpty()) {
            return ['halls' => null, 'hall' => []];
        }

        $intro = str_replace(
            [':count', ':price'],
            [(string) $halls->count(), $money($halls->min('base_price'))],
            $templates[$halls->count() === 1 ? 'halls_one' : 'halls']
        );

        $items = $halls->mapWithKeys(function (Hall $hall) use ($venue, $templates, $terms, $money, $sentence, $locale) {
            $parts = [$hall->translatedName($locale).'.', filled($hall->translatedDescription($locale)) ? $sentence(trim($hall->translatedDescription($locale))) : null];

            $parts[] = $sentence($terms['setting_sentence'][$hall->setting] ?? Str::headline((string) $hall->setting));

            $parts[] = $hall->size_sqm
                ? str_replace([':min', ':max', ':size'], [(string) $hall->min_guests, (string) $hall->max_guests, (string) $hall->size_sqm], $templates['capacity'])
                : str_replace([':min', ':max'], [(string) $hall->min_guests, (string) $hall->max_guests], $templates['capacity_nosize']);

            if ($hall->view_type) {
                $parts[] = $sentence($terms['view'][$hall->view_type] ?? Str::headline($hall->view_type));
            }

            $parts[] = str_replace(':price', $money($hall->base_price), $templates['price']);

            if ($venue->weekday_discount_percent > 0) {
                $parts[] = str_replace(':percent', (string) $venue->weekday_discount_percent, $templates['weekday']);
            }

            return [$hall->slug => implode(' ', array_filter($parts))];
        })->all();

        return ['halls' => $intro, 'hall' => $items];
    }

    /**
     * @return array<string, string>
     */
    private function openingLabels(string $locale): array
    {
        return match ($locale) {
            'en' => [
                'eyebrow' => 'Wedding venue · AI concierge', 'welcome' => 'Your story,', 'welcome_em' => 'one chapter at a time.',
                'tagline' => 'Wander through the venue with an AI concierge who knows every hall, open date and wedding package — from an intimate vow to a grand celebration.',
                'enter' => 'Enter :name', 'empty' => 'The venue is being prepared. Please come back soon.',
                'loading' => 'Drawing the curtains…', 'sound_on' => 'Sound on', 'sound_off' => 'Sound off', 'directory' => 'Order of the day',
                'halls' => 'Halls', 'services' => 'Wedding services', 'reservation' => 'Hold your date', 'staff' => 'Wedding team',
            ],
            'ja' => [
                'eyebrow' => 'ウェディング会場 · AIコンシェルジュ', 'welcome' => 'ふたりの物語を、', 'welcome_em' => '一章ずつ。',
                'tagline' => 'すべてのホール・空き日程・プランを知るAIコンシェルジュと、会場を巡りましょう。少人数の誓いから盛大なお披露目まで。',
                'enter' => ':name に入る', 'empty' => '会場は準備中です。しばらくしてからお越しください。',
                'loading' => 'カーテンを開いています…', 'sound_on' => 'サウンドオン', 'sound_off' => 'サウンドオフ', 'directory' => '本日の進行',
                'halls' => 'ホール', 'services' => 'ウェディングサービス', 'reservation' => '日程を仮予約', 'staff' => 'ウェディングチーム',
            ],
            default => [
                'eyebrow' => 'Gedung pernikahan · AI concierge', 'welcome' => 'Kisah Anda,', 'welcome_em' => 'satu bab demi satu.',
                'tagline' => 'Jelajahi gedung bersama AI Concierge yang hafal setiap hall, tanggal kosong, dan paket pernikahan — dari ikrar yang intim hingga perayaan megah.',
                'enter' => 'Masuk ke :name', 'empty' => 'Gedung sedang dipersiapkan. Silakan kembali lagi nanti.',
                'loading' => 'Membuka tirai…', 'sound_on' => 'Suara aktif', 'sound_off' => 'Suara mati', 'directory' => 'Susunan acara',
                'halls' => 'Hall pernikahan', 'services' => 'Layanan pernikahan', 'reservation' => 'Amankan tanggal', 'staff' => 'Tim wedding',
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function wizardLabels(Venue $venue, string $locale): array
    {
        $weekday = str_replace(
            [':percent'],
            [(string) $venue->weekday_discount_percent],
            match ($locale) {
                'en' => 'Weekday rate: :percent% off for events from Monday to Thursday.',
                'ja' => '平日割引：月曜〜木曜の挙式は:percent%オフ。',
                default => 'Tarif hari kerja: hemat :percent% untuk acara Senin sampai Kamis.',
            }
        );

        return [...ReservationController::MESSAGES[$locale], 'weekday_hint' => $venue->weekday_discount_percent > 0 ? $weekday : '', ...match ($locale) {
            'en' => [
                'title' => 'Hold your date', 'intro' => 'A few quick steps. Final availability is confirmed by our wedding team. No payment is taken now.',
                'step_of' => 'Step :current of :total', 'steps' => ['Date', 'Your event', 'Hall', 'Your details', 'Summary'],
                'cal_prev' => 'Previous month', 'cal_next' => 'Next month', 'cal_pick' => 'Pick your wedding date.', 'cal_none' => 'No dates are open online right now. Ask the concierge or our wedding team.', 'cal_full' => 'Fully booked', 'cal_choose' => 'Choose a date',
                'guests_unit' => 'guests', 'event_date' => 'Wedding date', 'event_type' => 'Which event?', 'guests' => 'Number of guests', 'guests_hint' => 'Invited guests expected at the event', 'extra_hours' => 'Extra hours (optional)', 'extra_hours_hint' => 'Overtime after the standard event window, :price per hour',
                'choose_hall' => 'Choose a hall', 'fits' => 'Seats :min–:max guests', 'too_small' => 'Too small for your guest list', 'closed' => 'Not open on this date',
                'name' => 'Your name (or both of you)', 'contact_method' => 'How should we contact you?',
                'whatsapp' => 'WhatsApp', 'phone' => 'Phone', 'email' => 'Email', 'contact_value' => 'Number or email address',
                'special' => 'Special request (optional)', 'special_placeholder' => 'e.g. Javanese-style decoration, own caterer, a prayer room for guests',
                'next' => 'Next', 'back' => 'Back', 'edit' => 'Edit', 'submit' => 'Send date request', 'sending' => 'Sending…',
                'checking' => 'Checking the date…', 'estimated_total' => 'Estimated total', 'subtotal' => 'Hall rental', 'extra_hours_total' => 'Extra hours', 'discount' => 'Weekday discount', 'deposit' => 'Down payment (:percent%)',
                'hall' => 'Hall', 'event' => 'Event', 'dates' => 'Date',
                'contact' => 'Contact', 'disclaimer' => 'This is a date request, not a contract. Final availability and rates are confirmed by our wedding team, who will also arrange the down payment. No payment is taken now.',
                'available_instead' => 'Free on your date instead:', 'done_title' => 'Request received', 'reference' => 'Your reference',
                'awaiting' => 'Awaiting confirmation from the wedding team', 'done_hint' => 'Send your request to our team to speed up confirmation.',
                'send_whatsapp' => 'Send to WhatsApp', 'call_venue' => 'Call the wedding office', 'email_venue' => 'Email the wedding office', 'new_request' => 'New request',
                'error_network' => 'Connection problem. Your details are saved — please try again or contact the venue team.',
                'error_generic' => 'Something went wrong. Please try again or contact the venue team.', 'select_hall' => 'Please choose a hall.',
                'contact_staff' => 'Contact the wedding team', 'per_hour' => '/ hour',
            ],
            'ja' => [
                'title' => '日程を仮予約', 'intro' => 'かんたんな手順です。空き状況はウェディングチームが最終確認いたします。現時点でお支払いは発生しません。',
                'step_of' => 'ステップ :current / :total', 'steps' => ['日程', 'ご希望の式', 'ホール', 'ご連絡先', '確認'],
                'cal_prev' => '前の月', 'cal_next' => '次の月', 'cal_pick' => '挙式日を選んでください。', 'cal_none' => '現在オンラインで受付中の日程はありません。コンシェルジュまたはスタッフにご相談ください。', 'cal_full' => '満席', 'cal_choose' => '日付を選択',
                'guests_unit' => '名', 'event_date' => '挙式日', 'event_type' => 'ご希望の式', 'guests' => 'ゲスト人数', 'guests_hint' => '当日ご招待するゲストの人数', 'extra_hours' => '延長時間（任意）', 'extra_hours_hint' => '通常の時間枠を超える延長：1時間 :price',
                'choose_hall' => 'ホールを選ぶ', 'fits' => ':min〜:max名様', 'too_small' => '人数に対応できません', 'closed' => 'この日は受付していません',
                'name' => 'お名前（お二人のお名前でも可）', 'contact_method' => 'ご連絡方法',
                'whatsapp' => 'WhatsApp', 'phone' => '電話', 'email' => 'メール', 'contact_value' => '番号またはメールアドレス',
                'special' => 'ご要望（任意）', 'special_placeholder' => '例：ジャワ様式の装飾、持ち込みケータリング、ゲスト用の礼拝室',
                'next' => '次へ', 'back' => '戻る', 'edit' => '編集', 'submit' => '日程リクエストを送信', 'sending' => '送信中…',
                'checking' => '日程を確認中…', 'estimated_total' => '概算合計', 'subtotal' => '会場費', 'extra_hours_total' => '延長料金', 'discount' => '平日割引', 'deposit' => '手付金（:percent%）',
                'hall' => 'ホール', 'event' => '式', 'dates' => '日程',
                'contact' => 'ご連絡先', 'disclaimer' => 'これは日程のリクエストであり、契約ではありません。空き状況と料金はウェディングチームが最終確認し、手付金のご案内もいたします。現時点でお支払いは発生しません。',
                'available_instead' => 'ご希望の日に空いているホール：', 'done_title' => 'リクエストを受け付けました', 'reference' => '受付番号',
                'awaiting' => 'ウェディングチームの確認待ち', 'done_hint' => 'リクエストをスタッフに送ると、確認がスムーズです。',
                'send_whatsapp' => 'WhatsAppで送る', 'call_venue' => 'ウェディングオフィスに電話', 'email_venue' => 'ウェディングオフィスにメール', 'new_request' => '新しいリクエスト',
                'error_network' => '接続に問題があります。入力内容は保存されています。もう一度お試しいただくか、スタッフにご連絡ください。',
                'error_generic' => 'エラーが発生しました。もう一度お試しいただくか、スタッフにご連絡ください。', 'select_hall' => 'ホールを選択してください。',
                'contact_staff' => 'ウェディングチームに連絡', 'per_hour' => '/ 時間',
            ],
            default => [
                'title' => 'Amankan tanggal', 'intro' => 'Hanya beberapa langkah singkat. Ketersediaan final dikonfirmasi oleh tim wedding kami. Belum ada pembayaran yang diambil.',
                'step_of' => 'Langkah :current dari :total', 'steps' => ['Tanggal', 'Acara Anda', 'Hall', 'Data Anda', 'Ringkasan'],
                'cal_prev' => 'Bulan sebelumnya', 'cal_next' => 'Bulan berikutnya', 'cal_pick' => 'Pilih tanggal pernikahan Anda.', 'cal_none' => 'Belum ada tanggal yang dibuka untuk pengajuan online. Tanyakan ke concierge atau tim wedding kami.', 'cal_full' => 'Penuh', 'cal_choose' => 'Pilih tanggal',
                'guests_unit' => 'tamu', 'event_date' => 'Tanggal acara', 'event_type' => 'Acara apa?', 'guests' => 'Jumlah tamu', 'guests_hint' => 'Perkiraan tamu undangan yang hadir', 'extra_hours' => 'Tambahan jam (opsional)', 'extra_hours_hint' => 'Lembur di luar jam acara standar, :price per jam',
                'choose_hall' => 'Pilih hall', 'fits' => 'Menampung :min–:max tamu', 'too_small' => 'Terlalu kecil untuk jumlah tamu Anda', 'closed' => 'Belum dibuka pada tanggal ini',
                'name' => 'Nama Anda (atau berdua)', 'contact_method' => 'Bagaimana kami menghubungi Anda?',
                'whatsapp' => 'WhatsApp', 'phone' => 'Telepon', 'email' => 'Email', 'contact_value' => 'Nomor atau alamat email',
                'special' => 'Permintaan khusus (opsional)', 'special_placeholder' => 'mis. dekorasi adat Jawa, katering sendiri, mushola untuk tamu',
                'next' => 'Lanjut', 'back' => 'Kembali', 'edit' => 'Ubah', 'submit' => 'Kirim pengajuan tanggal', 'sending' => 'Mengirim…',
                'checking' => 'Memeriksa tanggal…', 'estimated_total' => 'Perkiraan total', 'subtotal' => 'Sewa hall', 'extra_hours_total' => 'Tambahan jam', 'discount' => 'Diskon hari kerja', 'deposit' => 'Uang muka (:percent%)',
                'hall' => 'Hall', 'event' => 'Acara', 'dates' => 'Tanggal',
                'contact' => 'Kontak', 'disclaimer' => 'Ini adalah pengajuan tanggal, bukan kontrak. Ketersediaan dan tarif final dikonfirmasi oleh tim wedding, yang juga akan mengatur uang muka. Belum ada pembayaran yang diambil.',
                'available_instead' => 'Hall yang kosong di tanggal Anda:', 'done_title' => 'Pengajuan diterima', 'reference' => 'Nomor referensi',
                'awaiting' => 'Menunggu konfirmasi tim wedding', 'done_hint' => 'Kirim pengajuan ke tim kami agar konfirmasi lebih cepat.',
                'send_whatsapp' => 'Kirim ke WhatsApp', 'call_venue' => 'Telepon kantor wedding', 'email_venue' => 'Email kantor wedding', 'new_request' => 'Pengajuan baru',
                'error_network' => 'Koneksi bermasalah. Data Anda tersimpan — coba lagi atau hubungi tim gedung.',
                'error_generic' => 'Terjadi kesalahan. Coba lagi atau hubungi tim gedung.', 'select_hall' => 'Silakan pilih hall.',
                'contact_staff' => 'Hubungi tim wedding', 'per_hour' => '/ jam',
            ],
        }];
    }

    /**
     * @return array<string, string>
     */
    private function lobbyLabels(string $locale): array
    {
        return match ($locale) {
            'en' => [
                'welcome' => 'Welcome to', 'lobby' => 'your wedding day',
                'intro' => 'A venue for vows and celebrations of every size. Browse the halls, find an open date and plan with your AI concierge beside you.',
                'home' => 'Welcome', 'explore' => 'Order of the day', 'reservation' => 'Hold your date', 'chapter' => 'Chapter',
                'reservation_intro' => 'Tell your AI Concierge your wedding date and how many guests you expect. We will help you find a hall and send a date request.',
                'reservation_q' => 'I would like to hold a wedding date. Please help me check availability.',
                'start_booking' => 'Hold my date', 'available' => 'Concierge on duty, 24/7',
                'assistant' => 'Your wedding concierge', 'illustration' => 'AI illustration',
                'staff_intro' => 'Prefer a person? Message us and our wedding team — event planning, catering and decoration coordinators — will pick it up.',
                'empty' => 'Ask your AI Concierge for more information.', 'back' => 'Back to the welcome',
                'event_hours' => 'Event hours', 'event_start' => 'Starts', 'event_end' => 'Ends', 'location' => 'Find the venue',
                'connection_error' => 'Chat could not connect. Please reload to try again.',
                'halls_empty' => 'Hall information will be available soon. Please ask our team.',
                'menu_info' => 'Venue information', 'info_about' => 'About the venue', 'info_policies' => 'Policies & rules', 'info_access' => 'Getting here', 'info_faq' => 'Frequently asked questions', 'info_tab_about' => 'About', 'info_tab_faq' => 'FAQ',
                'info_address' => 'Address', 'info_hours' => 'Event hours', 'info_contact' => 'Contact', 'info_map' => 'Open in Maps', 'info_ask' => 'Ask about the venue',
                'service_counter' => 'Service', 'prev_service' => 'Previous', 'next_service' => 'Next', 'ask_service' => 'Ask about this service', 'back_services' => 'All services', 'open_service' => 'Read more', 'ask_service_q' => 'Tell me more about :name.',
                'loading' => 'Drawing the curtains…',
                'sound_on' => 'Sound on', 'sound_off' => 'Sound off',
                'hall_scene' => 'Hall details', 'hall_counter' => 'Hall', 'gallery' => 'Photo gallery', 'photo' => 'Photo',
                'no_photo' => 'Photos coming soon', 'prev_hall' => 'Previous', 'next_hall' => 'Next',
                'ask_hall' => 'Ask about this hall', 'reserve_hall' => 'Hold a date here',
                'size' => 'Size', 'capacity' => 'Capacity', 'guests_unit' => 'guests', 'setting' => 'Setting', 'view' => 'View', 'seating' => 'Seating styles', 'catering' => 'Catering', 'extra_hours' => 'Extra hours',
                'catering_included' => 'Included', 'catering_excluded' => 'Bring your caterer or book ours',
                'extra_hours_yes' => ':price per hour', 'extra_hours_no' => 'Not available', 'amenities' => 'In the hall',
                'availability_note' => 'Final availability and rates are confirmed by the wedding team.',
                'rate_label' => 'Rental from', 'rate_per_event' => 'per event',
                'weekday_note' => ':percent% off events from Monday to Thursday',
                'deposit_note' => ':percent% down payment secures your date',
                'ask_hall_q' => 'Tell me more about :name.',
                'cta_halls' => 'See the halls', 'stat_from' => 'Rental from', 'stat_capacity' => 'Up to', 'stat_capacity_value' => ':count guests', 'stat_weekday' => 'Weekday events', 'stat_weekday_value' => ':percent% off',
            ],
            'ja' => [
                'welcome' => 'ようこそ', 'lobby' => 'ふたりの特別な一日へ',
                'intro' => 'どんな規模の誓いにも祝宴にも。ホールを眺め、空き日程を探し、AIコンシェルジュと一緒に計画しましょう。',
                'home' => 'ウェルカム', 'explore' => '本日の進行', 'reservation' => '日程を仮予約', 'chapter' => '第',
                'reservation_intro' => '挙式のご希望日とゲスト人数をAIコンシェルジュにお伝えください。ホール探しと日程リクエストをお手伝いします。',
                'reservation_q' => '挙式の日程を仮予約したいです。空き状況を確認してください。',
                'start_booking' => '日程を仮予約', 'available' => 'コンシェルジュ 24時間対応',
                'assistant' => 'ウェディングコンシェルジュ', 'illustration' => 'AIイラスト',
                'staff_intro' => 'スタッフと話したい場合は、メッセージをお送りください。進行・ケータリング・装飾のウェディングチームが対応します。',
                'empty' => '詳しくはAIコンシェルジュにお尋ねください。', 'back' => 'ウェルカムに戻る',
                'event_hours' => '挙式のお時間', 'event_start' => '開始', 'event_end' => '終了', 'location' => 'アクセス',
                'connection_error' => 'チャットに接続できませんでした。再読み込みしてください。',
                'halls_empty' => 'ホールの情報は準備中です。スタッフにお尋ねください。',
                'menu_info' => '会場のご案内', 'info_about' => '会場について', 'info_policies' => 'ご利用規約', 'info_access' => 'アクセス', 'info_faq' => 'よくあるご質問', 'info_tab_about' => '概要', 'info_tab_faq' => 'FAQ',
                'info_address' => '所在地', 'info_hours' => '挙式のお時間', 'info_contact' => 'お問い合わせ', 'info_map' => '地図で開く', 'info_ask' => '会場について聞く',
                'service_counter' => 'サービス', 'prev_service' => '前へ', 'next_service' => '次へ', 'ask_service' => 'このサービスについて聞く', 'back_services' => 'サービス一覧', 'open_service' => '詳しく見る', 'ask_service_q' => ':name について詳しく教えてください。',
                'loading' => 'カーテンを開いています…',
                'sound_on' => 'サウンドオン', 'sound_off' => 'サウンドオフ',
                'hall_scene' => 'ホールのご案内', 'hall_counter' => 'ホール', 'gallery' => 'フォトギャラリー', 'photo' => '写真',
                'no_photo' => '写真は準備中です', 'prev_hall' => '前へ', 'next_hall' => '次へ',
                'ask_hall' => 'このホールについて聞く', 'reserve_hall' => 'この会場で日程を仮予約',
                'size' => '広さ', 'capacity' => '収容人数', 'guests_unit' => '名', 'setting' => '環境', 'view' => '眺望', 'seating' => '座席レイアウト', 'catering' => 'ケータリング', 'extra_hours' => '延長',
                'catering_included' => '含まれます', 'catering_excluded' => '持ち込み可、または当会場のご利用も可能です',
                'extra_hours_yes' => '1時間 :price', 'extra_hours_no' => '利用不可', 'amenities' => 'ホールの設備',
                'availability_note' => '空き状況と料金は、ウェディングチームが最終確認いたします。',
                'rate_label' => '会場費', 'rate_per_event' => '1挙式あたり〜',
                'weekday_note' => '月曜〜木曜の挙式は:percent%オフ',
                'deposit_note' => '手付金:percent%で日程を確保',
                'ask_hall_q' => ':name について詳しく教えてください。',
                'cta_halls' => 'ホールを見る', 'stat_from' => '会場費', 'stat_capacity' => '最大', 'stat_capacity_value' => ':count名', 'stat_weekday' => '平日の挙式', 'stat_weekday_value' => ':percent%オフ',
            ],
            default => [
                'welcome' => 'Selamat datang di', 'lobby' => 'hari bahagia Anda',
                'intro' => 'Gedung untuk ikrar dan perayaan segala ukuran. Lihat hall, temukan tanggal yang kosong, dan susun rencana bersama AI Concierge di sisi Anda.',
                'home' => 'Sambutan', 'explore' => 'Susunan acara', 'reservation' => 'Amankan tanggal', 'chapter' => 'Bab',
                'reservation_intro' => 'Ceritakan tanggal pernikahan dan perkiraan jumlah tamu kepada AI Concierge. Kami bantu carikan hall hingga pengajuan tanggal.',
                'reservation_q' => 'Saya ingin mengamankan tanggal pernikahan. Bantu saya cek ketersediaan.',
                'start_booking' => 'Amankan tanggal saya', 'available' => 'Concierge siaga 24 jam',
                'assistant' => 'Wedding concierge Anda', 'illustration' => 'Ilustrasi AI',
                'staff_intro' => 'Lebih nyaman bicara dengan orang? Kirim pesan dan tim wedding kami — koordinator acara, katering, dan dekorasi — akan menindaklanjuti.',
                'empty' => 'Tanyakan informasi selengkapnya kepada AI Concierge.', 'back' => 'Kembali ke sambutan',
                'event_hours' => 'Jam acara', 'event_start' => 'Mulai', 'event_end' => 'Selesai', 'location' => 'Lokasi gedung',
                'connection_error' => 'Chat belum tersambung. Muat ulang halaman untuk mencoba lagi.',
                'halls_empty' => 'Informasi hall segera tersedia. Silakan tanyakan kepada tim kami.',
                'menu_info' => 'Informasi gedung', 'info_about' => 'Tentang gedung', 'info_policies' => 'Kebijakan & aturan', 'info_access' => 'Akses ke lokasi', 'info_faq' => 'Pertanyaan yang sering diajukan', 'info_tab_about' => 'Tentang', 'info_tab_faq' => 'FAQ',
                'info_address' => 'Alamat', 'info_hours' => 'Jam acara', 'info_contact' => 'Kontak', 'info_map' => 'Buka di Maps', 'info_ask' => 'Tanya tentang gedung',
                'service_counter' => 'Layanan', 'prev_service' => 'Sebelumnya', 'next_service' => 'Berikutnya', 'ask_service' => 'Tanya tentang layanan ini', 'back_services' => 'Semua layanan', 'open_service' => 'Selengkapnya', 'ask_service_q' => 'Ceritakan lebih banyak tentang :name.',
                'loading' => 'Membuka tirai…',
                'sound_on' => 'Suara aktif', 'sound_off' => 'Suara mati',
                'hall_scene' => 'Detail hall', 'hall_counter' => 'Hall', 'gallery' => 'Galeri foto', 'photo' => 'Foto',
                'no_photo' => 'Foto segera tersedia', 'prev_hall' => 'Sebelumnya', 'next_hall' => 'Berikutnya',
                'ask_hall' => 'Tanya tentang hall ini', 'reserve_hall' => 'Amankan tanggal di sini',
                'size' => 'Luas', 'capacity' => 'Kapasitas', 'guests_unit' => 'tamu', 'setting' => 'Suasana', 'view' => 'Pemandangan', 'seating' => 'Tata tempat duduk', 'catering' => 'Katering', 'extra_hours' => 'Tambahan jam',
                'catering_included' => 'Termasuk', 'catering_excluded' => 'Boleh bawa katering sendiri atau pakai katering kami',
                'extra_hours_yes' => ':price per jam', 'extra_hours_no' => 'Tidak tersedia', 'amenities' => 'Di dalam hall',
                'availability_note' => 'Ketersediaan dan tarif final dikonfirmasi oleh tim wedding.',
                'rate_label' => 'Sewa mulai', 'rate_per_event' => 'per acara',
                'weekday_note' => 'Hemat :percent% untuk acara Senin sampai Kamis',
                'deposit_note' => 'Uang muka :percent% untuk mengamankan tanggal',
                'ask_hall_q' => 'Ceritakan lebih banyak tentang :name.',
                'cta_halls' => 'Lihat hall', 'stat_from' => 'Sewa mulai', 'stat_capacity' => 'Hingga', 'stat_capacity_value' => ':count tamu', 'stat_weekday' => 'Acara hari kerja', 'stat_weekday_value' => 'hemat :percent%',
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    private function labels(string $locale): array
    {
        return match ($locale) {
            'en' => [
                'from' => 'from',
                'per_event' => '/ event',
                'ask_ai' => 'Ask the AI Concierge',
                'halls_heading' => 'Halls',
                'chat_heading' => 'AI Concierge',
                'chat_subtitle' => 'Ask about halls, open dates or wedding services — on duty 24/7.',
                'chat_placeholder' => 'Ask about a hall, a date or a package…',
                'chat_send' => 'Send',
                'chat_open' => 'Talk to the AI Concierge',
                'chat_close' => 'Close conversation',
                'chat_intro' => "Hi! I'm the Wedding Concierge of this venue. Ask me about halls, open dates, catering, decoration or anything about planning your day here.",
                'catering_included' => 'Catering included',
                'max_guests' => 'guests',
                'chat_catering_excluded' => 'Catering separate',
                'chat_discount' => 'weekday discount',
                'chat_deposit' => 'Down payment',
                'chat_no_availability' => 'No hall is free on that date.',
                'chat_booking_received' => 'Date request received',
                'chat_reference' => 'Ref',
                'chat_view_suffix' => 'view',
                'handed_over' => 'A member of the wedding team has joined this conversation and will reply shortly.',
                'chat_status_sent' => 'Message sent',
                'chat_status_waiting' => 'Waiting for the team to reply',
                'chat_status_replied' => 'The team has replied',
                'chat_draft_title' => 'Unsaved message',
                'chat_draft_body' => 'This message has not been sent. Keep it as a draft?',
                'chat_draft_keep' => 'Keep typing',
                'chat_draft_discard' => 'Discard draft',
                'thinking' => 'Thinking…',
                'chat_error' => "I'm having trouble responding right now. Please try again or contact the wedding team.", 'chat_slow' => 'You are sending messages very quickly. Please wait a moment and try again.', 'chat_retry' => 'Retry',
                'view_details' => 'View hall',
                'hall_details_question' => 'Show me more details and photos of :hall',
                'book_now' => 'Hold a date here',
                'menu_heading' => 'Start here',
                'menu_halls' => 'Find a hall',
                'menu_halls_q' => 'I would like to see the halls and what they can hold.',
                'menu_services' => 'Wedding services',
                'menu_services_q' => 'Which wedding services and vendors do you offer?',
                'menu_policies' => 'Policies & FAQ',
                'menu_policies_q' => 'What are the down payment, cancellation and venue policies?',
                'menu_staff' => 'Talk to the team',
                'menu_staff_q' => 'I would like to speak with the wedding team.',
            ],
            'ja' => [
                'from' => '',
                'per_event' => '〜 / 挙式',
                'ask_ai' => 'AIコンシェルジュに聞く',
                'halls_heading' => 'ホール',
                'chat_heading' => 'AIコンシェルジュ',
                'chat_subtitle' => 'ホール・空き日程・ウェディングサービスについて24時間いつでもどうぞ。',
                'chat_placeholder' => 'ホール、日程、プランについて質問…',
                'chat_send' => '送信',
                'chat_open' => 'AIコンシェルジュに相談',
                'chat_close' => '会話を閉じる',
                'chat_intro' => 'こんにちは。この会場のウェディングコンシェルジュです。ホール、空き日程、ケータリング、装飾など、結婚式のご準備について何でもお尋ねください。',
                'catering_included' => 'ケータリング込み',
                'max_guests' => '名まで',
                'chat_catering_excluded' => 'ケータリング別',
                'chat_discount' => '平日割引',
                'chat_deposit' => '手付金',
                'chat_no_availability' => 'ご希望の日は空いているホールがありません。',
                'chat_booking_received' => '日程リクエストを受け付けました',
                'chat_reference' => '受付番号',
                'chat_view_suffix' => 'の眺望',
                'handed_over' => 'ウェディングチームがこの会話に参加しました。まもなく返信いたします。',
                'chat_status_sent' => 'メッセージを送信しました',
                'chat_status_waiting' => 'チームの返信を待っています',
                'chat_status_replied' => 'チームが返信しました',
                'chat_draft_title' => '未送信のメッセージ',
                'chat_draft_body' => 'このメッセージはまだ送信されていません。下書きとして残しますか？',
                'chat_draft_keep' => '入力を続ける',
                'chat_draft_discard' => '下書きを破棄',
                'thinking' => '入力中…',
                'chat_error' => '現在うまくお答えできません。もう一度お試しいただくか、ウェディングチームにご連絡ください。', 'chat_slow' => 'メッセージが多すぎます。少し待ってからもう一度お試しください。', 'chat_retry' => '再試行',
                'view_details' => 'ホールを見る',
                'hall_details_question' => ':hall の詳細と写真を見せてください',
                'book_now' => 'この会場で日程を仮予約',
                'menu_heading' => 'ここから始める',
                'menu_halls' => 'ホールを探す',
                'menu_halls_q' => 'ホールの種類と収容人数を見せてください。',
                'menu_services' => 'ウェディングサービス',
                'menu_services_q' => 'どんなウェディングサービスやベンダーがありますか？',
                'menu_policies' => '規約・FAQ',
                'menu_policies_q' => '手付金、キャンセル、会場のルールを教えてください。',
                'menu_staff' => 'スタッフに相談',
                'menu_staff_q' => 'ウェディングチームと話したいです。',
            ],
            default => [
                'from' => 'mulai dari',
                'per_event' => '/ acara',
                'ask_ai' => 'Tanya AI Concierge',
                'halls_heading' => 'Hall',
                'chat_heading' => 'AI Concierge',
                'chat_subtitle' => 'Tanyakan hall, tanggal kosong, atau layanan pernikahan — siaga 24 jam.',
                'chat_placeholder' => 'Tanya soal hall, tanggal, atau paket…',
                'chat_send' => 'Kirim',
                'chat_open' => 'Ngobrol dengan AI Concierge',
                'chat_close' => 'Tutup percakapan',
                'chat_intro' => 'Halo! Saya Wedding Concierge gedung ini. Tanyakan apa saja soal hall, tanggal kosong, katering, dekorasi, atau persiapan hari bahagia Anda di sini.',
                'catering_included' => 'Termasuk katering',
                'max_guests' => 'tamu',
                'chat_catering_excluded' => 'Katering terpisah',
                'chat_discount' => 'diskon hari kerja',
                'chat_deposit' => 'Uang muka',
                'chat_no_availability' => 'Tidak ada hall yang kosong di tanggal tersebut.',
                'chat_booking_received' => 'Pengajuan tanggal diterima',
                'chat_reference' => 'Ref',
                'chat_view_suffix' => 'pemandangan',
                'handed_over' => 'Tim wedding telah bergabung dalam percakapan ini dan akan segera membalas.',
                'chat_status_sent' => 'Pesan terkirim',
                'chat_status_waiting' => 'Menunggu balasan tim',
                'chat_status_replied' => 'Tim telah membalas',
                'chat_draft_title' => 'Pesan belum dikirim',
                'chat_draft_body' => 'Pesan ini belum dikirim. Simpan sebagai draft?',
                'chat_draft_keep' => 'Lanjut mengetik',
                'chat_draft_discard' => 'Buang draft',
                'thinking' => 'Sedang mengetik…',
                'chat_error' => 'Saya sedang kesulitan menjawab. Silakan coba lagi atau hubungi tim wedding.', 'chat_slow' => 'Pesan terlalu cepat. Mohon tunggu sebentar lalu coba lagi.', 'chat_retry' => 'Coba lagi',
                'view_details' => 'Lihat hall',
                'hall_details_question' => 'Tunjukkan detail dan foto lengkap :hall',
                'book_now' => 'Amankan tanggal di sini',
                'menu_heading' => 'Mulai dari sini',
                'menu_halls' => 'Cari hall',
                'menu_halls_q' => 'Saya ingin melihat pilihan hall dan kapasitasnya.',
                'menu_services' => 'Layanan pernikahan',
                'menu_services_q' => 'Layanan dan vendor pernikahan apa saja yang tersedia?',
                'menu_policies' => 'Kebijakan & FAQ',
                'menu_policies_q' => 'Bagaimana ketentuan uang muka, pembatalan, dan aturan gedung?',
                'menu_staff' => 'Bicara dengan tim',
                'menu_staff_q' => 'Saya ingin bicara dengan tim wedding.',
            ],
        };
    }
}
