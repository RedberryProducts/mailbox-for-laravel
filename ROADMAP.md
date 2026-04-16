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

### 5. Typed pagination result **[breaking]**

`MessageStore::paginate()` returns a loose `array`, losing type information and forcing the controller to hand-assemble `pagination` metadata.

- Replace with a `PaginatedMessages` value object (or Spatie `DataCollection` with typed meta).
- Controller and Inertia response derive everything from the value object.
- Cleaner contract for custom-driver authors.

### 6. Declarative transport decoration

Today, wrapping another transport requires constructor-level wiring in user code.

- New config key: `mailbox.decorate => 'smtp'` (or any driver name the `MailManager` knows).
- Service provider resolves the named transport from `MailManager` and wraps it with `MailboxTransport` automatically.
- Capture *and* real delivery with zero user-code changes.

### 7. Write-path idempotency

Duplicate `message_id` values (retries, queued job re-runs, replayed fixtures) currently create duplicate inbox entries.

- Optional unique index on `message_id` when present (database driver).
- Upsert semantics in `CaptureService::store()` — existing `message_id` updates rather than inserts.
- Makes test suites that replay fixtures safe by default.

### 8. Collapse `DatabaseMessageStore` into a SQLite-first story

The contract allows arbitrary connections, but the auto-config hardcodes a dedicated SQLite file — which is what nearly everyone actually wants.

- Rename the default flavor to `sqlite` so intent is obvious.
- Keep `database` as an advanced escape hatch for bring-your-own-connection users.
- Document the tradeoffs (portability of the SQLite file vs. integration with host DB tooling).

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
