<?php

return [

    'enabled' => env('PULSE_ENABLED', true),

    'path' => env('PULSE_PATH', 'pulse'),

    'domain' => env('PULSE_DOMAIN', null),

    'middleware' => [
        \Spark\Pulse\Http\Middleware\AuthorizePulse::class,
    ],

    'storage' => [
        'driver' => env('PULSE_STORAGE_DRIVER', 'database'),
        'table' => 'pulse_entries',
        'connection' => env('PULSE_DB_CONNECTION', null),
        'prune_hours' => 24,
    ],

    'slow_thresholds' => [
        'request' => 500,
        'query' => 50,
        'job' => 1000,
        'http_client' => 1000,
    ],

    'ignore_paths' => [
        'favicon.ico',
    ],

    'ignore_commands' => [
        'pulse:*',
        'queue:work',
    ],

    'sensitive_keys' => [
        'password',
        'password_confirmation',
        'secret',
        'token',
        'authorization',
        'cookie',
        'api_key',
        'access_token',
        'app_key',
        'set-cookie',
        'x-api-key',
        'proxy-authorization',
        'x-csrf-token',
        'x-xsrf-token',
        '_token',
        'refresh_token',
        'client_secret',
    ],

    'watchers' => [
        'requests' => [
            'enabled' => true,
            'ignore_status_codes' => [],
        ],
        'commands' => [
            'enabled' => true,
        ],
        'jobs' => [
            'enabled' => true,
        ],
        'cache' => [
            'enabled' => true,
        ],
        'events' => [
            'enabled' => true,
            'ignore' => [],
        ],
        'exceptions' => [
            'enabled' => true,
        ],
        'gates' => [
            'enabled' => true,
        ],
        'http_client' => [
            'enabled' => true,
        ],
        'logs' => [
            'enabled' => true,
            'level' => 'debug',
        ],
        'mail' => [
            'enabled' => true,
            'record_body' => false,
        ],
        'models' => [
            'enabled' => true,
        ],
        'queries' => [
            'enabled' => true,
            'detect_duplicates' => true,
            'record_bindings' => false,
        ],
        'views' => [
            'enabled' => true,
        ],
        'language_files' => [
            'enabled' => true,
        ],
        'performance' => [
            'enabled' => true,
            'sample_rate' => 1,
        ],
    ],

];
