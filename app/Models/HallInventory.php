<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The only source of truth for which dates a hall is open and what it costs.
 * Stands in for a real calendar/booking-engine adapter for V1 — the AI must
 * reach this table (or its future adapter) through a tool call, never answer
 * from its own memory.
 */
#[Fillable([
    'hall_id',
    'event_date',
    'total_slots',
    'booked_slots',
    'price',
])]
class HallInventory extends Model
{
    protected $table = 'hall_inventory';

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'price' => 'decimal:2',
        ];
    }

    public function hall(): BelongsTo
    {
        return $this->belongsTo(Hall::class);
    }

    public function availableSlots(): int
    {
        return max(0, $this->total_slots - $this->booked_slots);
    }

    public function isAvailable(): bool
    {
        return $this->availableSlots() > 0;
    }
}
