<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the venue team that a client asked to hold a date for their wedding.
 */
class NewBookingRequest extends Notification implements ShouldQueue
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
        $contact = $booking->client_phone ?? $booking->client_email;

        return (new MailMessage)
            ->subject("New wedding date request {$booking->reference} from {$booking->client_name}")
            ->greeting("New date request for {$booking->venue->name}")
            ->line("{$booking->client_name} asked for {$booking->hall->name} on {$booking->event_date->toFormattedDayDateString()}.")
            ->line('Event: '.str_replace('_', ' & ', $booking->event_type).", {$booking->guest_count} guests".($booking->extra_hours ? ", +{$booking->extra_hours} extra hour(s)" : '').'.')
            ->line("Total: {$booking->venue->currency} ".number_format((float) $booking->total_price, 0, ',', '.')." (down payment {$booking->venue->currency} ".number_format((float) $booking->deposit_amount, 0, ',', '.').').')
            ->line("Contact ({$booking->contact_type}): {$contact}")
            ->when($booking->notes, fn (MailMessage $mail) => $mail->line("Note: {$booking->notes}"))
            ->action('Review the request', route('admin.bookings.index', ['status' => Booking::STATUS_PENDING]));
    }
}
