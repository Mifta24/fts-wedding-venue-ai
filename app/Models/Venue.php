<?php

namespace App\Models;

use App\Models\Concerns\FlushesVenueContentCache;
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
    'video_url',
    'catalog_mode',
    'theme',
    'hall_groups',
    'plan_info',
    'access_code_hash',
    'public_status',
])]
class Venue extends Model
{
    use FlushesVenueContentCache, HasFactory, SoftDeletes;

    public function venueIdForContentCache(): ?int
    {
        return $this->id;
    }

    /** Events on these ISO weekdays (Monday to Thursday) earn the weekday discount. */
    public const WEEKDAY_DISCOUNT_DAYS = [1, 2, 3, 4];

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'hall_groups' => 'array',
            'plan_info' => 'array',
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

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
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

    /**
     * The YouTube video id of the venue film, or null when none is set or the
     * link is not a YouTube address. Only the id reaches the page, never the URL.
     */
    public function videoId(): ?string
    {
        $url = trim((string) $this->video_url);

        if ($url === '') {
            return null;
        }

        if (preg_match('~^(?:https?://)?(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})(?:[?&#/].*)?$~', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    /** Reachable by its link. Unlisted venues are not shown in the opening list or the sitemap. */
    public function isPublished(): bool
    {
        return in_array($this->public_status, ['published', 'unlisted'], true);
    }

    /** A venue that sells packages per location instead of renting halls. */
    public function isPlanCatalog(): bool
    {
        return $this->catalog_mode === 'plan';
    }

    /** Visitors must enter the access code before any public page opens. */
    public function isGated(): bool
    {
        return filled($this->access_code_hash);
    }

    public function checkAccessCode(string $code): bool
    {
        return $this->isGated() && \Illuminate\Support\Facades\Hash::check($code, $this->access_code_hash);
    }

    /** @return list<array{key: string, label: string}> */
    public function hallGroupOptions(string $locale): array
    {
        return collect($this->hall_groups ?? [])
            ->map(fn (array $group) => [
                'key' => $group['key'],
                'label' => $group['names'][$locale] ?? $group['names']['en'] ?? $group['key'],
            ])->all();
    }

    /** @return array<string, mixed> shared plan copy for one language, falling back to English */
    public function planInfoFor(string $locale): array
    {
        $info = $this->plan_info ?? [];

        return $info[$locale] ?? $info['en'] ?? [];
    }

    public function isUnlisted(): bool
    {
        return $this->public_status === 'unlisted';
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
