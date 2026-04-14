---
description: Rules for adding or modifying storage drivers
globs: src/Storage/**/*.php, src/Contracts/**/*.php, src/StoreManager.php
---

# Storage Driver Rules

When adding or modifying a storage driver:

1. Implement `Contracts\MessageStore` interface — 8 methods: `store`, `find`, `paginate`, `count`, `update`, `delete`, `purgeOlderThan`, `clear`
2. Register in `StoreManager` via a `createXxxDriver()` method (for built-in drivers) or via config resolvers (for custom drivers)
3. `store()` receives a payload array with at least: `id`, `timestamp`, `saved_at`
4. `paginate()` must return results in **newest-first** ordering
5. Default driver is `database` (`DatabaseMessageStore` with dedicated SQLite at `storage/app/mailbox/mailbox.sqlite`)
6. `FileStorage` uses JSON files on disk at a configurable path
7. Driver-specific config lives under `config('mailbox.store.{driver_name}')`
8. Write unit tests mirroring `tests/Unit/Contracts/MessageStoreContractTest.php` — this is the contract test that both drivers must pass
9. Never reference the HTTP or Transport layer from storage implementations
10. Storage drivers must be stateless — no cached state between requests
