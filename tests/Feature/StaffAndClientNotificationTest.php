<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Hall;
use App\Models\HallInventory;
use App\Models\HandoverRequest;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\BookingRequestReceived;
use App\Notifications\BookingStatusChanged;
use App\Notifications\NewBookingRequest;
use App\Notifications\NewHandoverRequest;
use App\Services\Concierge\VenueConciergeTools;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffAndClientNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    private Hall $hall;

    private User $owner;

    private User $inactiveStaff;

    private CarbonImmutable $eventDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'default_locale' => 'en']);
        $this->hall = $this->venue->halls()->create(['name' => 'Studio', 'slug' => 'studio', 'translations' => ['ja' => ['name' => 'スタジオ']], 'base_price' => 500000, 'is_active' => true]);

        $this->owner = User::factory()->create();
        $this->inactiveStaff = User::factory()->create();
        $this->venue->users()->attach($this->owner->id, ['role' => 'owner', 'status' => 'active']);
        $this->venue->users()->attach($this->inactiveStaff->id, ['role' => 'staff', 'status' => 'suspended']);

        $this->eventDate = CarbonImmutable::now($this->venue->timezone)->addDays(5)->startOfDay();
        HallInventory::create(['hall_id' => $this->hall->id, 'event_date' => $this->eventDate->toDateString(), 'total_slots' => 1, 'booked_slots' => 0, 'price' => 500000]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function reservation(array $overrides = []): array
    {
        return [
            'hall_slug' => 'studio', 'event_date' => $this->eventDate->toDateString(), 'event_type' => 'akad_reception',
            'guests' => 80, 'locale' => 'id', 'client_name' => 'Ayu', 'contact_type' => 'email', 'contact_value' => 'ayu@example.test',
            ...$overrides,
        ];
    }

    public function test_a_date_request_notifies_active_staff_and_acknowledges_a_client_who_left_an_email(): void
    {
        Notification::fake();

        $this->postJson('/demo/reservation', $this->reservation())->assertCreated();

        $booking = Booking::firstOrFail();
        Notification::assertSentTo($this->owner, NewBookingRequest::class, fn ($notification) => $notification->booking->is($booking));
        Notification::assertNotSentTo($this->inactiveStaff, NewBookingRequest::class);
        Notification::assertSentOnDemand(BookingRequestReceived::class, fn ($notification, $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'ayu@example.test');
        $this->assertSame('id', $booking->locale);
    }

    public function test_a_client_who_chose_whatsapp_gets_no_email_but_staff_are_still_told(): void
    {
        Notification::fake();

        $this->postJson('/demo/reservation', $this->reservation(['contact_type' => 'whatsapp', 'contact_value' => '+62 812 0000 1111']))->assertCreated();

        Notification::assertSentTo($this->owner, NewBookingRequest::class);
        Notification::assertNothingSentTo(new AnonymousNotifiable);
        Notification::assertSentOnDemandTimes(BookingRequestReceived::class, 0);
    }

    public function test_a_request_for_a_taken_date_notifies_nobody(): void
    {
        Notification::fake();

        HallInventory::query()->update(['booked_slots' => 1]);

        $this->postJson('/demo/reservation', $this->reservation())->assertUnprocessable();

        Notification::assertNothingSent();
    }

    public function test_the_concierge_booking_tool_notifies_staff_and_remembers_the_client_language(): void
    {
        Notification::fake();
        $conversation = Conversation::create(['venue_id' => $this->venue->id, 'client_token' => (string) Str::uuid(), 'locale' => 'ja']);

        (new VenueConciergeTools($this->venue, $conversation, 'ja'))->dispatch('create_booking_request', [
            'hall_slug' => 'studio', 'event_date' => $this->eventDate->toDateString(), 'guests' => 60, 'client_name' => 'Aki', 'client_phone' => '+81 90 0000 0000', 'client_email' => 'aki@example.test',
        ]);

        Notification::assertSentTo($this->owner, NewBookingRequest::class);
        Notification::assertSentOnDemand(BookingRequestReceived::class, fn ($notification, $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'aki@example.test');
        $this->assertSame('ja', Booking::firstOrFail()->locale);
    }

    public function test_a_handover_notifies_active_staff_with_a_link_to_the_conversation(): void
    {
        Notification::fake();
        $conversation = Conversation::create(['venue_id' => $this->venue->id, 'client_token' => (string) Str::uuid(), 'locale' => 'en']);

        (new VenueConciergeTools($this->venue, $conversation, 'en'))->dispatch('request_human_handover', ['reason' => HandoverRequest::REASON_SPECIAL_REQUEST, 'summary' => 'Wants an outside caterer']);

        $handover = HandoverRequest::firstOrFail();
        Notification::assertSentTo($this->owner, NewHandoverRequest::class, fn ($notification) => $notification->handover->is($handover));
        Notification::assertNotSentTo($this->inactiveStaff, NewHandoverRequest::class);

        $mail = (new NewHandoverRequest($handover))->toMail($this->owner);
        $this->assertStringContainsString('Wants an outside caterer', implode(' ', $mail->introLines));
        $this->assertSame(route('admin.handovers.show', $handover), $mail->actionUrl);
    }

    public function test_the_client_emails_are_written_in_the_language_the_client_used(): void
    {
        $booking = Booking::create([
            'reference' => 'WD-TEST01', 'venue_id' => $this->venue->id, 'hall_id' => $this->hall->id, 'client_name' => 'Aki',
            'client_email' => 'aki@example.test', 'event_date' => $this->eventDate, 'event_type' => 'akad_reception', 'guest_count' => 80,
            'total_price' => 10000000, 'deposit_amount' => 3000000, 'status' => Booking::STATUS_CONFIRMED, 'locale' => 'ja',
        ]);

        $confirmed = (new BookingStatusChanged($booking))->toMail(new AnonymousNotifiable);
        $this->assertSame('挙式日が確定しました WD-TEST01', $confirmed->subject);
        $this->assertContains('会場: スタジオ', $confirmed->introLines);
        $this->assertContains('ご希望の式: 挙式＋披露宴', $confirmed->introLines);
        $this->assertContains('ゲスト人数: 80', $confirmed->introLines);
        $this->assertContains('手付金: IDR 3.000.000', $confirmed->introLines);
        $this->assertTrue(collect($confirmed->introLines)->contains(fn (string $line) => str_starts_with($line, '挙式日: '.$this->eventDate->year.'年'.$this->eventDate->month.'月'.$this->eventDate->day.'日')));

        $booking->update(['status' => Booking::STATUS_CANCELLED, 'locale' => 'id']);
        $this->assertSame('Pengajuan tanggal WD-TEST01 dibatalkan', (new BookingStatusChanged($booking))->toMail(new AnonymousNotifiable)->subject);

        $booking->update(['locale' => null]);
        $this->assertSame('Your date request WD-TEST01 was cancelled', (new BookingStatusChanged($booking))->toMail(new AnonymousNotifiable)->subject);
    }
}
