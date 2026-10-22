<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    /**
     * Records one admin action against a venue.
     */
    public function record(Venue $venue, ?User $user, string $action, string $description, ?Model $subject = null): ActivityLog
    {
        return ActivityLog::create([
            'venue_id' => $venue->id,
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
        ]);
    }
}
