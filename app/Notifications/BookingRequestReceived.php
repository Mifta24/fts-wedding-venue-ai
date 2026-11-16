<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a client their date request arrived. It is not a confirmation yet.
 */
class BookingRequestReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking)
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing(['venue', 'hall']);
        $locale = $booking->locale ?? $booking->venue->default_locale;
        $copy = $this->copy($locale);
        $summary = ClientBookingSummary::lines($booking, $locale);

        return (new MailMessage)
            ->subject(sprintf($copy['subject'], $booking->reference))
            ->greeting(sprintf($copy['greeting'], $booking->client_name))
            ->line(sprintf($copy['intro'], $booking->venue->name))
            ->lines($summary)
            ->line($copy['next'])
            ->salutation($booking->venue->name);
    }

    /**
     * @return array{subject: string, greeting: string, intro: string, next: string}
     */
    private function copy(string $locale): array
    {
        return [
            'en' => [
                'subject' => 'We received your wedding date request %s',
                'greeting' => 'Hello %s,',
                'intro' => 'Thank you for choosing %s for your big day. Our wedding team will check the date and reply shortly. This is not a confirmation yet and no payment has been taken.',
                'next' => 'Keep your reference handy if you contact us.',
            ],
            'ja' => [
                'subject' => '挙式日のリクエストを受け付けました %s',
                'greeting' => '%s 様',
                'intro' => '%s をお選びいただきありがとうございます。ウェディングチームが日程を確認し、まもなくご連絡します。まだ確定ではなく、お支払いも発生していません。',
                'next' => 'お問い合わせの際は予約番号をお知らせください。',
            ],
            'th' => [
                'subject' => 'เราได้รับคำขอวันจัดงานแต่งงานของคุณแล้ว %s',
                'greeting' => 'สวัสดีค่ะ คุณ%s',
                'intro' => 'ขอบคุณที่เลือก %s เป็นสถานที่สำหรับวันสำคัญของคุณ ทีมงานแต่งงานของเราจะตรวจสอบวันที่และตอบกลับโดยเร็วที่สุด ขณะนี้ยังไม่ใช่การยืนยัน และยังไม่มีการเรียกเก็บเงินใด ๆ',
                'next' => 'โปรดเก็บหมายเลขอ้างอิงนี้ไว้ เพื่อใช้เมื่อติดต่อเรา',
            ],
            'zh' => [
                'subject' => '我們已收到您的婚禮日期申請 %s',
                'greeting' => '%s 您好，',
                'intro' => '感謝您選擇 %s 作為您的大喜之日場地。我們的婚禮團隊會確認該日期，並盡快回覆您。目前尚未確認，也尚未收取任何款項。',
                'next' => '與我們聯繫時，請備妥您的預約編號。',
            ],
            'id' => [
                'subject' => 'Pengajuan tanggal pernikahan %s kami terima',
                'greeting' => 'Halo %s,',
                'intro' => 'Terima kasih telah memilih %s untuk hari bahagia Anda. Tim wedding kami akan memeriksa tanggalnya dan segera membalas. Ini belum konfirmasi dan belum ada pembayaran yang diambil.',
                'next' => 'Simpan nomor referensi ini bila Anda menghubungi kami.',
            ],
        ][$locale] ?? $this->copy('en');
    }
}
