# Architecture

## Mail capture pipeline

```
MailboxTransport → MessageNormalizer → CaptureService → MessageStore driver
```

1. **`MailboxTransport`** (`src/Transport/`) — registered as the `mailbox` mail driver. Intercepts sent mail and optionally decorates another transport. Toggleable.
2. **`MessageNormalizer`** (`src/Support/`) — converts Symfony `Email` / `RawMessage` into a canonical array, extracting attachments as `AttachmentData` DTOs.
3. **`CaptureService`** (`src/CaptureService.php`) — high-level API: `store`, `list`, `find`, `update`, `delete`, `purge`. Returns `MailboxMessageData` DTOs. Storage-driver-agnostic. Cascades attachment cleanup automatically on `delete`, `clearAll`, `purgeOlderThan`.
4. **`StoreManager`** (`src/StoreManager.php`) — extends Laravel's `Manager`. Resolves driver: `sqlite` (default — auto-configured dedicated SQLite file), `database` (same Eloquent store, but bring-your-own connection from `config/database.php`), or `file` (JSON on disk).
5. **Storage drivers** (`src/Storage/`) — `DatabaseMessageStore` (Eloquent, used by both `sqlite` and `database` drivers; the SQLite variant auto-provisions `storage/app/mailbox/mailbox.sqlite`) and `FileStorage` (JSON on disk). Both implement `Contracts\MessageStore`.
6. **Attachment store pair** — `DatabaseAttachmentStore` or `FileAttachmentStore` is bound alongside the chosen `MessageStore`. Both implement `Contracts\AttachmentStore` and return `StoredAttachment` DTOs. `CidRewriter` resolves inline `cid:` references through the contract regardless of driver.

## Self-contained Vue dashboard

The dashboard is **completely isolated** from the host app:

- The Blade root view (`mailbox::app`) embeds the initial page payload as a `<script type="application/json">` blob.
- `dashboard.js` parses it, hydrates a shared reactive store, and mounts a plain Vue 3 app.
- All subsequent interactions (polling, search, pagination, delete) hit the same `MailboxController` — HTML on first load, JSON on AJAX (`$request->wantsJson()`).
- Own Vite build output at `public/vendor/mailbox/`. Own Vue app instance. Zero host-app coupling.

For the full architectural deep-dive, see `ARCHITECTURE.md` at the package root (`vendor/redberry/mailbox-for-laravel/ARCHITECTURE.md` in consumer apps).

## Extending with a custom driver

Message store and attachment store are paired — registering one without the other forces the package to fall back to `DatabaseAttachmentStore`, which silently introduces a database dependency you may not want.

### 1. Implement both contracts

- **`Contracts\MessageStore` — 10 methods**: `store`, `find`, `findIdByMessageId`, `paginate`, `count`, `update`, `delete`, `purgeOlderThan`, `idsOlderThan`, `clear`.
  - `findIdByMessageId` powers write-path idempotency (dedup by RFC 822 Message-ID header).
  - `idsOlderThan` is what `CaptureService` calls to cascade attachment cleanup before purging messages — never collapse it into `purgeOlderThan`.
  - `paginate()` must return newest-first.
- **`Contracts\AttachmentStore` — 8 methods**: `store`, `find`, `findByMessage`, `findByCid`, `delete`, `deleteByMessage`, `getContent`, `clear`. Every read must return a `DTO\StoredAttachment`.

Both halves of the pair share the same content disk (`mailbox.attachments.disk` + `mailbox.attachments.path`); only metadata storage differs.

### 2. Register through config resolvers, not `Manager::extend()`

The package convention is config-driven. In `config/mailbox.php`:

```php
'store' => [
    'driver' => 'redis',
    'resolvers' => [
        'redis' => fn ($app) => new \App\Mailbox\RedisMessageStore($app['redis']),
    ],
],
```

Then bind the matching `Contracts\AttachmentStore` in your `AppServiceProvider`:

```php
$this->app->bind(
    \Redberry\MailboxForLaravel\Contracts\AttachmentStore::class,
    \App\Mailbox\RedisAttachmentStore::class,
);
```

Note the config key is **`mailbox.store`** (singular) with a `resolvers` sub-key — not `mailbox.stores`.

### 3. Stay stateless

Storage drivers must not cache state between requests. `CaptureService` already cascades attachment cleanup on `delete`, `clearAll`, and `purgeOlderThan` — never duplicate that logic inside a `MessageStore` implementation.

### 4. Mirror the contract tests

The package's own `tests/Unit/Contracts/MessageStoreContractTest.php` and `AttachmentStoreContractTest.php` are dataset-driven and exercise every driver against the same expectations. A new driver should be added to those datasets so it passes the same test surface as the built-ins.
