# Mailbox for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/redberry/mailbox-for-laravel.svg?style=flat-square)](https://packagist.org/packages/redberry/mailbox-for-laravel)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/RedberryProducts/mailbox-for-laravel/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/RedberryProducts/mailbox-for-laravel/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/RedberryProducts/mailbox-for-laravel/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/RedberryProducts/mailbox-for-laravel/actions?query=workflow%3A%22Fix+PHP+code+style+issues%22+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/redberry/mailbox-for-laravel.svg?style=flat-square)](https://packagist.org/packages/redberry/mailbox-for-laravel)

**A zero-configuration local email inbox for Laravel.** Capture, inspect, and test emails without external services—like Mailtrap, but self-contained within your application.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Dashboard Overview](#dashboard-overview)
  - [Accessing the Dashboard](#accessing-the-dashboard)
  - [API Endpoints](#api-endpoints)
  - [Sending Test Emails](#sending-test-emails)
  - [Message Capture Flow](#message-capture-flow)
  - [Attachments](#attachments)
  - [Clearing the Inbox](#clearing-the-inbox)
- [Frontend Integration](#frontend-integration)
- [Storage Drivers](#storage-drivers)
- [Authorization & Security](#authorization--security)
- [Testing](#testing)
- [Development](#development)
- [Changelog](#changelog)
- [Security Vulnerabilities](#security-vulnerabilities)
- [Credits](#credits)
- [License](#license)

## Features

✨ **Core Features:**

- **Local Mailtrap-style inbox** — Capture all outgoing emails in a beautiful web interface
- **Zero external dependencies** — No Mailtrap, Mailhog, or third-party services required
- **Self-contained Inertia.js dashboard** — Vue 3-powered UI that's completely isolated from your host application
- **Works with any frontend stack** — Compatible with Blade-only, Vue, React, Livewire, or existing Inertia apps
- **Multiple storage drivers** — Database (SQLite, MySQL, PostgreSQL) or file-based storage
- **Message normalization** — Structured capture of headers, recipients, attachments, HTML/text bodies
- **Automatic retention policies** — Configure message pruning to prevent disk bloat
- **Authorization middleware** — Gate-based access control for production safety
- **Attachment support** — View and download email attachments
- **Mark as read/unread** — Track which messages you've reviewed
- **Delete functionality** — Clear entire inbox or delete individual messages with confirmation dialogs
- **Responsive UI** — Beautiful TailwindCSS-based interface with dark mode support
- **Developer-friendly** — Auto-enabled in non-production environments
- **Test helpers** — Send test emails directly from the dashboard

## Requirements

- **PHP:** `^8.3`
- **Laravel:** `^10.0` | `^11.0` | `^12.0`
- **Node.js & NPM:** Required only if rebuilding frontend assets (pre-built assets included)

## Installation

### 1. Install via Composer

For development environments (recommended):

```bash
composer require redberry/mailbox-for-laravel --dev
```

For production (if you need to capture emails in production):

```bash
composer require redberry/mailbox-for-laravel
```

### 2. Run the Install Command

```bash
php artisan mailbox:install
```

This command will:
- Publish frontend assets to `public/vendor/mailbox/`
- Run database migrations (creates `mailbox_messages` table by default)
- Set up the necessary configuration

**Available Flags:**

- `--dev` — Link assets for development (watches for file changes)
- `--force` — Force overwrite existing published assets
- `--refresh` — Run `migrate:refresh` instead of `migrate` (⚠️ drops tables)

**Examples:**

```bash
# Standard installation
php artisan mailbox:install

# Force reinstall (overwrites existing assets)
php artisan mailbox:install --force

# Development mode with linked assets
php artisan mailbox:install --dev

# Fresh install with database reset
php artisan mailbox:install --refresh
```

### 3. Configure Your Mail Driver **Required:**

Set your mail driver to `mailbox` to capture outgoing emails.

Add to your `.env`:

```env
MAIL_MAILER=mailbox
```

Configure you new mailer, that will use `mailbox` transport `config/mail.php`:

```php
'mailers' => [
    'mailbox' => [
        'transport' => 'mailbox',
    ],
],
```

> **Note:** Without this configuration, emails will be sent normally but won't be captured by the mailbox.

### 4. Access the Dashboard

Visit the dashboard at:

```
http://localhost/mailbox
```

Or your configured route (see [Configuration](#configuration)).

> **Note:** The package is auto-discovered by Laravel. No manual service provider registration needed.

## Configuration

After installation, the configuration file is available at `config/mailbox.php`.

### Configuration Reference

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Mailbox
    |--------------------------------------------------------------------------
    |
    | By default, the mailbox is enabled in all environments except production.
    | Set MAILBOX_ENABLED=true in production to capture emails.
    |
    */
    'enabled' => env('MAILBOX_ENABLED', env('APP_ENV') !== 'production'),

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configure where captured emails are stored. Available drivers:
    | - database: Store in a dedicated database connection (SQLite by default)
    | - file: Store as JSON files on disk
    |
    */
    'store' => [
        'driver' => env('MAILBOX_STORE_DRIVER', 'database'),

        // Custom storage driver resolvers
        'resolvers' => [
            // 'custom' => fn() => new \App\CustomMessageStore,
        ],

        // File storage options
        'file' => [
            'path' => env('MAILBOX_FILE_PATH', storage_path('app/mailbox')),
        ],

        // Database storage options
        'database' => [
            'connection' => env('MAILBOX_DB_CONNECTION', 'mailbox'),
            'table' => env('MAILBOX_DB_TABLE', 'mailbox_messages'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Message Retention
    |--------------------------------------------------------------------------
    |
    | Automatically purge messages older than the specified number of seconds.
    | Default: 24 hours (86400 seconds).
    |
    */
    'retention' => [
        'seconds' => (int) env('MAILBOX_RETENTION', 60 * 60 * 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization Gate
    |--------------------------------------------------------------------------
    |
    | Define which Laravel Gate ability is checked before allowing access
    | to the mailbox dashboard. Default: 'viewMailbox'
    |
    | Define in AuthServiceProvider:
    | Gate::define('viewMailbox', fn ($user) => $user->isAdmin());
    |
    */
    'gate' => env('MAILBOX_GATE', 'viewMailbox'),

    /*
    |--------------------------------------------------------------------------
    | Unauthorized Redirect
    |--------------------------------------------------------------------------
    |
    | Where to redirect users who fail authorization.
    | Default: null (shows Laravel's 403 page)
    |
    */
    'unauthorized_redirect' => env('MAILBOX_REDIRECT', null),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Route
    |--------------------------------------------------------------------------
    |
    | The URI prefix where the mailbox dashboard is accessible.
    | Default: 'mailbox'
    |
    */
    'route' => env('MAILBOX_DASHBOARD_ROUTE', 'mailbox'),

    /*
    |--------------------------------------------------------------------------
    | Middleware Stack
    |--------------------------------------------------------------------------
    |
    | Middleware applied to all mailbox routes.
    | Default: ['web']
    |
    */
    'middleware' => ['web'],
];
```

### Environment Variables

Add these to your `.env` file to customize behavior:

```env
# Enable in production (default: auto-enabled in non-production)
MAILBOX_ENABLED=true

# Storage driver (database or file)
MAILBOX_STORE_DRIVER=database

# Database connection (for database driver)
MAILBOX_DB_CONNECTION=mailbox
MAILBOX_DB_TABLE=mailbox_messages

# File storage path (for file driver)
MAILBOX_FILE_PATH=/path/to/storage/mailbox

# Message retention (in seconds, default: 24 hours)
MAILBOX_RETENTION=86400

# Authorization gate
MAILBOX_GATE=viewMailbox

# Redirect on unauthorized access
MAILBOX_REDIRECT=/login

# Dashboard route prefix
MAILBOX_DASHBOARD_ROUTE=mailbox
```

### Database Configuration

The package uses a **separate SQLite database** by default to avoid cluttering your main database. This is configured in `config/mailbox.php`:

```php
'store' => [
    'driver' => env('MAILBOX_STORE_DRIVER', 'database'),
    // ... 
    'database' => [
        'connection' => env('MAILBOX_DB_CONNECTION', 'mailbox'),
        'table' => env('MAILBOX_DB_TABLE', 'mailbox_messages'),
    ],
    // ... 
],
```
and then we inject 'mailbox' database connection into the config array, like so:
```php
config([
    'database.connections.mailbox' => [
        'driver' => 'sqlite',
        'database' => storage_path('app/mailbox/mailbox.sqlite'),
        'prefix' => '',
        'foreign_key_constraints' => true,
    ],
]);
```
If you want to override default connection you can add new connection or use existing one. All you need to do is add new connection into your `config/database.php`:
```php
'connections' => [
    'custom_connection' => [
        'driver' => 'sqlite',
        'url' => env('MAILBOX_DB_URL'),
        'database' => env('MAILBOX_DB_DATABASE', database_path('mailbox.sqlite')),
        'prefix' => '',
        'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
    ],
]
```
And then set new custom connection in `.env`

```env
MAILBOX_DB_CONNECTION=custom_connection
```

**Or use your main database connection:**

```env
MAILBOX_DB_CONNECTION=mysql  # or pgsql, sqlsrv, etc.
```

## Usage

### Dashboard Overview

The mailbox dashboard provides a modern email client interface with:

- **Message list** — All captured emails sorted newest-first
- **Preview pane** — View email content (HTML, plain text, raw source)
- **Recipient filtering** — Filter by To, Cc, Bcc recipients
- **Read/Unread tracking** — Mark messages as seen
- **Attachment viewer** — Download email attachments
- **Test email sender** — Send sample emails for testing
- **Clear inbox** — Remove all captured messages with confirmation
- **Delete messages** — Remove individual messages with confirmation

### Accessing the Dashboard

Navigate to your configured route (default: `/mailbox`):

```
http://localhost/mailbox
http://yourapp.test/mailbox
https://staging.yourapp.com/mailbox
```

### API Endpoints

The package registers the following HTTP endpoints:

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/mailbox` | Load the dashboard (Inertia page) |
| `DELETE` | `/mailbox/messages` | Delete all captured messages |
| `DELETE` | `/mailbox/messages/{id}` | Delete a specific message |
| `POST` | `/mailbox/test-email` | Send a test email |
| `POST` | `/mailbox/messages/{id}/seen` | Mark a message as read/unread |

**Example: Mark Message as Read**

```javascript
// From frontend (Inertia)
router.post(`/mailbox/messages/${messageId}/seen`, { seen: true })

// Response: Updated message object
{
  "id": "msg_123",
  "seen_at": "2025-11-19T10:30:00.000000Z",
  ...
}
```

**Example: Delete a Specific Message**

```javascript
// From frontend (Inertia)
router.delete(`/mailbox/messages/${messageId}`)

// Response:
{
  "status": "deleted"
}
```

**Example: Clear All Messages**

```javascript
// From frontend (Inertia)
router.delete('/mailbox/messages')

// Response: Empty JSON
{}
```

### Sending Test Emails

Use the "Send Test Email" button in the dashboard, or programmatically:

```php
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Message;

Mail::raw('This is a test email', function (Message $message) {
    $message->to('recipient@example.com')
        ->subject('Test Email')
        ->from('sender@example.com');
});
```

### Message Capture Flow

1. **Your application sends an email** using Laravel's `Mail` facade
2. **Mailbox Transport intercepts** the outgoing message
3. **Message is normalized** into a structured format (headers, body, attachments)
4. **Stored in configured driver** (database or file system)
5. **Displayed in dashboard** with full content and metadata

**Behind the scenes:**

```php
// MailboxTransport::doSend()
$payload = MessageNormalizer::normalize($original, $envelope, $raw, true);
$key = $this->mailbox->store($payload);
```

**Stored payload structure:**

```json
{
  "from": "sender@example.com",
  "to": ["recipient@example.com"],
  "cc": [],
  "bcc": [],
  "subject": "Test Email",
  "date": "2025-11-19T10:30:00+00:00",
  "text": "Plain text body",
  "html": "<html>HTML body</html>",
  "attachments": [],
  "raw": "Full RFC 822 message",
  "timestamp": 1732017000,
  "saved_at": "2025-11-19T10:30:00.000000Z",
  "seen_at": null
}
```

### Attachments

Email attachments are captured and stored alongside the message. Access them via:

- **Dashboard UI:** Click "View" on the attachment
- **Direct download:** `/mailbox/messages/{id}/attachments/{index}`

**Attachment metadata:**

```json
{
  "filename": "document.pdf",
  "content_type": "application/pdf",
  "size": 102400,
  "content": "base64-encoded-data"
}
```

### Clearing the Inbox

**From Dashboard:**
- Click the "Clear Inbox" button in the filter bar (with trash icon)
- Confirm the action in the dialog that appears
- All messages will be permanently deleted

**From Message Detail:**
- Click the trash icon button in the top-right corner of the message preview
- Confirm the deletion in the dialog
- The specific message will be permanently deleted

**Programmatically:**

```php
use Redberry\MailboxForLaravel\Facades\Mailbox;

// Clear all messages
Mailbox::clearAll();

// Delete a specific message
Mailbox::delete($messageId);
```

**Via Artisan (future feature):**

```bash
php artisan mailbox:clear
```

> **Note:** Both clear and delete operations show confirmation dialogs in the UI to prevent accidental data loss.

## Frontend Integration

### Architecture Overview

The mailbox uses **Inertia.js + Vue 3** for its dashboard, but operates in **complete isolation** from your host application's frontend stack.

**Key isolation mechanisms:**

1. **Namespaced components** — All Inertia renders use the `mailbox::` prefix
2. **Dedicated middleware** — `mailbox.inertia` middleware handles Inertia responses separately
3. **Scoped assets** — Built to `public/vendor/mailbox/` with independent manifest
4. **Separate Vue instance** — Creates its own app, doesn't mount to your app's root

### How It Works

**Backend (Controller):**

```php
use Inertia\Inertia;

return Inertia::render('mailbox::Dashboard', [
    'messages' => $messages,
    'title' => 'Mailbox for Laravel',
]);
```

**Frontend (Entry Point):**

```javascript
// resources/js/dashboard.js
import { createInertiaApp } from '@inertiajs/vue3'

createInertiaApp({
    resolve: (name) => {
        const pageName = name.replace(/^mailbox::/, '')
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })
        return pages[`./Pages/${pageName}.vue`]
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el)
    },
})
```

### Compatibility

✅ **Works alongside your existing frontend:**

- **Blade-only apps** — No conflicts, package bundles its own JS
- **Vue without Inertia** — Separate Vue instances, no shared state
- **React** — No conflicts with React or other frameworks
- **Existing Inertia apps** — Uses different middleware and namespaces
- **Livewire** — Fully compatible

### Asset Building

Pre-built assets are included, but you can rebuild if needed:

```bash
# Install dependencies
npm install

# Build for production
npm run build

# Watch for changes (development)
npm run dev
```

Built assets output to:
- `public/vendor/mailbox/manifest.json`
- `public/vendor/mailbox/assets/dashboard-[hash].js`
- `public/vendor/mailbox/assets/dashboard-[hash].css`

## Storage Drivers

### Database Driver (Default)

Stores messages in a dedicated SQLite database (`database/mailbox.sqlite`).

**Pros:**
- Fast queries and filtering
- ACID compliance
- Supports complex queries
- Easy to inspect with database tools

**Configuration:**

```env
MAILBOX_STORE_DRIVER=database
MAILBOX_DB_CONNECTION=mailbox
MAILBOX_DB_TABLE=mailbox_messages
```

### File Driver

Stores each message as a JSON file in `storage/app/mailbox/`.

**Pros:**
- No database required
- Easy to inspect/debug
- Portable (copy files between environments)

**Configuration:**

```env
MAILBOX_STORE_DRIVER=file
MAILBOX_FILE_PATH=/path/to/storage/mailbox
```

### Custom Drivers

Implement the `MessageStore` contract:

```php
namespace App\Storage;

use Redberry\MailboxForLaravel\Contracts\MessageStore;

class RedisMessageStore implements MessageStore
{
    public function store(array $payload): string|int
    {
        // Implementation
    }

    public function get(string|int $key): ?array
    {
        // Implementation
    }

    // ... other methods
}
```

Register in `config/mailbox.php`:

```php
'store' => [
    'resolvers' => [
        'redis' => fn() => new \App\Storage\RedisMessageStore,
    ],
],
```

Use via `.env`:

```env
MAILBOX_STORE_DRIVER=redis
```

## Authorization & Security

### Gate-Based Authorization

By default, access is controlled via Laravel's Gate system using the `viewMailbox` ability.

**Define in `AuthServiceProvider`:**

```php
use Illuminate\Support\Facades\Gate;

public function boot()
{
    Gate::define('viewMailbox', function ($user) {
        return $user->isAdmin();
    });
}
```

**Or use a Policy:**

```php
Gate::define('viewMailbox', [MailboxPolicy::class, 'view']);
```

### Public Access (Development Only)

To disable authorization (e.g., for local development):

```php
// In AuthServiceProvider::boot()
Gate::define('viewMailbox', fn () => true);
```

Or create a custom gate in config:

```env
MAILBOX_GATE=alwaysAllow
```

```php
Gate::define('alwaysAllow', fn () => true);
```

### Production Considerations

⚠️ **Security warnings:**

- Captured emails may contain sensitive data (passwords, tokens, etc.)
- Always require authentication in production
- Consider IP whitelisting for staging environments
- Use `MAILBOX_ENABLED=false` in production unless necessary

**Recommended production config:**

```env
# Disable by default
MAILBOX_ENABLED=false

# Enable only for admins
MAILBOX_GATE=viewMailbox

# Redirect unauthorized users
MAILBOX_REDIRECT=/login
```

## Testing

### Running Tests

The package includes comprehensive test coverage using **Pest**.

```bash
# Run all tests
composer test

# Run with coverage report
composer test-coverage

# Run only unit tests
./vendor/bin/pest --filter=Unit

# Run only feature tests
./vendor/bin/pest --filter=Feature
```

### Test Coverage

Target coverage: **90%+ lines**, **80%+ branches**

```bash
# Generate HTML coverage report
./vendor/bin/pest --coverage --coverage-html=coverage

# Fail if coverage drops below threshold
./vendor/bin/pest --coverage --min=90
```

### Static Analysis

Run PHPStan for type safety:

```bash
composer analyse

# Or directly
./vendor/bin/phpstan analyse
```

### Code Style

Format code with Laravel Pint:

```bash
composer format

# Or directly
./vendor/bin/pint
```

### Writing Tests

Tests follow the repository structure:

```
tests/
├── Architecture/    # Arch tests for architectural rules
├── Feature/         # Integration tests for controllers, commands
└── Unit/            # Unit tests for services, transport, storage
```

**Example test:**

```php
use Redberry\MailboxForLaravel\CaptureService;

it('stores a message and returns a key', function () {
    $service = app(CaptureService::class);
    
    $key = $service->store([
        'from' => 'sender@example.com',
        'subject' => 'Test',
        'raw' => 'Full message',
    ]);
    
    expect($key)->toBeString();
    expect($service->get($key))->toBeArray();
});
```

## Development

### Setting Up a Development Environment

The package uses **Orchestra Testbench Workbench** for local development.

**1. Clone the repository:**

```bash
git clone https://github.com/RedberryProducts/mailbox-for-laravel.git
cd mailbox-for-laravel
```

**2. Install dependencies:**

```bash
composer install
npm install
```

**3. Set up the database:**

```bash
php artisan mailbox:install --dev
```

**4. Start the development server:**

```bash
# Terminal 1: Laravel dev server
php artisan serve

# Terminal 2: Vite dev server (hot reload)
npm run dev
```

**5. Visit the dashboard:**

```
http://localhost:8000/mailbox
```

### Workbench

The package includes a Workbench app for testing integration:

```bash
# Run Workbench
php artisan serve

# Access at http://localhost:8000
```

**Workbench configuration:**

```
workbench/
├── app/          # Test application code
├── bootstrap/    # Workbench bootstrap
├── config/       # Test config files
├── database/     # Test migrations/seeders
└── routes/       # Test routes
```

### Building Frontend Assets

**Development mode (with hot reload):**

```bash
npm run dev
```

**Production build:**

```bash
npm run build
```

**Link assets for development:**

```bash
php artisan mailbox:install --dev
```

This creates symlinks instead of copying files, allowing hot module replacement.

### Contribution Guidelines

We welcome contributions! Please follow these guidelines:

**Code Standards:**
- Follow **Laravel coding style** (PSR-12)
- Run `composer format` before committing
- Ensure PHPStan passes: `composer analyse`
- Write tests for new features (90%+ coverage required)

**Pull Request Process:**

1. **Fork** the repository
2. **Create a feature branch:** `git checkout -b feature/my-feature`
3. **Make changes** with tests and documentation
4. **Run tests:** `composer test && composer analyse`
5. **Format code:** `composer format`
6. **Commit** with conventional commit messages: `feat: add message filtering`
7. **Push** and **open a Pull Request**

**Commit Convention:**

- `feat:` — New features
- `fix:` — Bug fixes
- `docs:` — Documentation changes
- `test:` — Test additions/changes
- `refactor:` — Code refactoring
- `chore:` — Maintenance tasks

**Branch Naming:**

- `feature/description` — New features
- `fix/description` — Bug fixes
- `docs/description` — Documentation updates

## Changelog

All notable changes to this project are documented in [CHANGELOG.md](CHANGELOG.md).

## Security Vulnerabilities

If you discover a security vulnerability within this package, please email **security@redberry.ge** instead of using the issue tracker. All security vulnerabilities will be promptly addressed.

## Credits

- **[Nika Jorjoliani](https://github.com/nikajorjoliani)** — Creator & Maintainer
- **[Redberry](https://redberry.international)** — Development Agency
- **[All Contributors](https://github.com/RedberryProducts/mailbox-for-laravel/graphs/contributors)**

## License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md) for more information.

