# Mailbox for Laravel

A Laravel package that captures outgoing mail and stores it for in-app viewing. It adds a `mailbox` mail transport, a web dashboard for browsing messages and attachments, and convenient development defaults.

## Features

- Registers configuration, routes, views, and an install command for publishing assets and config.
- Adds a `mailbox` mailer transport that persists every sent email for later inspection.
- Web dashboard at `/mailbox` (configurable) with authorization middleware.
- Store messages on disk by default with automatic pruning.
- Works out of the box in non-production environments.

## Installation

1. Install the package via Composer:
   ```bash
   composer require redberry/mailbox-for-laravel --dev
   ```
2. Publish public assets and configuration:
   ```bash
   php artisan mailbox:install
   # or
   php artisan vendor:publish --tag=mailbox-install
   ```
3. Register the `mailbox` mailer driver in `config/mail.php`:
   ```php
   'mailers' => [
       // ...
       'mailbox' => ['transport' => 'mailbox'],
   ],
   ```
4. Use the `mailbox` mailer by setting it in your `.env`:
   ```env
   MAIL_MAILER=mailbox
   ```
5. Go to [http://localhost/mailbox](http://localhost/mailbox)

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

