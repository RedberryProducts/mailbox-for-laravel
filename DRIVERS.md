# Writing a Custom Storage Driver

This guide explains how to build a custom storage driver for Mailbox for Laravel. A complete driver is a **pair**: a `MessageStore` for message metadata and an `AttachmentStore` for attachment metadata + content. Both contracts must be implemented for a fully functional driver.

## MessageStore contract

Implement `Redberry\MailboxForLaravel\Contracts\MessageStore`. The interface has 10 methods:

```php
interface MessageStore
{
    // Write path
    public function store(array $payload): string;
    public function update(string $id, array $changes): ?array;
    public function delete(string $id): void;
    public function clear(): void;
    public function purgeOlderThan(int $seconds): void;

    // Read path
    public function find(string $id): ?array;
    public function findIdByMessageId(string $messageId): ?string;
    public function paginate(int $page, int $perPage, ?string $search = null): array;
    public function count(?string $search = null): int;
    public function idsOlderThan(int $seconds): array;
}
```

### Method details

**`store(array $payload): string`** — Persist a message payload. The payload always includes `id` (a 26-char ULID string), `timestamp` (Unix int), and `saved_at` (ISO 8601 string). These are supplied by `CaptureService` — your driver must never mint its own IDs. Return the `id` verbatim. If a record with the same `id` already exists, update it (upsert semantics).

**`find(string $id): ?array`** — Retrieve a single payload by its ULID `id`. Return `null` if not found.

**`findIdByMessageId(string $messageId): ?string`** — Look up the ULID `id` for a message with the given RFC 822 `Message-ID` header value (e.g. `<abc@example.com>`). Used by `CaptureService` for write-path idempotency — duplicate emails reuse the existing record instead of creating a new one. Return `null` if no match.

**`paginate(int $page, int $perPage, ?string $search = null): array`** — Return a page of payloads, sorted **newest-first** by `timestamp`. When `$search` is non-null and non-empty, filter results. Delegate filtering to the injected `MessageSearch` strategy (see below).

**`count(?string $search = null): int`** — Total message count, optionally filtered by search.

**`update(string $id, array $changes): ?array`** — Merge `$changes` into the existing payload. Return the updated payload, or `null` if not found.

**`delete(string $id): void`** — Remove a single message by `id`. Does not cascade to attachments — `CaptureService` handles that.

**`purgeOlderThan(int $seconds): void`** — Remove all messages with `timestamp` older than `now - $seconds`.

**`idsOlderThan(int $seconds): array`** — Return the `id` strings of messages older than the threshold. `CaptureService` uses this to cascade attachment cleanup before calling `purgeOlderThan`.

**`clear(): void`** — Remove all messages.

### Search integration

Drivers should accept a `MessageSearch` instance (via constructor injection) and delegate search filtering to it rather than implementing search logic directly. This keeps search behavior consistent across drivers.

For SQL-based drivers, use `$this->search->applyToQuery($query, $needle)`. For in-memory drivers (like the file driver), use `$this->search->matches($payload, $needle)`.

```php
use Redberry\MailboxForLaravel\Contracts\MessageSearch;
use Redberry\MailboxForLaravel\Search\DefaultMessageSearch;

class RedisMessageStore implements MessageStore
{
    public function __construct(
        private readonly MessageSearch $search = new DefaultMessageSearch,
    ) {}
}
```

## AttachmentStore contract

Implement `Redberry\MailboxForLaravel\Contracts\AttachmentStore`. The interface has 8 methods:

```php
interface AttachmentStore
{
    public function store(string $messageId, AttachmentData $data): StoredAttachment;
    public function find(string $id): ?StoredAttachment;
    public function findByMessage(string $messageId): array;
    public function findByCid(string $messageId, string $cid): ?StoredAttachment;
    public function delete(string $id): void;
    public function deleteByMessage(string $messageId): void;
    public function getContent(StoredAttachment $attachment): ?string;
    public function clear(): void;
}
```

### Key points

- `store()` receives an `AttachmentData` DTO (filename, mimeType, size, base64 content, cid, isInline) and returns a `StoredAttachment` value object.
- Attachment **content bytes** are written to the configured filesystem disk (`mailbox.attachments.disk`). Your driver stores the metadata; the disk stores the binary.
- `findByCid()` resolves inline images by Content-ID — the `CidRewriter` uses this to rewrite `cid:` references in HTML bodies to downloadable routes.
- `deleteByMessage()` removes all attachments for a given message. Called by `CaptureService` during cascade cleanup.
- `getContent()` reads the base64-encoded content from the disk. Return `null` if the file is missing.
- All read methods return `StoredAttachment` DTOs — never expose your internal model.

## Registering a custom driver

### 1. Implement both contracts

```php
namespace App\Mailbox;

use Redberry\MailboxForLaravel\Contracts\MessageStore;
use Redberry\MailboxForLaravel\Contracts\AttachmentStore;

class RedisMessageStore implements MessageStore { /* ... */ }
class RedisAttachmentStore implements AttachmentStore { /* ... */ }
```

### 2. Register via config resolver

In `config/mailbox.php`:

```php
'store' => [
    'driver' => 'redis',
    'resolvers' => [
        'redis' => fn ($app) => new \App\Mailbox\RedisMessageStore(
            $app->make(\Redberry\MailboxForLaravel\Contracts\MessageSearch::class),
        ),
    ],
],
```

### 3. Bind the matching AttachmentStore

In your service provider:

```php
use Redberry\MailboxForLaravel\Contracts\AttachmentStore;

public function register(): void
{
    $this->app->singleton(AttachmentStore::class, function ($app) {
        return new \App\Mailbox\RedisAttachmentStore(
            config('mailbox.attachments.disk', 'mailbox'),
            config('mailbox.attachments.path', 'attachments'),
        );
    });
}
```

If you only bind a custom `MessageStore` without a matching `AttachmentStore`, the package falls back to `DatabaseAttachmentStore` — which requires a database connection you may not want.

## CaptureService and cascade cleanup

`CaptureService` is the single entry point for all storage operations. It orchestrates both the `MessageStore` and `AttachmentStore` and handles cascade cleanup automatically:

- `delete($id)` — deletes attachments first, then the message
- `clearAll()` — clears all attachments, then all messages
- `purgeOlderThan($seconds)` — collects victim IDs via `idsOlderThan()`, deletes their attachments, then purges messages

Your drivers should **never** cascade deletes internally. Let `CaptureService` handle it.

## PaginatedMessages DTO

`CaptureService::list()` returns a `PaginatedMessages` value object:

```php
class PaginatedMessages extends Data
{
    public function __construct(
        public readonly array $data,           // MailboxMessageData[]
        public readonly int $total,
        public readonly int $perPage,
        public readonly int $currentPage,
        public readonly bool $hasMore,
        public readonly ?int $latestTimestamp,
    ) {}
}
```

This wrapping happens in `CaptureService`, not in drivers. Your `MessageStore::paginate()` just returns a raw `array<int, array<string, mixed>>`.

## Testing your driver

Mirror the contract tests that ship with the package:

- `tests/Unit/Contracts/MessageStoreContractTest.php` — validates method signatures and return types
- `tests/Unit/Contracts/AttachmentStoreContractTest.php` — exercises store/find/delete/clear/cid lookup

The attachment contract test uses a Pest dataset to run every scenario against both the database and file drivers. Add your driver to the dataset to get full coverage for free.

## Reference implementations

Study the built-in drivers for patterns:

- **Eloquent-backed**: `src/Storage/DatabaseMessageStore.php` + `src/Storage/DatabaseAttachmentStore.php`
- **File-backed**: `src/Storage/FileStorage.php` + `src/Storage/FileAttachmentStore.php`
