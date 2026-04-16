---
description: Rules for adding or modifying storage drivers
globs: src/Storage/**/*.php, src/Contracts/**/*.php, src/StoreManager.php
---

# Storage Driver Rules

When adding or modifying a storage driver:

1. Implement `Contracts\MessageStore` interface — 10 methods: `store`, `find`, `findIdByMessageId`, `paginate`, `count`, `update`, `delete`, `purgeOlderThan`, `idsOlderThan`, `clear`. `idsOlderThan` is what `CaptureService` uses to cascade attachment cleanup before purge — never skip it. `findIdByMessageId` enables write-path idempotency (dedup by RFC Message-ID header).
2. Register in `StoreManager` via a `createXxxDriver()` method (for built-in drivers) or via config resolvers (for custom drivers)
3. `store()` receives a payload array with at least: `id`, `timestamp`, `saved_at`
4. `paginate()` must return results in **newest-first** ordering
5. Default driver is `sqlite` (`DatabaseMessageStore` with dedicated SQLite at `storage/app/mailbox/mailbox.sqlite`). `database` is an alias for bring-your-own-connection users.
6. `FileStorage` uses JSON files on disk at a configurable path
7. Driver-specific config lives under `config('mailbox.store.{driver_name}')`
8. Write unit tests mirroring `tests/Unit/Contracts/MessageStoreContractTest.php` — this is the contract test that both drivers must pass
9. Never reference the HTTP or Transport layer from storage implementations
10. Storage drivers must be stateless — no cached state between requests

## Driver pairs (message store ↔ attachment store)

Every `MessageStore` driver should be paired with a matching `Contracts\AttachmentStore` implementation so users get consistent behavior across the storage stack.

11. Implement `Contracts\AttachmentStore` (8 methods: `store`, `find`, `findByMessage`, `findByCid`, `delete`, `deleteByMessage`, `getContent`, `clear`) and return `DTO\StoredAttachment` from every read.
12. When registering a custom `MessageStore` driver via `mailbox.store.resolvers`, also bind the matching `Contracts\AttachmentStore` in the same service provider — otherwise the package falls back to `DatabaseAttachmentStore`, which forces a DB dependency you may not want.
13. Both halves of the pair share the same content disk (`mailbox.attachments.disk` + `mailbox.attachments.path`); only the metadata storage differs (DB rows vs. JSON sidecars vs. your custom backend).
14. `CaptureService` cascades attachment cleanup automatically — never duplicate that logic inside a `MessageStore` implementation.
15. Mirror `tests/Unit/Contracts/AttachmentStoreContractTest.php` for any new attachment driver — the dataset-driven contract test exercises store/find/delete/clear/cid lookup against every driver in one place.
