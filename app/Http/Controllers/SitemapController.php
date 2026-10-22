<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Models\VenueKnowledgeItem;
use App\Services\VenueContentCache;
use Illuminate\Http\Response;

/**
 * Sitemaps for search engines: one index of published venues, and one sitemap per venue
 * listing every public page in every language.
 */
class SitemapController extends Controller
{
    private const LOCALES = ['id', 'en', 'ja'];

    public function __construct(private readonly VenueContentCache $content) {}

    public function index(): Response
    {
        $venues = Venue::where('public_status', 'published')->orderBy('slug')->get(['id', 'slug', 'updated_at']);

        return $this->xml(view('sitemap.index', ['venues' => $venues])->render());
    }

    public function venue(string $venueSlug): Response
    {
        $venue = Venue::where('slug', $venueSlug)->firstOrFail();

        abort_unless($venue->isPublished(), 404);

        $halls = $this->content->remember($venue, 'sitemap-halls', fn () => $venue->halls()->where('is_active', true)->orderBy('sort_order')->get(['id', 'slug', 'updated_at']));
        $services = $this->content->remember($venue, 'sitemap-services', fn () => $venue->knowledgeItems()->where('is_active', true)
            ->whereIn('category', [VenueKnowledgeItem::CATEGORY_SERVICES, VenueKnowledgeItem::CATEGORY_CATERING])
            ->orderBy('sort_order')->get(['id', 'updated_at']));

        $pages = collect([
            ['route' => 'venue.show', 'params' => [], 'updated' => $venue->updated_at],
            ['route' => 'venue.halls', 'params' => [], 'updated' => $halls->max('updated_at')],
            ['route' => 'venue.services', 'params' => [], 'updated' => $services->max('updated_at')],
            ['route' => 'venue.info', 'params' => [], 'updated' => $venue->updated_at],
            ['route' => 'venue.staff', 'params' => [], 'updated' => $venue->updated_at],
            ['route' => 'venue.reservation', 'params' => [], 'updated' => $venue->updated_at],
            ...$halls->map(fn ($hall) => ['route' => 'venue.hall', 'params' => ['hallSlug' => $hall->slug], 'updated' => $hall->updated_at]),
            ...$services->map(fn ($service) => ['route' => 'venue.service', 'params' => ['serviceId' => $service->id], 'updated' => $service->updated_at]),
        ])->map(function (array $page) use ($venue) {
            $url = fn (string $locale) => route($page['route'], ['venueSlug' => $venue->slug, ...$page['params'], 'lang' => $locale]);

            return [
                'loc' => $url($venue->default_locale),
                'lastmod' => $page['updated']?->toAtomString(),
                'alternates' => collect(self::LOCALES)->mapWithKeys(fn (string $locale) => [$locale => $url($locale)])->all(),
            ];
        });

        return $this->xml(view('sitemap.venue', ['pages' => $pages])->render());
    }

    private function xml(string $body): Response
    {
        return response('<?xml version="1.0" encoding="UTF-8"?>'."\n".$body, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
