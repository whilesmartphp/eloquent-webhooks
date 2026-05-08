<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Model Configuration
    |--------------------------------------------------------------------------
    */
    'user_model' => env('WEBHOOKS_USER_MODEL', 'App\\Models\\User'),
    'workspace_model' => env('WEBHOOKS_WORKSPACE_MODEL', 'App\\Models\\Workspace'),
    'project_model' => env('WEBHOOKS_PROJECT_MODEL', 'App\\Models\\Project'),

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    */
    'register_routes' => env('WEBHOOKS_REGISTER_ROUTES', true),
    'route_prefix' => env('WEBHOOKS_ROUTE_PREFIX', ''),
    'route_middleware' => ['auth:sanctum'],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    */
    'workspace_scoped' => env('WEBHOOKS_WORKSPACE_SCOPED', true),
    'project_scoped' => env('WEBHOOKS_PROJECT_SCOPED', true),
    'track_events' => env('WEBHOOKS_TRACK_EVENTS', true),
];
