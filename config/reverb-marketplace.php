<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Personal access token
    |--------------------------------------------------------------------------
    |
    | Generated on Reverb under My Profile → API & Integrations. Tokens do
    | not expire and are bound to one environment: a production token is
    | refused by the sandbox and the reverse.
    |
    | The variables are prefixed REVERB_MARKETPLACE_ so they cannot collide
    | with Laravel Reverb, the WebSocket server, which owns REVERB_*.
    |
    */

    'token' => env('REVERB_MARKETPLACE_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | "production" talks to api.reverb.com and "sandbox" to
    | sandbox.reverb.com. Sandbox is the default so that a missing variable
    | can never write to a live shop.
    |
    */

    'environment' => env('REVERB_MARKETPLACE_ENVIRONMENT', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Base URL override
    |--------------------------------------------------------------------------
    |
    | Leave null to derive the URL from the environment. Set it only to
    | point the client at a proxy or a recording server.
    |
    */

    'base_url' => env('REVERB_MARKETPLACE_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Request headers
    |--------------------------------------------------------------------------
    */

    'api_version' => '3.0',

    'display_currency' => env('REVERB_MARKETPLACE_DISPLAY_CURRENCY', 'USD'),

    'accept_language' => env('REVERB_MARKETPLACE_ACCEPT_LANGUAGE'),

    'shipping_region' => env('REVERB_MARKETPLACE_SHIPPING_REGION'),

    /*
    |--------------------------------------------------------------------------
    | Timeouts and retries
    |--------------------------------------------------------------------------
    |
    | Retries cover connection failures and 429 rate-limit responses only.
    | A 5xx on a write is never retried: a create that timed out may have
    | succeeded, and repeating it would list the same instrument twice.
    |
    */

    'connect_timeout' => 5,

    'timeout' => 20,

    'retries' => 2,

    'retry_delay_ms' => 500,

    /*
    |--------------------------------------------------------------------------
    | Reference data cache
    |--------------------------------------------------------------------------
    |
    | Categories, conditions, regions and the like change a few times a
    | year. Seconds to keep them; 0 disables caching.
    |
    */

    'cache_ttl' => 86400,

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | Reverb accepts only a URL and a topic when a webhook is registered:
    | no custom headers and no signing secret. The only gate available is a
    | shared token carried in the registered URL's query string. Set
    | "path" to null to skip registering the route.
    |
    */

    'webhooks' => [
        'token' => env('REVERB_MARKETPLACE_WEBHOOK_TOKEN'),
        'path' => env('REVERB_MARKETPLACE_WEBHOOK_PATH', 'webhooks/reverb-marketplace'),
        'middleware' => ['throttle:120,1'],
    ],

];
