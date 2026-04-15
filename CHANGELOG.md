# Changelog

All notable changes to `mailbox-for-laravel` will be documented in this file.

## [Unreleased]

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
