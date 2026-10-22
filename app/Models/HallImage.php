<?php

namespace App\Models;

use App\Models\Concerns\FlushesVenueContentCache;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'hall_id',
    'image_path',
    'image_url',
    'tags',
    'alt_text',
    'sort_order',
])]
class HallImage extends Model
{
    use FlushesVenueContentCache;

    public function venueIdForContentCache(): ?int
    {
        return Hall::withTrashed()->whereKey($this->hall_id)->value('venue_id');
    }

    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }

    public function hall(): BelongsTo
    {
        return $this->belongsTo(Hall::class);
    }

    public function getImageSourceAttribute(): ?string
    {
        if ($this->image_url) {
            return $this->image_url;
        }

        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags ?? [], true);
    }
}
