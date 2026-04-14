# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Mailbox for Laravel** (`redberry/mailbox-for-laravel`) is a Laravel package that embeds a local email inbox for development. It intercepts outgoing mail via a custom Symfony transport, stores messages, and serves them through an isolated Inertia.js/Vue 3 dashboard. Works with any Laravel frontend stack (Blade, Livewire, Inertia, React).

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

CI runs on PHP 8.3/8.4 with Laravel 11/12 on Ubuntu and Windows.

## File Map

```
src/
  MailboxServiceProvider.php        # Bindings, transport, boot config (the only place for config() reads and bindings)
  CaptureService.php                # High-level API: store/list/find/update/delete/purge
  StoreManager.php                  # Laravel Manager — resolves storage drivers
  Contracts/MessageStore.php        # Storage driver interface (8 methods)
  Storage/
    DatabaseMessageStore.php        # Default driver (Eloquent, dedicated SQLite)
    FileStorage.php                 # JSON-on-disk alternative driver
    AttachmentStore.php             # File-based attachment persistence
  Transport/MailboxTransport.php    # Symfony AbstractTransport — captures outgoing mail
  Support/
    MessageNormalizer.php           # Email → canonical array + attachment extraction
    CidRewriter.php                 # Rewrites inline cid: refs to downloadable routes
  Http/Controllers/                 # 7 thin controllers, return Inertia or JSON responses
  Http/Middleware/                  # HandleInertiaRequests, AuthorizeMailboxMiddleware
  DTO/                              # MailboxMessageData, AttachmentData (Spatie Laravel Data)
  Models/                           # MailboxMessage, MailboxAttachment (Eloquent)
  Commands/                         # mailbox:install, mailbox:clear, mailbox:dev-link
  Facades/Mailbox.php               # Facade for CaptureService

resources/js/
  dashboard.js                      # Isolated Inertia app entry point
  Pages/Dashboard.vue               # Main page component
  components/mail/                  # Domain components (list, preview, filters — 11 components)
  components/ui/                    # Reusable UI primitives (button, input, tabs, select, etc.)
  composables/useMailboxPolling.ts  # Auto-refresh polling logic
  types/mailbox.ts                  # TypeScript interfaces
  lib/                              # Utilities (utils.ts, mail-data.ts)

config/mailbox.php                  # All package configuration
routes/mailbox.php                  # Route definitions (prefixed, middlewared)
database/migrations/                # 2 migrations (messages table + attachments table)
tests/                              # Architecture/, Commands/, Feature/, Unit/
```

## Architecture

### Mail Capture Pipeline

`MailboxTransport` → `MessageNormalizer` → `CaptureService` → `MessageStore` driver

1. **MailboxTransport** (`src/Transport/`) — Registered as the `mailbox` mail driver. Intercepts sent mail, optionally decorates another transport. Toggleable.
2. **MessageNormalizer** (`src/Support/`) — Converts Symfony `Email`/`RawMessage` to a canonical array. Extracts attachments as `AttachmentData` DTOs.
3. **CaptureService** (`src/CaptureService.php`) — High-level API for store/list/find/update/delete/purge. Returns `MailboxMessageData` DTOs. Storage-driver-agnostic.
4. **StoreManager** (`src/StoreManager.php`) — Extends Laravel's `Manager`. Resolves `database` (default) or `file` drivers.
5. **Storage Drivers** (`src/Storage/`) — `DatabaseMessageStore` (Eloquent, dedicated SQLite at `storage/app/mailbox/mailbox.sqlite`) and `FileStorage` (JSON on disk). Both implement `MessageStore` contract.
6. **AttachmentStore** + **CidRewriter** — Manage attachment files on a dedicated `mailbox` filesystem disk and rewrite inline `cid:` references.

### Isolated Inertia Dashboard

The package runs a **completely isolated** Inertia.js app that does not interfere with the host app. Own root view (`mailbox::layout`), own Vite build (`public/vendor/mailbox/`), own Vue app instance. See `ARCHITECTURE.md` for the full deep-dive.

### HTTP Layer

Routes under `config('mailbox.route', 'mailbox')` prefix with middleware: `web`, `mailbox.inertia`, `mailbox.authorize`. Authorization via `viewMailbox` gate (allows all in non-production by default).

## Testing

Uses **Pest** with Orchestra Testbench. Base `TestCase` sets up in-memory SQLite, loads package migrations, registers providers, and creates a mock Vite manifest.

```
tests/
├── Architecture/    # Arch rules (26 rules enforcing boundaries)
├── Commands/        # Artisan command tests
├── Feature/         # HTTP/integration tests (9 controller/middleware tests)
└── Unit/            # Unit tests (12 files covering all core services)
```

### Test Policy

- Every new class or feature MUST have corresponding tests
- Unit tests for services, contracts, drivers, DTOs
- Feature tests for HTTP routes, middleware, commands
- Arch tests for dependency boundaries
- Coverage target: **90%+ lines, 80%+ branches**
- Use Pest `describe()` blocks and dataset-driven test cases
- Use named routes in HTTP tests: `route('mailbox.index')`
- Inertia assertions: `$response->assertInertia(fn (Assert $page) => ...)`

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
