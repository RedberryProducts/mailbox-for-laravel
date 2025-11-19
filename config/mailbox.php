<?php

return [
    'enabled' => env('MAILBOX_ENABLED', env('APP_ENV') !== 'production'),
    'store' => [
        'driver' => env('MAILBOX_STORE_DRIVER', 'database'),
        'resolvers' => [
            // 'custom' => fn() => new \App\CustomMessageStore,
        ],
        'file' => [
            'path' => env('MAILBOX_FILE_PATH', storage_path('app/mailbox')),
        ],
        'database' => [
            'connection' => env('MAILBOX_DB_CONNECTION', 'mailbox'),
            'table' => env('MAILBOX_DB_TABLE', 'mailbox_messages'),
        ],
    ],

    'retention' => [
        'seconds' => (int) env('MAILBOX_RETENTION', 60 * 60 * 24),
    ],
    'gate' => env('MAILBOX_GATE', 'viewMailbox'),
    'unauthorized_redirect' => env('MAILBOX_REDIRECT', null),
    'route' => env('MAILBOX_DASHBOARD_ROUTE', 'mailbox'),
    'middleware' => ['web'],

    'polling' => [
        'enabled' => (bool) env('MAILBOX_POLLING_ENABLED', true),
        'interval' => (int) env('MAILBOX_POLLING_INTERVAL', 5000), // milliseconds
    ],

    'pagination' => [
        'per_page' => (int) env('MAILBOX_PER_PAGE', 20),
    ],

];
