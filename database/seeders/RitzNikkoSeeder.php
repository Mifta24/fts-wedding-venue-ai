<?php

namespace Database\Seeders;

use App\Models\Hall;
use App\Models\HallInventory;
use App\Models\Venue;
use App\Models\VenueKnowledgeItem;
use Illuminate\Database\Seeder;

/**
 * "The Ritz-Carlton, Nikko" demo venue, shown next to the original demo.
 *
 * It is unlisted: reachable by its link, left out of the opening list and the
 * sitemap, and marked noindex. The photos are the hotel's own press images and
 * are for this demo only. Halls, capacities and prices below are SAMPLE figures
 * for the demo, not the hotel's real offer.
 */
class RitzNikkoSeeder extends Seeder
{
    public const SLUG = 'ritz-carlton-nikko';

    private const INVENTORY_DAYS = 540;

    public function run(): void
    {
        $description = [
            'id' => 'Resor tepi danau Chuzenji di Taman Nasional Nikko, dikelilingi Gunung Nantai dan dedaunan musim gugur. Demo konsep pernikahan di tepi danau, lengkap dengan lounge bar dan suite pengantin menghadap danau.',
            'en' => 'A lakeside resort on Lake Chuzenji in Nikko National Park, ringed by Mt. Nantai and autumn colour. A demo of a lakeside wedding with a lounge bar and lake-facing bridal suite.',
            'ja' => '日光国立公園の中禅寺湖畔に佇むリゾート。男体山と紅葉に包まれた湖畔ウェディングを、ラウンジバーと湖を望むブライダルスイートとともにご紹介する、デモ用の会場です。',
            'th' => 'รีสอร์ตริมทะเลสาบชูเซนจิในอุทยานแห่งชาตินิกโก ล้อมรอบด้วยภูเขานันไตและใบไม้เปลี่ยนสี ตัวอย่างงานแต่งงานริมทะเลสาบ พร้อมเลานจ์บาร์และห้องสวีทเจ้าสาวที่เห็นทะเลสาบ',
            'zh' => '位於日光國立公園中禪寺湖畔的度假飯店，環繞著男體山與秋日紅葉。此為示範用的湖畔婚禮場地，備有酒吧休息室與面向湖景的新娘套房。',
        ];

        $venue = Venue::updateOrCreate(
            ['slug' => self::SLUG],
            [
                'name' => 'The Ritz-Carlton, Nikko',
                'description' => $description['ja'],
                'translations' => collect($description)->map(fn (string $text) => ['description' => $text])->all(),
                'address' => '2482 Chugushi, Nikko',
                'city' => 'Tochigi',
                'country' => 'Japan',
                'latitude' => 36.738361,
                'longitude' => 139.497583,
                'timezone' => 'Asia/Tokyo',
                'currency' => 'JPY',
                'default_locale' => 'ja',
                'event_start_time' => '10:00',
                'event_end_time' => '21:00',
                'weekday_discount_percent' => 10,
                'deposit_percent' => 30,
                'cover_path' => '/images/ritz-nikko/exterior.webp',
                'video_url' => 'https://www.youtube.com/watch?v=rShzTN66Auw',
                'public_status' => 'unlisted',
            ]
        );

        foreach ($this->halls() as $sort => $definition) {
            $images = $definition['images'];
            unset($definition['images']);

            $hall = $venue->halls()->updateOrCreate(
                ['slug' => $definition['slug']],
                [...$definition, 'sort_order' => $sort, 'is_active' => true]
            );

            $hall->images()->delete();
            foreach ($images as $imageSort => $image) {
                $hall->images()->create([
                    'image_url' => $image['url'],
                    'tags' => $image['tags'],
                    'alt_text' => $image['alt'],
                    'sort_order' => $imageSort,
                ]);
            }

            $this->seedInventory($hall);
        }

        $venue->knowledgeItems()->delete();
        foreach ($this->knowledge() as $sort => $item) {
            $venue->knowledgeItems()->create([...$item, 'sort_order' => $sort, 'is_active' => true]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function halls(): array
    {
        $img = fn (string $file) => "/images/ritz-nikko/{$file}.webp";

        return [
            [
                'name' => 'Lakeside Garden',
                'slug' => 'lakeside-garden',
                'description' => 'Taman tepi danau untuk upacara dan resepsi, dengan latar Gunung Nantai dan dedaunan musim gugur. Data contoh untuk demo.',
                'translations' => [
                    'en' => ['name' => 'Lakeside Garden', 'description' => 'A garden on the lake shore for ceremonies and receptions, set against Mt. Nantai and the autumn leaves. Sample details for the demo.'],
                    'ja' => ['name' => 'レイクサイド ガーデン', 'description' => '男体山と紅葉を背に、湖畔で挙式と披露宴を行えるガーデン。デモ用のサンプル情報です。'],
                    'th' => ['name' => 'เลคไซด์ การ์เด้น', 'description' => 'สวนริมทะเลสาบสำหรับพิธีและงานเลี้ยง ฉากหลังเป็นภูเขานันไตและใบไม้เปลี่ยนสี ข้อมูลตัวอย่างสำหรับการสาธิต'],
                    'zh' => ['name' => '湖畔花園', 'description' => '位於湖畔的花園，適合舉辦儀式與婚宴，背景為男體山與秋日紅葉。以下為示範用的範例資訊。'],
                ],
                'size_sqm' => 600,
                'setting' => Hall::SETTING_OUTDOOR,
                'min_guests' => 20,
                'max_guests' => 120,
                'seating_styles' => ['banquet', 'cocktail'],
                'view_type' => 'lake',
                'catering_included' => true,
                'extra_hour_available' => true,
                'extra_hour_price' => 150000,
                'base_price' => 1800000,
                'amenities' => ['sound_system', 'bridal_room', 'parking', 'valet', 'wifi', 'garden_lights', 'rain_cover'],
                'images' => [
                    ['url' => $img('exterior'), 'tags' => ['exterior', 'ceremony'], 'alt' => 'Hotel tepi danau di kaki bukit musim gugur'],
                    ['url' => $img('marshland'), 'tags' => ['exterior', 'decor'], 'alt' => 'Lahan basah dan dedaunan musim gugur di Nikko'],
                ],
            ],
            [
                'name' => 'The Bar & Lake Suite',
                'slug' => 'bar-and-lake-suite',
                'description' => 'Lounge bar dan suite pengantin menghadap danau, untuk jamuan kecil dan persiapan pengantin. Data contoh untuk demo.',
                'translations' => [
                    'en' => ['name' => 'The Bar & Lake Suite', 'description' => 'A lounge bar and a lake-facing bridal suite, for small gatherings and getting ready. Sample details for the demo.'],
                    'ja' => ['name' => 'ザ・バー＆レイクスイート', 'description' => 'ラウンジバーと湖を望むブライダルスイート。少人数のパーティーや花嫁様のお支度に。デモ用のサンプル情報です。'],
                    'th' => ['name' => 'เดอะ บาร์ แอนด์ เลค สวีท', 'description' => 'เลานจ์บาร์และห้องสวีทเจ้าสาวที่เห็นทะเลสาบ เหมาะกับงานเลี้ยงขนาดเล็กและการแต่งตัวของเจ้าสาว ข้อมูลตัวอย่างสำหรับการสาธิต'],
                    'zh' => ['name' => '酒吧與湖景套房', 'description' => '酒吧休息室與面向湖景的新娘套房，適合小型聚會與新娘準備。以下為示範用的範例資訊。'],
                ],
                'size_sqm' => 180,
                'setting' => Hall::SETTING_INDOOR,
                'min_guests' => 10,
                'max_guests' => 40,
                'seating_styles' => ['cocktail', 'lounge'],
                'view_type' => 'lake',
                'catering_included' => true,
                'extra_hour_available' => true,
                'extra_hour_price' => 80000,
                'base_price' => 950000,
                'amenities' => ['air_conditioning', 'sound_system', 'bridal_room', 'wifi', 'valet'],
                'images' => [
                    ['url' => $img('bar'), 'tags' => ['interior', 'reception'], 'alt' => 'Lounge bar hotel'],
                    ['url' => $img('lake-suite'), 'tags' => ['interior', 'decor'], 'alt' => 'Suite menghadap danau'],
                    ['url' => $img('lake-suite-pano'), 'tags' => ['interior'], 'alt' => 'Panorama suite menghadap danau'],
                ],
            ],
        ];
    }

    private function seedInventory(Hall $hall): void
    {
        $start = now()->startOfDay();

        for ($i = 0; $i < self::INVENTORY_DAYS; $i++) {
            $date = $start->copy()->addDays($i);
            $multiplier = match (true) {
                $date->isSaturday() => 1.25,
                $date->isSunday() => 1.1,
                default => 1.0,
            };

            if (in_array($date->month, [10, 11, 5, 6], true)) {
                $multiplier *= 1.08;
            }

            $demand = match (true) {
                $date->isSaturday() => 7,
                $date->isSunday() => 5,
                $date->isFriday() => 3,
                default => 1,
            };

            HallInventory::updateOrCreate(
                ['hall_id' => $hall->id, 'event_date' => $date],
                [
                    'total_slots' => 1,
                    'booked_slots' => (($i * 7 + $hall->id * 13) % 10) < $demand ? 1 : 0,
                    'price' => round((float) $hall->base_price * $multiplier, -4),
                ]
            );
        }
    }

    /** @return list<array<string, mixed>> */
    private function knowledge(): array
    {
        return [
            [
                'category' => VenueKnowledgeItem::CATEGORY_GENERAL,
                'title' => 'Tentang The Ritz-Carlton, Nikko (demo)',
                'body' => 'Ini adalah venue demo bertema The Ritz-Carlton, Nikko di tepi Danau Chuzenji, Taman Nasional Nikko. Hall, kapasitas, dan harga di sini adalah contoh, bukan penawaran resmi hotel.',
                'translations' => [
                    'en' => ['title' => 'About The Ritz-Carlton, Nikko (demo)', 'body' => 'This is a demo venue themed on The Ritz-Carlton, Nikko on Lake Chuzenji in Nikko National Park. The halls, capacities and prices here are samples, not the hotel\'s official offer.'],
                    'ja' => ['title' => 'ザ・リッツ・カールトン日光について（デモ）', 'body' => '日光国立公園の中禅寺湖畔にあるザ・リッツ・カールトン日光をテーマにしたデモ会場です。ホール、収容人数、料金はサンプルであり、ホテルの正式なご案内ではありません。'],
                    'th' => ['title' => 'เกี่ยวกับ The Ritz-Carlton, Nikko (ตัวอย่าง)', 'body' => 'นี่คือสถานที่ตัวอย่างในธีม The Ritz-Carlton, Nikko ริมทะเลสาบชูเซนจิ ในอุทยานแห่งชาตินิกโก ห้อง ความจุ และราคาในที่นี้เป็นข้อมูลตัวอย่าง ไม่ใช่ข้อเสนออย่างเป็นทางการของโรงแรม'],
                    'zh' => ['title' => '關於日光麗思卡爾頓（示範）', 'body' => '這是以日光國立公園中禪寺湖畔的日光麗思卡爾頓為主題的示範場地。此處的廳房、容納人數與價格皆為範例，並非飯店的正式方案。'],
                ],
                'tags' => ['overview', 'about', 'demo', 'nikko'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_FAQ,
                'title' => 'Bagaimana cara menonton video venue?',
                'body' => 'Di halaman depan ada tombol "Tonton videonya". Klik untuk membuka video dalam jendela pop-up; video berhenti saat jendela ditutup.',
                'translations' => [
                    'en' => ['title' => 'How do I watch the venue film?', 'body' => 'On the front page there is a "Watch the film" button. Click it to open the video in a pop-up; it stops when the pop-up is closed.'],
                    'ja' => ['title' => '会場の映像はどこで見られますか？', 'body' => 'トップページの「映像を見る」ボタンを押すと、ポップアップで動画が開きます。閉じると動画は停止します。'],
                    'th' => ['title' => 'จะชมวิดีโอสถานที่ได้อย่างไร', 'body' => 'ที่หน้าแรกมีปุ่ม "ชมวิดีโอ" กดเพื่อเปิดวิดีโอในหน้าต่างป๊อปอัป วิดีโอจะหยุดเมื่อปิดหน้าต่าง'],
                    'zh' => ['title' => '如何觀看場地影片？', 'body' => '首頁有「觀看影片」按鈕，點擊後會以彈出視窗播放影片；關閉視窗即停止播放。'],
                ],
                'tags' => ['video', 'film', 'faq'],
            ],
        ];
    }
}
