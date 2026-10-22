<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Services\Reservation\ReservationHandover;

/**
 * The few lines every client email repeats about the event, in the client's language.
 */
class ClientBookingSummary
{
    /**
     * @return list<string>
     */
    public static function lines(Booking $booking, string $locale): array
    {
        $booking->loadMissing(['venue', 'hall']);

        $labels = match ($locale) {
            'ja' => ['予約番号', '会場', '挙式日', 'ご希望の式', 'ゲスト人数', '合計', '手付金'],
            'id' => ['Referensi', 'Hall', 'Tanggal acara', 'Acara', 'Jumlah tamu', 'Total', 'Uang muka'],
            default => ['Reference', 'Hall', 'Event date', 'Event', 'Guests', 'Total', 'Down payment'],
        };

        $dateFormat = $locale === 'ja' ? 'Y年n月j日 (D)' : 'l, j F Y';
        $money = fn ($value) => $booking->venue->currency.' '.number_format((float) $value, 0, ',', '.');

        return [
            "{$labels[0]}: {$booking->reference}",
            "{$labels[1]}: {$booking->hall->translatedName($locale)}",
            "{$labels[2]}: ".$booking->event_date->locale($locale)->translatedFormat($dateFormat),
            "{$labels[3]}: ".ReservationHandover::eventTypeLabel($booking->event_type, $locale),
            "{$labels[4]}: {$booking->guest_count}",
            "{$labels[5]}: ".$money($booking->total_price),
            "{$labels[6]}: ".$money($booking->deposit_amount),
        ];
    }
}
