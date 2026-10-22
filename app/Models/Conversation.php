<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'venue_id',
    'client_token',
    'client_name',
    'client_email',
    'locale',
    'status',
    'handover_summary',
    'current_scene',
    'selected_hall_id',
    'selected_service_id',
    'reservation_state',
    'last_message_at',
])]
class Conversation extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_HANDED_OVER = 'handed_over';

    public const STATUS_CLOSED = 'closed';

    /**
     * The UI scenes the client can be in while talking to the concierge.
     */
    public const SCENES = ['lobby', 'reception', 'halls', 'hall_detail', 'services', 'service_detail', 'reservation', 'handover'];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'reservation_state' => 'array',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function selectedHall(): BelongsTo
    {
        return $this->belongsTo(Hall::class, 'selected_hall_id');
    }

    public function selectedService(): BelongsTo
    {
        return $this->belongsTo(VenueKnowledgeItem::class, 'selected_service_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->orderBy('created_at');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function handoverRequest(): HasOne
    {
        return $this->hasOne(HandoverRequest::class)->latestOfMany();
    }

    public function isHandedOver(): bool
    {
        return $this->status === self::STATUS_HANDED_OVER;
    }
}
