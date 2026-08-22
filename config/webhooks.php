<?php

return [
    // Use UUID primary keys (and uuid morph/foreign columns) instead of auto-incrementing
    // integers. Must be set before the migrations run, and assumes the owner / created_by
    // models also use UUID keys.
    'uuids' => (bool) env('WEBHOOKS_UUIDS', false),

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    */
    'register_routes' => env('WEBHOOKS_REGISTER_ROUTES', true),
    'route_prefix' => env('WEBHOOKS_ROUTE_PREFIX', ''),
    'route_middleware' => ['auth:sanctum'],

    // What guards the incoming address. A sender is a third party with only the
    // token in the URL, so an authenticated stack here means no webhook ever
    // arrives. Throttling belongs in this list.
    'ingress_middleware' => [],

    /*
    |--------------------------------------------------------------------------
    | Outbound Delivery
    |--------------------------------------------------------------------------
    | Settings for signed, retried delivery of outgoing webhooks.
    */
    'signing' => [
        'header' => env('WEBHOOKS_SIGNATURE_HEADER', 'X-Webhook-Signature'),
        'algo' => env('WEBHOOKS_SIGNATURE_ALGO', 'sha256'),
    ],

    'delivery' => [
        'timeout' => (int) env('WEBHOOKS_DELIVERY_TIMEOUT', 10),
        'max_attempts' => (int) env('WEBHOOKS_DELIVERY_MAX_ATTEMPTS', 6),
        'backoff' => [10, 60, 300, 1800, 7200, 21600],
        'queue' => env('WEBHOOKS_DELIVERY_QUEUE', null),
        'connection' => env('WEBHOOKS_DELIVERY_CONNECTION', null),
    ],

    'auto_disable_after' => (int) env('WEBHOOKS_AUTO_DISABLE_AFTER', 15),
];
