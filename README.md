# Wedding Venue AI

Situs gedung pernikahan interaktif dengan **AI Wedding Concierge**. Calon pengantin menjelajahi venue seperti membuka acara pernikahan bab demi bab (sambutan, hall, layanan pernikahan, informasi gedung, pengajuan tanggal, tim wedding) sambil mengobrol dengan concierge AI. Concierge menjawab dari data venue yang terkontrol, mengecek tanggal kosong dan harga sewa hall, membuat pengajuan tanggal, dan menyerahkan percakapan ke tim wedding bila perlu. Tim mengelola semuanya lewat panel admin.

Satu aplikasi bisa melayani beberapa venue. Setiap venue punya halaman sendiri di `/{venueSlug}`.

## Fitur

### Sisi calon pengantin
- **Venue sebagai susunan acara**: setiap bagian adalah satu bab (I sambutan, II hall, III layanan pernikahan, IV informasi gedung, V amankan tanggal, VI tim wedding). Menu berupa panel "susunan acara" bernomor angka Romawi, header menampilkan bab yang sedang dibuka, dan perpindahan halaman ditandai tirai beludru yang menutup dan membuka. Di ponsel, panel menjadi strip tombol bab.
- **Hall**: tiap hall punya suasana (indoor, outdoor, semi-outdoor), luas, kapasitas minimal–maksimal tamu, tata tempat duduk, pemandangan, fasilitas, galeri foto, dan tarif sewa per acara.
- **Sewa per tanggal**: satu tanggal disewakan untuk satu pasangan. Tarif per tanggal diatur admin (Sabtu dan musim ramai lebih mahal). **Diskon hari kerja** berlaku untuk acara Senin–Kamis, dan **uang muka** (persen dari total) dihitung di satu tempat sehingga wizard, AI Concierge, dan total booking selalu sama. Beberapa hall menjual **tambahan jam** (maks. 4 jam).
- **Jenis acara**: akad nikah, resepsi, akad & resepsi, atau lamaran.
- **Tiga bahasa**: Indonesia (`id`), Inggris (`en`), dan Jepang (`ja`), dipilih lewat parameter `?lang=`.
- **Chat dengan AI Wedding Concierge**
  - Tahu di bab mana pengunjung berada (hall atau layanan yang sedang dilihat, formulir tanggal yang sedang diisi), jadi bisa menjawab "hall ini".
  - Menjawab dalam bahasa yang ditulis pengunjung.
  - Menampilkan kartu hall (suasana, kapasitas, total setelah diskon, uang muka) langsung di dalam chat.
- **Pengajuan tanggal**: pilih tanggal di kalender (tanggal yang sudah terisi dicoret, hari kerja berdiskon diberi titik), pilih jenis acara dan jumlah tamu, pilih hall, lalu kirim pengajuan (status awal `pending`). Data tanggal kosong dari `GET /{venueSlug}/reservation/availability`.

### Desain
Tema "Ever After": tinta anggur gelap, kertas gading, dan aksen emas champagne seperti kartu undangan. Font Cormorant Garamond (judul, miring untuk penekanan) dan Jost (isi). Foto pernikahan dan interior venue dari Unsplash.

### Sisi admin (`/admin`)
- Dashboard ringkasan: pengajuan yang menunggu lebih dari 24 jam, handover terbuka, pernikahan terkonfirmasi 30 hari ke depan, dan persentase tanggal terisi 30 hari ke depan. Panel admin bisa dipakai dari ponsel.
- CRUD **hall** (suasana, kapasitas, tata duduk, tarif dasar, tambahan jam, fasilitas, foto).
- CRUD **knowledge items**, yaitu basis pengetahuan yang menjadi sumber jawaban concierge (umum, layanan, katering, kebijakan, akses lokasi, FAQ).
- **Kalender dan harga** per hall (menu Halls → Calendar): buka rentang tanggal, atur jumlah acara per tanggal (biasanya 1) dan tarif per acara. Jumlah tidak bisa diturunkan di bawah yang sudah dibooking. Tampilan bulanan berupa kalender: ketuk satu hari untuk mengisi formulir, ketuk hari berikutnya untuk memilih ujung rentang.
- **Pengaturan venue** (khusus `owner`): kontak, jam acara, bahasa dan zona waktu, diskon hari kerja, persen uang muka, serta status halaman publik (draft atau published).
- Daftar **booking** dan ubah statusnya. Alurnya `pending` → `confirmed` atau `cancelled`, dan `confirmed` → `cancelled`. `cancelled` bersifat final (tanggalnya dikembalikan ke kalender). Hall yang masih punya booking aktif tidak bisa dihapus.
- **Handover**: percakapan yang diserahkan concierge ke tim. Staf bisa membalas langsung ke pasangan dan menandainya selesai.
- Peran pengguna per venue: `owner` dan `staff`.

### Notifikasi email
- **Ke tim** (semua anggota aktif venue): pengajuan tanggal baru dan handover baru dari concierge.
- **Ke pasangan** yang memberi alamat email: tanda terima pengajuan, lalu pemberitahuan saat tanggal dikonfirmasi atau dibatalkan, lengkap dengan ringkasan acara dan uang muka. Bahasanya mengikuti bahasa pengunjung (`id`, `en`, `ja`). Pengunjung yang memilih WhatsApp atau telepon dihubungi langsung oleh tim.
- Email dikirim lewat queue, jadi worker harus jalan (`composer dev` sudah menyertakannya; di produksi jalankan `php artisan queue:work`). Atur `MAIL_*` di `.env`. Bawaannya `MAIL_MAILER=log`, yaitu email hanya ditulis ke log.

## Cara kerja AI Concierge

Kode ada di [app/Services/Concierge/](app/Services/Concierge).

- **[ConciergeService](app/Services/Concierge/ConciergeService.php)** menjalankan satu giliran percakapan. Ia memanggil endpoint chat yang kompatibel dengan OpenAI (LM Studio atau Ollama yang di-host sendiri) dan menjalankan loop tool-calling (maksimal 6 putaran per giliran). Riwayat percakapan disimpan di database. Request dikirim dengan `reasoning_effort=none` agar model tidak melakukan fase berpikir panjang. Seluruh giliran dibatasi 85 detik supaya muat dalam batas 100 detik Cloudflare.
- **[VenueConciergeTools](app/Services/Concierge/VenueConciergeTools.php)** berisi alat yang bisa dipanggil model. Semua fakta venue, hall, harga, dan ketersediaan harus lewat sini, karena model sendiri tidak dipercaya menyimpan data apa pun.

  | Tool | Fungsi |
  |---|---|
  | `search_knowledge` | Mencari di knowledge base venue (layanan, katering, kebijakan, akses, FAQ) |
  | `search_halls` | Mencari hall yang kosong di suatu tanggal dan muat jumlah tamu, lengkap dengan total harga setelah diskon dan uang muka |
  | `get_hall_detail` | Detail satu hall, termasuk tata duduk, fasilitas, foto, dan tarif tambahan jam |
  | `check_availability` | Cek ketersediaan dan harga pasti (diskon hari kerja, uang muka) untuk satu hall di satu tanggal |
  | `create_booking_request` | Membuat pengajuan tanggal |
  | `request_human_handover` | Menyerahkan percakapan ke tim wedding |

- **[ContentGuard](app/Services/Concierge/ContentGuard.php)** adalah pengaman deterministik di kode. Pesan yang kasar, bersifat seksual, atau ilegal (Indonesia, Inggris, Jepang) dijawab dengan balasan baku tanpa sampai ke model. Balasan model yang masih memuat kata-kata tersebut juga diganti. Pola dibuat sempit supaya pertanyaan pernikahan yang wajar tidak ikut terblokir.
- **Harga tanpa sumber ditolak**: balasan yang memuat angka harga atau placeholder yang tidak berasal dari tool akan ditantang, supaya model tidak mengarang harga.
- **Handover**: alasan yang didukung adalah `special_request`, `complaint`, `custom_package`, `negotiated_rate`, `reschedule`, `payment_issue`, dan `low_confidence`.

## Teknologi

- PHP 8.3+ (dikembangkan di 8.4), Laravel 13
- SQLite sebagai database bawaan. Session, cache, dan queue memakai driver `database`
- Vite 8 dan Tailwind CSS 4 untuk frontend, dengan JavaScript vanilla (tanpa framework) di `resources/js/`
- LLM lokal lewat HTTP (endpoint kompatibel OpenAI)
- PHPUnit 12 untuk tes, Laravel Pint untuk format kode

## Memulai

### Prasyarat
PHP 8.3+ dengan ekstensi SQLite, Composer, dan Node.js 20.19+ (atau 22.12+) dengan npm. Untuk fitur chat AI, Anda juga perlu server LLM yang kompatibel dengan OpenAI (lihat bagian berikutnya).

### Instalasi

```bash
composer setup
```

Perintah itu menjalankan `composer install`, menyalin `.env.example` ke `.env`, membuat `APP_KEY`, menjalankan migrasi, lalu `npm install` dan `npm run build`.

Isi data demo (hanya jalan di environment `local` dan `testing`):

```bash
php artisan db:seed
```

Seeder membuat venue demo **FTS Wedding Venue AI** (slug `fts-wedding-venue-ai`) di Bintaro, Tangerang Selatan: lima hall (Grand Ballroom Aurora, Garden Pavilion Mawar, Lakeside Terrace Danau, Crystal Glasshouse, Chandelier Salon), kalender 540 hari dengan tarif akhir pekan dan musim ramai, diskon hari kerja 15%, uang muka 30%, serta knowledge base (uang muka, pembatalan, jam acara, vendor luar, wedding organizer, dekorasi, rias, dokumentasi, hiburan, katering, parkir, lokasi, FAQ).

### Menjalankan

```bash
composer dev
```

Perintah ini menjalankan semua proses development lewat `php artisan dev`. Daftar prosesnya bisa dilihat dengan `php artisan dev:list`.

| Halaman | URL |
|---|---|
| Halaman pembuka | `http://localhost:8000/` |
| Venue demo | `http://localhost:8000/fts-wedding-venue-ai` |
| Admin | `http://localhost:8000/admin` |

Akun admin demo (hanya untuk lokal, jangan dipakai di produksi):

```
email    : owner@ftswedding.test
password : password
```

### Konfigurasi LLM

Atur di `.env`:

```dotenv
LOCAL_LLM_BASE_URL=   # mis. http://127.0.0.1:1234/v1 (LM Studio) atau http://127.0.0.1:11434/v1 (Ollama)
LOCAL_LLM_API_KEY=    # kosongkan jika server tidak memakai autentikasi
LOCAL_LLM_MODEL=      # nama model yang dimuat di server
```

Model harus mendukung function calling. Tanpa konfigurasi ini, halaman venue tetap jalan tetapi chat concierge tidak bisa menjawab.

Kalau perlu, ubah juga `APP_NAME`, `APP_URL`, dan `APP_TIMEZONE` (bawaan `Asia/Jakarta`).

### Mencoba concierge dari terminal

```bash
php artisan concierge:chat fts-wedding-venue-ai --locale=id
```

Pilihan `--locale` adalah `id`, `en`, atau `ja`. Cara ini berguna untuk menguji loop tool-calling tanpa membuka browser.

## Endpoint utama

| Method | Path | Keterangan |
|---|---|---|
| GET | `/{venueSlug}` | Sambutan (bab I) |
| GET | `/{venueSlug}/halls`, `/halls/{hallSlug}` | Galeri dan detail hall (bab II) |
| GET | `/{venueSlug}/services`, `/services/{id}` | Layanan pernikahan dan katering (bab III) |
| GET | `/{venueSlug}/info`, `/reservation`, `/staff` | Informasi gedung (IV), amankan tanggal (V), tim wedding (VI) |
| GET | `/{venueSlug}/reservation/availability` | Tanggal yang masih punya hall kosong, untuk kalender (`?hall=slug` untuk satu hall) |
| POST | `/{venueSlug}/reservation/quote` | Hitung harga, diskon hari kerja, tambahan jam, dan uang muka |
| POST | `/{venueSlug}/reservation` | Kirim pengajuan tanggal |
| POST | `/{venueSlug}/concierge/start` | Mulai percakapan |
| POST | `/{venueSlug}/concierge/message` | Kirim pesan ke concierge |
| GET | `/{venueSlug}/concierge/history` | Riwayat percakapan |

Endpoint concierge dan reservasi dibatasi laju (rate limit). Pembatas concierge didefinisikan di [AppServiceProvider](app/Providers/AppServiceProvider.php), dan login admin dibatasi 5 percobaan per menit.

## Struktur proyek

```
app/
  Console/Commands/     concierge:chat
  Http/Controllers/     halaman publik, chat, reservasi, dan Admin/
  Models/               Venue, Hall, HallInventory, Booking, Conversation, HandoverRequest, ...
  Services/Concierge/   ConciergeService, VenueConciergeTools, ContentGuard
  Services/Reservation/ ReservationService, ReservationHandover
database/
  migrations/           skema (venue, hall, kalender hall, knowledge, percakapan, booking, handover)
  seeders/              DemoVenueSeeder
resources/
  js/                   stage, narrator, concierge, reservation, date-picker, sound
  views/                halaman publik (venue/), admin (admin/), komponen
tests/
  Feature/ dan Unit/
```

## Pengujian

```bash
composer test
```

Atau jalankan satu berkas:

```bash
php artisan test --compact tests/Feature/ConciergeChatTest.php
```

Cakupan tes: akses admin, chat concierge, tool booking (termasuk diskon hari kerja dan filter jumlah tamu), pengajuan tanggal, status booking, notifikasi email, kalender dan pengaturan admin, semua bab dan panel susunan acara, dan `ContentGuard`. GitHub Actions (`.github/workflows/ci.yml`) menjalankan Pint dan seluruh tes di setiap push ke `master` dan pull request.

## Gaya kode

```bash
vendor/bin/pint --dirty
```

## Deployment

- Jalankan `npm run build` dan `php artisan migrate --force`.
- Set `APP_ENV=production` dan `APP_DEBUG=false`.
- Seeder demo tidak jalan di produksi. Buat akun owner dan venue Anda sendiri, lalu isi `weekday_discount_percent` dan `deposit_percent` sesuai kebijakan venue, dan buka tanggal lewat menu Halls → Calendar.
- Pastikan server aplikasi bisa menjangkau `LOCAL_LLM_BASE_URL`. Pada setup saat ini endpoint LLM diakses lewat Tailscale.
- Jalankan queue worker dan isi `MAIL_*` dengan SMTP asli, atau notifikasi email tidak akan terkirim.
- Karena ada batas 100 detik dari Cloudflare, jangan menaikkan batas waktu respons concierge melebihi 85 detik.

## Lisensi

Project ini dibangun di atas [Laravel](https://laravel.com), yang berlisensi [MIT](https://opensource.org/licenses/MIT).
