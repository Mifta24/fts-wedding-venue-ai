<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reference',
    'venue_id',
    'hall_id',
    'conversation_id',
    'client_name',
    'client_email',
    'client_phone',
    'contact_type',
    'locale',
    'event_date',
    'event_type',
    'guest_count',
    'extra_hours',
    'total_price',
    'deposit_amount',
    'status',
    'notes',
])]
class Booking extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const EVENT_AKAD = 'akad';

    public const EVENT_RECEPTION = 'reception';

    public const EVENT_AKAD_RECEPTION = 'akad_reception';

    public const EVENT_ENGAGEMENT = 'engagement';

    public const EVENT_TYPES = [self::EVENT_AKAD, self::EVENT_RECEPTION, self::EVENT_AKAD_RECEPTION, self::EVENT_ENGAGEMENT];

    /**
     * Which status a booking may move to from its current one. Cancelled is
     * final: its date went back to the inventory and is not held any more.
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_CANCELLED],
        self::STATUS_CANCELLED => [],
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'guest_count' => 'integer',
            'extra_hours' => 'integer',
            'total_price' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function hall(): BelongsTo
    {
        return $this->belongsTo(Hall::class)->withTrashed();
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * A short, unambiguous, non-sequential code guests can quote to staff.
     */
    public static function generateReference(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $reference = 'WD-'.collect(range(1, 6))
                ->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])
                ->implode('');
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }
}
