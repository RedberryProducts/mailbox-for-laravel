# Changelog

All notable changes to `mailbox-for-laravel` will be documented in this file.

## [2.3.2] - 2026-08-24

Patch release. Fixes the database message store crashing when a stored payload carries a key the messages table has no column for, and picks up the outstanding npm security advisories that affect the dashboard bundle. No config, routes, or public APIs changed. **Rebuilt assets ship with this release**, so run `php artisan vendor:publish --tag=mailbox-assets --force` after upgrading.

### Fixed
- **Unknown payload keys no longer crash the database driver.** `DatabaseMessageStore::store()` and `update()` mass-assigned the whole payload into an unguarded model, so any key without a matching column raised an SQLSTATE "no such column" error mid-send. Payloads are now filtered down to the columns the package migration creates — listed on `MailboxMessage::PERSISTABLE_COLUMNS` — and anything else is dropped. `FileStorage` is unaffected and still persists extra keys verbatim.
- **PHPStan analysis on CI.** larastan >= 3.10 types `updateOrCreate()`'s `$values` as `array<model property of Model, mixed>`; with `checkModelProperties: true` enabled, the free-form payload array failed analysis. The filtered array carries the literal keys that type requires — no baseline entry or ignore comment was added.

### Security
- **Dashboard dependencies patched.** `axios` 1.15.2 → 1.19.0 closes a long list of advisories carried by the bundled HTTP client (ReDoS via cookie name injection, several prototype-pollution gadgets, `maxBodyLength` bypasses, `NO_PROXY` bypasses, proxy credential leaks on redirect). `form-data` 4.0.5 → 4.0.6 (CRLF injection in multipart field names) and `nanoid` → 3.3.18/5.1.16 (infinite loop on zero/negative size) come along as transitive fixes. The rebuilt bundle in `public/vendor/mailbox/` carries the patched axios — publish assets to pick it up.
- **Build tooling patched.** `postcss` 8.5.6 → 8.5.26 fixes three `sourceMappingURL` path-traversal advisories. Maintainer-side only; postcss never ships to consumers.
- Remaining advisories (`vite` and its `esbuild`, both dev-server-only on Windows) need the vite 8 major and are deliberately **not** in this patch release — see #77.

### Changed
- **`MailboxMessage` now declares `$fillable` instead of `$guarded = []`.** Mass assignment is limited to `MailboxMessage::PERSISTABLE_COLUMNS`, so every write path — the storage driver, the factory, or host code touching the model directly — is held to the columns the table actually has. A test pins that list to the live schema, so adding a column to the migration without updating it fails CI.
- **Dashboard assets rebuilt.** The CSS drops from 42.5 kB to 31.1 kB: Tailwind v4's automatic content detection had been scanning the previously built bundle in `public/vendor/mailbox/`, turning CSS keywords found in library code (`sticky`, `capitalize`, `inline-block`, …) into real utility classes and carrying them forward every release. None of the 85 removed classes is used by the dashboard; rendering is unchanged.

## [2.3.1] - 2026-08-20

Patch release. Fixes the dashboard search box losing characters while you type, and polling re-inserting non-matching messages into a filtered list. Frontend only — no PHP, config, routes, or public APIs changed. Rebuilt assets ship with this release, so run `php artisan vendor:publish --tag=mailbox-assets --force` after upgrading.

### Fixed
- **Search input no longer rewrites itself while you type.** The dashboard mirrored the server's echoed (and trimmed) query back into the controlled search field. Because the server trims the term, a trailing space was deleted from under the cursor on every search; and any characters typed while a request was in flight were reverted to the older query when that response landed. The typed value is now the single source of truth for the field — the response only updates the message list.
- **Searches are no longer silently dropped.** The debounced handler re-read the input ref when the timer fired rather than the query it was scheduled for. Once the field had been reverted (above), the pending query matched the last committed search and the guard returned early, so the search the user actually typed never reached the server. The debounce now uses the query it was created with.
- **Out-of-order search responses can no longer overwrite newer results.** Each search carries a request id; a slow earlier response is discarded if a later search has already been issued.
- **Polling no longer pollutes a filtered list.** `useMailboxPolling` merges page 1 into the store on every tick. A poll issued before a search — or during the debounce window, using the previous term — landed afterwards and merged unfiltered messages into the filtered results, which the list renders as-is. Each poll is now scoped to the search it was issued for and its response is discarded if the active search changed while it was in flight.

## [2.3.0] - 2026-06-04

### Added
- Laravel 13 support. `illuminate/contracts` constraint now allows `^13.0`; `orchestra/testbench` allows `^11.0`. CI matrix now covers Laravel 11/12/13. Dev-only constraints (pest, pest-plugin-laravel, pest-plugin-arch, larastan, nunomaduro/collision) widened to include the majors that support Laravel 13.

### Changed
- **Auto-pruning is now opt-in.** `mailbox.retention_schedule` (env: `MAILBOX_RETENTION_SCHEDULE`) now defaults to `false` instead of `true`. The package no longer registers the daily `mailbox:clear --outdated` purge unless you explicitly enable it. Hosts that relied on the automatic purge should set `MAILBOX_RETENTION_SCHEDULE=true` to keep it running.

## [2.2.1] - 2026-05-15

Patch release. Documentation-only fixes to the Laravel Boost skill that shipped in v2.2.0 — no package code, runtime behavior, or APIs changed. Downstream consumers should re-run `php artisan boost:install` to refresh the skill in their agent folder.

### Fixed
- **`rules/architecture.md`** — corrected the custom-driver section:
  - `Contracts\MessageStore` method count from 9 to **10** (`store`, `find`, `findIdByMessageId`, `paginate`, `count`, `update`, `delete`, `purgeOlderThan`, `idsOlderThan`, `clear`).
  - Registration mechanism from the inaccurate `StoreManager::extend()` to the package's actual config-driven path: `config('mailbox.store.resolvers')`.
  - Config key from `mailbox.stores` (plural, wrong) to `mailbox.store` (singular).
  - Added a real resolver example, an `AttachmentStore` binding example, and an explicit warning about falling back to `DatabaseAttachmentStore` when only one half of the driver pair is registered.
- **`rules/http.md`** — removed the non-existent `route('mailbox.show', $message)` example; replaced with the actual eight named routes the package exposes (`mailbox.index`, `mailbox.messages.destroy`, `mailbox.attachments.download`, etc.).
- **`rules/conventions.md`** — architecture rule count corrected from 26 to **31** declared rules (currently stubs in `tests/Architecture/ArchitectureTest.php`).

### Added
- **`rules/frontend.md`** — covers the standalone Vue 3 dashboard: boot sequence, `mailboxStore.ts` / `mailboxUrl()` API, Vite output at `public/vendor/mailbox/`, hot file location, Reka UI primitives, and the explicit "Inertia is **not** used" callout.
- **`rules/commands.md`** — full signatures and intent for all four Artisan commands: `mailbox:install`, `mailbox:clear`, `mailbox:dev-link`, and the previously undocumented `mailbox:upgrade` (v1 → v2 config/env rewriter).

### Changed
- **`SKILL.md`** — activation description now mentions the 10/8 method counts, the resolvers-not-extend mechanism, the no-Inertia stance, `mailbox:upgrade`, and `CidRewriter`. Quick-reference indexes the two new rule files. Storage-driver section gained the `sqlite` (auto-configured) vs. `database` (bring-your-own-connection) distinction.

## [Unreleased]

### v2.0.0-dev — Inertia Removal

#### Removed
- `inertiajs/inertia-laravel` Composer dependency. The package no longer requires any Inertia version on the host app.
- `@inertiajs/vue3` npm dependency.
- `src/Http/Middleware/HandleInertiaRequests.php` and the `mailbox.inertia` middleware alias.
- `tests/Feature/HandleInertiaRequestsTest.php`.

#### Changed
- `MailboxController`, `ClearMailboxController`, and `DeleteMailboxMessageController` are now dual-mode: they return a Blade view (`mailbox::app`) for browser requests and JSON when `$request->wantsJson()` is true.
- `resources/views/app.blade.php` embeds the initial payload as a `<script id="mailbox-data" type="application/json">` block. `resources/js/dashboard.js` parses it, hydrates a shared reactive store (`resources/js/lib/mailboxStore.ts`), and mounts a plain `createApp()` onto `#mailbox-app`.
- All subsequent dashboard interactions (polling, search, pagination, clear inbox, delete, seen) use `axios` against the same JSON endpoints. URL state syncs via `history.replaceState`.
- Frontend imports no longer pull from `@inertiajs/vue3`. The only runtime deps are Vue 3, axios, date-fns, reka-ui, and lucide-vue-next.

#### Breaking Changes
- Host apps that relied on the package's `mailbox.inertia` middleware alias must remove it from custom route wiring. The package's own routes no longer apply it.
- Custom integrations that asserted Inertia shared props via `assertInertia()` in tests should switch to `assertViewIs('mailbox::app')` + `assertViewHas('data', …)` for HTML responses and `$this->getJson(...)->assertJsonPath(...)` for AJAX responses.
- The dashboard assets must be rebuilt and re-published: `npm run build && php artisan mailbox:install --force`.

### v2.0.0-dev — Automatic Retention

#### Added
- `MailboxServiceProvider` now registers a daily `Schedule::command('mailbox:clear --outdated')` automatically — captures no longer pile up forever when the host app runs Laravel's scheduler. Guarded by `mailbox.enabled`, `mailbox.retention > 0`, and `mailbox.retention_schedule`.
- New `mailbox.retention_schedule` config key (env: `MAILBOX_RETENTION_SCHEDULE`, default `true`). Set to `false` to opt out and wire the purge manually.
- Unit tests covering each guard (enabled / retention positive / schedule flag) plus end-to-end resolution through `callAfterResolving`.

#### Notes
- Hosts already scheduling `mailbox:clear --outdated` manually should set `MAILBOX_RETENTION_SCHEDULE=false` to avoid a duplicate daily run.
- The schedule uses `->onOneServer()` and a named constraint so multi-server deploys don't run the purge concurrently.

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
