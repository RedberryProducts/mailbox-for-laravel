# Mailbox for Laravel — Roadmap

This document tracks planned work toward **v2.0.0**. Items are grouped by theme and ordered roughly by impact. Breaking changes are called out explicitly.

## v2.0.0 — Streamlined capture & storage

Theme: reduce driver divergence, make the non-intrusive posture automatic rather than aspirational, and stabilize the public API surface so downstream integrations (custom drivers, decorated transports, UI extensions) are first-class.

### 1. Unified attachment handling **[breaking]** — *Implemented*

Shipped on the `v2.0.0-dev` branch. Driver-agnostic `Contracts\AttachmentStore` plus paired `DatabaseAttachmentStore` and `FileAttachmentStore` implementations. The `MessageStore` driver and the matching `AttachmentStore` are bound together by `MailboxServiceProvider`. `CaptureService` now owns cascade cleanup (`delete`, `clearAll`, `purgeOlderThan`) so neither controllers nor commands need to know about attachments. See the `## v2.0.0-dev — Unified Attachment Handling` section in [`CHANGELOG.md`](CHANGELOG.md) for the full breaking-change list.

### 2. Canonical message IDs **[breaking]** — *Implemented*

Shipped on the `v2.0.0-dev` branch. `CaptureService::store()` assigns a `Str::ulid()` to every payload (preserving caller-supplied ids for fixture replay), and both `DatabaseMessageStore` and `FileStorage` now throw when the upstream id is missing. The `mailbox_messages.id` column is a ULID primary key and the `mailbox_attachments.message_id` FK matches, so the same 26-char id flows through URLs, controllers, and the dashboard regardless of driver. `MessageStore::store()` return type is narrowed to `string`. Upgrade path for existing deployments: `php artisan mailbox:install --refresh`. See the `## v2.0.0-dev — Canonical Message IDs` section in [`CHANGELOG.md`](CHANGELOG.md).

### 3. Automatic retention — *Implemented*

Shipped on the `v2.0.0-dev` branch. `MailboxServiceProvider` registers a daily `Schedule::command('mailbox:clear --outdated')` via `callAfterResolving(Schedule::class, …)`, triple-guarded by `mailbox.enabled`, `mailbox.retention > 0`, and a new `mailbox.retention_schedule` flag (env: `MAILBOX_RETENTION_SCHEDULE`, default `true`). Hosts that prefer to wire the purge by hand can flip the flag off; multi-server deployments are covered by `->onOneServer()`. See the `## v2.0.0-dev — Automatic Retention` section in [`CHANGELOG.md`](CHANGELOG.md).

### 4. Search as a strategy, not per-driver — *Implemented*

Both drivers hand-roll search over `subject`, `from`, `to`, `text` and drift independently.

- Extract a `MessageSearch` contract with two shapes: `matches(array $payload, string $needle): bool` for in-memory drivers and `applyToQuery(Builder $q, string $needle): Builder` for SQL drivers.
- One canonical definition of searchable fields and match semantics.
- Unlocks future extensions (header search, attachment filename search) without touching drivers.

### 5. Typed pagination result **[breaking]** — *Implemented*

`CaptureService::list()` returned a loose `array`, losing type information and forcing the controller to hand-assemble `pagination` metadata.

- Replaced with a `PaginatedMessages` value object (Spatie `Data` DTO with typed properties: `data`, `total`, `perPage`, `currentPage`, `hasMore`, `latestTimestamp`).
- Controller and Inertia response derive everything from the value object via property access.
- Cleaner contract for custom-driver authors. `MessageStore::paginate()` still returns `array` — the DTO wrapping happens in `CaptureService`.

### 6. Declarative transport decoration — *Implemented*

Shipped on the `v2.0.0-dev` branch. New config key `mailbox.decorate` (env: `MAILBOX_DECORATE`, default `null`). When set to a mailer name (e.g. `'smtp'`, `'ses'`, `'postmark'`), the service provider resolves that mailer's Symfony transport via `MailManager` and passes it as the `$decorated` argument to `MailboxTransport` — capture *and* real delivery with zero user-code changes. Circular references (`decorate => 'mailbox'`) are guarded with a clear exception. See the `## v2.0.0-dev — Declarative Transport Decoration` section in [`CHANGELOG.md`](CHANGELOG.md).

### 7. Write-path idempotency — *Implemented*

Shipped on the `v2.0.0-dev` branch. `CaptureService::store()` now checks for an existing record with the same RFC 822 `message_id` before minting a new ULID — if found, it reuses the existing id so the downstream `store()` call becomes an update instead of an insert. A new `findIdByMessageId(string): ?string` method on the `MessageStore` contract (10th method, breaking) enables the lookup in both `DatabaseMessageStore` (indexed query) and `FileStorage` (file scan). A new migration adds a unique index on `mailbox_messages.message_id` (NULLs are exempt). Priority order: explicit caller-supplied `id` > `message_id` lookup > new ULID. Null/empty `message_id` values always create fresh entries. See the `## v2.0.0-dev — Write-Path Idempotency` section in [`CHANGELOG.md`](CHANGELOG.md).

### 8. Collapse `DatabaseMessageStore` into a SQLite-first story — *Implemented*

Shipped on the `v2.0.0-dev` branch. Default driver renamed from `database` to `sqlite` (env: `MAILBOX_STORE_DRIVER`, default `sqlite`). Both `sqlite` and `database` resolve to `DatabaseMessageStore` under the hood — `sqlite` is the zero-config dedicated SQLite file, `database` is the bring-your-own-connection escape hatch for MySQL/Postgres users. `StoreManager` now has `createSqliteDriver()` alongside the existing `createDatabaseDriver()`. See the `## v2.0.0-dev — SQLite-First Driver Naming` section in [`CHANGELOG.md`](CHANGELOG.md).

---

## Cross-cutting concerns for v2.0.0

- **Migration guide** from v1.x → v2.0.0 covering ID format, attachment layout, and payload-shape changes.
- **Driver author guide** reflecting the new contract (`AttachmentStore`, `MessageSearch`, `PaginatedMessages`).
- **Upgrade command** (`php artisan mailbox:upgrade`) to rewrite existing v1 data into the v2 layout where possible.

## Post-2.0 ideas (not scheduled)

- Full-text search backend (Meilisearch / Scout bridge).
- Webhook sink for exporting captured mail to external inspectors (Mailpit, Postmark sandbox).
- Per-environment retention policies.
- Attachment virus/type scanning hook.
