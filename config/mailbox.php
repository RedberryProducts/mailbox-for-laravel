<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mailbox Master Switch
    |--------------------------------------------------------------------------
    |
    | Controls whether the Mailbox transport captures outgoing mail and
    | whether the dashboard routes are registered. When false, the package
    | is completely inert — no capture, no HTTP endpoints. Defaults to
    | "true" in every environment except "production".
    |
    */

    'enabled' => (bool) env('MAILBOX_ENABLED', env('APP_ENV') !== 'production'),

    /*
    |--------------------------------------------------------------------------
    | Transport Decoration
    |--------------------------------------------------------------------------
    |
    | When set to a mailer name (e.g. "smtp", "ses", "postmark"), the
    | Mailbox transport will capture the email locally AND forward it
    | to the named mailer for real delivery. Leave null for capture-only
    | mode (the default). Requires MAIL_MAILER=mailbox.
    |
    | Example: MAILBOX_DECORATE=smtp → emails appear in the dashboard
    | and are also delivered via your SMTP server.
    |
    */

    'decorate' => env('MAILBOX_DECORATE'),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Path
    |--------------------------------------------------------------------------
    |
    | The URI path where the Mailbox dashboard will be accessible from. If
    | this path collides with an existing route in your application, you
    | are free to change it here to anything you like.
    |
    */

    'path' => env('MAILBOX_PATH', 'mailbox'),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Middleware
    |--------------------------------------------------------------------------
    |
    | The middleware stack applied to the dashboard routes. The default
    | "web" group is almost always what you want — add your own guards
    | (e.g. "auth") here to require authentication in staging, etc.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Authorization Gate
    |--------------------------------------------------------------------------
    |
    | The ability name checked by the AuthorizeMailbox middleware. Define a
    | corresponding Gate::define('viewMailbox', ...) in your app if you
    | want to restrict dashboard access; the default gate in the service
    | provider allows every request in non-production environments.
    |
    */

    'gate' => env('MAILBOX_GATE', 'viewMailbox'),

    /*
    |--------------------------------------------------------------------------
    | Unauthorized Redirect
    |--------------------------------------------------------------------------
    |
    | When the gate denies access, unauthenticated users are redirected
    | here. Leave null to render a 403 response instead of redirecting.
    |
    */

    'unauthorized_redirect' => env('MAILBOX_UNAUTHORIZED_REDIRECT'),

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Choose how captured messages are persisted:
    |
    |   "sqlite"   (default) — auto-configured dedicated SQLite file at
    |               storage/app/mailbox/mailbox.sqlite, zero setup required.
    |   "database" — bring-your-own connection (MySQL, Postgres, etc.).
    |               Define the connection in config/database.php first.
    |   "file"     — JSON files on disk, no database needed.
    |
    | Both "sqlite" and "database" use the same Eloquent-backed store
    | internally. Custom drivers may be registered via the "resolvers"
    | array — each entry is a callable returning a MessageStore instance.
    |
    */

    'store' => [

        'driver' => env('MAILBOX_STORE_DRIVER', 'sqlite'),

        'resolvers' => [
            // 'custom' => fn () => new \App\Support\CustomMailboxStore(),
        ],

        'file' => [
            'path' => env('MAILBOX_STORE_FILE_PATH', storage_path('app/mailbox')),
        ],

        'database' => [
            'connection' => env('MAILBOX_STORE_DATABASE_CONNECTION', 'mailbox'),
            'table' => env('MAILBOX_STORE_DATABASE_TABLE', 'mailbox_messages'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Maximum age (in seconds) a captured message should be kept before
    | the "mailbox:clear --older-than" command prunes it. The default
    | retains messages for 24 hours.
    |
    */

    'retention' => (int) env('MAILBOX_RETENTION', 60 * 60 * 24),

    /*
    |--------------------------------------------------------------------------
    | Retention Schedule
    |--------------------------------------------------------------------------
    |
    | When enabled, the package registers a daily scheduled task that runs
    | "mailbox:clear --outdated" to prune messages older than the retention
    | window. Set to false if you prefer to wire the purge manually in
    | your application's schedule.
    |
    */

    'retention_schedule' => (bool) env('MAILBOX_RETENTION_SCHEDULE', true),

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Number of messages displayed per page on the dashboard list view.
    |
    */

    'per_page' => (int) env('MAILBOX_PER_PAGE', 20),

    /*
    |--------------------------------------------------------------------------
    | Live Updates (Polling)
    |--------------------------------------------------------------------------
    |
    | The dashboard polls for new mail in the background. Interval is in
    | milliseconds. Set "enabled" to false to disable polling entirely.
    |
    */

    'polling' => [
        'enabled' => (bool) env('MAILBOX_POLLING_ENABLED', true),
        'interval' => (int) env('MAILBOX_POLLING_INTERVAL', 5000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    |
    | Attachment metadata follows the chosen store driver (database →
    | rows, file → JSON sidecars), while the content bytes always live
    | on the configured filesystem disk.
    |
    */

    'attachments' => [

        'enabled' => (bool) env('MAILBOX_ATTACHMENTS_ENABLED', true),

        'disk' => env('MAILBOX_ATTACHMENTS_DISK', 'mailbox'),

        'path' => env('MAILBOX_ATTACHMENTS_PATH', 'attachments'),

    ],

];
