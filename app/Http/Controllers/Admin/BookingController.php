<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentVenue;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Reservation\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
    use ResolvesCurrentVenue;

    public function __construct(private readonly ReservationService $reservations) {}

    public function index(Request $request): View
    {
        $venue = $this->currentVenue($request);

        $status = in_array($request->query('status'), [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED, Booking::STATUS_CANCELLED], true)
            ? $request->query('status')
            : null;

        $bookings = $venue->bookings()
            ->with('hall')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.bookings.index', compact('venue', 'bookings', 'status'));
    }

    public function updateStatus(Request $request, Booking $booking): RedirectResponse
    {
        $venue = $this->currentVenue($request);
        abort_if($booking->venue_id !== $venue->id, 404);

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', [Booking::STATUS_CONFIRMED, Booking::STATUS_CANCELLED])],
        ]);

        if (! $this->reservations->changeStatus($booking, $data['status'])) {
            return back()->with('error', "Booking {$booking->reference} is {$booking->status} and cannot be marked as {$data['status']}.");
        }

        return back()->with('status', "Booking {$booking->reference} marked as {$data['status']}.");
    }
}
