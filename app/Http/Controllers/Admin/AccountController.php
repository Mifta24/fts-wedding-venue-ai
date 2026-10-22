<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentVenue;
use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * The signed-in staff member's own account: changing their password.
 */
class AccountController extends Controller
{
    use ResolvesCurrentVenue;

    public function __construct(private readonly ActivityLogger $activity) {}

    public function edit(Request $request): View
    {
        return view('admin.account.edit', ['user' => $request->user()]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $venue = $this->currentVenue($request);

        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8), 'different:current_password'],
        ], [
            'current_password.current_password' => 'Kata sandi saat ini salah.',
            'password.different' => 'Kata sandi baru harus berbeda dari yang lama.',
        ]);

        $request->user()->update(['password' => $data['password']]);

        $this->activity->record($venue, $request->user(), 'account.password_changed', 'Mengganti kata sandi sendiri.', $request->user());

        return redirect()->route('admin.account.edit')->with('status', 'Kata sandi diperbarui.');
    }
}
