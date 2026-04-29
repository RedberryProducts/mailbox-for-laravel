# Copilot Instructions — Mailbox for Laravel (Repository‑wide)

> Place this file at **`.github/copilot-instructions.md`** in the repository root so Copilot (Chat, PR review, code suggestions, and Coding Agent) loads it automatically.
>
> See also: `CLAUDE.md` for Claude Code-specific guidance and `ARCHITECTURE.md` for dashboard isolation details.

## Project overview

* This repository is a **Laravel package** that embeds a local email mailbox for development and QA, similar to Mailtrap but self‑contained. It ships a Vue‑powered dashboard, storage drivers, a custom mail transport, and HTTP controllers.
* Primary goals: zero‑config local email capture, a clean UI, and **full automated test coverage** for every class and feature.

## Tech stack & versions (default)

* **PHP**: ^8.3 (strict types, typed properties, readonly where applicable)
* **Laravel**: ^10 | ^11 | ^12 | ^13
* **Testing**: Pest 2/3/4, Laravel test helpers; Coverage target: **90%+ lines**, **80%+ branches**
* **Static analysis**: PHPStan (Larastan) level 5
* **Code style**: Laravel Pint (PSR‑12), EditorConfig
* **Front end**: Vue 3 + Vite, TypeScript (for any new scripts), TailwindCSS v4 with **scoped/prefixed classes** to avoid collisions with host apps
* **UI components**: Reka UI (radix-vue successor)
* **Tooling**: ESLint + Prettier (for JS/TS/Vue), Stylelint (optional)

## How to build & test (for Copilot to follow)

* **Install PHP deps**: `composer install`
* **Install Node deps**: `npm ci`
* **Build assets for the package**: `npm run build`
* **Run tests (fast)**: `./vendor/bin/pest -p`
* **Run tests (coverage)**: `./vendor/bin/pest --coverage --min=90`
* **Static analysis**: `./vendor/bin/phpstan analyse`
* **Lint**: `./vendor/bin/pint -v`

## Architecture (high level)

* **Transport**: `MailboxTransport` captures `SentMessage`, normalizes it, stores payload, optionally decorates another transport. Decoration is declarative via `mailbox.decorate` (mailer name) — when set, capture and real delivery happen with zero user-code changes.
* **Capture & Storage**:

    * `CaptureService` is the high-level API: store/list/find/update/delete/purge. Owns cascading attachment cleanup. Mints canonical ULIDs and provides write-path idempotency via the RFC 822 `message_id` header.
    * `MessageStore` contract (10 methods) — default driver is `sqlite` (`DatabaseMessageStore` against a dedicated SQLite file at `storage/app/mailbox/mailbox.sqlite`). `database` is an alias for bring-your-own-connection users. `file` driver writes JSON on disk.
    * `AttachmentStore` contract (8 methods) — paired with the active `MessageStore` driver. `DatabaseAttachmentStore` for sqlite/database; `FileAttachmentStore` for the file driver. Both return `DTO\StoredAttachment` value objects.
    * `MessageSearch` contract — pluggable search strategy (`DefaultMessageSearch` searches `subject`, `from`, `to`, `html`, `text`).
    * `StoreManager` resolves drivers via config & custom resolvers.
* **DTOs**: `MailboxMessageData`, `AttachmentData`, `StoredAttachment`, `PaginatedMessages` — typed value objects under `src/DTO/`.
* **HTTP**: `MailboxController`, `SendTestMailController`, `ClearMailboxController`, `DeleteMailboxMessageController`, `SeenController`, `PublicAssetController`, `AttachmentController`, `AuthorizeMailboxMiddleware` (route gating).
* **Retention**: `MailboxServiceProvider` registers a daily `mailbox:clear --outdated` via `callAfterResolving(Schedule::class, …)`, triple-guarded by `mailbox.enabled`, `mailbox.retention > 0`, and `mailbox.retention_schedule`.
* **Support**: `MessageNormalizer` (canonical payload + attachment extraction), `CidRewriter` (rewrites inline `cid:` references), `MailboxServiceProvider`, `InstallCommand`, `UpgradeCommand`, `ClearInboxCommand`, `config/mailbox.php`.
* **Testing API**: `src/Testing/` — `InteractsWithMailbox` trait, `MailboxAssertions`, `PendingMailboxMessageAssertion`, plus facade-level `Mailbox::assertSent()` etc.

## Directory & naming conventions

* **Namespaces**: `Redberry\MailboxForLaravel\...`
* **Contracts** under `Contracts/`; **Support** under `Support/`; **DTO** under `DTO/`; **Testing** under `Testing/`
* **HTTP** under `Http/Controllers` and `Http/Middleware`
* **Storage drivers** under `Storage/`
* **Tests** mirror src namespaces: `tests/Unit`, `tests/Feature`, `tests/Commands`, `tests/Architecture`
* **Vue** in `resources/js/`, entry `resources/js/dashboard.js`. Built assets ship to `public/vendor/mailbox/` (NOT `public/build/`).

## Coding standards for Copilot

### PHP & Laravel

* Prefer **value objects**, **enums**, and **DTOs** over loose arrays where practical.
* Always add **return types** and **param types**. No mixed unless unavoidable; document with PHPDoc if union types are complex.
* **Never** call `env()` outside `config/` files. Use `config()` or typed config accessors.
* Avoid facades in core services; depend on **interfaces** and constructor injection; keep controllers thin.
* **Immutable data** where possible (`readonly` properties, new instances vs mutation).
* **No hidden state**: services should be stateless; transport may store the last key but avoid global singletons.
* Throw **domain exceptions** for invalid states; no silent failures.
* Use **`Response::view()` / `view()`** with view models; no heavy logic in Blade.
* File IO via storage drivers; never touch `storage_path()` directly from controllers.
* Prefer **`Clock`** abstraction (or now() injection) for time; avoid `time()` directly in business logic; guard with tests.

### Vue / TS / Vite

* Use **script setup** and **defineProps/defineEmits**.
* Use **TypeScript** for new files; define `Message`, `Envelope`, etc. interfaces.
* Stateless, presentational components; lift state to a store if needed (Pinia optional).
* Accessibility: semantic elements, keyboard navigation, focus rings.

### Security & safety

* Do not render raw HTML without sanitization in the dashboard.
* Treat captured emails as **non‑production** only; redact secrets in UI where possible.
* Avoid writing to arbitrary paths; storage driver paths must be constrained to package directory.

## Test policy (Copilot must generate tests by default)

> **Everything is tested**: every class, controller action, middleware path, transport behavior, and storage driver.

### Coverage & gates

* Generate **unit tests** for all services, contracts, and drivers.
* Generate **feature tests** for HTTP routes, middleware, publishing/installation, and asset serving.
* Add **arch tests** to enforce boundaries (forbidden dependencies, env usage, etc.).
* Target: **90%+** statement coverage; PRs below fail.

### Testing Assertions API (`src/Testing/`)

The package provides built-in testing helpers for verifying captured emails:

* **`InteractsWithMailbox` trait** — Add via `uses()` in Pest or `use` in PHPUnit. Auto-clears mailbox before each test.
* **`Mailbox` facade** — Supports `assertSent()`, `assertSentTo()`, `assertNothingSent()`, `assertSentCount()`, `sent()`, `firstSent()`
* **`PendingMailboxMessageAssertion`** — Fluent per-message assertions via `firstSent()`: `assertHasSubject()`, `assertSeeInHtml()`, `assertHasTo()`, `assertHasAttachment()`, etc.

When writing tests that verify email sending, prefer these helpers over manual `CaptureService::all()` boilerplate.

### Required test suites & example scenarios

* **CaptureService (Unit)**

    * mints a canonical ULID id; preserves caller-supplied ids
    * dedups by RFC 822 `message_id` via `findIdByMessageId()` (write-path idempotency)
    * lists messages in **newest‑first** order via `PaginatedMessages` DTO
    * updates existing message; returns null for unknown id
    * cascades attachment cleanup on `delete()`, `clearAll()`, `purgeOlderThan()`
* **StoreManager (Unit)**

    * resolves default `sqlite` driver; `database` alias resolves to the same backend
    * throws on unsupported driver
    * accepts **custom resolver** via config and returns custom store instance
* **Storage drivers (Unit)** — share a common contract test (`tests/Unit/Contracts/MessageStoreContractTest.php`, `AttachmentStoreContractTest.php`)

    * persists and retrieves payloads
    * `paginate()` returns newest‑first
    * `update()` merges without data loss
    * `clear()` wipes safely
    * `findIdByMessageId()` powers write‑path idempotency
    * `idsOlderThan()` powers cascade attachment cleanup
* **Transport\MailboxTransport (Unit)**

    * normalizes and saves message when `enabled=true`
    * decorates underlying transport when `mailbox.decorate` is set
    * no‑op capture when disabled; still delegates if decorated
* **Support\MessageNormalizer (Unit)**

    * converts `SentMessage` to canonical structure (from, to, cc, subject, date, text, html, attachments, raw)
    * extracts attachments as `AttachmentData` DTOs
    * handles edge cases: missing headers, non‑UTF8, multiple parts
* **Support\CidRewriter (Unit)**

    * rewrites inline `cid:` references in HTML to attachment download URLs
* **Http Controllers (Feature)**

    * `MailboxController` returns Blade view on first load, JSON on `wantsJson()`; paginates newest-first
    * `ClearMailboxController` empties store on `DELETE /mailbox/messages`
    * `DeleteMailboxMessageController` deletes a single message on `DELETE /mailbox/messages/{id}`
    * `SeenController` toggles `seen_at` and returns updated entity
    * `AttachmentController` streams attachment content from the active `AttachmentStore`
    * `PublicAssetController` serves versioned assets with correct headers
* **Middleware (Feature)**

    * `AuthorizeMailboxMiddleware` denies/permits based on config/closure/gate
* **Service Provider & Commands (Feature)**

    * `MailboxServiceProvider` registers bindings, publishes assets/config/routes, schedules retention purge
    * `InstallCommand` publishes resources and prints next steps
    * `UpgradeCommand` detects stale v1 config and offers a refresh
    * `ClearInboxCommand` clears all (or only outdated) messages
* **Architecture**

    * dependency-boundary rules (storage drivers don't depend on HTTP/Transport, services depend on contracts not concrete classes, etc.)
    * no `env()` usage outside `config/`

> Copilot: when you add a **new file**, also scaffold **tests** (Unit or Feature) with realistic data and edge cases. Update `phpstan.neon`, `pint.json`, and CI as needed.

## Pull requests & commit conventions

* PRs should be **small and focused**, include tests, docs, and passing CI.
* Use Conventional Commits (`feat:`, `fix:`, `chore:`, `test:`, `refactor:`, `docs:`).
* Include a short description, screenshots (for UI), and checklists: tests added, coverage holds, docs updated.

## Copilot do’s & don’ts

* **Do**: follow these instructions for every generation, add missing tests, propose refactors that reduce complexity, prefer pure functions, suggest interfaces and DI.
* **Do**: emit Pest tests using dataset‑driven cases, fakes, and Laravel helpers; prefer named routes in tests.
* **Do**: add type‑safe DTOs and enums over associative arrays when evolving structures.
* **Don’t**: introduce global state, call `env()` outside config, or mix UI concerns in controllers.
* **Don’t**: add external services or self‑hosted SMTP; this package captures locally only.

## Example prompts (for maintainers)

* “Implement a Redis-backed `MessageStore` driver and a paired `AttachmentStore`, registered via `mailbox.store.resolvers`, with contract tests mirrored from the existing drivers.”
* “Add a `MessageSearch` strategy that searches inside attachment filenames, behind the `Contracts\MessageSearch` contract.”
* “Generate Vue components for the Mailbox list with read/unread states; consume the JSON endpoints via axios + the shared `mailboxStore`.”
* “Write an arch test preventing `env()` usage outside `config/` and preventing `Http\Controllers` from depending on `Storage\*` directly.”

## Documentation expectations

* Every public class/method should have minimal PHPDoc where types are not self‑evident.
* Update `README.md` with any new features, configuration flags, or artisan commands.
* Add upgrade notes on breaking changes under `CHANGELOG.md`.

## CI expectations

* GitHub Actions runs: install PHP/Node, cache deps, **run Pint, PHPStan, Pest with coverage**, and build assets.
* PRs must pass all checks and maintain coverage thresholds.

## Definition of done (per task)

1. Code adheres to these standards
2. Tests added and passing locally and in CI
3. Static analysis & style pass
4. Docs updated (README/CHANGELOG)
5. No new global state, no env leaks

---

By keeping this document concise but explicit, Copilot can reliably scaffold code, tests, and reviews that align with this package’s architecture and quality bar.
