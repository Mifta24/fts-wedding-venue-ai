<?php

namespace App\Services\Reservation;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Hall;
use App\Models\HallInventory;
use App\Models\Venue;
use App\Notifications\BookingRequestReceived;
use App\Notifications\BookingStatusChanged;
use App\Notifications\NewBookingRequest;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * The single place where event prices, availability and reservation requests
 * are computed. Both the AI concierge tools and the client-facing reservation
 * wizard go through here, so they can never disagree.
 */
class ReservationService
{
    /** The most guests one online request may carry; larger gatherings go to the team. */
    public const MAX_GUESTS = 3000;

    /**
     * Prices one event date, then takes off the venue's weekday discount if it
     * applies. Returns null when the hall is not open on that date or is
     * already taken — this is the one place price and availability truth
     * comes from, never the model.
     *
     * @return array{event_date: string, is_weekday: bool, hall_total: float, extra_hours: int, extra_hours_total: float, subtotal: float, discount_percent: int, discount_total: float, grand_total: float, deposit_percent: int, deposit_total: float}|null
     */
    public function quote(
        Hall $hall,
        CarbonImmutable $eventDate,
        int $extraHours = 0,
        bool $lockForUpdate = false,
    ): ?array {
        $query = HallInventory::where('hall_id', $hall->id)->whereDate('event_date', $eventDate->toDateString());

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $day = $query->first();

        if (! $day || ! $day->isAvailable()) {
            return null;
        }

        $venue = $hall->venue;
        $extraHours = $hall->extra_hour_available ? max(0, min($extraHours, Hall::MAX_EXTRA_HOURS)) : 0;

        $hallTotal = (float) $day->price;
        $extraHoursTotal = (float) $hall->extra_hour_price * $extraHours;
        $subtotal = $hallTotal + $extraHoursTotal;
        $discountPercent = $venue->weekdayDiscountPercent($eventDate);
        $discountTotal = round($subtotal * $discountPercent / 100, 2);
        $grandTotal = $subtotal - $discountTotal;

        return [
            'event_date' => $eventDate->toDateString(),
            'is_weekday' => in_array($eventDate->dayOfWeekIso, Venue::WEEKDAY_DISCOUNT_DAYS, true),
            'hall_total' => $hallTotal,
            'extra_hours' => $extraHours,
            'extra_hours_total' => $extraHoursTotal,
            'subtotal' => $subtotal,
            'discount_percent' => $discountPercent,
            'discount_total' => $discountTotal,
            'grand_total' => $grandTotal,
            'deposit_percent' => $venue->deposit_percent,
            'deposit_total' => round($grandTotal * $venue->deposit_percent / 100, 2),
        ];
    }

    /**
     * Creates a pending reservation request and holds the date for it.
     * Returns null when the hall is no longer available on that date.
     *
     * @param  array{event_date: CarbonImmutable, event_type?: ?string, guest_count: int, extra_hours?: int, client_name: string, client_email?: ?string, client_phone?: ?string, contact_type?: ?string, locale?: ?string, notes?: ?string}  $data
     */
    public function createRequest(Venue $venue, Hall $hall, array $data, ?Conversation $conversation = null): ?Booking
    {
        $booking = DB::transaction(function () use ($venue, $hall, $data, $conversation) {
            $quote = $this->quote($hall, $data['event_date'], (int) ($data['extra_hours'] ?? 0), lockForUpdate: true);

            if (! $quote) {
                return null;
            }

            $booking = Booking::create([
                'reference' => Booking::generateReference(),
                'venue_id' => $venue->id,
                'hall_id' => $hall->id,
                'conversation_id' => $conversation?->id,
                'client_name' => $data['client_name'],
                'client_email' => $data['client_email'] ?? null,
                'client_phone' => $data['client_phone'] ?? null,
                'contact_type' => $data['contact_type'] ?? null,
                'locale' => $data['locale'] ?? null,
                'event_date' => $data['event_date']->toDateString(),
                'event_type' => in_array($data['event_type'] ?? null, Booking::EVENT_TYPES, true) ? $data['event_type'] : Booking::EVENT_AKAD_RECEPTION,
                'guest_count' => $data['guest_count'],
                'extra_hours' => $quote['extra_hours'],
                'total_price' => $quote['grand_total'],
                'deposit_amount' => $quote['deposit_total'],
                'status' => Booking::STATUS_PENDING,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->adjustInventory($booking, +1);

            return $booking;
        });

        if ($booking) {
            $venue->notifyStaff(new NewBookingRequest($booking));
            $this->notifyClient($booking, new BookingRequestReceived($booking));
        }

        return $booking;
    }

    /**
     * Confirms or cancels a booking. Returns false when its current status
     * does not allow the change, so a cancelled booking can never come back
     * without its date being held again. Cancelling frees the date.
     */
    public function changeStatus(Booking $booking, string $status): bool
    {
        $changed = DB::transaction(function () use ($booking, $status) {
            $current = Booking::whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            if (! $current->canTransitionTo($status)) {
                return false;
            }

            if ($status === Booking::STATUS_CANCELLED) {
                $this->releaseInventory($current);
            }

            $current->update(['status' => $status]);

            return true;
        });

        $booking->refresh();

        if ($changed) {
            $this->notifyClient($booking, new BookingStatusChanged($booking));
        }

        return $changed;
    }

    /**
     * Clients who left an email address get a message; those who chose
     * WhatsApp or phone are answered by the team instead.
     */
    private function notifyClient(Booking $booking, Notification $notification): void
    {
        if (filled($booking->client_email)) {
            NotificationFacade::route('mail', $booking->client_email)->notify($notification->locale($booking->locale));
        }
    }

    /**
     * Gives the date held by a booking back to the inventory.
     */
    public function releaseInventory(Booking $booking): void
    {
        $this->adjustInventory($booking, -1);
    }

    private function adjustInventory(Booking $booking, int $direction): void
    {
        HallInventory::where('hall_id', $booking->hall_id)
            ->whereDate('event_date', $booking->event_date->toDateString())
            ->{$direction > 0 ? 'increment' : 'decrement'}('booked_slots');
    }
}
