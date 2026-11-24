<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'concierge' => [
        'retention_days' => (int) env('CONCIERGE_RETENTION_DAYS', 90),
    ],

    'local_llm' => [
        'base_url' => env('LOCAL_LLM_BASE_URL'),
        'api_key' => env('LOCAL_LLM_API_KEY'),
        'model' => env('LOCAL_LLM_MODEL'),
    ],

    // Optional cloud voice for the concierge (OpenAI-compatible POST {base_url}/audio/speech).
    // Off by default: the chat and narrator then use the guest's own device voice, which is free.
    'tts' => [
        'enabled' => (bool) env('TTS_ENABLED', false),
        'base_url' => env('TTS_BASE_URL', 'https://api.openai.com/v1'),
        'api_key' => env('TTS_API_KEY'),
        'model' => env('TTS_MODEL', 'gpt-4o-mini-tts'),
        'voice' => env('TTS_VOICE', 'nova'),
        'max_chars' => (int) env('TTS_MAX_CHARS', 600),
        // Hard stop on spend: audio generated per day (cached repeats are free). 0 = no cap.
        'daily_limit' => (int) env('TTS_DAILY_LIMIT', 400),
    ],

    // Daily exchange rates for per-language pricing (ECB via Frankfurter). Empty disables conversion.
    'fx' => [
        'feed' => env('FX_FEED_URL', 'https://api.frankfurter.dev/v1/latest'),
        // Used only for currencies the first feed lacks (TWD).
        'fallback_feed' => env('FX_FALLBACK_FEED_URL', 'https://open.er-api.com/v6/latest/{base}'),
    ],

];
