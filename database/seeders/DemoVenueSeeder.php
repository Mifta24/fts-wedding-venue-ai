<?php

namespace Database\Seeders;

use App\Models\Hall;
use App\Models\HallInventory;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueKnowledgeItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoVenueSeeder extends Seeder
{
    /** Weddings are planned far ahead, so the calendar opens about eighteen months. */
    private const INVENTORY_DAYS = 540;

    private const VENUE_NAME = 'FTS Wedding Venue AI';

    public function run(): void
    {
        $owner = User::firstOrCreate(
            ['email' => 'owner@ftswedding.test'],
            [
                'name' => 'Owner',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $description = [
            'id' => 'Gedung pernikahan di tengah taman luas di Bintaro, Tangerang Selatan. Lima hall untuk akad, resepsi, dan lamaran — dari salon intim 40 tamu hingga ballroom 1.000 tamu — lengkap dengan tim wedding organizer, katering, dan dekorasi di tempat.',
            'en' => 'A wedding venue set in landscaped gardens in Bintaro, South Tangerang. Five halls for ceremonies, receptions and engagements — from an intimate 40-guest salon to a 1,000-guest ballroom — with an in-house wedding organizer, catering and decoration team.',
            'ja' => '南タンゲラン・ビンタロの広大な庭園に佇むウェディング会場。40名の親密なサロンから1,000名のボールルームまで、挙式・披露宴・婚約式のための5つのホール。ウェディングプランナー、ケータリング、装飾チームが常駐しています。',
        ];

        $venue = Venue::updateOrCreate(
            ['name' => self::VENUE_NAME],
            [
                'slug' => Venue::where('name', self::VENUE_NAME)->value('slug') ?? Venue::generateUniqueSlug(self::VENUE_NAME),
                'description' => $description['id'],
                'translations' => collect($description)->map(fn (string $text) => ['description' => $text])->all(),
                'address' => 'Jl. Taman Bintaro Raya No. 12, Pondok Aren',
                'city' => 'Tangerang Selatan',
                'country' => 'Indonesia',
                'latitude' => -6.2745,
                'longitude' => 106.7283,
                'phone' => '+62 21 7488 2020',
                'whatsapp' => '6281234567890',
                'email' => 'wedding@ftswedding.test',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'default_locale' => 'id',
                'event_start_time' => '08:00',
                'event_end_time' => '22:00',
                'weekday_discount_percent' => 15,
                'deposit_percent' => 30,
                'cover_path' => 'https://images.unsplash.com/photo-1670529776180-60e4132ab90c?auto=format&fit=crop&w=1920&q=80',
                'public_status' => 'published',
            ]
        );

        $venue->users()->syncWithoutDetaching([
            $owner->id => ['role' => 'owner', 'status' => 'active'],
        ]);

        $sort = 0;
        foreach ($this->hallDefinitions() as $definition) {
            $images = $definition['images'];
            unset($definition['images']);

            $hall = $venue->halls()->updateOrCreate(
                ['slug' => $definition['slug']],
                [...$definition, 'sort_order' => $sort++, 'is_active' => true]
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

        $this->seedKnowledgeBase($venue);

        $this->command?->info('Demo login: owner@ftswedding.test — password: password');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function hallDefinitions(): array
    {
        $photo = fn (string $id) => "https://images.unsplash.com/photo-{$id}?auto=format&fit=crop&w=1200&q=80";

        return [
            [
                'name' => 'Grand Ballroom Aurora',
                'slug' => 'grand-ballroom-aurora',
                'description' => 'Ballroom megah berlangit-langit tinggi dengan lampu kristal, panggung lebar, dan layar LED — untuk resepsi besar yang tak terlupakan.',
                'translations' => [
                    'en' => ['name' => 'Grand Ballroom Aurora', 'description' => 'A grand ballroom with soaring ceilings, crystal chandeliers, a wide stage and LED screens — made for a large, unforgettable reception.'],
                    'ja' => ['name' => 'グランドボールルーム オーロラ', 'description' => '高い天井とクリスタルシャンデリア、広いステージとLEDスクリーンを備えた、忘れられない大規模披露宴のためのボールルームです。'],
                ],
                'size_sqm' => 900,
                'setting' => Hall::SETTING_INDOOR,
                'min_guests' => 300,
                'max_guests' => 1000,
                'seating_styles' => ['banquet', 'theatre', 'cocktail'],
                'view_type' => 'skyline',
                'catering_included' => false,
                'extra_hour_available' => true,
                'extra_hour_price' => 6000000,
                'base_price' => 85000000,
                'amenities' => ['air_conditioning', 'chandelier', 'sound_system', 'led_screen', 'stage', 'stage_lighting', 'aisle_runway', 'bridal_room', 'parking', 'valet', 'generator', 'wifi', 'prayer_room'],
                'images' => [
                    ['url' => $photo('1519167758481-83f550bb49b3'), 'tags' => ['reception', 'interior'], 'alt' => 'Ballroom dengan meja bundar untuk resepsi'],
                    ['url' => $photo('1690812304029-bce8cef581f6'), 'tags' => ['interior', 'chandelier'], 'alt' => 'Lampu kristal dan langit-langit ballroom'],
                    ['url' => $photo('1587271407850-8d438ca9fdf2'), 'tags' => ['ceremony', 'decor'], 'alt' => 'Pelaminan di bawah kanopi bunga'],
                ],
            ],
            [
                'name' => 'Garden Pavilion Mawar',
                'slug' => 'garden-pavilion-mawar',
                'description' => 'Paviliun taman terbuka dengan lorong bunga, lampu gantung hangat, dan kanopi putih — suasana pernikahan taman yang romantis, siang maupun malam.',
                'translations' => [
                    'en' => ['name' => 'Garden Pavilion Mawar', 'description' => 'An open garden pavilion with a flower-lined aisle, warm string lights and white canopies — a romantic garden wedding by day or night.'],
                    'ja' => ['name' => 'ガーデンパビリオン マワール', 'description' => '花に縁取られたバージンロード、温かなストリングライト、白いキャノピーを備えた開放的なガーデンパビリオン。昼も夜もロマンチックなガーデンウェディングを。'],
                ],
                'size_sqm' => 1400,
                'setting' => Hall::SETTING_OUTDOOR,
                'min_guests' => 150,
                'max_guests' => 500,
                'seating_styles' => ['banquet', 'theatre', 'long_table'],
                'view_type' => 'garden',
                'catering_included' => false,
                'extra_hour_available' => true,
                'extra_hour_price' => 4000000,
                'base_price' => 55000000,
                'amenities' => ['garden_lights', 'rain_cover', 'sound_system', 'aisle_runway', 'bridal_room', 'parking', 'generator', 'catering_kitchen', 'prayer_room'],
                'images' => [
                    ['url' => $photo('1607861884586-c7cfaed16290'), 'tags' => ['ceremony', 'exterior', 'decor'], 'alt' => 'Lorong pengantin bertabur lampu di malam hari'],
                    ['url' => $photo('1772127822525-7eda37383b9f'), 'tags' => ['reception', 'exterior'], 'alt' => 'Resepsi taman dengan kanopi putih'],
                    ['url' => $photo('1772127822514-682aeffcc0d3'), 'tags' => ['reception', 'decor'], 'alt' => 'Meja resepsi dengan rangkaian bunga'],
                    ['url' => $photo('1759124650320-d629a3d73d9f'), 'tags' => ['reception', 'decor'], 'alt' => 'Meja bundar dengan bunga warna-warni'],
                ],
            ],
            [
                'name' => 'Lakeside Terrace Danau',
                'slug' => 'lakeside-terrace-danau',
                'description' => 'Teras tepi danau dengan deretan kursi putih menghadap air dan pegunungan — pilihan tenang untuk akad nikah dan resepsi di alam terbuka.',
                'translations' => [
                    'en' => ['name' => 'Lakeside Terrace Danau', 'description' => 'A lakeside terrace with rows of white chairs facing the water and the hills — a calm choice for an outdoor ceremony and reception.'],
                    'ja' => ['name' => 'レイクサイドテラス ダナウ', 'description' => '湖と山々に向かって白い椅子が並ぶ湖畔のテラス。自然の中での挙式と披露宴に、穏やかな時間をお届けします。'],
                ],
                'size_sqm' => 800,
                'setting' => Hall::SETTING_OUTDOOR,
                'min_guests' => 100,
                'max_guests' => 350,
                'seating_styles' => ['theatre', 'banquet', 'lounge'],
                'view_type' => 'lake',
                'catering_included' => false,
                'extra_hour_available' => true,
                'extra_hour_price' => 3500000,
                'base_price' => 48000000,
                'amenities' => ['garden_lights', 'rain_cover', 'sound_system', 'aisle_runway', 'bridal_room', 'parking', 'generator', 'prayer_room'],
                'images' => [
                    ['url' => $photo('1505944357431-27579db47558'), 'tags' => ['ceremony', 'exterior'], 'alt' => 'Kursi akad menghadap danau'],
                    ['url' => $photo('1529636798458-92182e662485'), 'tags' => ['decor', 'exterior'], 'alt' => 'Gapura bunga dan kain putih'],
                    ['url' => $photo('1523438885200-e635ba2c371e'), 'tags' => ['ceremony', 'exterior'], 'alt' => 'Gazebo pelaminan di antara pepohonan'],
                ],
            ],
            [
                'name' => 'Crystal Glasshouse',
                'slug' => 'crystal-glasshouse',
                'description' => 'Rumah kaca modern beratap, berdinding kaca, dan dikelilingi taman — cahaya alami sepanjang hari, aman dari hujan. Paket katering sudah termasuk.',
                'translations' => [
                    'en' => ['name' => 'Crystal Glasshouse', 'description' => 'A modern glasshouse with a roof and glass walls, wrapped in gardens — natural light all day and safe from the rain. Catering package included.'],
                    'ja' => ['name' => 'クリスタルグラスハウス', 'description' => '屋根とガラス壁を備え、庭園に囲まれたモダンなグラスハウス。一日中自然光が差し込み、雨でも安心です。ケータリングパッケージ込み。'],
                ],
                'size_sqm' => 550,
                'setting' => Hall::SETTING_SEMI_OUTDOOR,
                'min_guests' => 80,
                'max_guests' => 250,
                'seating_styles' => ['banquet', 'theatre', 'cocktail'],
                'view_type' => 'garden',
                'catering_included' => true,
                'extra_hour_available' => true,
                'extra_hour_price' => 4500000,
                'base_price' => 72000000,
                'amenities' => ['air_conditioning', 'sound_system', 'led_screen', 'stage_lighting', 'aisle_runway', 'bridal_room', 'parking', 'generator', 'catering_kitchen', 'wifi'],
                'images' => [
                    ['url' => $photo('1738225734899-30852be7e396'), 'tags' => ['ceremony', 'interior', 'decor'], 'alt' => 'Gapura bunga melingkar di rumah kaca'],
                    ['url' => $photo('1670529776180-60e4132ab90c'), 'tags' => ['ceremony', 'decor'], 'alt' => 'Lorong bunga putih menuju pelaminan'],
                    ['url' => $photo('1525441273400-056e9c7517b3'), 'tags' => ['reception', 'decor'], 'alt' => 'Meja panjang dengan rangkaian bunga'],
                    ['url' => $photo('1469371670807-013ccf25f16a'), 'tags' => ['ceremony', 'decor'], 'alt' => 'Rangkaian bunga di sepanjang lorong'],
                ],
            ],
            [
                'name' => 'Chandelier Salon',
                'slug' => 'chandelier-salon',
                'description' => 'Salon klasik berlampu kristal untuk lamaran, akad intim, atau resepsi keluarga kecil — hangat, privat, dan elegan.',
                'translations' => [
                    'en' => ['name' => 'Chandelier Salon', 'description' => 'A classic crystal-lit salon for engagements, intimate ceremonies and small family receptions — warm, private and elegant.'],
                    'ja' => ['name' => 'シャンデリアサロン', 'description' => 'クリスタルの灯りに包まれたクラシックなサロン。婚約式、少人数の挙式、ご家族だけの披露宴に。温かくプライベートで上品な空間です。'],
                ],
                'size_sqm' => 260,
                'setting' => Hall::SETTING_INDOOR,
                'min_guests' => 40,
                'max_guests' => 120,
                'seating_styles' => ['banquet', 'long_table', 'lounge'],
                'view_type' => null,
                'catering_included' => false,
                'extra_hour_available' => true,
                'extra_hour_price' => 2500000,
                'base_price' => 28000000,
                'amenities' => ['air_conditioning', 'chandelier', 'sound_system', 'bridal_room', 'parking', 'generator', 'wifi', 'prayer_room'],
                'images' => [
                    ['url' => $photo('1723832347953-83c28e2d4dd2'), 'tags' => ['ceremony', 'interior'], 'alt' => 'Ikrar pernikahan di bawah lampu kristal'],
                    ['url' => $photo('1768508951405-10e83c4a2872'), 'tags' => ['reception', 'interior'], 'alt' => 'Meja resepsi di salon kayu hangat'],
                    ['url' => $photo('1710204326042-1f28992414fb'), 'tags' => ['interior'], 'alt' => 'Aula klasik bernuansa biru dan emas'],
                ],
            ],
        ];
    }

    private function seedInventory(Hall $hall): void
    {
        $start = now()->startOfDay();

        for ($i = 0; $i < self::INVENTORY_DAYS; $i++) {
            $date = $start->copy()->addDays($i);

            // Saturdays are the sought-after wedding day, Sundays next; the
            // peak wedding months carry a further premium.
            $multiplier = match (true) {
                $date->isSaturday() => 1.25,
                $date->isSunday() => 1.1,
                default => 1.0,
            };

            if (in_array($date->month, [5, 6, 10, 12], true)) {
                $multiplier *= 1.08;
            }

            // Deterministic pseudo-demand so the demo shows realistic
            // partial availability instead of every date being wide open.
            $demand = match (true) {
                $date->isSaturday() => 7,
                $date->isSunday() => 5,
                $date->isFriday() => 3,
                default => 1,
            };
            $booked = (($i * 7 + $hall->id * 13) % 10) < $demand ? 1 : 0;

            // Match on the same value the date cast stores, so re-seeding
            // updates the existing date instead of colliding with it.
            HallInventory::updateOrCreate(
                ['hall_id' => $hall->id, 'event_date' => $date],
                [
                    'total_slots' => 1,
                    'booked_slots' => $booked,
                    'price' => round((float) $hall->base_price * $multiplier, -5),
                ]
            );
        }
    }

    private function seedKnowledgeBase(Venue $venue): void
    {
        $photo = fn (string $id) => "https://images.unsplash.com/photo-{$id}?auto=format&fit=crop&w=800&q=80";

        $items = [
            [
                'category' => VenueKnowledgeItem::CATEGORY_GENERAL,
                'title' => 'Tentang FTS Wedding Venue AI',
                'body' => 'FTS Wedding Venue AI adalah gedung pernikahan di Bintaro, Tangerang Selatan, dengan lima hall untuk akad, resepsi, dan lamaran: dari Chandelier Salon untuk 40 tamu hingga Grand Ballroom Aurora untuk 1.000 tamu. Satu tanggal disewakan untuk satu pasangan, sehingga seluruh area hall menjadi milik acara Anda sepanjang hari. Tim wedding organizer, katering, dan dekorasi kami siap bekerja di tempat.',
                'translations' => [
                    'en' => ['title' => 'About FTS Wedding Venue AI', 'body' => 'FTS Wedding Venue AI is a wedding venue in Bintaro, South Tangerang, with five halls for ceremonies, receptions and engagements: from the Chandelier Salon for 40 guests to the Grand Ballroom Aurora for 1,000. Each date is rented to one couple, so the whole hall is yours for the day. Our wedding organizer, catering and decoration teams work on site.'],
                    'ja' => ['title' => 'FTS Wedding Venue AI について', 'body' => 'FTS Wedding Venue AI は南タンゲラン・ビンタロにあるウェディング会場で、40名のシャンデリアサロンから1,000名のグランドボールルーム オーロラまで、挙式・披露宴・婚約式のための5つのホールがあります。1日1組様の貸切なので、ホール全体を一日中ふたりのために使えます。ウェディングプランナー、ケータリング、装飾チームが会場に常駐しています。'],
                ],
                'tags' => ['overview', 'about', 'tentang', 'gedung'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_POLICIES,
                'title' => 'Uang muka dan jadwal pembayaran',
                'body' => 'Tanggal diamankan dengan uang muka 30% dari total sewa setelah tim wedding mengonfirmasi pengajuan Anda. Pelunasan dilakukan paling lambat 14 hari sebelum hari acara. Pembayaran lewat transfer bank ke rekening resmi gedung; kami tidak menerima pembayaran lewat tautan atau kurir.',
                'translations' => [
                    'en' => ['title' => 'Down payment and payment schedule', 'body' => 'A date is secured with a 30% down payment of the rental total once the wedding team confirms your request. The balance is due at least 14 days before the event. Pay by bank transfer to the venue\'s official account; we do not accept payment links or couriers.'],
                    'ja' => ['title' => '手付金とお支払いスケジュール', 'body' => 'ウェディングチームがリクエストを確定した後、会場費総額の30%の手付金で日程を確保します。残金は挙式の14日前までにお支払いください。お支払いは会場の公式口座への銀行振込のみで、支払いリンクや集金は承っておりません。'],
                ],
                'tags' => ['down payment', 'dp', 'uang muka', 'deposit', 'pembayaran', 'payment'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_POLICIES,
                'title' => 'Pembatalan dan perubahan tanggal',
                'body' => 'Perubahan tanggal gratis satu kali selama tanggal baru masih kosong dan diajukan minimal 90 hari sebelum acara. Pembatalan lebih dari 180 hari sebelum acara mengembalikan 50% uang muka; 90–180 hari uang muka tidak dikembalikan tetapi dapat dialihkan ke tanggal lain dalam 12 bulan; kurang dari 90 hari berlaku biaya sesuai pembayaran yang sudah masuk.',
                'translations' => [
                    'en' => ['title' => 'Cancellation and rescheduling', 'body' => 'One free date change is allowed if the new date is open and requested at least 90 days before the event. Cancelling more than 180 days ahead refunds 50% of the down payment; 90–180 days ahead the down payment is not refunded but can be moved to another date within 12 months; under 90 days ahead the payments already made are retained.'],
                    'ja' => ['title' => 'キャンセルと日程変更', 'body' => '挙式の90日前までにお申し込みいただき、新しい日程が空いている場合、日程変更は1回まで無料です。180日より前のキャンセルは手付金の50%を返金、90〜180日前は手付金は返金されませんが12か月以内の別日程に振り替え可能、90日未満は支払済みの金額を頂戴します。'],
                ],
                'tags' => ['cancellation', 'refund', 'pembatalan', 'reschedule', 'ubah tanggal', 'jadwal ulang'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_POLICIES,
                'title' => 'Jam acara dan tambahan jam',
                'body' => 'Sewa hall berlaku untuk satu hari, pukul 08:00 sampai 22:00 termasuk persiapan dan pembongkaran dekorasi. Tambahan jam sampai 4 jam tersedia untuk hall tertentu dengan tarif per jam yang tertera di halaman hall. Musik langsung dan sound system harus berhenti pukul 22:00 sesuai aturan lingkungan.',
                'translations' => [
                    'en' => ['title' => 'Event hours and extra hours', 'body' => 'A hall rental covers one day, 8:00 AM to 10:00 PM, including set-up and take-down of decoration. Up to 4 extra hours are available for selected halls at the hourly rate on the hall page. Live music and sound systems must stop at 10:00 PM under neighbourhood rules.'],
                    'ja' => ['title' => '挙式時間と延長', 'body' => 'ホールのご利用は1日単位で、8:00〜22:00（装飾の設営・撤去を含む）です。一部のホールでは最大4時間まで延長でき、1時間あたりの料金は各ホールのページに記載しています。生演奏と音響は、近隣のルールにより22:00までに終了していただきます。'],
                ],
                'tags' => ['hours', 'jam', 'overtime', 'extra hours', 'tambahan jam', 'curfew'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_POLICIES,
                'title' => 'Vendor luar dan aturan gedung',
                'body' => 'Dekorasi, rias, dokumentasi, dan hiburan boleh memakai vendor pilihan Anda dengan biaya akses vendor Rp3.000.000 per vendor dan wajib mendaftar ke tim wedding paling lambat 14 hari sebelum acara. Katering luar diperbolehkan hanya untuk hall tanpa paket katering dan dikenakan biaya dapur Rp8.000.000. Dilarang membawa kembang api, drone tanpa izin, dan minuman beralkohol.',
                'translations' => [
                    'en' => ['title' => 'Outside vendors and house rules', 'body' => 'You may bring your own decoration, makeup, photo and entertainment vendors for a vendor access fee of IDR 3,000,000 per vendor, registered with the wedding team at least 14 days before the event. Outside caterers are allowed only for halls without a catering package and incur a kitchen fee of IDR 8,000,000. Fireworks, unapproved drones and alcohol are not allowed.'],
                    'ja' => ['title' => '外部ベンダーと会場ルール', 'body' => '装飾・メイク・撮影・エンターテインメントは、ベンダー入館料1社300万ルピアでお好みの業者を持ち込めます（挙式14日前までにウェディングチームへ登録）。外部ケータリングはケータリングパッケージのないホールのみ可能で、キッチン使用料800万ルピアがかかります。花火、無許可のドローン、アルコールの持ち込みは禁止です。'],
                ],
                'tags' => ['vendor', 'outside vendor', 'vendor luar', 'rules', 'aturan', 'catering luar'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_SERVICES,
                'title' => 'Wedding organizer dan koordinator hari-H',
                'image_url' => $photo('1721677337543-37b07e7e28b5'),
                'body' => 'Tim wedding organizer kami mendampingi mulai konsultasi, survei lokasi, penyusunan rundown, gladi bersih, hingga koordinasi seluruh vendor di hari-H. Paket penuh mencakup satu lead organizer dan empat kru; paket koordinator hari-H mencakup satu lead dan dua kru.',
                'translations' => [
                    'en' => ['title' => 'Wedding organizer and day-of coordination', 'body' => 'Our wedding organizer team accompanies you from the first consultation and site visit through the rundown and rehearsal to coordinating every vendor on the day. The full package includes one lead organizer and four crew; the day-of coordination package includes one lead and two crew.'],
                    'ja' => ['title' => 'ウェディングプランナーと当日進行', 'body' => '初回相談と会場下見から、進行表の作成、リハーサル、当日の全ベンダーの調整まで、ウェディングプランナーチームが寄り添います。フルパッケージはリードプランナー1名とスタッフ4名、当日進行パッケージはリード1名とスタッフ2名です。'],
                ],
                'tags' => ['wedding organizer', 'wo', 'planner', 'rundown', 'koordinator'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_SERVICES,
                'title' => 'Dekorasi dan rangkaian bunga',
                'image_url' => $photo('1560117531-02eeab8e3593'),
                'body' => 'Dekorator rekanan kami menyediakan pelaminan, gapura bunga, dekorasi lorong, rangkaian meja, dan tata lampu dalam tema klasik, rustic, modern minimalis, atau adat. Bunga segar dan bunga artifisial premium tersedia; pertemuan desain gratis dan mock-up 3D untuk paket di atas Rp40.000.000.',
                'translations' => [
                    'en' => ['title' => 'Decoration and floral design', 'body' => 'Our partner decorators provide the stage, floral arches, aisle decoration, table arrangements and lighting in classic, rustic, modern-minimal or traditional themes. Fresh and premium artificial flowers are available; a free design meeting and 3D mock-up come with packages above IDR 40,000,000.'],
                    'ja' => ['title' => '装飾とフラワーデザイン', 'body' => '提携装飾会社が、クラシック、ラスティック、モダンミニマル、伝統様式のテーマで、ステージ、フラワーアーチ、バージンロード装飾、テーブルフラワー、照明をご用意します。生花と高品質造花をお選びいただけ、4,000万ルピア以上のパッケージには無料のデザイン打ち合わせと3Dモックアップが付きます。'],
                ],
                'tags' => ['decoration', 'dekorasi', 'flower', 'bunga', 'pelaminan', 'backdrop'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_SERVICES,
                'title' => 'Rias pengantin dan busana',
                'image_url' => $photo('1606490194859-07c18c9f0968'),
                'body' => 'Kami bermitra dengan penata rias dan butik busana pengantin untuk adat Jawa, Sunda, Minang, Betawi, hingga gaun internasional. Ruang rias pengantin di setiap hall dapat dipakai sejak pukul 05:00 pada hari acara tanpa biaya tambahan.',
                'translations' => [
                    'en' => ['title' => 'Bridal makeup and attire', 'body' => 'We partner with makeup artists and bridal boutiques for Javanese, Sundanese, Minang and Betawi traditional looks as well as international gowns. The bridal suite in each hall is available from 5:00 AM on the event day at no extra charge.'],
                    'ja' => ['title' => 'ブライダルメイクと衣装', 'body' => 'ジャワ、スンダ、ミナン、ブタウィの伝統衣装から国際的なウェディングドレスまで、メイクアップアーティストやブティックと提携しています。各ホールの新婦控室は、挙式当日の5:00から追加料金なしでご利用いただけます。'],
                ],
                'tags' => ['makeup', 'rias', 'bridal', 'busana', 'attire', 'gown'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_SERVICES,
                'title' => 'Foto, video, dan drone',
                'image_url' => $photo('1583939003579-730e3918a45a'),
                'body' => 'Paket dokumentasi mencakup dua fotografer, satu videografer sinematik, album cetak, dan video highlight 3–5 menit. Penerbangan drone hanya diizinkan oleh operator berizin yang terdaftar di tim wedding. Foto prewedding di taman gedung gratis untuk pasangan dengan tanggal terkonfirmasi.',
                'translations' => [
                    'en' => ['title' => 'Photo, video and drone', 'body' => 'The documentation package includes two photographers, one cinematic videographer, a printed album and a 3–5 minute highlight film. Drone flights are allowed only by licensed operators registered with the wedding team. Pre-wedding photos in the venue gardens are free for couples with a confirmed date.'],
                    'ja' => ['title' => 'フォト・ビデオ・ドローン', 'body' => '撮影パッケージにはカメラマン2名、シネマティック映像1名、プリントアルバム、3〜5分のハイライトムービーが含まれます。ドローン撮影はウェディングチームに登録された有資格オペレーターのみ可能です。日程が確定したカップルは、庭園でのプレウェディング撮影が無料です。'],
                ],
                'tags' => ['photo', 'foto', 'video', 'drone', 'dokumentasi', 'prewedding'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_SERVICES,
                'title' => 'Hiburan, MC, dan sound',
                'image_url' => $photo('1700514077430-3659e38eb5e7'),
                'body' => 'Rekanan hiburan kami meliputi band akustik, orkestra mini, penyanyi, MC dwibahasa, dan DJ. Sound system, panggung, dan tata cahaya hall tersedia gratis; kebutuhan tambahan seperti line array atau LED video wall dihitung sesuai kebutuhan.',
                'translations' => [
                    'en' => ['title' => 'Entertainment, MC and sound', 'body' => 'Our entertainment partners include acoustic bands, mini orchestras, singers, bilingual MCs and DJs. The hall\'s sound system, stage and lighting are free to use; extras such as line arrays or an LED video wall are priced by need.'],
                    'ja' => ['title' => 'エンターテインメント・MC・音響', 'body' => '提携先にはアコースティックバンド、ミニオーケストラ、歌手、バイリンガルMC、DJがいます。ホールの音響・ステージ・照明は無料でご利用いただけ、ラインアレイやLEDビデオウォールなどの追加機材は内容に応じてお見積もりします。'],
                ],
                'tags' => ['entertainment', 'hiburan', 'band', 'mc', 'dj', 'sound', 'musik'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_CATERING,
                'title' => 'Paket katering prasmanan dan plated',
                'image_url' => $photo('1533120921505-7f40f5237ee1'),
                'body' => 'Katering halal kami menyediakan prasmanan (mulai Rp185.000 per tamu), plated dinner (mulai Rp265.000 per tamu), dan live station seperti sate, bakso, dan es krim. Menu mencakup masakan Nusantara dan internasional, dengan pilihan tanpa daging babi, vegetarian, dan bebas alergen atas permintaan. Harga katering di luar sewa hall, kecuali Crystal Glasshouse yang sudah termasuk paket.',
                'translations' => [
                    'en' => ['title' => 'Buffet and plated catering packages', 'body' => 'Our halal catering offers buffets (from IDR 185,000 per guest), plated dinners (from IDR 265,000 per guest) and live stations such as satay, meatball soup and ice cream. Menus span Indonesian and international dishes, with pork-free, vegetarian and allergen-free options on request. Catering is priced separately from the hall rental, except at the Crystal Glasshouse where a package is included.'],
                    'ja' => ['title' => 'ビュッフェ・コースのケータリング', 'body' => 'ハラール対応のケータリングは、ビュッフェ（1名18.5万ルピアから）、コースディナー（1名26.5万ルピアから）、サテやバッソ、アイスクリームなどのライブステーションをご用意しています。インドネシア料理と各国料理があり、豚肉不使用、ベジタリアン、アレルゲンフリーもご要望に応じて対応します。料金はホール使用料とは別ですが、クリスタルグラスハウスはパッケージ込みです。'],
                ],
                'tags' => ['catering', 'buffet', 'prasmanan', 'menu', 'halal', 'makanan'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_CATERING,
                'title' => 'Tasting menu dan kue pengantin',
                'image_url' => $photo('1525441273400-056e9c7517b3'),
                'body' => 'Pasangan dengan tanggal terkonfirmasi mendapat sesi tasting menu untuk empat orang tanpa biaya. Kue pengantin kustom (tiga hingga lima tingkat) dan dessert table dibuat oleh patissier rekanan; pemesanan minimal 30 hari sebelum acara.',
                'translations' => [
                    'en' => ['title' => 'Menu tasting and wedding cake', 'body' => 'Couples with a confirmed date receive a complimentary menu tasting for four. Custom wedding cakes (three to five tiers) and dessert tables are made by our partner patissier; order at least 30 days before the event.'],
                    'ja' => ['title' => '試食会とウェディングケーキ', 'body' => '日程が確定したカップルには、4名様分の試食会を無料でご用意します。3〜5段のオーダーウェディングケーキとデザートテーブルは提携パティシエが製作します。挙式の30日前までにご注文ください。'],
                ],
                'tags' => ['tasting', 'cake', 'kue', 'dessert', 'menu'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_ACCESS,
                'title' => 'Parkir, valet, dan akses tamu',
                'body' => 'Tersedia parkir untuk 350 mobil dan 200 motor, dengan layanan valet untuk tamu VIP dan keluarga inti. Area drop-off tertutup dan jalur kursi roda tersedia di setiap hall. Petugas keamanan dan tim P3K bertugas selama acara.',
                'translations' => [
                    'en' => ['title' => 'Parking, valet and guest access', 'body' => 'Parking is available for 350 cars and 200 motorbikes, with valet service for VIP guests and immediate family. A covered drop-off area and wheelchair access are available at every hall. Security officers and a first-aid team are on duty during events.'],
                    'ja' => ['title' => '駐車場・バレー・ゲストアクセス', 'body' => '車350台・バイク200台分の駐車場があり、VIPゲストとご親族にはバレーサービスをご用意しています。各ホールには屋根付きの降車場と車いす用の動線があります。イベント中は警備員と救護チームが待機しています。'],
                ],
                'tags' => ['parking', 'parkir', 'valet', 'access', 'wheelchair', 'keamanan'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_ACCESS,
                'title' => 'Lokasi dan hotel terdekat',
                'body' => 'Gedung berada sekitar 10 menit dari pintu tol Bintaro Viaduct, 35 menit dari Bandara Soekarno-Hatta lewat tol, dan 12 menit berjalan dari Stasiun Jurangmangu. Tersedia tiga hotel bintang tiga dan empat dalam radius 3 km; tim kami dapat membantu blok kamar untuk keluarga yang datang dari luar kota.',
                'translations' => [
                    'en' => ['title' => 'Location and nearby hotels', 'body' => 'The venue is about 10 minutes from the Bintaro Viaduct toll gate, 35 minutes from Soekarno-Hatta Airport by toll road and a 12-minute walk from Jurangmangu Station. Three three- and four-star hotels lie within 3 km, and our team can help block rooms for out-of-town family.'],
                    'ja' => ['title' => 'アクセスと近隣ホテル', 'body' => '会場はビンタロ・ビアダクト料金所から約10分、スカルノ・ハッタ空港から高速で約35分、ジュランマングー駅から徒歩12分です。3km圏内に3つ星・4つ星ホテルが3軒あり、遠方のご家族のための客室ブロックもお手伝いします。'],
                ],
                'tags' => ['location', 'lokasi', 'hotel', 'airport', 'bandara', 'stasiun', 'tol', 'direction'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_FAQ,
                'title' => 'Apakah akad nikah dan resepsi bisa di hari yang sama?',
                'body' => 'Bisa. Pilih jenis acara "akad dan resepsi" saat mengajukan tanggal. Karena satu tanggal disewakan untuk satu pasangan, Anda bisa memakai hall yang sama untuk akad pagi dan resepsi siang atau malam, dengan jeda penataan ulang yang disusun tim wedding.',
                'translations' => [
                    'en' => ['title' => 'Can the ceremony and reception be on the same day?', 'body' => 'Yes. Choose "ceremony & reception" when you request a date. Since each date is rented to one couple, you can use the same hall for a morning ceremony and an afternoon or evening reception, with a reset break arranged by the wedding team.'],
                    'ja' => ['title' => '挙式と披露宴は同じ日にできますか？', 'body' => 'はい。日程のリクエストで「挙式＋披露宴」をお選びください。1日1組様の貸切のため、同じホールで午前の挙式と午後または夜の披露宴を行え、入れ替えの時間はウェディングチームが調整します。'],
                ],
                'tags' => ['akad', 'resepsi', 'ceremony', 'reception', 'same day', 'sehari'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_FAQ,
                'title' => 'Bagaimana cara survei lokasi?',
                'body' => 'Survei lokasi gratis setiap hari pukul 10:00–16:00 dengan janji temu. Kirim pesan lewat tim wedding (WhatsApp) atau minta AI Concierge menyerahkan percakapan ke tim; kami akan menjadwalkan kunjungan dan menyiapkan simulasi tata ruang untuk tanggal pilihan Anda.',
                'translations' => [
                    'en' => ['title' => 'How do I arrange a site visit?', 'body' => 'Site visits are free every day from 10:00 to 16:00 by appointment. Message the wedding team on WhatsApp or ask the AI Concierge to hand the conversation over; we will schedule your visit and prepare a layout simulation for your chosen date.'],
                    'ja' => ['title' => '会場の下見はできますか？', 'body' => '下見は毎日10:00〜16:00に予約制で無料です。ウェディングチームにWhatsAppでご連絡いただくか、AIコンシェルジュに会話を引き継ぐよう依頼してください。日程を調整し、ご希望日のレイアウトシミュレーションをご用意します。'],
                ],
                'tags' => ['survey', 'site visit', 'survei', 'kunjungan', 'tour', 'visit'],
            ],
        ];

        $venue->knowledgeItems()->delete();

        foreach ($items as $sort => $item) {
            $venue->knowledgeItems()->create([...$item, 'sort_order' => $sort, 'is_active' => true]);
        }
    }
}
