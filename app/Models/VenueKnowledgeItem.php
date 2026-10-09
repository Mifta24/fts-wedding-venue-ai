<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * The approved knowledge base the AI Concierge retrieves from. This is the
 * ONLY place venue facts may come from — the model is never allowed to
 * answer a venue-knowledge question from its own memory.
 */
#[Fillable([
    'venue_id',
    'category',
    'title',
    'body',
    'translations',
    'tags',
    'image_url',
    'is_active',
    'sort_order',
])]
class VenueKnowledgeItem extends Model
{
    public const CATEGORY_GENERAL = 'general';

    public const CATEGORY_SERVICES = 'services';

    public const CATEGORY_POLICIES = 'policies';

    public const CATEGORY_CATERING = 'catering';

    public const CATEGORY_ACCESS = 'access';

    public const CATEGORY_FAQ = 'faq';

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'tags' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * A short icon name for this entry, taken from its tags or title so the
     * services cards can show a matching symbol without another column.
     */
    public function iconName(): string
    {
        $haystack = Str::lower($this->title.' '.implode(' ', $this->tags ?? []));

        foreach ([
            'decor' => ['decor', 'dekorasi', 'flower', 'bunga', 'floral', 'pelaminan', 'backdrop'],
            'catering' => ['catering', 'kuliner', 'menu', 'buffet', 'prasmanan', 'food', 'makanan', 'dessert'],
            'photo' => ['photo', 'foto', 'video', 'videograph', 'dokumentasi', 'drone', 'cinema'],
            'makeup' => ['makeup', 'make-up', 'rias', 'bridal', 'pengantin', 'hair', 'busana', 'gown', 'attire'],
            'music' => ['music', 'musik', 'live band', 'acoustic', 'entertain', 'hiburan', 'sound', 'mc', 'dj'],
            'planner' => ['planner', 'organizer', 'koordinator', 'rundown', 'coordinator', 'wo '],
            'room' => ['room', 'ruang', 'suite', 'lounge', 'akomodasi', 'menginap'],
            'parking' => ['parking', 'parkir', 'valet', 'car park'],
            'transit' => ['mrt', 'train', 'kereta', 'station', 'stasiun', 'transit', 'lokasi', 'location', 'direction', 'akses', 'access'],
            'transport' => ['airport', 'bandara', 'transfer', 'shuttle', 'antar-jemput'],
            'payment' => ['deposit', 'payment', 'pembayaran', 'dp', 'cicilan', 'refund', 'cancellation', 'pembatalan'],
            'security' => ['security', 'keamanan', 'safety', 'medic', 'p3k'],
            'place' => ['nearby', 'terdekat', 'neighbourhood', 'lingkungan', 'hotel', 'attraction'],
            'wifi' => ['wifi', 'wi-fi', 'internet', 'streaming', 'live'],
        ] as $icon => $needles) {
            foreach ($needles as $needle) {
                if (Str::contains($haystack, $needle)) {
                    return $icon;
                }
            }
        }

        return 'star';
    }

    public function translatedTitle(string $locale): string
    {
        $value = $this->translations[$locale]['title'] ?? null;

        return filled($value) ? $value : $this->title;
    }

    public function translatedBody(string $locale): string
    {
        $value = $this->translations[$locale]['body'] ?? null;

        return filled($value) ? $value : $this->body;
    }
}
