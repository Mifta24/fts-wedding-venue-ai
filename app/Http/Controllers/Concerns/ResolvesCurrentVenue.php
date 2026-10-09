<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Venue;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait ResolvesCurrentVenue
{
    protected function currentVenue(Request $request): Venue
    {
        $venue = $request->user()->currentVenue();

        if (! $venue) {
            throw new HttpException(403, 'No venue is linked to this account yet.');
        }

        return $venue;
    }
}
