<?php

namespace App\Models\Concerns;

use App\Services\VenueContentCache;

/**
 * Drops the venue's cached public content whenever this model is saved,
 * deleted or restored.
 */
trait FlushesVenueContentCache
{
    public static function bootFlushesVenueContentCache(): void
    {
        $flush = function ($model): void {
            $venueId = $model->venueIdForContentCache();

            if ($venueId !== null) {
                app(VenueContentCache::class)->forget($venueId);
            }
        };

        static::saved($flush);
        static::deleted($flush);

        if (method_exists(static::class, 'restored')) {
            static::restored($flush);
        }
    }

    public function venueIdForContentCache(): ?int
    {
        return $this->venue_id;
    }
}
