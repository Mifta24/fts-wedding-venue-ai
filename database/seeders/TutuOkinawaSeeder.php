<?php

namespace Database\Seeders;

use App\Models\Hall;
use App\Models\HallInventory;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * "TUTU Okinawa" as its own site: a bridal salon in Naha that finds couples a wedding venue,
 * rents dresses and shoots photo weddings. The salon sells no venue itself, so this is a
 * "guide" catalogue: 21 partner venues are introduced (photos, ceremony styles, guest numbers,
 * access) and nothing carries a price, a quote or a deposit.
 *
 * "Booking" here means booking a free consultation at one of the two salons. The days offered
 * are demo availability, like the other demo sites; the TUTU staff confirm the real day.
 *
 * Content comes from the company's public website (tutu-okinawa.jp). The site is unlisted
 * (reachable by its address, noindex, left out of the sitemap) until the owner has agreed to it;
 * set TUTU_ACCESS_CODE to put it behind a code, or TUTU_PUBLIC=1 to publish it.
 * Re-running updates in place. All text lives in database/seeders/data/tutu-okinawa.json.
 */
class TutuOkinawaSeeder extends Seeder
{
    /** Set by tests; production passes the code through TUTU_ACCESS_CODE. */
    public ?string $accessCode = null;

    public ?bool $public = null;

    public const SLUG = 'tutu-okinawa';

    private const INVENTORY_DAYS = 540;

    private const AREAS = ['naha', 'central', 'north'];

    private const LOCALES = ['ja', 'en', 'id', 'th', 'zh'];

    /** The four ceremony styles listed on the venue pages, in every language. */
    private const STYLES = [
        'チャペル式' => ['ja' => 'チャペル式', 'en' => 'Chapel', 'id' => 'Upacara di kapel', 'th' => 'พิธีในโบสถ์', 'zh' => '教堂式'],
        '神前式' => ['ja' => '神前式', 'en' => 'Shinto', 'id' => 'Shinto', 'th' => 'พิธีชินโต', 'zh' => '神前式'],
        '琉球結婚式' => ['ja' => '琉球結婚式', 'en' => 'Ryukyu-style', 'id' => 'Gaya Ryukyu', 'th' => 'พิธีแบบริวกิว', 'zh' => '琉球婚禮'],
        '人前式' => ['ja' => '人前式', 'en' => 'Guest-witnessed (non-religious)', 'id' => 'Disaksikan para tamu (non-religius)', 'th' => 'พิธีต่อหน้าแขก (ไม่ใช้ศาสนา)', 'zh' => '人前式（賓客見證）'],
    ];

    /** @var array<string, array<string, string>> words the translation pass left out: opening hours, the visit size and the error messages of the date request */
    private const HAND = [
        'ja' => ['event_start' => '開店', 'event_end' => '閉店', 'guests_unit' => '名',
            'date_past' => '過去の日付は選べません。', 'hall_unknown' => '会場を選んでください。', 'unavailable' => 'この日は、相談枠に空きがありません。',
            'capacity' => 'ご来店は1回につき最大:max名までです。人数が多い場合はTUTUにご連絡ください。'],
        'en' => ['event_start' => 'Opens', 'event_end' => 'Closes', 'guests_unit' => 'people',
            'date_past' => 'The consultation day cannot be in the past.', 'hall_unknown' => 'Please choose a venue.', 'unavailable' => 'No consultation place is free on that day.',
            'capacity' => 'Up to :max people can come per visit. For a bigger group, please contact TUTU.'],
        'id' => ['event_start' => 'Buka', 'event_end' => 'Tutup', 'guests_unit' => 'orang',
            'date_past' => 'Hari konsultasi tidak boleh di masa lalu.', 'hall_unknown' => 'Silakan pilih venue.', 'unavailable' => 'Tidak ada tempat konsultasi yang kosong pada hari itu.',
            'capacity' => 'Maksimal :max orang per kunjungan. Untuk rombongan lebih besar, silakan hubungi TUTU.'],
        'th' => ['event_start' => 'เปิด', 'event_end' => 'ปิด', 'guests_unit' => 'ท่าน',
            'date_past' => 'วันปรึกษาต้องไม่เป็นวันที่ผ่านมาแล้ว', 'hall_unknown' => 'กรุณาเลือกสถานที่จัดงาน', 'unavailable' => 'วันนั้นไม่มีคิวปรึกษาว่าง',
            'capacity' => 'มาได้สูงสุด :max ท่านต่อครั้ง หากมากันเป็นกลุ่มใหญ่ กรุณาติดต่อ TUTU'],
        'zh' => ['event_start' => '開門', 'event_end' => '打烊', 'guests_unit' => '位',
            'date_past' => '諮詢日期不能是過去的日期。', 'hall_unknown' => '請選擇會場。', 'unavailable' => '該日諮詢名額已滿。',
            'capacity' => '每次最多 :max 位到訪。人數較多時，請聯絡 TUTU。'],
    ];

    public function run(): void
    {
        $data = json_decode((string) file_get_contents(__DIR__.'/data/tutu-okinawa.json'), true, 512, JSON_THROW_ON_ERROR);
        $ui = $data['ui'];

        $info = [];
        foreach (self::LOCALES as $l) {
            $info[$l] = [
                ...$ui['misc'][$l],
                'stats' => $ui['stats'][$l],
                'kinds' => $ui['kinds'][$l],
                'detail_labels' => $ui['detail_labels'][$l],
                'event_types' => $ui['event_types'][$l],
                'lobby' => [...$ui['lobby'][$l], ...$ui['extra_lobby'][$l], 'event_start' => self::HAND[$l]['event_start'], 'event_end' => self::HAND[$l]['event_end']],
                'labels' => $ui['labels'][$l],
                'wizard' => [...$ui['wizard'][$l], ...array_diff_key(self::HAND[$l], array_flip(['event_start', 'event_end']))],
                'narration' => $ui['extra_narration'][$l],
                'link' => ['url' => $ui['link']['url'], 'name' => $ui['link']['names'][$l]],
            ];
        }

        $groups = collect(self::AREAS)->map(fn (string $key) => [
            'key' => $key,
            'names' => collect(self::LOCALES)->mapWithKeys(fn (string $l) => [$l => $data['areas'][$key][$l]])->all(),
        ])->all();

        $descriptions = $data['description'];

        $venueData = [
            'name' => 'TUTU Okinawa',
            'description' => $descriptions['ja'],
            'translations' => collect($descriptions)->map(fn (string $text) => ['description' => $text])->all(),
            'address' => '2-3-15 Matsuyama, Naha, Okinawa 900-0032',
            'city' => 'Naha',
            'country' => 'Japan',
            'phone' => '098-951-0222',
            'timezone' => 'Asia/Tokyo',
            'currency' => 'JPY',
            'default_locale' => 'ja',
            'event_start_time' => '10:00',
            'event_end_time' => '19:00',
            'weekday_discount_percent' => 0,
            'deposit_percent' => 0,
            'cover_path' => '/images/tutu/hero.webp',
            'catalog_mode' => 'guide',
            'theme' => 'tutu',
            'hall_groups' => $groups,
            'plan_info' => $info,
            'films' => [],
            'lobby_photos' => [],
        ];

        $code = $this->accessCode ?? env('TUTU_ACCESS_CODE');
        $public = $this->public ?? filter_var(env('TUTU_PUBLIC', false), FILTER_VALIDATE_BOOLEAN);
        $venueData['access_code_hash'] = filled($code) ? Hash::make($code) : null;
        $venueData['public_status'] = $public && blank($code) ? 'published' : 'unlisted';

        $venue = Venue::updateOrCreate(['slug' => self::SLUG], $venueData);

        $order = array_flip(self::AREAS);
        $venues = collect($data['venues'])->sortBy([fn ($a, $b) => $order[$a['area']] <=> $order[$b['area']], fn ($a, $b) => $a['n'] <=> $b['n']])->values();

        $keep = [];
        foreach ($venues as $sort => $item) {
            $translations = [];
            foreach (self::LOCALES as $l) {
                $translations[$l] = [
                    'name' => $item['name'][$l],
                    'description' => $item['desc'][$l],
                    'details' => $this->details($item, $l, $ui['detail_labels'][$l]),
                ];
            }

            $hall = $venue->halls()->updateOrCreate(['slug' => $item['slug']], [
                'group_key' => $item['area'],
                'name' => $item['name']['ja'],
                'description' => $item['desc']['ja'],
                'translations' => $translations,
                'size_sqm' => null,
                'setting' => $item['kind'], // the kind of venue: hotel, guesthouse, chapel or party
                'min_guests' => 1,
                'max_guests' => 20, // people coming to the salon, not wedding guests
                'seating_styles' => [],
                'view_type' => null,
                'catering_included' => false,
                'extra_hour_available' => false,
                'extra_hour_price' => null,
                'base_price' => 0,
                'price_options' => [],
                'amenities' => [],
                'sort_order' => $sort,
                'is_active' => true,
            ]);
            $keep[] = $hall->id;

            $hall->images()->delete();
            foreach ($item['images'] as $imageSort => $file) {
                $hall->images()->create([
                    'image_url' => "/images/tutu/{$file}",
                    'tags' => [],
                    'alt_text' => $item['name']['ja'],
                    'sort_order' => $imageSort,
                ]);
            }

            $this->seedInventory($hall);
        }

        $venue->halls()->whereNotIn('id', $keep)->update(['is_active' => false]);

        $venue->knowledgeItems()->delete();
        foreach ($data['knowledge'] as $sort => $item) {
            $venue->knowledgeItems()->create([
                'category' => $item['category'],
                'title' => $item['ja']['title'],
                'body' => $item['ja']['body'],
                'image_url' => $item['image'] ?? null,
                'translations' => collect(self::LOCALES)->mapWithKeys(fn (string $l) => [$l => $item[$l]])->all(),
                'tags' => [],
                'sort_order' => $sort,
                'is_active' => true,
            ]);
        }
    }

    /**
     * The facts line of one partner venue, in one language: ceremony styles, party size, cuisine, address, access.
     *
     * @param  array<string, mixed>  $item
     * @param  array<string, string>  $labels
     * @return list<array{key: string, label: string, value: string}>
     */
    private function details(array $item, string $locale, array $labels): array
    {
        $style = collect(explode('/', (string) ($item['style'] ?? '')))
            ->map(fn (string $s) => trim($s))
            ->filter()
            ->map(fn (string $s) => self::STYLES[$s][$locale] ?? $s)
            ->implode(' / ');

        $rows = [
            'style' => $style,
            'party' => $item['party'][$locale] ?? null,
            'cuisine' => $item['cuisine'][$locale] ?? null,
            'address' => $item['address'] ?? null,
            'access' => $item['access'][$locale] ?? null,
        ];

        $out = [];
        foreach ($rows as $key => $value) {
            if (filled($value)) {
                $out[] = ['key' => $key, 'label' => $labels[$key], 'value' => (string) $value];
            }
        }

        return $out;
    }

    /** Consultation days: a few places a day at every venue's page, a handful already taken. One bulk upsert per venue. */
    private function seedInventory(Hall $hall): void
    {
        $start = now('Asia/Tokyo')->startOfDay();
        $now = now();
        $rows = [];

        for ($i = 0; $i < self::INVENTORY_DAYS; $i++) {
            $date = $start->copy()->addDays($i);

            $rows[] = [
                'hall_id' => $hall->id,
                'event_date' => $date->toDateString(),
                'total_slots' => 3,
                'booked_slots' => (($i * 5 + $hall->id * 3) % 9) < ($date->isWeekend() ? 5 : 1) ? 1 : 0,
                'price' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        HallInventory::upsert($rows, ['hall_id', 'event_date'], ['total_slots', 'booked_slots', 'price', 'updated_at']);
    }
}
