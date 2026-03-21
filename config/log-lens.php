<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Log Lens
    |--------------------------------------------------------------------------
    | When set to false, the Log Lens UI routes will not be registered.
    */
    'enabled' => env('LOG_LENS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Route Prefix
    |--------------------------------------------------------------------------
    | The URI prefix for the Log Lens dashboard.
    */
    'route_prefix' => env('LOG_LENS_PREFIX', 'log-lens'),

    /*
    |--------------------------------------------------------------------------
    | Route Middleware
    |--------------------------------------------------------------------------
    | Middleware applied to Log Lens routes. Use 'auth' to restrict access.
    */
    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Log Storage Path
    |--------------------------------------------------------------------------
    | The directory where Laravel writes log files.
    */
    'storage_path' => storage_path('logs'),

    /*
    |--------------------------------------------------------------------------
    | Entries Per Page
    |--------------------------------------------------------------------------
    | Number of log entries to display per page.
    */
    'per_page' => 50,
];
