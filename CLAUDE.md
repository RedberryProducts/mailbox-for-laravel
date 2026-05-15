# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Mailbox for Laravel** (`redberry/mailbox-for-laravel`) is a Laravel package that embeds a local email inbox for development. It intercepts outgoing mail via a custom Symfony transport, stores messages, and serves them through a self-contained Vue 3 dashboard that talks to the package's own JSON endpoints. Zero dependency on the host app's frontend stack — works with Blade, Livewire, Inertia, React, or anything else.

## Commands

```bash
# PHP
composer test                    # Run Pest tests
composer test-coverage           # Tests with coverage report
composer analyse                 # PHPStan (level 5)
composer format                  # Laravel Pint (PSR-12)
bin/check                        # Run Pint + PHPStan + Pest in sequence
vendor/bin/pest --filter="test name"  # Run a single test
vendor/bin/pest tests/Unit/      # Run a test directory

# Frontend
npm run build                    # Production build (Vite)
npm run dev                      # Watch mode

# Package discovery (after dep changes)
composer prepare                 # testbench package:discover
```

CI runs on PHP 8.3/8.4 with Laravel 11/12/13 on Ubuntu and Windows.

## File Map

```
src/
  MailboxServiceProvider.php        # Bindings, transport, boot config (the only place for config() reads and bindings)
  CaptureService.php                # High-level API: store/list/find/update/delete/purge
  StoreManager.php                  # Laravel Manager — resolves storage drivers
  Contracts/
    MessageStore.php                # Message storage driver interface (10 methods incl. idsOlderThan, findIdByMessageId)
    AttachmentStore.php             # Attachment storage driver interface (8 methods)
    MessageSearch.php               # Pluggable search strategy (consumed by the storage drivers)
  Storage/
    DatabaseMessageStore.php        # Default message driver (Eloquent, dedicated SQLite)
    FileStorage.php                 # JSON-on-disk message driver
    DatabaseAttachmentStore.php     # DB-backed attachment driver (paired with database message driver)
    FileAttachmentStore.php         # JSON-sidecar attachment driver (paired with file message driver)
    AttachmentStore.php             # @deprecated shim — extends DatabaseAttachmentStore (scheduled for removal in v2.1)
  DTO/StoredAttachment.php          # Driver-agnostic attachment value object
  Transport/MailboxTransport.php    # Symfony AbstractTransport — captures outgoing mail
  Support/
    MessageNormalizer.php           # Email → canonical array + attachment extraction
    CidRewriter.php                 # Rewrites inline cid: refs to downloadable routes
  Testing/
    MailboxAssertions.php           # Collection-level assertions (assertSent, assertSentTo, etc.)
    PendingMailboxMessageAssertion.php  # Per-message fluent assertions (assertHasSubject, assertSeeInHtml, etc.)
    InteractsWithMailbox.php        # Trait for test classes — auto-clear, provides $this->mailbox()
  Http/Controllers/                 # 7 thin controllers, return Blade views or JSON responses
  Http/Middleware/                  # AuthorizeMailboxMiddleware
  DTO/                              # MailboxMessageData, AttachmentData (plain PHP DTOs with constructor property promotion)
  Models/                           # MailboxMessage, MailboxAttachment (Eloquent)
  Commands/                         # mailbox:install, mailbox:clear, mailbox:dev-link, mailbox:upgrade
  Facades/Mailbox.php               # Facade for CaptureService + assertion method proxying

resources/js/
  dashboard.js                      # Vue app entry point (reads JSON payload embedded in Blade)
  Pages/Dashboard.vue               # Main page component
  components/mail/                  # Domain components (list, preview, filters — 11 components)
  components/ui/                    # Reusable UI primitives (button, input, tabs, select, etc.)
  composables/useMailboxPolling.ts  # Auto-refresh polling logic
  types/mailbox.ts                  # TypeScript interfaces
  lib/                              # Utilities (utils.ts, mail-data.ts)

config/mailbox.php                  # All package configuration
routes/mailbox.php                  # Route definitions (prefixed, middlewared)
database/migrations/                # 3 migrations (messages table, attachments table, unique index on message_id)
tests/                              # Architecture/, Commands/, Feature/, Unit/
```

## Architecture

### Mail Capture Pipeline

`MailboxTransport` → `MessageNormalizer` → `CaptureService` → `MessageStore` driver

1. **MailboxTransport** (`src/Transport/`) — Registered as the `mailbox` mail driver. Intercepts sent mail, optionally decorates another transport. Toggleable.
2. **MessageNormalizer** (`src/Support/`) — Converts Symfony `Email`/`RawMessage` to a canonical array. Extracts attachments as `AttachmentData` DTOs.
3. **CaptureService** (`src/CaptureService.php`) — High-level API for store/list/find/update/delete/purge. Returns `MailboxMessageData` DTOs. Storage-driver-agnostic.
4. **StoreManager** (`src/StoreManager.php`) — Extends Laravel's `Manager`. Resolves `sqlite` (default), `database`, or `file` drivers.
5. **Storage Drivers** (`src/Storage/`) — `DatabaseMessageStore` (Eloquent, dedicated SQLite at `storage/app/mailbox/mailbox.sqlite`) and `FileStorage` (JSON on disk). Both implement `MessageStore` contract.
6. **AttachmentStore pair** — `DatabaseAttachmentStore` or `FileAttachmentStore` is bound alongside the chosen `MessageStore` driver. Both implement `Contracts\AttachmentStore` and return `StoredAttachment` DTOs. **CidRewriter** uses the contract to resolve inline `cid:` references regardless of driver. `CaptureService` cascades attachment cleanup automatically on `delete`, `clearAll`, and `purgeOlderThan`.

### Self-contained Vue Dashboard

The package ships a **completely isolated** Vue 3 dashboard that does not interfere with the host app. The Blade root view (`mailbox::app`) embeds the initial page payload as a `<script type="application/json">` blob; `dashboard.js` parses it, hydrates a shared reactive store, and mounts a plain Vue app. All subsequent interactions (polling, search, pagination, delete) talk to the same `MailboxController` — HTML on first load, JSON on AJAX. Own Vite build (`public/vendor/mailbox/`), own Vue app instance, zero host-app coupling. See `ARCHITECTURE.md` for the full deep-dive.

### HTTP Layer

Routes under `config('mailbox.path', 'mailbox')` prefix with middleware: `web`, `mailbox.authorize`. Authorization via `viewMailbox` gate (allows all in non-production by default). `MailboxController` returns a Blade view for browser requests and a JSON payload when `$request->wantsJson()` is true.

## Testing

Uses **Pest** with Orchestra Testbench. Base `TestCase` sets up in-memory SQLite, loads package migrations, registers providers, and creates a mock Vite manifest.

```
tests/
├── Architecture/    # Arch rules (currently 31 stub rules — see ArchitectureTest.php; bodies are placeholders)
├── Commands/        # Artisan command tests
├── Feature/         # HTTP/integration + InteractsWithMailbox tests
└── Unit/            # Unit tests (services, storage, testing assertions)
```

### Testing Assertions API (`src/Testing/`)

The package provides Laravel-idiomatic assertion helpers for verifying captured emails in test suites. Works with both **Pest** and **PHPUnit**.

**Trait:** `InteractsWithMailbox` — auto-clears mailbox between tests, provides `$this->mailbox()`.

**Collection-level** (via `$this->mailbox()` or `Mailbox` facade):
- `assertSent(Closure $callback, ?int $expectedCount = null)`
- `assertNotSent(Closure $callback)`
- `assertNothingSent()`
- `assertSentCount(int $count)`
- `assertSentTo(string $email, ?Closure $callback = null)`
- `assertNotSentTo(string $email, ?Closure $callback = null)`
- `sent(?Closure $callback = null): Collection` — raw query
- `firstSent(?Closure $callback = null): PendingMailboxMessageAssertion`

**Per-message fluent** (via `firstSent()`):
- `assertFrom()`, `assertHasTo()`, `assertHasCc()`, `assertHasBcc()`, `assertHasReplyTo()`
- `assertHasSubject()`, `assertSubjectContains()`
- `assertSeeInHtml()`, `assertDontSeeInHtml()`, `assertSeeInText()`, `assertDontSeeInText()`
- `assertSeeInOrderInHtml()`, `assertSeeInOrderInText()`
- `assertHasAttachment()`, `assertHasNoAttachments()`, `assertAttachmentCount()`
- `assertHasHeader()`

### Test Policy

- Every new class or feature MUST have corresponding tests
- Unit tests for services, contracts, drivers, DTOs
- Feature tests for HTTP routes, middleware, commands
- Arch tests for dependency boundaries
- Coverage target: **90%+ lines, 80%+ branches**
- Use Pest `describe()` blocks and dataset-driven test cases
- Use named routes in HTTP tests: `route('mailbox.index')`
- View assertions for initial load: `$response->assertViewIs('mailbox::app')->assertViewHas('data', fn ($data) => ...)`
- JSON assertions for AJAX: `$this->getJson(route('mailbox.index'))->assertJsonPath('messages.0.subject', ...)`
- Email assertions: use `InteractsWithMailbox` trait + `Mailbox::assertSent()` facade

## Coding Conventions

- **Namespace**: `Redberry\MailboxForLaravel`
- **No `env()` outside `config/`** — use `config()` everywhere else
- **Interfaces + constructor injection** — avoid facades in core services
- **Readonly/immutable** where possible; prefer DTOs and value objects over loose arrays
- **Stateless services** — transport may store last key but no global singletons
- **Conventional Commits**: `feat:`, `fix:`, `chore:`, `test:`, `refactor:`, `docs:`
- **Vue**: `<script setup>`, TypeScript for new files, scoped/prefixed TailwindCSS classes
- Throw domain exceptions for invalid states; no silent failures
- File IO via storage drivers only; never `storage_path()` directly in controllers

## Don't

- Don't call `env()` outside `config/` files
- Don't use facades in core services — use interfaces + constructor injection
- Don't add external services or self-hosted SMTP — this package captures locally only
- Don't render raw HTML without sanitization in the dashboard
- Don't write to arbitrary paths — storage drivers constrain paths to package directory
- Don't bypass the `MessageStore` contract — all storage goes through `CaptureService`
- Don't add global state or singletons (except bindings in service provider)
- Don't import from host app's frontend — the Vue app is fully isolated
- Don't hardcode absolute file paths in package code
- Don't use `dd()`, `dump()`, `ray()`, or `var_dump()` in committed code

## Definition of Done

1. Tests added and passing (`composer test`)
2. PHPStan passes (`composer analyse`)
3. Pint passes (`composer format`)
4. No new `env()` usage outside `config/`
5. README/CHANGELOG updated for user-facing changes
6. Conventional Commits format used
