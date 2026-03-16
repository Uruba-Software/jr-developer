<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Operating Mode
    |--------------------------------------------------------------------------
    |
    | Determines how Jr Developer processes messages by default.
    | Values: manual | agent | cloud
    |
    */
    'default_mode' => env('JR_DEFAULT_MODE', 'manual'),

    /*
    |--------------------------------------------------------------------------
    | AI Provider
    |--------------------------------------------------------------------------
    */
    'ai' => [
        'default_provider' => env('PRISM_DEFAULT_PROVIDER', 'anthropic'),
        'default_model'    => env('PRISM_DEFAULT_MODEL', 'claude-haiku-4-5-20251001'),

        'providers' => [
            'anthropic' => [
                'api_key' => env('ANTHROPIC_API_KEY'),
            ],
            'openai' => [
                'api_key' => env('OPENAI_API_KEY'),
            ],
            'gemini' => [
                'api_key' => env('GEMINI_API_KEY'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Messaging Platforms
    |--------------------------------------------------------------------------
    */
    'platforms' => [
        'slack' => [
            'bot_token'      => env('SLACK_BOT_TOKEN'),
            'signing_secret' => env('SLACK_SIGNING_SECRET'),
            'webhook_url'    => env('SLACK_WEBHOOK_URL'),
        ],
        'discord' => [
            'bot_token'   => env('DISCORD_BOT_TOKEN'),
            'public_key'  => env('DISCORD_PUBLIC_KEY'),
            'webhook_url' => env('DISCORD_WEBHOOK_URL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Context Management
    |--------------------------------------------------------------------------
    */
    'context' => [
        // Max tokens to keep in conversation context before pruning
        'max_tokens'  => 100_000,
        // TTL for conversation context in Redis (seconds)
        'ttl_seconds' => 60 * 60 * 24, // 24 hours
    ],

];
