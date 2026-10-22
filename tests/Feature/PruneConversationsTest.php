<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\HandoverRequest;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PruneConversationsTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
    }

    private function conversation(int $idleDays): Conversation
    {
        $conversation = Conversation::create([
            'venue_id' => $this->venue->id, 'client_token' => (string) Str::uuid(), 'locale' => 'id',
            'last_message_at' => now()->subDays($idleDays),
        ]);
        $conversation->messages()->create(['role' => 'client', 'content' => 'Halo']);

        return $conversation;
    }

    public function test_conversations_idle_past_the_retention_period_are_deleted_with_their_messages(): void
    {
        $old = $this->conversation(120);
        $recent = $this->conversation(10);

        $this->artisan('conversations:prune')->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertDatabaseMissing('conversation_messages', ['conversation_id' => $old->id]);
        $this->assertModelExists($recent);
        $this->assertDatabaseHas('conversation_messages', ['conversation_id' => $recent->id]);
    }

    public function test_the_retention_period_can_be_overridden_per_run(): void
    {
        $conversation = $this->conversation(10);

        $this->artisan('conversations:prune', ['--days' => 7])->assertSuccessful();

        $this->assertModelMissing($conversation);
    }

    public function test_a_conversation_with_an_open_handover_is_kept_but_a_resolved_one_is_not(): void
    {
        $open = $this->conversation(200);
        HandoverRequest::create(['conversation_id' => $open->id, 'reason' => 'complaint', 'summary' => 'Needs a call', 'status' => HandoverRequest::STATUS_OPEN]);
        $resolved = $this->conversation(200);
        HandoverRequest::create(['conversation_id' => $resolved->id, 'reason' => 'complaint', 'summary' => 'Done', 'status' => HandoverRequest::STATUS_RESOLVED]);

        $this->artisan('conversations:prune')->assertSuccessful();

        $this->assertModelExists($open);
        $this->assertModelMissing($resolved);
        $this->assertDatabaseMissing('handover_requests', ['conversation_id' => $resolved->id]);
    }

    public function test_a_booking_survives_when_its_conversation_is_deleted(): void
    {
        $conversation = $this->conversation(200);
        $hall = $this->venue->halls()->create(['name' => 'Hall', 'slug' => 'hall', 'base_price' => 1, 'is_active' => true]);
        $booking = Booking::create([
            'reference' => 'WD-KEEP', 'venue_id' => $this->venue->id, 'hall_id' => $hall->id, 'conversation_id' => $conversation->id,
            'client_name' => 'Rina', 'event_date' => now()->addMonth()->toDateString(), 'guest_count' => 100, 'total_price' => 1,
        ]);

        $this->artisan('conversations:prune')->assertSuccessful();

        $this->assertModelMissing($conversation);
        $this->assertNull($booking->fresh()->conversation_id);
    }

    public function test_a_dry_run_deletes_nothing(): void
    {
        $old = $this->conversation(120);

        $this->artisan('conversations:prune', ['--dry-run' => true])
            ->expectsOutputToContain('1 conversation(s)')
            ->assertSuccessful();

        $this->assertModelExists($old);
    }

    public function test_the_prune_is_scheduled_daily(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('conversations:prune')->assertSuccessful();
    }
}
