<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($venues as $venue)
    <sitemap>
        <loc>{{ route('venue.sitemap', ['venueSlug' => $venue->slug]) }}</loc>
        @if ($venue->updated_at)
        <lastmod>{{ $venue->updated_at->toAtomString() }}</lastmod>
        @endif
    </sitemap>
@endforeach
</sitemapindex>
