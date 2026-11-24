<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional cloud voice: turns one concierge reply into speech (mp3) through an
 * OpenAI-compatible /audio/speech endpoint. Only for a real conversation of a
 * published venue, rate limited, capped per day and cached, so cost stays bounded.
 * When TTS is off or fails the page falls back to the guest's device voice.
 */
class ConciergeSpeechController extends Controller
{
    private const LOCALE_NAMES = ['id' => 'Indonesian', 'en' => 'English', 'ja' => 'Japanese', 'th' => 'Thai', 'zh' => 'Traditional Chinese (Taiwan)'];

    public function speak(Request $request, string $venueSlug): Response
    {
        $config = config('services.tts');

        abort_unless($config['enabled'] && $config['api_key'], 404);

        $venue = Venue::where('slug', $venueSlug)->first();
        abort_if(! $venue || ! $venue->isPublished(), 404);

        $data = $request->validate([
            'client_token' => ['required', 'uuid'],
            'text' => ['required', 'string', 'max:4000'],
            'locale' => ['nullable', 'in:'.implode(',', array_keys(self::LOCALE_NAMES))],
        ]);

        abort_unless(Conversation::where('venue_id', $venue->id)->where('client_token', $data['client_token'])->exists(), 422);

        $text = $this->speakable($data['text'], (int) $config['max_chars']);
        abort_if($text === '', 422);

        $path = 'tts/'.hash('sha256', implode('|', [$config['model'], $config['voice'], $data['locale'] ?? '', $text])).'.mp3';
        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            $limit = (int) $config['daily_limit'];
            $counter = 'tts-generated:'.now()->format('Y-m-d');

            if ($limit > 0 && Cache::get($counter, 0) >= $limit) {
                abort(429, 'Daily voice limit reached.');
            }

            $disk->put($path, $this->synthesize($text, $data['locale'] ?? null, $config));
            Cache::add($counter, 0, now()->endOfDay());
            Cache::increment($counter);
        }

        return response($disk->get($path), 200, [
            'Content-Type' => 'audio/mpeg',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /** Strip markdown/links and cut at a sentence end so the voice never reads syntax or runs on. */
    private function speakable(string $text, int $max): string
    {
        $text = preg_replace('/!\[[^\]]*\]\([^)]*\)/u', ' ', $text);
        $text = preg_replace('/\[([^\]]+)\]\([^)]*\)/u', '$1', $text);
        $text = preg_replace('~https?://\S+~u', ' ', $text);
        $text = trim(preg_replace('/\s+/u', ' ', preg_replace('/[*_`#>]+/u', '', $text)));

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $cut = mb_substr($text, 0, $max);

        if (preg_match('/^(.*[.!?。！？])/us', $cut, $match) && mb_strlen($match[1]) > $max / 2) {
            return trim($match[1]);
        }

        return trim($cut);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function synthesize(string $text, ?string $locale, array $config): string
    {
        $payload = [
            'model' => $config['model'],
            'voice' => $config['voice'],
            'input' => $text,
            'response_format' => 'mp3',
        ];

        if (str_contains((string) $config['model'], 'gpt-4o')) {
            $payload['instructions'] = 'Speak as a warm, calm, polite wedding concierge'.($locale ? ' in '.self::LOCALE_NAMES[$locale] : '').'.';
        }

        $response = Http::withToken($config['api_key'])
            ->timeout(20)
            ->post(rtrim($config['base_url'], '/').'/audio/speech', $payload);

        if (! $response->successful() || $response->body() === '') {
            Log::warning('Concierge TTS failed', ['status' => $response->status()]);
            abort(502, 'Voice unavailable.');
        }

        return $response->body();
    }
}
