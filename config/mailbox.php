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

    'attachments' => [
        'enabled' => (bool) env('MAILBOX_ATTACHMENTS_ENABLED', true),
        'disk' => env('MAILBOX_ATTACHMENTS_DISK', 'mailbox'),
        'path' => env('MAILBOX_ATTACHMENTS_PATH', 'attachments'),
        'max_size' => (int) env('MAILBOX_MAX_ATTACHMENT_SIZE', 5 * 1024 * 1024), // 5MB
        'max_total_size' => (int) env('MAILBOX_MAX_TOTAL_SIZE_PER_MESSAGE', 20 * 1024 * 1024), // 20MB
        'allowed_mime_types' => [
            'image/*',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'text/csv',
        ],
    ],

];
