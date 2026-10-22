<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'conversation_id',
    'reason',
    'summary',
    'status',
    'assigned_to',
    'resolved_at',
])]
class HandoverRequest extends Model
{
    public const REASON_SPECIAL_REQUEST = 'special_request';

    public const REASON_COMPLAINT = 'complaint';

    public const REASON_CUSTOM_PACKAGE = 'custom_package';

    public const REASON_NEGOTIATED_RATE = 'negotiated_rate';

    public const REASON_RESCHEDULE = 'reschedule';

    public const REASON_PAYMENT_ISSUE = 'payment_issue';

    public const REASON_LOW_CONFIDENCE = 'low_confidence';

    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    /**
     * Indonesian label for a handover reason, as shown in the admin panel.
     */
    public static function reasonLabel(string $reason): string
    {
        return match ($reason) {
            self::REASON_SPECIAL_REQUEST => 'Permintaan khusus',
            self::REASON_COMPLAINT => 'Keluhan',
            self::REASON_CUSTOM_PACKAGE => 'Paket khusus',
            self::REASON_NEGOTIATED_RATE => 'Negosiasi harga',
            self::REASON_RESCHEDULE => 'Jadwal ulang',
            self::REASON_PAYMENT_ISSUE => 'Kendala pembayaran',
            self::REASON_LOW_CONFIDENCE => 'AI kurang yakin',
            default => ucfirst(str_replace('_', ' ', $reason)),
        };
    }

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
