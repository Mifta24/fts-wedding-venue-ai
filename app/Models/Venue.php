<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'slug',
    'custom_domain',
    'description',
    'translations',
    'address',
    'city',
    'country',
    'latitude',
    'longitude',
    'phone',
    'whatsapp',
    'email',
    'timezone',
    'currency',
    'default_locale',
    'event_start_time',
    'event_end_time',
    'weekday_discount_percent',
    'deposit_percent',
    'logo_path',
    'cover_path',
    'public_status',
])]
class Venue extends Model
{
    use HasFactory, SoftDeletes;

    /** Events on these ISO weekdays (Monday to Thursday) earn the weekday discount. */
    public const WEEKDAY_DISCOUNT_DAYS = [1, 2, 3, 4];

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'weekday_discount_percent' => 'integer',
            'deposit_percent' => 'integer',
        ];
    }

    /**
     * The weekday discount an event on this date earns, in percent.
     */
    public function weekdayDiscountPercent(CarbonInterface $eventDate): int
    {
        return in_array($eventDate->dayOfWeekIso, self::WEEKDAY_DISCOUNT_DAYS, true)
            ? $this->weekday_discount_percent
            : 0;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'venue_users')
            ->using(VenueUser::class)
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    /**
     * Sends a notification to every active member of the venue team.
     */
    public function notifyStaff(Notification $notification): void
    {
        NotificationFacade::send($this->users()->wherePivot('status', 'active')->get(), $notification);
    }

    public function halls(): HasMany
    {
        return $this->hasMany(Hall::class);
    }

    public function knowledgeItems(): HasMany
    {
        return $this->hasMany(VenueKnowledgeItem::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function translatedDescription(string $locale): ?string
    {
        $value = $this->translations[$locale]['description'] ?? null;

        return filled($value) ? $value : $this->description;
    }

    public function isPublished(): bool
    {
        return $this->public_status === 'published';
    }

    public function publicUrl(): string
    {
        return $this->custom_domain
            ? 'https://'.$this->custom_domain
            : url('/'.$this->slug);
    }

    public static function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'venue';
        $slug = $base;
        $suffix = 1;

        while (static::withTrashed()->where('slug', $slug)->exists() || in_array($slug, static::reservedSlugs(), true)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    public static function reservedSlugs(): array
    {
        return [
            'admin', 'login', 'logout', 'register', 'pricing', 'dashboard',
            'api', 'terms', 'privacy', 'forgot-password', 'reset-password',
            'verify-email', 'confirm-password', 'storage', 'build', 'account',
        ];
    }
}
