# Changelog

All notable changes to `mailbox-for-laravel` will be documented in this file.

## [Unreleased]

### v2.0.0-dev — Canonical Message IDs

#### Added
- `CaptureService::store()` now generates a canonical `Str::ulid()` id (26-char Crockford base32, time-ordered, URL-safe) when the payload doesn't already carry one. Replay-style callers can still pre-populate `$payload['id']` and it will be preserved verbatim.
- New `CaptureServiceTest` cases covering both id generation and caller-supplied id preservation.

#### Changed
- `mailbox_messages.id` is now a ULID primary key (was `bigIncrements`). Messages and attachments share the same id shape across drivers.
- `mailbox_attachments.message_id` is now a `ulid` column (was `unsignedBigInteger`) so the cascade FK matches.
- `MailboxMessage` Eloquent model sets `$incrementing = false` + `$keyType = 'string'`.
- `MailboxMessageFactory` mints ids with `Str::ulid()`.
- `DatabaseMessageStore` and `FileStorage` no longer mint their own ids; they throw `InvalidArgumentException` if `CaptureService` didn't supply one. Their `generateId()` helpers were removed.
- `SendTestMailControllerTest` now asserts the ULID id shape for both drivers.

#### Breaking Changes
- `MessageStore::store()` return type narrowed from `string|int` to `string`. Custom drivers must update their signature.
- Primary key column type changed: any v1 deployment that wants to preserve its inbox must migrate manually. Because the package captures ephemeral dev-mail, the documented upgrade path is `php artisan mailbox:install --refresh` — drops and recreates both tables with the new schema.
- The previous file-driver id format (`email_{timestamp}_{sha1}`) is gone. Hand-built URLs or fixtures relying on that shape must be regenerated.

### v2.0.0-dev — Unified Attachment Handling

#### Added
- New `Redberry\MailboxForLaravel\Contracts\AttachmentStore` interface — driver-agnostic surface for attachment persistence (8 methods).
- New `Redberry\MailboxForLaravel\DTO\StoredAttachment` value object — what every driver returns, replacing leakage of the `MailboxAttachment` Eloquent model across boundaries.
- New `Redberry\MailboxForLaravel\Storage\DatabaseAttachmentStore` — DB-backed implementation (cascade FK preserved).
- New `Redberry\MailboxForLaravel\Storage\FileAttachmentStore` — filesystem-only implementation backed by per-message JSON sidecars.
- New `MessageStore::idsOlderThan(int $seconds): array` contract method — lets `CaptureService` cascade attachment cleanup before purging.
- New shared contract test `tests/Unit/Contracts/AttachmentStoreContractTest.php` exercising both drivers through the same scenarios.

#### Changed
- `MailboxServiceProvider` now binds `Contracts\AttachmentStore` based on the active `mailbox.store.driver` (database ⇒ `DatabaseAttachmentStore`, file ⇒ `FileAttachmentStore`).
- `CaptureService` now takes an optional `Contracts\AttachmentStore` and cascades cleanup from `delete()`, `clearAll()`, and `purgeOlderThan()`.
- `MailboxTransport`, `AttachmentController`, `MailboxController`, `CidRewriter`, `DeleteMailboxMessageController`, `ClearMailboxController`, and `mailbox:clear` now depend on `Contracts\AttachmentStore` (or delegate through `CaptureService`).

#### Breaking Changes
- `Redberry\MailboxForLaravel\Storage\AttachmentStore` is now a `@deprecated` shim extending `DatabaseAttachmentStore`. Type-hint `Contracts\AttachmentStore` instead. Will be removed in v2.1.
- Attachment-store methods now return `StoredAttachment` DTOs instead of the `MailboxAttachment` Eloquent model. Read attribute names with camelCase (`->mimeType`, `->isInline`).
- `AttachmentStore::deleteAll()` renamed to `clear()` (mirrors `MessageStore::clear`).
- `MessageStore` contract gained `idsOlderThan(int $seconds): array`. Custom drivers must implement it.
- **Config keys renamed** (published `config/mailbox.php` must be re-published or migrated):
  - `mailbox.route` → `mailbox.path`
  - `mailbox.retention.seconds` → `mailbox.retention` (now a flat int)
  - `mailbox.pagination.per_page` → `mailbox.per_page` (now a flat int)
- **Environment variables renamed** — update `.env` accordingly:
  - `MAILBOX_DASHBOARD_ROUTE` → `MAILBOX_PATH`
  - `MAILBOX_FILE_PATH` → `MAILBOX_STORE_FILE_PATH`
  - `MAILBOX_DB_CONNECTION` → `MAILBOX_STORE_DATABASE_CONNECTION`
  - `MAILBOX_DB_TABLE` → `MAILBOX_STORE_DATABASE_TABLE`
  - `MAILBOX_REDIRECT` → `MAILBOX_UNAUTHORIZED_REDIRECT`
- **Removed unimplemented attachment limit keys** — `mailbox.attachments.max_size`, `mailbox.attachments.max_total_size`, and `mailbox.attachments.allowed_mime_types` were declared in the config but never enforced. Removed to avoid misleading users.

### Added
- Added Testing Assertions API (`src/Testing/`) for verifying captured emails in test suites
  - `InteractsWithMailbox` trait — auto-clears mailbox between tests, provides `$this->mailbox()`
  - `MailboxAssertions` — collection-level: `assertSent()`, `assertNotSent()`, `assertNothingSent()`, `assertSentCount()`, `assertSentTo()`, `assertNotSentTo()`, `sent()`, `firstSent()`
  - `PendingMailboxMessageAssertion` — per-message fluent: `assertHasSubject()`, `assertSeeInHtml()`, `assertHasTo()`, `assertHasAttachment()`, and more
  - Facade support: `Mailbox::assertSent()`, `Mailbox::assertSentTo()`, etc.
  - Works with both Pest and PHPUnit
- Added "Clear Inbox" functionality to delete all messages via DELETE /mailbox/messages
- Added "Delete Single Message" functionality to delete individual messages via DELETE /mailbox/messages/{id}
- Added AlertDialog component suite using radix-vue for confirmation dialogs
- Added trash icon buttons with confirmation dialogs to prevent accidental deletion

### Changed
- Changed ClearMailboxController route from POST /mailbox/clear to DELETE /mailbox/messages for RESTful compliance
- Updated route names: `mailbox.clear` is now `mailbox.messages.clear`

### Breaking Changes
- The clear inbox endpoint has changed from POST /mailbox/clear to DELETE /mailbox/messages
- Route name changed from `mailbox.clear` to `mailbox.messages.clear`
