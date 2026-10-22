<?php

namespace App\Services;

use App\Models\Venue;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Caches the venue content that public pages read on every visit (halls with
 * their photos, services, information). Each venue has a version token that
 * is part of every cache key, so replacing the token drops all of that
 * venue's entries at once — no need to know which keys exist.
 */
class VenueContentCache
{
    /** Safety net in case an edit slips past the invalidation hooks. */
    private const TTL_SECONDS = 3600;

    public function remember(Venue $venue, string $name, Closure $callback): mixed
    {
        return Cache::remember($this->key($venue->id, $name), self::TTL_SECONDS, $callback);
    }

    public function forget(int $venueId): void
    {
        Cache::forever($this->versionKey($venueId), Str::random(16));
    }

    private function key(int $venueId, string $name): string
    {
        return "venue-content:{$venueId}:{$this->version($venueId)}:{$name}";
    }

    private function version(int $venueId): string
    {
        return Cache::rememberForever($this->versionKey($venueId), fn () => Str::random(16));
    }

    private function versionKey(int $venueId): string
    {
        return "venue-content-version:{$venueId}";
    }
}
