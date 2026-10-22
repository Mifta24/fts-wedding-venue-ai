<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminTeamTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    private User $owner;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $this->owner = User::factory()->create(['name' => 'Rina Owner']);
        $this->staff = User::factory()->create(['name' => 'Budi Staff']);
        $this->venue->users()->attach($this->owner->id, ['role' => 'owner', 'status' => 'active']);
        $this->venue->users()->attach($this->staff->id, ['role' => 'staff', 'status' => 'active']);
    }

    public function test_only_the_owner_can_see_and_manage_the_team_and_the_activity_log(): void
    {
        $this->actingAs($this->owner)->get(route('admin.staff.index'))->assertOk()->assertSee('Budi Staff');
        $this->actingAs($this->owner)->get(route('admin.activity.index'))->assertOk();

        $this->actingAs($this->staff)->get(route('admin.staff.index'))->assertForbidden();
        $this->actingAs($this->staff)->get(route('admin.activity.index'))->assertForbidden();
        $this->actingAs($this->staff)->post(route('admin.staff.store'), ['name' => 'X', 'email' => 'x@example.test', 'password' => 'password123', 'role' => 'owner'])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'x@example.test']);
    }

    public function test_the_owner_adds_a_member_who_can_then_sign_in(): void
    {
        $this->actingAs($this->owner)
            ->post(route('admin.staff.store'), ['name' => 'Sari', 'email' => 'sari@example.test', 'password' => 'secret-pass-1', 'role' => 'staff'])
            ->assertRedirect(route('admin.staff.index'));

        $sari = User::where('email', 'sari@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('secret-pass-1', $sari->password));
        $this->assertSame('staff', $this->venue->users()->whereKey($sari->id)->first()->pivot->role);

        auth()->logout();
        $this->post(route('admin.login.store'), ['email' => 'sari@example.test', 'password' => 'secret-pass-1'])->assertRedirect(route('admin.dashboard'));
    }

    public function test_adding_a_member_validates_the_email_the_password_and_the_role(): void
    {
        $this->actingAs($this->owner)
            ->post(route('admin.staff.store'), ['name' => 'Dup', 'email' => $this->staff->email, 'password' => 'short', 'role' => 'admin'])
            ->assertSessionHasErrors(['email', 'password', 'role']);
    }

    public function test_a_deactivated_member_can_no_longer_sign_in_or_use_an_open_session(): void
    {
        $this->actingAs($this->owner)
            ->put(route('admin.staff.update', $this->staff), ['role' => 'staff', 'status' => 'inactive'])
            ->assertRedirect(route('admin.staff.index'));

        $this->actingAs($this->staff)->get(route('admin.dashboard'))->assertForbidden();

        auth()->logout();
        $this->post(route('admin.login.store'), ['email' => $this->staff->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_the_owner_can_change_a_role_and_remove_a_member_but_not_themselves(): void
    {
        $this->actingAs($this->owner)->put(route('admin.staff.update', $this->staff), ['role' => 'owner', 'status' => 'active']);
        $this->assertSame('owner', $this->venue->users()->whereKey($this->staff->id)->first()->pivot->role);

        $this->actingAs($this->owner)->put(route('admin.staff.update', $this->owner), ['role' => 'staff', 'status' => 'active'])->assertSessionHas('error');
        $this->actingAs($this->owner)->delete(route('admin.staff.destroy', $this->owner))->assertSessionHas('error');
        $this->assertSame('owner', $this->venue->users()->whereKey($this->owner->id)->first()->pivot->role);

        $this->actingAs($this->owner)->delete(route('admin.staff.destroy', $this->staff))->assertRedirect(route('admin.staff.index'));
        $this->assertDatabaseMissing('venue_users', ['user_id' => $this->staff->id]);
    }

    public function test_an_owner_cannot_touch_a_user_of_another_venue(): void
    {
        $other = Venue::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $stranger = User::factory()->create();
        $other->users()->attach($stranger->id, ['role' => 'owner', 'status' => 'active']);

        $this->actingAs($this->owner)->put(route('admin.staff.update', $stranger), ['role' => 'staff', 'status' => 'inactive'])->assertNotFound();
        $this->actingAs($this->owner)->delete(route('admin.staff.destroy', $stranger))->assertNotFound();
        $this->assertSame('owner', $other->users()->whereKey($stranger->id)->first()->pivot->role);
    }

    public function test_a_member_changes_their_own_password_only_with_the_current_one(): void
    {
        $this->actingAs($this->staff)
            ->put(route('admin.account.password'), ['current_password' => 'wrong', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])
            ->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('password', $this->staff->fresh()->password));

        $this->actingAs($this->staff)
            ->put(route('admin.account.password'), ['current_password' => 'password', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])
            ->assertRedirect(route('admin.account.edit'));
        $this->assertTrue(Hash::check('brand-new-pass', $this->staff->fresh()->password));
    }

    public function test_a_forgotten_password_can_be_reset_from_the_emailed_link(): void
    {
        Notification::fake();

        $this->post(route('admin.password.email'), ['email' => $this->staff->email])->assertSessionHas('status');

        Notification::assertSentTo($this->staff, ResetPassword::class, function (ResetPassword $notification) {
            $url = $notification->toMail($this->staff)->actionUrl;
            $this->assertStringContainsString('/admin/reset-password/', $url);

            $this->post(route('admin.password.update'), ['token' => $notification->token, 'email' => $this->staff->email, 'password' => 'after-reset-1', 'password_confirmation' => 'after-reset-1'])
                ->assertRedirect(route('admin.login'));

            return true;
        });

        $this->assertTrue(Hash::check('after-reset-1', $this->staff->fresh()->password));
    }

    public function test_the_reset_form_does_not_reveal_whether_an_address_has_an_account(): void
    {
        Notification::fake();

        $known = $this->post(route('admin.password.email'), ['email' => $this->staff->email]);
        $unknown = $this->post(route('admin.password.email'), ['email' => 'nobody@example.test']);

        $known->assertSessionHas('status');
        $this->assertSame($known->getSession()->get('status'), $unknown->getSession()->get('status'));
        Notification::assertCount(1);
    }

    public function test_a_bad_reset_token_is_refused(): void
    {
        $this->post(route('admin.password.update'), ['token' => 'nope', 'email' => $this->staff->email, 'password' => 'after-reset-1', 'password_confirmation' => 'after-reset-1'])
            ->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password', $this->staff->fresh()->password));
    }

    public function test_admin_actions_are_written_to_the_activity_log_with_who_did_them(): void
    {
        $hall = $this->venue->halls()->create(['name' => 'Garden', 'slug' => 'garden', 'base_price' => 1000, 'is_active' => true]);
        $booking = Booking::create([
            'reference' => 'WD-LOG1', 'venue_id' => $this->venue->id, 'hall_id' => $hall->id, 'client_name' => 'Client',
            'event_date' => now()->addMonth()->toDateString(), 'guest_count' => 10, 'total_price' => 1000,
        ]);

        $this->actingAs($this->staff)->patch(route('admin.bookings.status', $booking), ['status' => 'confirmed']);
        $this->actingAs($this->staff)->post(route('admin.knowledge-items.store'), ['category' => 'general', 'title' => 'Parking', 'body' => 'Free parking.', 'is_active' => 1]);

        $this->assertDatabaseHas('activity_logs', ['venue_id' => $this->venue->id, 'user_id' => $this->staff->id, 'user_name' => 'Budi Staff', 'action' => 'booking.confirmed', 'subject_type' => 'Booking', 'subject_id' => $booking->id]);
        $this->assertDatabaseHas('activity_logs', ['user_name' => 'Budi Staff', 'action' => 'knowledge.created']);

        $this->actingAs($this->owner)->get(route('admin.activity.index'))->assertOk()->assertSee('Budi Staff')->assertSee('WD-LOG1');
    }

    public function test_a_log_entry_keeps_the_name_after_the_account_is_removed_and_a_failed_change_is_not_logged(): void
    {
        $hall = $this->venue->halls()->create(['name' => 'Garden', 'slug' => 'garden', 'base_price' => 1000, 'is_active' => true]);
        $booking = Booking::create([
            'reference' => 'WD-LOG2', 'venue_id' => $this->venue->id, 'hall_id' => $hall->id, 'client_name' => 'Client', 'status' => Booking::STATUS_CANCELLED,
            'event_date' => now()->addMonth()->toDateString(), 'guest_count' => 10, 'total_price' => 1000,
        ]);

        $this->actingAs($this->staff)->patch(route('admin.bookings.status', $booking), ['status' => 'confirmed'])->assertSessionHas('error');
        $this->assertSame(0, ActivityLog::where('action', 'booking.confirmed')->count());

        $this->actingAs($this->owner)->post(route('admin.staff.store'), ['name' => 'Temp', 'email' => 'temp@example.test', 'password' => 'secret-pass-1', 'role' => 'staff']);
        $log = ActivityLog::where('action', 'staff.created')->firstOrFail();
        $this->owner->delete();

        $this->assertNull($log->fresh()->user_id);
        $this->assertSame('Rina Owner', $log->fresh()->user_name);
    }
}
