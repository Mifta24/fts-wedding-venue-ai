<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a client that the team confirmed or cancelled their date request.
 */
class BookingStatusChanged extends Notification implements ShouldQueue
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
        $copy = $this->copy($locale)[$booking->status];

        return (new MailMessage)
            ->subject(sprintf($copy['subject'], $booking->reference))
            ->greeting(sprintf($copy['greeting'], $booking->client_name))
            ->line(sprintf($copy['intro'], $booking->venue->name))
            ->lines(ClientBookingSummary::lines($booking, $locale))
            ->line($copy['next'])
            ->salutation($booking->venue->name);
    }

    /**
     * @return array<string, array{subject: string, greeting: string, intro: string, next: string}>
     */
    private function copy(string $locale): array
    {
        return [
            'en' => [
                Booking::STATUS_CONFIRMED => [
                    'subject' => 'Your wedding date %s is confirmed',
                    'greeting' => 'Hello %s,',
                    'intro' => 'Congratulations! %s has confirmed your wedding date request.',
                    'next' => 'Our team will contact you about the down payment, the walkthrough and the event rundown.',
                ],
                Booking::STATUS_CANCELLED => [
                    'subject' => 'Your date request %s was cancelled',
                    'greeting' => 'Hello %s,',
                    'intro' => 'We are sorry: %s could not keep this date request.',
                    'next' => 'Please contact us if you would like another date or another hall.',
                ],
            ],
            'ja' => [
                Booking::STATUS_CONFIRMED => [
                    'subject' => '挙式日が確定しました %s',
                    'greeting' => '%s 様',
                    'intro' => 'おめでとうございます。%s が挙式日のリクエストを確定しました。',
                    'next' => '手付金、会場の下見、当日の進行について、スタッフよりご連絡します。',
                ],
                Booking::STATUS_CANCELLED => [
                    'subject' => '挙式日のリクエストはキャンセルされました %s',
                    'greeting' => '%s 様',
                    'intro' => '申し訳ありません。%s ではこのリクエストをお受けできませんでした。',
                    'next' => '別の日程や会場をご希望の場合はお問い合わせください。',
                ],
            ],
            'th' => [
                Booking::STATUS_CONFIRMED => [
                    'subject' => 'วันจัดงานแต่งงานของคุณได้รับการยืนยันแล้ว %s',
                    'greeting' => 'สวัสดีค่ะ คุณ%s',
                    'intro' => 'ขอแสดงความยินดีด้วยค่ะ! %s ได้ยืนยันคำขอวันจัดงานแต่งงานของคุณแล้ว',
                    'next' => 'ทีมงานของเราจะติดต่อคุณเกี่ยวกับเงินมัดจำ การเข้าชมสถานที่ และกำหนดการของงาน',
                ],
                Booking::STATUS_CANCELLED => [
                    'subject' => 'คำขอวันจัดงานของคุณถูกยกเลิก %s',
                    'greeting' => 'สวัสดีค่ะ คุณ%s',
                    'intro' => 'ขออภัยเป็นอย่างยิ่งค่ะ %s ไม่สามารถดำเนินการตามคำขอวันจัดงานนี้ต่อได้',
                    'next' => 'หากต้องการเลือกวันอื่นหรือฮอลล์อื่น โปรดติดต่อเราได้เลยค่ะ',
                ],
            ],
            'zh' => [
                Booking::STATUS_CONFIRMED => [
                    'subject' => '您的婚禮日期已確認 %s',
                    'greeting' => '%s 您好，',
                    'intro' => '恭喜您！%s 已確認您的婚禮日期申請。',
                    'next' => '我們的團隊將與您聯繫，說明訂金、場地參觀及活動流程等事宜。',
                ],
                Booking::STATUS_CANCELLED => [
                    'subject' => '您的日期申請已取消 %s',
                    'greeting' => '%s 您好，',
                    'intro' => '非常抱歉，%s 無法保留這項日期申請。',
                    'next' => '若您希望更換日期或其他宴會廳，歡迎與我們聯繫。',
                ],
            ],
            'id' => [
                Booking::STATUS_CONFIRMED => [
                    'subject' => 'Tanggal pernikahan %s dikonfirmasi',
                    'greeting' => 'Halo %s,',
                    'intro' => 'Selamat! %s telah mengonfirmasi pengajuan tanggal pernikahan Anda.',
                    'next' => 'Tim kami akan menghubungi Anda soal uang muka, survei lokasi, dan rundown acara.',
                ],
                Booking::STATUS_CANCELLED => [
                    'subject' => 'Pengajuan tanggal %s dibatalkan',
                    'greeting' => 'Halo %s,',
                    'intro' => 'Mohon maaf, %s tidak dapat melanjutkan pengajuan tanggal ini.',
                    'next' => 'Hubungi kami bila Anda ingin tanggal atau hall lain.',
                ],
            ],
        ][$locale] ?? $this->copy('en');
    }
}
