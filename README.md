# Mailbox for Laravel

A Laravel package that captures outgoing mail and stores it for in-app viewing. It adds a `mailbox` mail transport, a web dashboard for browsing messages and attachments, and convenient development defaults.

## Features

- **Self-contained Inertia.js dashboard** — completely isolated from your host application's frontend stack
- Registers configuration, routes, views, and an install command for publishing assets and config
- Adds a `mailbox` mailer transport that persists every sent email for later inspection
- Web dashboard at `/mailbox` (configurable) with authorization middleware
- Store messages on disk by default with automatic pruning
- Works out of the box in non-production environments
- **Works with any frontend stack** — Vue, React, Blade-only, or even other Inertia apps

## Installation

1. Install the package via Composer:
   ```bash
   composer require redberry/mailbox-for-laravel --dev
   ```

2. Publish the package assets:
   ```bash
   php artisan mailbox:install
   ```
   This will publish the configuration file to `config/mailbox.php` and the frontend assets to `public/vendor/mailbox`.

3. Configure your application to use the mailbox mailer in your `.env`:
   ```env
   MAIL_MAILER=mailbox
   ```

4. Visit the dashboard at [http://localhost/mailbox](http://localhost/mailbox)

> **Note:** The package is auto-discovered by Laravel and the `mailbox` mail transport is automatically registered. No manual service provider or mailer configuration is needed.

## Architecture

### Scoped Inertia.js Integration

This package uses **Inertia.js** as its communication layer but operates in complete isolation from your host application:

- **Independent Inertia stack** — The package has its own Inertia middleware (`HandleInertiaRequests`) and root layout
- **Namespaced components** — All Inertia pages use the `mailbox::` prefix (e.g., `mailbox::Dashboard`)
- **Separate entry point** — Uses `resources/js/dashboard.js` as its dedicated JavaScript entry
- **Scoped assets** — Built assets are published to `public/vendor/mailbox/` with their own manifest
- **No conflicts** — Works alongside your application's existing Inertia setup (if any) without interference

### No Host Dependencies

The dashboard works regardless of your host application's frontend stack:

- ✅ Works with Laravel + Blade only
- ✅ Works with Laravel + Vue (without Inertia)
- ✅ Works with Laravel + React (without Inertia)
- ✅ Works with Laravel + Inertia (Vue or React)
- ✅ Works with any other frontend framework

The package bundles its own frontend dependencies and doesn't require your application to install or configure Inertia.

## Configuration

The published `config/mailbox.php` file exposes several options:

- `MAILBOX_ENABLED` &mdash; enable the mailbox even in production (defaults to non-production only).
- `MAILBOX_GATE` &mdash; ability checked by the `mailbox.authorize` middleware (defaults to `viewMailbox`).
- `MAILBOX_DASHBOARD_ROUTE` &mdash; URI where the dashboard is mounted (`/mailbox` by default).
- `MAILBOX_REDIRECT` &mdash; URI where the user is redirected when they are unauthorized (defaults to Laravel's Forbidden Page).
- `MAILBOX_STORE_DRIVER` & `MAILBOX_FILE_PATH` &mdash; storage driver and path for captured messages.
- `MAILBOX_RETENTION` &mdash; number of seconds before stored messages are purged.

## Usage

Visit the dashboard route to browse stored messages. Attachments and inline assets are served through dedicated routes. Access is protected by the `mailbox.authorize` middleware which uses Laravel's Gate system; define the `viewMailbox` ability or set `MAILBOX_PUBLIC=true` to expose it without authentication.

## Testing

Run the test suite with:

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Contributions are welcome! Please submit pull requests or open issues on GitHub.

## Security

If you discover any security related issues, please email security@redberry.ge instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

