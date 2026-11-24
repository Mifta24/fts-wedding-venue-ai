<?php

namespace Tests\Feature;

use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConciergeSpeechTest extends TestCase
{
    use RefreshDatabase;

    private function enableTts(array $overrides = []): void
    {
        config(['services.tts' => array_merge([
            'enabled' => true, 'base_url' => 'http://tts.test/v1', 'api_key' => 'k', 'model' => 'gpt-4o-mini-tts',
            'voice' => 'nova', 'max_chars' => 600, 'daily_limit' => 400,
        ], $overrides)]);
        Storage::fake('local');
        Cache::flush();
    }

    private function token(string $locale = 'en'): string
    {
        Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'city' => 'Naha', 'country' => 'Japan', 'currency' => 'JPY', 'default_locale' => 'en']);

        return $this->postJson('/demo/concierge/start', ['locale' => $locale])->assertOk()->json('client_token');
    }

    public function test_chat_has_a_voice_toggle_and_the_page_says_whether_cloud_voice_is_on(): void
    {
        $this->token();
        config(['services.tts.enabled' => false]);
        $this->get('/demo?lang=en')->assertSee('data-chat-voice', false)->assertSee('data-tts-enabled="0"', false);

        config(['services.tts.enabled' => true, 'services.tts.api_key' => 'k']);
        $this->get('/demo?lang=en')->assertSee('data-tts-enabled="1"', false);
    }

    public function test_chat_has_a_voice_call_button_with_labels_in_every_language(): void
    {
        $this->token();
        foreach (['id' => 'panggilan suara', 'en' => 'voice call', 'ja' => '通話する', 'th' => 'โทรด้วยเสียง', 'zh' => '語音通話'] as $lang => $needle) {
            $this->get("/demo?lang={$lang}")->assertSee('data-chat-call', false)->assertSee($needle)->assertSee('data-label-call-ended', false);
        }
    }

    public function test_voice_labels_exist_in_every_language(): void
    {
        $this->token();
        foreach (['id' => 'Bacakan', 'en' => 'Read the concierge', 'ja' => '読み上げる', 'th' => 'อ่านคำตอบ', 'zh' => '朗讀'] as $lang => $needle) {
            $this->get("/demo?lang={$lang}")->assertSee($needle);
        }
    }

    public function test_it_is_not_available_while_cloud_voice_is_off(): void
    {
        $this->enableTts(['enabled' => false]);
        $token = $this->token();
        Http::fake();

        $this->postJson('/demo/concierge/speech', ['client_token' => $token, 'text' => 'Hello'])->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_it_returns_mp3_strips_markdown_and_caches_the_audio(): void
    {
        $this->enableTts();
        $token = $this->token();
        Http::fake(['tts.test/*' => Http::response('MP3DATA', 200)]);

        $payload = ['client_token' => $token, 'text' => 'Hello **dear guest**, see [the hall](https://example.com/x) https://example.com/y', 'locale' => 'ja'];
        $first = $this->postJson('/demo/concierge/speech', $payload)->assertOk();
        $this->assertSame('audio/mpeg', $first->headers->get('Content-Type'));
        $this->assertSame('MP3DATA', $first->getContent());
        $this->postJson('/demo/concierge/speech', $payload)->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            return $request->url() === 'http://tts.test/v1/audio/speech'
                && $request['input'] === 'Hello dear guest, see the hall'
                && $request['voice'] === 'nova'
                && str_contains($request['instructions'], 'Japanese');
        });
    }

    public function test_it_needs_a_real_conversation_and_text(): void
    {
        $this->enableTts();
        $this->token();
        Http::fake();

        $this->postJson('/demo/concierge/speech', ['client_token' => (string) Str::uuid(), 'text' => 'Hi'])->assertStatus(422);
        $this->postJson('/demo/concierge/speech', ['text' => 'Hi'])->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_long_text_is_cut_at_a_sentence_and_the_daily_cap_stops_new_audio(): void
    {
        $this->enableTts(['max_chars' => 60, 'daily_limit' => 1]);
        $token = $this->token();
        Http::fake(['tts.test/*' => Http::response('MP3', 200)]);

        $text = 'This is the first sentence of the answer. This second sentence is much longer and will be cut away.';
        $this->postJson('/demo/concierge/speech', ['client_token' => $token, 'text' => $text])->assertOk();
        Http::assertSent(fn (Request $r) => $r['input'] === 'This is the first sentence of the answer.');

        $this->postJson('/demo/concierge/speech', ['client_token' => $token, 'text' => 'Another reply today.'])->assertStatus(429);
        Http::assertSentCount(1);
    }

    public function test_a_provider_failure_is_a_502_so_the_page_falls_back_to_the_device_voice(): void
    {
        $this->enableTts();
        $token = $this->token();
        Http::fake(['tts.test/*' => Http::response('nope', 500)]);

        $this->postJson('/demo/concierge/speech', ['client_token' => $token, 'text' => 'Hello'])->assertStatus(502);
    }
}
