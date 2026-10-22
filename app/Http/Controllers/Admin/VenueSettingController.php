<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentVenue;
use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Models\VenueUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Contact details, event hours, the weekday discount, the down payment and whether the venue
 * is visible to couples. Only the owner may change them.
 */
class VenueSettingController extends Controller
{
    use ResolvesCurrentVenue;

    public function edit(Request $request): View
    {
        return view('admin.settings.edit', ['venue' => $this->ownedVenue($request)]);
    }

    public function update(Request $request): RedirectResponse
    {
        $venue = $this->ownedVenue($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'translations.en.description' => ['nullable', 'string', 'max:2000'],
            'translations.ja.description' => ['nullable', 'string', 'max:2000'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'timezone' => ['required', 'timezone:all'],
            'default_locale' => ['required', Rule::in(['id', 'en', 'ja'])],
            'event_start_time' => ['required', 'date_format:H:i'],
            'event_end_time' => ['required', 'date_format:H:i', 'after:event_start_time'],
            'weekday_discount_percent' => ['required', 'integer', 'min:0', 'max:90'],
            'deposit_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'public_status' => ['required', Rule::in(['draft', 'published'])],
        ], [
            'event_end_time.after' => 'The event must end after it starts.',
        ]);

        $venue->update([
            ...$data,
            'translations' => [
                ...($venue->translations ?? []),
                'en' => [...($venue->translations['en'] ?? []), 'description' => $data['translations']['en']['description'] ?? null],
                'ja' => [...($venue->translations['ja'] ?? []), 'description' => $data['translations']['ja']['description'] ?? null],
            ],
        ]);

        return redirect()->route('admin.settings.edit')->with('status', 'Venue settings saved.');
    }

    private function ownedVenue(Request $request): Venue
    {
        $venue = $this->currentVenue($request);

        abort_unless($venue->pivot?->role === VenueUser::ROLE_OWNER, 403, 'Only the owner can change venue settings.');

        return $venue;
    }
}
