<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\HandoverRequest;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    private Venue $otherVenue;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $this->otherVenue = Venue::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);

        $this->staff = User::factory()->create();
        $this->venue->users()->attach($this->staff->id, ['role' => 'owner', 'status' => 'active']);
    }

    private function foreignBooking(): Booking
    {
        $hall = $this->otherVenue->halls()->create(['name' => 'Foreign', 'slug' => 'foreign', 'base_price' => 1, 'is_active' => true]);

        return Booking::create([
            'reference' => 'WD-FOREIGN', 'venue_id' => $this->otherVenue->id, 'hall_id' => $hall->id, 'client_name' => 'Foreign Client',
            'event_date' => now()->addDays(3)->toDateString(), 'guest_count' => 1, 'total_price' => 1,
        ]);
    }

    private function foreignHandover(): HandoverRequest
    {
        $conversation = Conversation::create(['venue_id' => $this->otherVenue->id, 'client_token' => (string) Str::uuid(), 'locale' => 'en']);

        return HandoverRequest::create(['conversation_id' => $conversation->id, 'reason' => 'complaint', 'summary' => 'Foreign complaint']);
    }

    public function test_visitors_are_sent_to_the_login_page_for_every_admin_screen(): void
    {
        foreach (['admin.dashboard', 'admin.halls.index', 'admin.knowledge-items.index', 'admin.bookings.index', 'admin.handovers.index'] as $route) {
            $this->get(route($route))->assertRedirect(route('admin.login'));
        }
    }

    public function test_staff_only_see_bookings_of_their_own_venue(): void
    {
        $this->foreignBooking();

        $this->actingAs($this->staff)->get(route('admin.bookings.index'))->assertOk()->assertDontSee('BK-FOREIGN')->assertDontSee('Foreign Client');
    }

    public function test_staff_cannot_change_another_venues_booking(): void
    {
        $booking = $this->foreignBooking();

        $this->actingAs($this->staff)
            ->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_CONFIRMED])
            ->assertNotFound();

        $this->assertSame(Booking::STATUS_PENDING, $booking->fresh()->status);
    }

    public function test_staff_cannot_read_reply_to_or_resolve_another_venues_handover(): void
    {
        $handover = $this->foreignHandover();

        $this->actingAs($this->staff)->get(route('admin.handovers.show', $handover))->assertNotFound();
        $this->actingAs($this->staff)->post(route('admin.handovers.reply', $handover), ['message' => 'Hello'])->assertNotFound();
        $this->actingAs($this->staff)->post(route('admin.handovers.resolve', $handover))->assertNotFound();

        $this->assertSame(0, ConversationMessage::count());
        $this->assertSame(HandoverRequest::STATUS_OPEN, $handover->fresh()->status);
    }

    public function test_staff_reply_reaches_the_client_and_resolving_returns_the_conversation_to_the_ai(): void
    {
        $conversation = Conversation::create(['venue_id' => $this->venue->id, 'client_token' => (string) Str::uuid(), 'locale' => 'en', 'status' => Conversation::STATUS_HANDED_OVER]);
        $handover = HandoverRequest::create(['conversation_id' => $conversation->id, 'reason' => 'complaint', 'summary' => 'Noisy hall']);

        $this->actingAs($this->staff)->post(route('admin.handovers.reply', $handover), ['message' => 'We are on it.'])->assertRedirect();
        $this->assertSame('We are on it.', $conversation->messages()->where('role', ConversationMessage::ROLE_STAFF)->value('content'));

        $this->actingAs($this->staff)->post(route('admin.handovers.resolve', $handover))->assertRedirect(route('admin.handovers.index'));
        $this->assertSame(Conversation::STATUS_ACTIVE, $conversation->fresh()->status);
        $this->assertSame(HandoverRequest::STATUS_RESOLVED, $handover->fresh()->status);
    }

    public function test_admin_login_is_rate_limited(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post(route('admin.login.store'), ['email' => $this->staff->email, 'password' => 'wrong'])->assertSessionHasErrors();
        }

        $this->post(route('admin.login.store'), ['email' => $this->staff->email, 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_staff_can_give_a_service_a_photo_and_only_a_real_url_is_accepted(): void
    {
        $item = $this->venue->knowledgeItems()->create(['category' => 'services', 'title' => 'Garden pool', 'body' => 'Open until 8pm.', 'is_active' => true]);
        $form = ['category' => 'services', 'title' => 'Garden pool', 'body' => 'Open until 8pm.', 'is_active' => 1];

        $this->actingAs($this->staff)
            ->put(route('admin.knowledge-items.update', $item), [...$form, 'image_url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('image_url');

        $this->actingAs($this->staff)
            ->put(route('admin.knowledge-items.update', $item), [...$form, 'image_url' => 'ftp://example.test/pool.jpg'])
            ->assertSessionHasErrors('image_url');

        $this->actingAs($this->staff)
            ->put(route('admin.knowledge-items.update', $item), [...$form, 'image_url' => 'https://example.test/pool.jpg'])
            ->assertSessionHasNoErrors();

        $this->assertSame('https://example.test/pool.jpg', $item->fresh()->image_url);
    }
}
