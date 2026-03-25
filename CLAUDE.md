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
vendor/bin/pest --filter="test name"  # Run a single test
vendor/bin/pest tests/Unit/      # Run a test directory

# Frontend
npm run build                    # Production build (Vite)
npm run dev                      # Watch mode

# Package discovery (after dep changes)
composer prepare                 # testbench package:discover
```

CI runs on PHP 8.3/8.4 with Laravel 11/12 on Ubuntu and Windows. Coverage target: 90%+ lines, 80%+ branches.

## Architecture

### Mail Capture Pipeline

`MailboxTransport` (Symfony AbstractTransport) → `MessageNormalizer` → `CaptureService` → `MessageStore` (driver)

1. **MailboxTransport** (`src/Transport/`) — Registered as the `mailbox` mail driver. Intercepts sent mail, optionally decorates another transport. Toggleable.
2. **MessageNormalizer** (`src/Support/`) — Converts Symfony `Email`/`RawMessage` to a canonical array. Extracts attachments as `AttachmentData` DTOs.
3. **CaptureService** (`src/CaptureService.php`) — High-level API for store/list/find/update/delete/purge. Returns `MailboxMessageData` DTOs. Storage-driver-agnostic.
4. **StoreManager** (`src/StoreManager.php`) — Extends Laravel's `Manager`. Resolves `database` (default) or `file` drivers. Supports custom resolvers.
5. **Storage Drivers** (`src/Storage/`) — `DatabaseMessageStore` (Eloquent, dedicated SQLite connection at `storage/app/mailbox/mailbox.sqlite`) and `FileStorage` (JSON on disk). Both implement `MessageStore` contract.
6. **AttachmentStore** (`src/Storage/AttachmentStore.php`) — Manages attachment files on a dedicated `mailbox` filesystem disk. `CidRewriter` rewrites inline `cid:` references to downloadable routes.

### Isolated Inertia Dashboard

The package runs a **completely isolated** Inertia.js application that does not interfere with the host app:

- **`HandleInertiaRequests`** middleware — Own root view (`mailbox::layout`), own shared data, registered as `mailbox.inertia`
- **Controllers** render `mailbox::ComponentName` — the `mailbox::` prefix is stripped in the frontend resolver to load from `resources/js/Pages/`
- **Vite** builds to `public/vendor/mailbox/` with a dedicated hot file
- **Independent Vue app instance** — own mount point, own Inertia plugin, no shared state with host

### HTTP Layer

Routes under `config('mailbox.route', 'mailbox')` prefix with middleware: `web`, `mailbox.inertia`, `mailbox.authorize`.

Key controllers in `src/Http/Controllers/`: `MailboxController` (paginated list), `SendTestMailController`, `ClearMailboxController`, `DeleteMailboxMessageController`, `SeenController`, `AttachmentController`, `PublicAssetController`.

Authorization via `viewMailbox` gate (allows all in non-production by default).

### Service Provider

`MailboxServiceProvider` (extends Spatie's `PackageServiceProvider`):
- Registers `StoreManager`, `MessageStore`, `AttachmentStore`, `CidRewriter`, `CaptureService` bindings
- Registers `mailbox` mail transport (non-production or when explicitly enabled)
- Configures dedicated SQLite connection and filesystem disk at boot
- Registers `mailbox.authorize` and `mailbox.inertia` middleware aliases
- Commands: `mailbox:install`, `mailbox:clear`, `mailbox:dev-link` (local env only)

### Models

- **MailboxMessage** (`src/Models/`) — Uses configurable connection/table. JSON casts for `from`, `to`, `cc`, `bcc`, `reply_to`, `headers`, `attachments`.
- **MailboxAttachment** — ULID primary key, foreign key to message (cascade delete), tracks `cid` and `is_inline`.

### DTOs

`MailboxMessageData` and `AttachmentData` in `src/DTO/` — built with Spatie Laravel Data.

## Testing

Uses **Pest** with Orchestra Testbench. Base `TestCase` sets up in-memory SQLite, loads package migrations, registers providers, and creates a mock Vite manifest.

```
tests/
├── Architecture/    # Arch rule tests
├── Commands/        # Artisan command tests
├── Feature/         # HTTP/integration tests
└── Unit/            # Unit tests
```

Key conventions:
- Tests mirror `src/` namespace structure
- Dataset-driven test cases with realistic data
- Inertia assertions via `assertInertia()`
- Named routes preferred in tests

## Coding Conventions

- **Namespace**: `Redberry\MailboxForLaravel`
- **No `env()` outside `config/`** — use `config()` everywhere else
- **Interfaces + constructor injection** — avoid facades in core services
- **Readonly/immutable** where possible; prefer DTOs and value objects over loose arrays
- **Stateless services** — transport may store last key but no global singletons
- **Conventional Commits**: `feat:`, `fix:`, `chore:`, `test:`, `refactor:`, `docs:`
- **Vue**: `<script setup>`, TypeScript for new files, scoped/prefixed TailwindCSS classes
