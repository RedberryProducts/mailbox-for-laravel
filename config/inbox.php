<?php

return [
    'enabled' => env('INBOX_ENABLED', env('APP_ENV') !== 'production'),
    'public' => env('INBOX_PUBLIC', false),
    'store' => [
        'driver' => env('INBOX_STORE_DRIVER', 'file'),
        'resolvers' => [
            // 'custom' => fn() => new \App\CustomMessageStore,
        ],
        'file' => [
            'path' => env('INBOX_FILE_PATH', storage_path('app/inbox')),
        ],
    ],

    'retention' => [
        'seconds' => (int) env('INBOX_RETENTION', 60 * 60 * 24),
    ],
    'gate' => env('INBOX_GATE', 'viewMailbox'),
    'route' => env('INBOX_DASHBOARD_ROUTE', 'mailbox'),
    'middleware' => ['web'],
];
