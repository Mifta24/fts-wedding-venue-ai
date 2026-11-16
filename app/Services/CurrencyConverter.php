<?php

namespace App\Services;

use App\Models\Venue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Prices are kept in the venue's own currency (IDR). Each language shows and
 * bills in its own currency: Indonesian in rupiah, Japanese in yen, English
 * in US dollars. The rate comes from the Frankfurter (ECB) feed, fixed for
 * one Jakarta calendar day so a quote and the booking made from it agree.
 * When no rate can be had at all, the venue currency is used instead of
 * guessing a number.
 */
class CurrencyConverter
{
    /** The currency each page language shows and bills in. */
    public const LOCALE_CURRENCY = ['id' => 'IDR', 'ja' => 'JPY', 'en' => 'USD', 'th' => 'THB', 'zh' => 'TWD'];

    /** Amounts are rounded to this step: ¥1,000 and $10 keep large venue prices tidy. */
    public const STEP = ['IDR' => 1, 'JPY' => 1000, 'USD' => 10, 'THB' => 100, 'TWD' => 100];

    private const SYMBOL = ['IDR' => 'Rp', 'JPY' => '¥', 'USD' => '$', 'THB' => '฿', 'TWD' => 'NT$'];

    private const SEPARATORS = ['IDR' => [',', '.'], 'JPY' => ['.', ','], 'USD' => ['.', ','], 'THB' => ['.', ','], 'TWD' => ['.', ',']];

    /** @var array<string, array<string, float>|null> */
    private array $memo = [];

    /** The currency a client reading this language is shown and billed in. */
    public function currencyFor(Venue $venue, ?string $locale): string
    {
        $wanted = self::LOCALE_CURRENCY[$locale ?? ''] ?? $venue->currency;

        return $this->rate($venue->currency, $wanted) === null ? $venue->currency : $wanted;
    }

    /** How many units of `$to` one unit of `$from` buys; null when unknown. */
    public function rate(string $from, string $to): ?float
    {
        if ($from === $to) {
            return 1.0;
        }

        $rates = $this->rates($from);

        return isset($rates[$to]) ? (float) $rates[$to] : null;
    }

    /** Converts an amount in the venue currency, rounded to the currency's step. */
    public function convert(float $amount, Venue $venue, string $currency): float
    {
        $rate = $this->rate($venue->currency, $currency) ?? 1.0;

        return $this->round($amount * $rate, $currency);
    }

    public function round(float $amount, string $currency): float
    {
        $step = self::STEP[$currency] ?? 1;

        return round($amount / $step) * $step;
    }

    /** The venue's price list (in its own currency) shown in `$currency`. */
    public function display(float $amount, Venue $venue, string $currency, string $space = ' '): string
    {
        return $this->format($this->convert($amount, $venue, $currency), $currency, $space);
    }

    /** Formats an amount that is already in `$currency`. */
    public function format(float $amount, string $currency, string $space = ' '): string
    {
        [$decimal, $thousands] = self::SEPARATORS[$currency] ?? ['.', ','];
        $symbol = self::SYMBOL[$currency] ?? $currency.' ';
        $number = number_format($amount, 0, $decimal, $thousands);

        return $symbol === self::SYMBOL['IDR'] || ! isset(self::SYMBOL[$currency])
            ? rtrim($symbol).$space.$number
            : $symbol.$number;
    }

    /** @return array<string, float>|null */
    private function rates(string $base): ?array
    {
        if (array_key_exists($base, $this->memo)) {
            return $this->memo[$base];
        }

        $symbols = array_values(array_diff(array_unique(self::LOCALE_CURRENCY), [$base]));
        $today = now('Asia/Jakarta')->toDateString();
        $key = "fx:{$base}:{$today}";

        $rates = Cache::get($key);

        if ($rates === null) {
            $rates = $this->fetch($base, $symbols);

            if ($rates !== null) {
                Cache::put($key, $rates, now()->addDays(2));
                Cache::forever("fx:{$base}:last", $rates);
            } else {
                // The feed is down: keep yesterday's rate rather than change the currency under the client.
                $rates = Cache::get("fx:{$base}:last");
            }
        }

        return $this->memo[$base] = $rates;
    }

    /** @return array<string, float>|null */
    private function fetch(string $base, array $symbols): ?array
    {
        $feed = config('services.fx.feed');

        if (! $feed) {
            return null;
        }

        // The ECB feed has no TWD; a second public feed fills in whatever it lacks.
        $rates = $this->fromFeed($feed, ['base' => $base, 'symbols' => implode(',', $symbols)]) ?? [];
        $missing = array_diff($symbols, array_keys($rates));

        if ($missing !== [] && ($fallback = config('services.fx.fallback_feed'))) {
            $rates += array_intersect_key($this->fromFeed(str_replace('{base}', $base, $fallback), []) ?? [], array_flip($missing));
        }

        return $rates === [] ? null : $rates;
    }

    /** @return array<string, float>|null */
    private function fromFeed(string $url, array $query): ?array
    {
        try {
            $response = Http::timeout(5)->get($url, $query);
            $rates = $response->successful() ? $response->json('rates') : null;
        } catch (\Throwable $e) {
            Log::warning('Exchange rate feed failed: '.$e->getMessage());

            return null;
        }

        if (! is_array($rates) || $rates === []) {
            return null;
        }

        return array_map('floatval', array_filter($rates, fn ($rate) => is_numeric($rate) && $rate > 0));
    }
}
