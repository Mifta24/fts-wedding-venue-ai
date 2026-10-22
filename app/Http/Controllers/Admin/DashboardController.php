<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentVenue;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\HallInventory;
use App\Models\HandoverRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use ResolvesCurrentVenue;

    /** How far ahead the booked-dates figure looks. */
    private const BOOKED_WINDOW_DAYS = 30;

    /** How far ahead confirmed events are listed. */
    private const UPCOMING_DAYS = 30;

    /** A pending request older than this counts as a couple left waiting. */
    private const WAITING_HOURS = 24;

    public function index(Request $request): View
    {
        $venue = $this->currentVenue($request);

        $today = now($venue->timezone)->startOfDay();

        $booked = HallInventory::whereIn('hall_id', $venue->halls()->where('is_active', true)->select('id'))
            ->whereDate('event_date', '>=', $today->toDateString())
            ->whereDate('event_date', '<=', $today->copy()->addDays(self::BOOKED_WINDOW_DAYS - 1)->toDateString())
            ->selectRaw('SUM(total_slots) as total, SUM(booked_slots) as booked')
            ->first();

        $stats = [
            'halls' => $venue->halls()->count(),
            'knowledge_items' => $venue->knowledgeItems()->count(),
            'pending_bookings' => $venue->bookings()->where('status', Booking::STATUS_PENDING)->count(),
            'waiting_bookings' => $venue->bookings()
                ->where('status', Booking::STATUS_PENDING)
                ->where('created_at', '<', now()->subHours(self::WAITING_HOURS))
                ->count(),
            'upcoming_events' => $venue->bookings()
                ->where('status', Booking::STATUS_CONFIRMED)
                ->whereDate('event_date', '>=', $today->toDateString())
                ->whereDate('event_date', '<=', $today->copy()->addDays(self::UPCOMING_DAYS - 1)->toDateString())
                ->count(),
            'booked_percent' => $booked?->total > 0 ? (int) round($booked->booked / $booked->total * 100) : null,
            'open_handovers' => HandoverRequest::whereHas('conversation', fn ($q) => $q->where('venue_id', $venue->id))
                ->where('status', HandoverRequest::STATUS_OPEN)
                ->count(),
        ];

        $upcoming = $venue->bookings()
            ->where('status', Booking::STATUS_CONFIRMED)
            ->whereDate('event_date', '>=', $today->toDateString())
            ->whereDate('event_date', '<=', $today->copy()->addDays(self::UPCOMING_DAYS - 1)->toDateString())
            ->with('hall')
            ->orderBy('event_date')
            ->get();

        $recentBookings = $venue->bookings()->latest()->take(5)->with('hall')->get();

        $openHandovers = HandoverRequest::whereHas('conversation', fn ($q) => $q->where('venue_id', $venue->id))
            ->where('status', HandoverRequest::STATUS_OPEN)
            ->latest()
            ->take(5)
            ->with('conversation')
            ->get();

        return view('admin.dashboard', [
            'venue' => $venue,
            'stats' => $stats,
            'upcoming' => $upcoming,
            'recentBookings' => $recentBookings,
            'bookedWindowDays' => self::BOOKED_WINDOW_DAYS,
            'upcomingDays' => self::UPCOMING_DAYS,
            'waitingHours' => self::WAITING_HOURS,
            'openHandovers' => $openHandovers,
        ]);
    }
}
