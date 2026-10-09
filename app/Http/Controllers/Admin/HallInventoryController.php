<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentVenue;
use App\Http\Controllers\Controller;
use App\Models\Hall;
use App\Models\HallInventory;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Where staff open dates for weddings and set what a hall costs per event. The
 * concierge and the reservation wizard only ever read this table.
 */
class HallInventoryController extends Controller
{
    use ResolvesCurrentVenue;

    /** The longest range one submit may open, so a typo cannot create years of rows. */
    private const MAX_RANGE_DAYS = 366;

    public function index(Request $request, Hall $hall): View
    {
        $venue = $this->currentVenue($request);
        abort_if($hall->venue_id !== $venue->id, 404);

        $month = $this->month($request, $venue->timezone);

        $inventory = $hall->inventory()
            ->whereDate('event_date', '>=', $month->toDateString())
            ->whereDate('event_date', '<=', $month->endOfMonth()->toDateString())
            ->get()
            ->keyBy(fn (HallInventory $day) => $day->event_date->toDateString());

        return view('admin.halls.inventory', [
            'venue' => $venue,
            'hall' => $hall,
            'month' => $month,
            'days' => CarbonPeriod::create($month, $month->endOfMonth()),
            'inventory' => $inventory,
            'today' => now($venue->timezone)->toDateString(),
        ]);
    }

    public function store(Request $request, Hall $hall): RedirectResponse
    {
        $venue = $this->currentVenue($request);
        abort_if($hall->venue_id !== $venue->id, 404);

        $today = now($venue->timezone)->toDateString();

        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'total_slots' => ['required', 'integer', 'min:0', 'max:50'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ]);

        if (CarbonImmutable::parse($data['from'])->diffInDays($data['to']) >= self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages(['to' => 'Open at most '.self::MAX_RANGE_DAYS.' days at a time.']);
        }

        $range = CarbonPeriod::create($data['from'], $data['to']);

        DB::transaction(function () use ($hall, $data, $range) {
            $existing = $hall->inventory()
                ->whereDate('event_date', '>=', $data['from'])
                ->whereDate('event_date', '<=', $data['to'])
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (HallInventory $day) => $day->event_date->toDateString());

            if ($existing->contains(fn (HallInventory $day) => $day->booked_slots > $data['total_slots'])) {
                throw ValidationException::withMessages([
                    'total_slots' => 'Some dates in that range already have more events booked than this. Cancel those bookings first, or choose a higher number.',
                ]);
            }

            foreach ($range as $date) {
                $day = $existing->get($date->toDateString());

                if ($day) {
                    $day->update([
                        'total_slots' => $data['total_slots'],
                        'price' => $data['price'] ?? $day->price,
                    ]);

                    continue;
                }

                $hall->inventory()->create([
                    'event_date' => $date->toDateString(),
                    'total_slots' => $data['total_slots'],
                    'booked_slots' => 0,
                    'price' => $data['price'] ?? $hall->base_price,
                ]);
            }
        });

        return redirect()
            ->route('admin.halls.inventory.index', [$hall, 'month' => substr($data['from'], 0, 7)])
            ->with('status', "Calendar for \"{$hall->name}\" updated from {$data['from']} to {$data['to']}.");
    }

    private function month(Request $request, string $timezone): CarbonImmutable
    {
        $requested = $request->query('month');

        if (is_string($requested) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $requested)) {
            return CarbonImmutable::createFromFormat('Y-m-d', $requested.'-01', $timezone)->startOfDay();
        }

        return now($timezone)->toImmutable()->startOfMonth();
    }
}
