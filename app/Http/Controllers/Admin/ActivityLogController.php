<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentVenue;
use App\Http\Controllers\Controller;
use App\Models\VenueUser;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    use ResolvesCurrentVenue;

    public function index(Request $request): View
    {
        $venue = $this->currentVenue($request);

        abort_unless($venue->pivot?->role === VenueUser::ROLE_OWNER, 403, 'Only the owner can read the activity log.');

        $logs = $venue->activityLogs()->latest('id')->paginate(30);

        return view('admin.activity.index', compact('venue', 'logs'));
    }
}
