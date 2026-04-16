# Upgrading from v1.x to v2.0.0

This guide covers every breaking change in v2.0.0 and what you need to do about each one. The package captures ephemeral development mail, so the recommended upgrade path is fast and non-destructive to your application code.

## Quick path (recommended)

If you don't need to preserve captured messages from v1:

```bash
composer update redberry/mailbox-for-laravel
php artisan mailbox:upgrade
```

The upgrade command detects stale config keys, refreshes the database schema, and re-publishes assets. All done.

## Step-by-step manual upgrade

### 1. Update the package

```bash
composer update redberry/mailbox-for-laravel
```

### 2. Re-publish the config

The config structure changed significantly. Re-publish to get the new keys:

```bash
php artisan vendor:publish --tag=mailbox-config --force
```

If you had customizations in the old config, re-apply them after publishing. The renamed keys are:

| v1 key | v2 key |
|---|---|
| `mailbox.route` | `mailbox.path` |
| `mailbox.retention.seconds` | `mailbox.retention` (flat int, seconds) |
| `mailbox.pagination.per_page` | `mailbox.per_page` (flat int) |

### 3. Update environment variables

If your `.env` uses any of these, rename them:

| v1 variable | v2 variable |
|---|---|
| `MAILBOX_DASHBOARD_ROUTE` | `MAILBOX_PATH` |
| `MAILBOX_FILE_PATH` | `MAILBOX_STORE_FILE_PATH` |
| `MAILBOX_DB_CONNECTION` | `MAILBOX_STORE_DATABASE_CONNECTION` |
| `MAILBOX_DB_TABLE` | `MAILBOX_STORE_DATABASE_TABLE` |
| `MAILBOX_REDIRECT` | `MAILBOX_UNAUTHORIZED_REDIRECT` |

New variables in v2 (all have sensible defaults):

| Variable | Default | Purpose |
|---|---|---|
| `MAILBOX_STORE_DRIVER` | `sqlite` | Was `database` — renamed for clarity |
| `MAILBOX_DECORATE` | `null` | Forward captured mail to another mailer for real delivery |
| `MAILBOX_RETENTION_SCHEDULE` | `true` | Auto-register daily retention purge |
| `MAILBOX_ATTACHMENTS_ENABLED` | `true` | Toggle attachment capture |
| `MAILBOX_ATTACHMENTS_DISK` | `mailbox` | Filesystem disk for attachment content |
| `MAILBOX_POLLING_ENABLED` | `true` | Dashboard live-update polling |
| `MAILBOX_POLLING_INTERVAL` | `5000` | Polling interval in milliseconds |

### 4. Refresh the database schema

Message IDs changed from auto-increment integers to 26-character ULIDs. The attachment table's foreign key changed to match. The simplest path is to drop and recreate:

```bash
php artisan mailbox:install --refresh
```

This drops both `mailbox_messages` and `mailbox_attachments` and recreates them with the v2 schema. Captured messages from v1 are lost — they were development emails, not production data.

### 5. Re-publish assets

```bash
php artisan mailbox:install
```

Or if you use dev mode:

```bash
php artisan mailbox:install --dev
```

### 6. Default driver renamed: `database` → `sqlite`

The default storage driver is now called `sqlite` instead of `database`. If your `.env` explicitly sets `MAILBOX_STORE_DRIVER=database`, it still works — `database` is a supported alias. But the config file default is now `sqlite`. No action required unless you want to update your `.env` for clarity.

## Breaking changes for custom code

### MessageStore contract

The `MessageStore` interface gained two new methods that custom drivers must implement:

```php
// Added in v2 — unified attachment handling
public function idsOlderThan(int $seconds): array;

// Added in v2 — write-path idempotency
public function findIdByMessageId(string $messageId): ?string;
```

The `store()` return type narrowed from `string|int` to `string`. All IDs are now ULID strings.

### AttachmentStore contract (new)

v2 introduced `Contracts\AttachmentStore` — a driver-agnostic interface for attachment persistence. If you had code that depended on the old `Storage\AttachmentStore` class directly, switch to type-hinting `Contracts\AttachmentStore`. The old class is a deprecated shim and will be removed in v2.1.

Attachment store methods now return `DTO\StoredAttachment` value objects instead of `MailboxAttachment` Eloquent models. Property access uses camelCase (`->mimeType`, `->isInline`).

### MessageSearch contract (new)

Search is now a pluggable strategy. The default `DefaultMessageSearch` searches `subject`, `from`, `to`, `html`, and `text` fields. Custom search implementations should implement `Contracts\MessageSearch`.

### PaginatedMessages DTO (new)

`CaptureService::list()` now returns a `PaginatedMessages` value object instead of a loose array. Access properties directly: `$result->data`, `$result->total`, `$result->hasMore`.

### Removed config keys

The following keys were declared in v1 but never enforced. They have been removed:

- `mailbox.attachments.max_size`
- `mailbox.attachments.max_total_size`
- `mailbox.attachments.allowed_mime_types`

### Route name change

The clear inbox endpoint changed from `POST /mailbox/clear` to `DELETE /mailbox/messages`. The route name changed from `mailbox.clear` to `mailbox.messages.clear`.

## Need help?

If you run into issues upgrading, please [open an issue](https://github.com/RedberryProducts/mailbox-for-laravel/issues) with your Laravel version and the error you're seeing.
