<?php

namespace App\Services\Reservation;

use App\Models\Booking;
use App\Models\Venue;

/**
 * Builds the WhatsApp / phone / email hand-over links that carry a
 * reservation summary (or a plain "talk to the team" request) to the venue team.
 */
class ReservationHandover
{
    /**
     * @return array{whatsapp_url: ?string, phone_url: ?string, email_url: ?string}
     */
    public function forBooking(Venue $venue, Booking $booking, string $locale): array
    {
        $message = $this->reservationMessage($booking, $locale);

        return $this->links($venue, $message, $this->subject('reservation', $locale).' '.$booking->reference);
    }

    /**
     * @return array{whatsapp_url: ?string, phone_url: ?string, email_url: ?string}
     */
    public function forStaff(Venue $venue, string $locale): array
    {
        return $this->links($venue, $this->subject('staff_message', $locale), $this->subject('staff', $locale));
    }

    /**
     * @return array{whatsapp_url: ?string, phone_url: ?string, email_url: ?string}
     */
    private function links(Venue $venue, string $message, string $subject): array
    {
        $whatsapp = preg_replace('/\D+/', '', (string) $venue->whatsapp);
        $phone = preg_replace('/[^\d+]/', '', (string) $venue->phone);

        return [
            'whatsapp_url' => $whatsapp !== '' ? 'https://wa.me/'.$whatsapp.'?text='.rawurlencode($message) : null,
            'phone_url' => $phone !== '' ? 'tel:'.$phone : null,
            'email_url' => filled($venue->email) ? 'mailto:'.$venue->email.'?subject='.rawurlencode($subject).'&body='.rawurlencode($message) : null,
        ];
    }

    private function reservationMessage(Booking $booking, string $locale): string
    {
        $booking->loadMissing('hall');

        $labels = match ($locale) {
            'en' => ['Hello, I would like to reserve a date at your wedding venue.', 'Name', 'Event date', 'Event', 'Guests', 'Hall', 'Special request', 'Reference'],
            'ja' => ['こんにちは。結婚式会場の日程を仮予約したいです。', 'お名前', '挙式日', 'ご希望の式', 'ゲスト人数', '会場', 'ご要望', '予約番号'],
            'th' => ['สวัสดีค่ะ ดิฉันต้องการขอจองวันจัดงานแต่งงานที่สถานที่ของคุณ', 'ชื่อ', 'วันที่จัดงาน', 'งาน', 'จำนวนแขก', 'ฮอลล์', 'คำขอพิเศษ', 'หมายเลขอ้างอิง'],
            'zh' => ['您好，我想申請預約貴場地的婚禮日期。', '姓名', '活動日期', '活動類型', '賓客人數', '宴會廳', '特殊需求', '預約編號'],
            default => ['Halo, saya ingin mengajukan tanggal pernikahan di gedung Anda.', 'Nama', 'Tanggal acara', 'Acara', 'Jumlah tamu', 'Hall', 'Permintaan khusus', 'Referensi'],
        };

        return implode("\n", [
            $labels[0],
            '',
            "{$labels[1]}: {$booking->client_name}",
            "{$labels[2]}: ".$booking->event_date->locale($locale === 'zh' ? 'zh_TW' : $locale)->translatedFormat($locale === 'zh' ? 'Y年n月j日 (D)' : 'l, j F Y'),
            "{$labels[3]}: ".self::eventTypeLabel($booking->event_type, $locale),
            "{$labels[4]}: {$booking->guest_count}",
            "{$labels[5]}: ".$booking->hall->translatedName($locale),
            "{$labels[6]}: ".($booking->notes ?: '-'),
            '',
            "{$labels[7]}: {$booking->reference}",
        ]);
    }

    /**
     * The client-facing name of an event type.
     */
    public static function eventTypeLabel(?string $type, string $locale): string
    {
        $labels = [
            'en' => ['akad' => 'Wedding ceremony (akad)', 'reception' => 'Wedding reception', 'akad_reception' => 'Ceremony & reception', 'engagement' => 'Engagement (lamaran)'],
            'ja' => ['akad' => '挙式（アカド）', 'reception' => '披露宴', 'akad_reception' => '挙式＋披露宴', 'engagement' => '婚約式（ラマラン）'],
            'th' => ['akad' => 'พิธีสมรส (อากัด)', 'reception' => 'งานเลี้ยงฉลองมงคลสมรส', 'akad_reception' => 'พิธีสมรสและงานเลี้ยง', 'engagement' => 'พิธีหมั้น (ลามารัน)'],
            'zh' => ['akad' => '婚禮儀式（akad，伊斯蘭結婚儀式）', 'reception' => '婚宴', 'akad_reception' => '婚禮儀式＋婚宴', 'engagement' => '訂婚儀式（lamaran，男方家族正式提親）'],
            'id' => ['akad' => 'Akad nikah', 'reception' => 'Resepsi', 'akad_reception' => 'Akad & resepsi', 'engagement' => 'Lamaran'],
        ];

        return $labels[$locale][$type] ?? ($labels['id'][$type] ?? '-');
    }

    private function subject(string $key, string $locale): string
    {
        return [
            'en' => ['reservation' => 'Wedding date request', 'staff' => 'Question for the venue team', 'staff_message' => 'Hello, I would like to speak with the wedding venue team.'],
            'ja' => ['reservation' => '挙式日のリクエスト', 'staff' => '会場スタッフへのご相談', 'staff_message' => 'こんにちは。結婚式会場のスタッフとお話ししたいです。'],
            'th' => ['reservation' => 'คำขอวันจัดงานแต่งงาน', 'staff' => 'คำถามถึงทีมงานสถานที่', 'staff_message' => 'สวัสดีค่ะ ดิฉันต้องการพูดคุยกับทีมงานของสถานที่จัดงานแต่งงาน'],
            'zh' => ['reservation' => '婚禮日期申請', 'staff' => '詢問場地團隊', 'staff_message' => '您好，我想與婚禮場地的團隊聯繫。'],
            'id' => ['reservation' => 'Pengajuan tanggal pernikahan', 'staff' => 'Pertanyaan untuk tim gedung', 'staff_message' => 'Halo, saya ingin bicara dengan tim gedung pernikahan.'],
        ][$locale][$key];
    }
}
