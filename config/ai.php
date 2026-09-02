<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | AI layer
    |--------------------------------------------------------------------------
    |
    | Without an API key the AI features stay visible but disabled, and say so
    | plainly rather than failing at request time.
    |
    */

    'api_key' => env('ANTHROPIC_API_KEY'),

    'model' => env('ANTHROPIC_MODEL', 'claude-opus-5'),

    // Chart insights are short; the chat agent needs room for tool loops.
    'max_tokens' => [
        'insight' => 1024,
        'chat' => 8000,
        'analysis' => 2048,
    ],

    'effort' => [
        'insight' => 'low',
        'chat' => 'high',
        'analysis' => 'low',
    ],

    // How long a per-chart insight stays valid before it is regenerated.
    'insight_ttl_hours' => 24,

    // A single chat answer may call at most this many tools before we stop.
    'max_tool_iterations' => 8,

    'credits' => [
        'chat' => 1,
        'chart_insight' => 1,
        'analysis' => 1,
    ],
];
