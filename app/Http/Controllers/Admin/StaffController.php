<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentVenue;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueUser;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * The people who can sign in to this venue's admin panel. Only the owner manages them.
 */
class StaffController extends Controller
{
    use ResolvesCurrentVenue;

    public function __construct(private readonly ActivityLogger $activity) {}

    public function index(Request $request): View
    {
        $venue = $this->ownedVenue($request);

        return view('admin.staff.index', [
            'venue' => $venue,
            'members' => $venue->users()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $venue = $this->ownedVenue($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
            'role' => ['required', Rule::in([VenueUser::ROLE_OWNER, VenueUser::ROLE_STAFF])],
        ], [
            'email.unique' => 'Email itu sudah terdaftar.',
        ]);

        $member = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        $venue->users()->attach($member->id, ['role' => $data['role'], 'status' => 'active']);

        $this->activity->record($venue, $request->user(), 'staff.created', "Menambahkan {$member->name} ({$member->email}) sebagai ".$this->roleLabel($data['role']).'.', $member);

        return redirect()->route('admin.staff.index')->with('status', "{$member->name} ditambahkan ke tim.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $venue = $this->ownedVenue($request);
        $member = $this->member($venue, $user);

        if ($this->isSelf($request, $user)) {
            return back()->with('error', 'Anda tidak bisa mengubah akun Anda sendiri di sini.');
        }

        $data = $request->validate([
            'role' => ['required', Rule::in([VenueUser::ROLE_OWNER, VenueUser::ROLE_STAFF])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $venue->users()->updateExistingPivot($member->id, $data);

        $this->activity->record($venue, $request->user(), 'staff.updated', "Mengubah {$member->name}: ".$this->roleLabel($data['role']).', '.($data['status'] === 'active' ? 'aktif' : 'nonaktif').'.', $member);

        return redirect()->route('admin.staff.index')->with('status', "{$member->name} diperbarui.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $venue = $this->ownedVenue($request);
        $member = $this->member($venue, $user);

        if ($this->isSelf($request, $user)) {
            return back()->with('error', 'Anda tidak bisa mengeluarkan diri sendiri dari tim.');
        }

        $venue->users()->detach($member->id);

        $this->activity->record($venue, $request->user(), 'staff.removed', "Mengeluarkan {$member->name} ({$member->email}) dari tim.", $member);

        return redirect()->route('admin.staff.index')->with('status', "{$member->name} dikeluarkan dari tim.");
    }

    private function ownedVenue(Request $request): Venue
    {
        $venue = $this->currentVenue($request);

        abort_unless($venue->pivot?->role === VenueUser::ROLE_OWNER, 403, 'Only the owner can manage the team.');

        return $venue;
    }

    private function member(Venue $venue, User $user): User
    {
        return $venue->users()->whereKey($user->id)->firstOrFail();
    }

    private function isSelf(Request $request, User $user): bool
    {
        return $request->user()->is($user);
    }

    private function roleLabel(string $role): string
    {
        return $role === VenueUser::ROLE_OWNER ? 'pemilik' : 'staf';
    }
}
