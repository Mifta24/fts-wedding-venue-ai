<?php

namespace Database\Seeders;

use App\Models\Hall;
use App\Models\HallInventory;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * "J's Acul Wedding" as its own site: a photo-wedding producer in Utsunomiya
 * (Tochigi) selling flat-rate packages at locations in Aizu, Nikko and Nasu.
 *
 * Content comes from the company's public website, used with the owner's
 * permission. The site is public (published, indexable) unless JSACUL_ACCESS_CODE
 * is set: then it is private (unlisted, noindex, behind that access code).
 * Re-running updates in place.
 *
 * Each package is a "hall" in the data model; the three areas group them. The
 * base price is the Western-attire plan; the other costume plans are
 * price_options. All text lives in database/seeders/data/jsacul.json.
 */
class JsAculSeeder extends Seeder
{
    /** Set by tests; production passes the code through JSACUL_ACCESS_CODE. */
    public ?string $accessCode = null;

    public const SLUG = 'jsacul';

    private const INVENTORY_DAYS = 540;

    private const WEEKEND_SURCHARGE = 30000;

    private const AREAS = ['aizu', 'nikko', 'nasu'];

    private const PRICES = ['western_1' => 400000, 'western_2' => 580000, 'kimono_1' => 420000, 'mixed' => 620000];

    private const LOCALES = ['ja', 'en', 'id', 'th', 'zh'];

    /** Two short films from the owner's site, hosted here and shown as small icons on the lobby. */
    private const FILMS = [
        ['src' => '/videos/jsacul/helicopter.mp4', 'poster' => '/images/jsacul/film-helicopter.jpg', 'titles' => ['ja' => 'ヘリコプター フォトウエディング', 'en' => 'Helicopter photo wedding', 'id' => 'Foto pernikahan helikopter', 'th' => 'ถ่ายภาพแต่งงานด้วยเฮลิคอปเตอร์', 'zh' => '直升機婚紗攝影']],
        ['src' => '/videos/jsacul/castle.mp4', 'poster' => '/images/jsacul/film-castle.jpg', 'titles' => ['ja' => 'ウェディングムービー', 'en' => 'Wedding film', 'id' => 'Film pernikahan', 'th' => 'วิดีโองานแต่ง', 'zh' => '婚禮影片']],
    ];

    /** The chapter that holds the wedding services is called Options here, like the owner's own site. */
    private const OPTIONS_LABEL = ['ja' => 'オプション', 'en' => 'Options', 'id' => 'Opsi', 'th' => 'ตัวเลือก', 'zh' => '選項'];

    /** Interface wording for the plan pages. */
    private const UI = [
        'ja' => ['halls_question' => '撮影プランの種類と料金を見せてください。', 'prices_heading' => '料金（衣裳の組み合わせ別）', 'plan_col' => 'プラン', 'price_col' => '料金（税別）', 'includes_heading' => 'プランに含まれるもの', 'extras_heading' => '別途費用（税別）', 'rate_label' => '基本料金', 'per_event' => '／1プラン',
            'price_sentence' => '基本料金は:priceからです。会場使用料などは別途かかります。', 'intro' => '会津・日光・那須の:count件のフォトウエディングプランをご用意しています。:priceから。エリアを選んで、気になるプランを開いてください。'],
        'en' => ['halls_question' => 'I would like to see the photo plans and their prices.', 'prices_heading' => 'Prices by outfit combination', 'plan_col' => 'Plan', 'price_col' => 'Price (excl. tax)', 'includes_heading' => 'Included in the plan', 'extras_heading' => 'Additional costs (excl. tax)', 'rate_label' => 'Base price', 'per_event' => '/ plan',
            'price_sentence' => 'The base price starts from :price; venue fees and extras are charged separately.', 'intro' => 'We have :count photo-wedding plans across Aizu, Nikko and Nasu, from :price. Pick an area, then open a plan and I will tell you about it.'],
        'id' => ['halls_question' => 'Saya ingin melihat paket foto dan harganya.', 'prices_heading' => 'Harga menurut kombinasi busana', 'plan_col' => 'Paket', 'price_col' => 'Harga (belum termasuk pajak)', 'includes_heading' => 'Termasuk dalam paket', 'extras_heading' => 'Biaya tambahan (belum termasuk pajak)', 'rate_label' => 'Harga dasar', 'per_event' => '/ paket',
            'price_sentence' => 'Harga dasar mulai dari :price; biaya lokasi dan biaya tambahan dihitung terpisah.', 'intro' => 'Ada :count paket foto pernikahan di Aizu, Nikko, dan Nasu, mulai dari :price. Pilih area, lalu buka satu paket dan saya jelaskan.'],
        'th' => ['halls_question' => 'ฉันอยากดูแพ็กเกจถ่ายภาพและราคาค่ะ', 'prices_heading' => 'ราคาตามชุดที่เลือก', 'plan_col' => 'แพ็กเกจ', 'price_col' => 'ราคา (ไม่รวมภาษี)', 'includes_heading' => 'สิ่งที่รวมอยู่ในแพ็กเกจ', 'extras_heading' => 'ค่าใช้จ่ายเพิ่มเติม (ไม่รวมภาษี)', 'rate_label' => 'ราคาเริ่มต้น', 'per_event' => '/ แพ็กเกจ',
            'price_sentence' => 'ราคาเริ่มต้น :price ไม่รวมค่าสถานที่และค่าใช้จ่ายเพิ่มเติม', 'intro' => 'เรามีแพ็กเกจถ่ายภาพแต่งงาน :count แบบ ในพื้นที่ไอสึ นิกโก และนาสุ เริ่มต้น :price เลือกพื้นที่ แล้วเปิดแพ็กเกจที่สนใจได้เลย'],
        'zh' => ['halls_question' => '我想看看婚紗攝影方案與費用。', 'prices_heading' => '依服裝組合的費用', 'plan_col' => '方案', 'price_col' => '費用（未稅）', 'includes_heading' => '方案包含', 'extras_heading' => '另計費用（未稅）', 'rate_label' => '基本費用', 'per_event' => '／每方案',
            'price_sentence' => '基本費用 :price 起，場地費與其他費用另計。', 'intro' => '我們在會津、日光與那須提供 :count 個婚紗攝影方案，:price 起。請先選擇地區，再開啟想了解的方案。'],
    ];

    public function run(): void
    {
        $data = json_decode((string) file_get_contents(__DIR__.'/data/jsacul.json'), true, 512, JSON_THROW_ON_ERROR);

        $info = [];
        foreach (self::LOCALES as $locale) {
            $block = $data['info'][$locale];
            $info[$locale] = [
                ...self::UI[$locale],
                'menu_options' => self::OPTIONS_LABEL[$locale],
                'options' => $block['plan_labels'],
                'includes' => $block['includes'],
                'extras' => $block['extras'],
                'early_bird' => $block['early_bird'],
                'flat_rate' => $block['flat_rate'],
                'tax_note' => $block['tax_note'],
            ];
        }

        $groups = collect(self::AREAS)->map(fn (string $key) => [
            'key' => $key,
            'names' => collect(self::LOCALES)->mapWithKeys(fn (string $l) => [$l => $data['info'][$l]['areas'][$key]])->all(),
        ])->all();

        $descriptions = collect(self::LOCALES)->mapWithKeys(fn (string $l) => [$l => $data['info'][$l]['venue_description']])->all();

        $venueData = [
            'name' => "J's Acul Wedding",
            'description' => $descriptions['ja'],
            'translations' => collect($descriptions)->map(fn (string $text) => ['description' => $text])->all(),
            'address' => '1784-23 Yanase-machi, Utsunomiya-shi, Tochigi 321-0933',
            'city' => 'Utsunomiya',
            'country' => 'Japan',
            'phone' => '028-651-3254',
            'timezone' => 'Asia/Tokyo',
            'currency' => 'JPY',
            'default_locale' => 'ja',
            'event_start_time' => '10:00',
            'event_end_time' => '19:00',
            'weekday_discount_percent' => 0,
            'deposit_percent' => 0,
            'cover_path' => '/images/jsacul/08-1.webp',
            'catalog_mode' => 'plan',
            'theme' => 'jsacul',
            'hall_groups' => $groups,
            'plan_info' => $info,
            'films' => self::FILMS,
        ];

        // No code: public and open. With a code: private (unlisted + noindex + gate).
        $code = $this->accessCode ?? env('JSACUL_ACCESS_CODE');
        $venueData['access_code_hash'] = filled($code) ? Hash::make($code) : null;
        $venueData['public_status'] = filled($code) ? 'unlisted' : 'published';

        $venue = Venue::updateOrCreate(['slug' => self::SLUG], $venueData);

        $order = array_flip(self::AREAS);
        $plans = collect($data['plans'])->sortBy([fn ($a, $b) => $order[$a['area']] <=> $order[$b['area']], fn ($a, $b) => $a['n'] <=> $b['n']])->values();

        $keep = [];
        foreach ($plans as $sort => $plan) {
            $translations = [];
            foreach (self::LOCALES as $locale) {
                $translations[$locale] = ['name' => $plan['name'][$locale], 'description' => $plan['desc'][$locale]];
            }

            $hall = $venue->halls()->updateOrCreate(['slug' => $plan['slug']], [
                'group_key' => $plan['area'],
                'name' => $plan['name']['ja'],
                'description' => $plan['desc']['ja'],
                'translations' => $translations,
                'size_sqm' => null,
                'setting' => Hall::SETTING_OUTDOOR,
                'min_guests' => 2,
                'max_guests' => 10,
                'seating_styles' => [],
                'view_type' => null,
                'catering_included' => false,
                'extra_hour_available' => false,
                'extra_hour_price' => null,
                'base_price' => self::PRICES['western_1'],
                'price_options' => collect(self::PRICES)->map(fn (int $price, string $key) => ['key' => $key, 'price' => $price])->values()->all(),
                'amenities' => [],
                'sort_order' => $sort,
                'is_active' => true,
            ]);
            $keep[] = $hall->id;

            $hall->images()->delete();
            foreach ($plan['images'] as $imageSort => $file) {
                $hall->images()->create([
                    'image_url' => "/images/jsacul/{$file}",
                    'tags' => [],
                    'alt_text' => $plan['name']['ja'],
                    'sort_order' => $imageSort,
                ]);
            }

            $this->seedInventory($hall);
        }

        // Plans removed from the data file stop showing; nothing else is touched.
        $venue->halls()->whereNotIn('id', $keep)->update(['is_active' => false]);

        $venue->knowledgeItems()->delete();
        foreach ($data['knowledge'] as $sort => $item) {
            $venue->knowledgeItems()->create([
                'category' => $item['category'],
                'title' => $item['ja']['title'],
                'body' => $item['ja']['body'],
                'translations' => collect(self::LOCALES)->mapWithKeys(fn (string $l) => [$l => $item[$l]])->all(),
                'tags' => [],
                'sort_order' => $sort,
                'is_active' => true,
            ]);
        }
    }

    /** Closed on Tuesdays; weekends and holidays carry the real JPY 30,000 surcharge. One bulk upsert per plan. */
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
                'total_slots' => 2,
                'booked_slots' => $date->isTuesday() ? 2 : ((($i * 5 + $hall->id * 3) % 9) < ($date->isWeekend() ? 4 : 1) ? 1 : 0),
                'price' => (float) $hall->base_price + ($date->isWeekend() ? self::WEEKEND_SURCHARGE : 0),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        HallInventory::upsert($rows, ['hall_id', 'event_date'], ['total_slots', 'booked_slots', 'price', 'updated_at']);
    }
}
