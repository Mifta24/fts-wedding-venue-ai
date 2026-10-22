<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Who changed what in the admin panel. The user's name is copied into the row
 * so the entry still reads correctly after that account is removed.
 */
#[Fillable([
    'venue_id',
    'user_id',
    'user_name',
    'action',
    'subject_type',
    'subject_id',
    'description',
])]
class ActivityLog extends Model
{
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
