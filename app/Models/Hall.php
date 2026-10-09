<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'venue_id',
    'name',
    'slug',
    'description',
    'translations',
    'size_sqm',
    'setting',
    'min_guests',
    'max_guests',
    'seating_styles',
    'view_type',
    'catering_included',
    'extra_hour_available',
    'extra_hour_price',
    'base_price',
    'amenities',
    'is_active',
    'sort_order',
])]
class Hall extends Model
{
    use HasFactory, SoftDeletes;

    public const SETTING_INDOOR = 'indoor';

    public const SETTING_OUTDOOR = 'outdoor';

    public const SETTING_SEMI_OUTDOOR = 'semi_outdoor';

    public const SETTINGS = [self::SETTING_INDOOR, self::SETTING_OUTDOOR, self::SETTING_SEMI_OUTDOOR];

    /** The most overtime hours a request may add to one event. */
    public const MAX_EXTRA_HOURS = 4;

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'min_guests' => 'integer',
            'max_guests' => 'integer',
            'seating_styles' => 'array',
            'amenities' => 'array',
            'catering_included' => 'boolean',
            'extra_hour_available' => 'boolean',
            'extra_hour_price' => 'decimal:2',
            'base_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(HallImage::class)->orderBy('sort_order');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(HallInventory::class);
    }

    public function isOutdoor(): bool
    {
        return $this->setting === self::SETTING_OUTDOOR;
    }

    public function fitsGuests(int $guests): bool
    {
        return $guests >= 1 && $guests <= $this->max_guests;
    }

    public function translatedName(string $locale): string
    {
        $value = $this->translations[$locale]['name'] ?? null;

        return filled($value) ? $value : $this->name;
    }

    public function translatedDescription(string $locale): ?string
    {
        $value = $this->translations[$locale]['description'] ?? null;

        return filled($value) ? $value : $this->description;
    }
}
