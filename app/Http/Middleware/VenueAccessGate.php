<?php

namespace App\Http\Middleware;

use App\Models\Venue;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a private venue behind an access code. A venue with no code set is open
 * as before. Once the right code is entered, the browser session stays unlocked
 * for that venue only.
 */
class VenueAccessGate
{
    public const SESSION_KEY = 'venue_access';

    public static function isUnlocked(Request $request, Venue $venue): bool
    {
        return in_array($venue->id, (array) $request->session()->get(self::SESSION_KEY, []), true);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $venue = Venue::where('slug', $request->route('venueSlug'))->first();

        if (! $venue || ! $venue->isGated() || self::isUnlocked($request, $venue) || $request->routeIs('venue.access*')) {
            return $next($request);
        }

        if ($request->expectsJson() || ! $request->isMethod('GET')) {
            abort(403, 'This venue is private.');
        }

        return redirect()->route('venue.access', ['venueSlug' => $venue->slug, 'lang' => $request->query('lang')]);
    }
}
