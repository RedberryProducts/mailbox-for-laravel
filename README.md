# Mailbox for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/redberry/mailbox-for-laravel.svg?style=flat-square)](https://packagist.org/packages/redberry/mailbox-for-laravel)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/RedberryProducts/mailbox-for-laravel/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/RedberryProducts/mailbox-for-laravel/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/RedberryProducts/mailbox-for-laravel/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/RedberryProducts/mailbox-for-laravel/actions?query=workflow%3A%22Fix+PHP+code+style+issues%22+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/redberry/mailbox-for-laravel.svg?style=flat-square)](https://packagist.org/packages/redberry/mailbox-for-laravel)

Mailbox for Laravel captures your application's outgoing mail and serves it through a local, self-hosted dashboard — like Mailtrap or Mailhog, but without an external service or a second process to run. It ships with a fluent testing API that, unlike `Mail::fake()`, asserts against the fully rendered message: real HTML, real recipients, real attachments.

<!-- TODO: dashboard screenshot -->

## Installation

Require the package via Composer. In most cases you only want this in development:

```bash
composer require redberry/mailbox-for-laravel --dev
```

Run the install command to publish assets, publish the config file, and run migrations:

```bash
php artisan mailbox:install
```

Point your mailer at the `mailbox` transport in `.env`:

```env
MAIL_MAILER=mailbox
```

The dashboard is then available at `/mailbox` (or whatever path you configure). The package is auto-discovered — no manual provider registration needed.

**Requirements:** PHP 8.3+, Laravel 10 / 11 / 12.

## Capturing Mail

Everything your app sends through Laravel's `Mail` facade is intercepted by the `mailbox` transport and stored before delivery. Visit the dashboard to see captured messages:

- Sorted newest-first with a live-updating list
- HTML, plain-text, and raw RFC 822 views per message
- Attachment preview and download
- Read/unread tracking, single-message delete, and clear-all
- Recipient filtering and search
- A "Send test email" button for smoke tests

Internally, the pipeline is: transport → normalizer → `CaptureService` → paired message/attachment store. The architectural details are in [ARCHITECTURE.md](ARCHITECTURE.md).

## Testing your emails

`Mail::fake()` only tells you a Mailable was queued; it can't tell you whether the rendered email would actually contain what you expect. This package's assertions run against the captured message after Laravel renders it, so you can assert on subject lines, recipients, HTML content, and attachments as the recipient would see them.

Add the `InteractsWithMailbox` trait to your test. It clears the mailbox before every test, and exposes `$this->mailbox()` for assertions.

**Pest:**

```php
use Redberry\MailboxForLaravel\Testing\InteractsWithMailbox;

uses(InteractsWithMailbox::class);
```

**PHPUnit:**

```php
class OrderEmailTest extends TestCase
{
    use InteractsWithMailbox;
}
```

### Collection-level assertions

```php
use Redberry\MailboxForLaravel\Facades\Mailbox;
use Redberry\MailboxForLaravel\DTO\MailboxMessageData;

Mailbox::assertSentCount(2);
Mailbox::assertNothingSent();
Mailbox::assertSentTo('user@example.com');
Mailbox::assertNotSentTo('admin@example.com');

Mailbox::assertSent(fn (MailboxMessageData $m) => $m->subject === 'Welcome');

Mailbox::assertSent(
    fn (MailboxMessageData $m) => str_contains($m->subject, 'Newsletter'),
    expectedCount: 3,
);
```

### Per-message fluent assertions

Call `firstSent()` to chain assertions against a single captured message:

```php
Mailbox::firstSent()
    ->assertHasSubject('Order Confirmation')
    ->assertFrom('noreply@shop.com')
    ->assertHasTo('buyer@example.com')
    ->assertSeeInHtml('Order #12345')
    ->assertDontSeeInHtml('error')
    ->assertHasAttachment('invoice.pdf', 'application/pdf')
    ->assertAttachmentCount(1);
```

`firstSent()` also accepts a filter callback:

```php
Mailbox::firstSent(fn (MailboxMessageData $m) => $m->subject === 'Password Reset')
    ->assertHasTo('user@example.com')
    ->assertSeeInHtml('Reset your password');
```

### Reference — per-message assertions

| Method | Description |
|---|---|
| `assertFrom($email, $name?)` | Assert the sender email (and optionally name) |
| `assertHasTo($email, $name?)` | Assert a "to" recipient exists |
| `assertHasCc($email, $name?)` | Assert a "cc" recipient exists |
| `assertHasBcc($email, $name?)` | Assert a "bcc" recipient exists |
| `assertHasReplyTo($email, $name?)` | Assert a "reply-to" address exists |
| `assertHasSubject($subject)` | Assert exact subject match |
| `assertSubjectContains($substring)` | Assert subject contains a substring |
| `assertSeeInHtml($string)` | Assert HTML body contains string |
| `assertDontSeeInHtml($string)` | Assert HTML body does not contain string |
| `assertSeeInText($string)` | Assert text body contains string |
| `assertDontSeeInText($string)` | Assert text body does not contain string |
| `assertSeeInOrderInHtml($strings)` | Assert strings appear in order in HTML |
| `assertSeeInOrderInText($strings)` | Assert strings appear in order in text |
| `assertHasAttachment($filename, $mimeType?)` | Assert attachment exists |
| `assertHasNoAttachments()` | Assert no attachments |
| `assertAttachmentCount($count)` | Assert number of attachments |
| `assertHasHeader($name, $value?)` | Assert header exists (optionally with value) |

### End-to-end example

```php
it('sends welcome email with getting started guide', function () {
    $this->post('/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'secret123',
    ]);

    Mailbox::assertSentCount(1);
    Mailbox::assertSentTo('john@example.com');

    Mailbox::firstSent()
        ->assertHasSubject('Welcome, John!')
        ->assertFrom('noreply@myapp.com')
        ->assertSeeInOrderInHtml(['Welcome', 'Getting Started', 'Support'])
        ->assertHasAttachment('getting-started.pdf');
});
```

## Configuration

Defaults live in [config/mailbox.php](config/mailbox.php) and work without modification. Publish the config file only — without touching views, assets, or migrations — if you need to customize:

```bash
php artisan vendor:publish --tag=mailbox-config
```

Add `--force` to overwrite an existing `config/mailbox.php` after a package upgrade.

The keys you're most likely to touch:

- `path` — the URI prefix the dashboard lives at (default `mailbox`).
- `middleware` — routes run under the `web` group by default; add your own guards here (e.g. `auth`) for staging access control.
- `gate` — the Gate ability checked before dashboard access (default `viewMailbox`). See [Authorization](#authorization).
- `store.driver` — `database` (default) or `file`. See [Storage](#storage).
- `store.database.connection` — the connection the DB driver uses. Defaults to an auto-created `mailbox` SQLite file isolated from your app's database.
- `retention` — seconds before `mailbox:clear --outdated` prunes a message (default 24 h).
- `retention_schedule` — when `true` (default), the package auto-registers a daily `mailbox:clear --outdated` on Laravel's scheduler. Set `false` if you prefer to wire the purge yourself.
- `per_page` — dashboard pagination size (default 20, clamped 1–100).
- `attachments.disk` — the filesystem disk attachment content is written to (default `mailbox` local disk).

### Environment variables

| Variable | Default | Description |
|---|---|---|
| `MAILBOX_ENABLED` | `true` (non-production) | Master on/off switch — routes and transport only register when true |
| `MAILBOX_PATH` | `mailbox` | URL prefix for the dashboard |
| `MAILBOX_GATE` | `viewMailbox` | Gate ability checked by the authorize middleware |
| `MAILBOX_UNAUTHORIZED_REDIRECT` | `null` | Redirect target on gate denial (null = 403 response) |
| `MAILBOX_STORE_DRIVER` | `database` | `database` or `file` |
| `MAILBOX_STORE_DATABASE_CONNECTION` | `mailbox` | Connection name for the DB driver |
| `MAILBOX_STORE_DATABASE_TABLE` | `mailbox_messages` | Messages table name |
| `MAILBOX_STORE_FILE_PATH` | `storage/app/mailbox` | Path for the file driver |
| `MAILBOX_RETENTION` | `86400` | Retention period in seconds |
| `MAILBOX_RETENTION_SCHEDULE` | `true` | Auto-register a daily `mailbox:clear --outdated` on the scheduler |
| `MAILBOX_PER_PAGE` | `20` | Messages per dashboard page |
| `MAILBOX_ATTACHMENTS_DISK` | `mailbox` | Disk for attachment content |

## Storage

### Database driver (default)

Messages are stored in a dedicated `mailbox` SQLite database at `storage/app/mailbox/mailbox.sqlite`, isolated from your app's main database. The package only creates this connection if one with the configured name doesn't already exist, so you can point it elsewhere.

To use your own connection (e.g. MySQL), define it in `config/database.php`:

```php
'connections' => [
    'mailbox' => [
        'driver' => 'mysql',
        'host' => env('MAILBOX_DB_HOST', '127.0.0.1'),
        'database' => env('MAILBOX_DB_DATABASE', 'mailbox'),
        'username' => env('MAILBOX_DB_USERNAME', 'root'),
        'password' => env('MAILBOX_DB_PASSWORD', ''),
    ],
],
```

Or point `MAILBOX_STORE_DATABASE_CONNECTION` at an existing connection such as `mysql`. Run `php artisan mailbox:install` again to create the `mailbox_messages` and `mailbox_attachments` tables there.

### File driver

Captures each message to a JSON file under `storage/app/mailbox/`. Use it when you can't write to a database at all. It's slower for listing and paginates in-memory.

```env
MAILBOX_STORE_DRIVER=file
```

### Attachment disks

Attachment content lives on the `mailbox` filesystem disk, independent of the message driver. By default the package registers a local disk at `storage/app/mailbox/`, but — like the database connection — it won't overwrite a disk of the same name you've already defined. To store attachments on S3:

```php
// config/filesystems.php
'disks' => [
    'mailbox' => [
        'driver' => 's3',
        'bucket' => env('MAILBOX_S3_BUCKET'),
        'region' => env('MAILBOX_S3_REGION', 'us-east-1'),
    ],
],
```

### Custom drivers

Implement [`Contracts\MessageStore`](src/Contracts/MessageStore.php) for messages, and [`Contracts\AttachmentStore`](src/Contracts/AttachmentStore.php) for their attachments. Register the pair in your service provider and point `mailbox.store.driver` at your resolver key:

```php
'store' => [
    'driver' => 'redis',
    'resolvers' => [
        'redis' => fn () => new \App\Storage\RedisMessageStore,
    ],
],
```

Drivers are always resolved as a pair — if you ship a custom `MessageStore`, also bind a matching `AttachmentStore` so attachment reads don't fall through to the database driver.

## Authorization

Dashboard access is gated through Laravel's `Gate::allows()` using the `viewMailbox` ability. The package defines a default gate that allows access in local environments or whenever `mailbox.enabled` is true; if you define your own `viewMailbox` gate, the package will not overwrite it.

```php
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('viewMailbox', fn ($user) => $user?->isAdmin());
}
```

For staging servers, this gives you authenticated-only access without any extra middleware. You can also point the config's `unauthorized_redirect` at a login URL if you'd rather redirect than serve a 403.

Captured messages can include passwords, tokens, and personal data, so leave `MAILBOX_ENABLED=false` in production unless you deliberately want the inbox running there. If you do, define a strict gate and make sure the dashboard sits behind authentication.

## Artisan Commands

```bash
# Publish assets + config, then run package migrations.
# Flags: --force (overwrite published files), --refresh (drop and rebuild tables),
#        --dev (symlink assets for hot reload).
php artisan mailbox:install

# Clear captured mail. With --outdated, only remove messages older than `retention`.
# The --outdated variant runs daily on Laravel's scheduler automatically unless
# MAILBOX_RETENTION_SCHEDULE=false.
php artisan mailbox:clear
php artisan mailbox:clear --outdated

# Recreate the dev-mode asset symlink (rarely needed directly; --dev on install uses it).
php artisan mailbox:dev-link
```

## Upgrading

Breaking changes between major versions are documented in [CHANGELOG.md](CHANGELOG.md). Re-publish the config after any upgrade to pick up new keys:

```bash
php artisan vendor:publish --tag=mailbox-config --force
```

When a release changes the storage schema — v2.0.0 switched message ids from auto-increment integers to ULIDs — run `php artisan mailbox:install --refresh` to drop and recreate the mailbox tables.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for local setup, tests, and the coding standards we enforce.

## Security Vulnerabilities

If you discover a security vulnerability within this package, please email **security@redberry.ge** instead of using the issue tracker. All security vulnerabilities will be promptly addressed.

## Credits

- [Nika Jorjoliani](https://github.com/nikajorjoliani) — Creator & maintainer
- [Redberry](https://redberry.international)
- [All contributors](https://github.com/RedberryProducts/mailbox-for-laravel/graphs/contributors)

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
