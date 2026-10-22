<?php

namespace App\Services;

use App\Models\Venue;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Caches the venue content that public pages read on every visit (halls with
 * their photos, services, information). Each venue has a version token that
 * is part of every cache key, so replacing the token drops all of that
 * venue's entries at once — no need to know which keys exist.
 *
 * Models are stored as plain attribute arrays and rebuilt on the way out:
 * the cache refuses to unserialize objects (cache.serializable_classes), so
 * storing the models themselves would come back as incomplete classes.
 */
class VenueContentCache
{
    /** Safety net in case an edit slips past the invalidation hooks. */
    private const TTL_SECONDS = 3600;

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @param  Closure(): Collection<int, TModel>  $load
     * @return Collection<int, TModel>
     */
    public function rememberModels(Venue $venue, string $name, string $model, Closure $load): Collection
    {
        $rows = Cache::remember($this->key($venue->id, $name), self::TTL_SECONDS, fn () => $load()->map(fn (Model $item) => $this->dehydrate($item))->all());

        return $this->hydrate($model, $rows);
    }

    public function forget(int $venueId): void
    {
        Cache::forever($this->versionKey($venueId), Str::random(16));
    }

    /**
     * @return array{attributes: array<string, mixed>, relations: array<string, array<int, array<string, mixed>>>}
     */
    private function dehydrate(Model $model): array
    {
        $relations = [];

        foreach ($model->getRelations() as $relation => $related) {
            if ($related instanceof Collection) {
                $relations[$relation] = $related->map(fn (Model $item) => $this->dehydrate($item))->all();
            }
        }

        return ['attributes' => $model->getAttributes(), 'relations' => $relations];
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $class
     * @param  array<int, array<string, mixed>>  $rows
     * @return Collection<int, TModel>
     */
    private function hydrate(string $class, array $rows): Collection
    {
        $prototype = new $class;

        return $prototype->newCollection(array_map(function (array $row) use ($prototype) {
            $model = $prototype->newFromBuilder($row['attributes']);

            foreach ($row['relations'] as $relation => $children) {
                $related = $model->{$relation}()->getRelated();
                $model->setRelation($relation, $this->hydrate($related::class, $children));
            }

            return $model;
        }, $rows));
    }

    private function key(int $venueId, string $name): string
    {
        return "venue-content:{$venueId}:{$this->version($venueId)}:{$name}:rows";
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
