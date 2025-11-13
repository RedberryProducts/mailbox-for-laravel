# Copilot Instructions — Mailbox for Laravel (Repository‑wide)

> Place this file at **`.github/copilot-instructions.md`** in the repository root so Copilot (Chat, PR review, code suggestions, and Coding Agent) loads it automatically.

## Project overview

* This repository is a **Laravel package** that embeds a local email mailbox for development and QA, similar to Mailtrap but self‑contained. It ships a Vue‑powered dashboard, storage drivers, a custom mail transport, and HTTP controllers.
* Primary goals: zero‑config local email capture, a clean UI, and **full automated test coverage** for every class and feature.

## Tech stack & versions (default)

* **PHP**: ^8.2 (strict types, typed properties, readonly where applicable)
* **Laravel**: ^10 | ^11
* **Testing**: Pest 3/4, Laravel test helpers; Coverage target: **90%+ lines**, **80%+ branches**
* **Static analysis**: PHPStan (Larastan) level ≥ 6; Psalm optional
* **Code style**: Laravel Pint (PSR‑12), EditorConfig
* **Front end**: Vue 3 + Vite, TypeScript (for any new scripts), TailwindCSS with **scoped/prefixed classes** to avoid collisions with host apps
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

* **Transport**: `MailboxTransport` captures `SentMessage`, normalizes it, stores payload, optionally decorates another transport.
* **Capture & Storage**:

    * `CaptureService` generates deterministic keys, persists payloads with metadata, lists/paginates, update, clear.
    * `MessageStore` (contract) with a default `FileStorage` driver.
    * `StoreManager` resolves drivers via config & custom resolvers.
* **HTTP**: `MailboxController`, `SendTestMailController`, `ClearMailboxController`, `SeenController`, `AssetController` (static assets), `AuthorizeMailboxMiddleware` (route gating).
* **Support**: `MessageNormalizer` (creates canonical payload), `MailboxServiceProvider`, `InstallCommand` (publishes assets/config/routes), `config/mailbox.php`.

## Directory & naming conventions

* **Namespaces**: `Redberry\MailboxForLaravel\...`
* **Contracts** under `Contracts/`; **Support** under `Support/`
* **HTTP** under `Http/Controllers` and `Http/Middleware`
* **Storage drivers** under `Storage/`
* **Tests** mirror src namespaces: `tests/Unit`, `tests/Feature`, `tests/Arch`
* **Vue** in `resources/js/mailbox` (or equivalent), single entry `resources/js/mailbox.js`

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

### Required test suites & example scenarios

* **CaptureService (Unit)**

    * stores payload with metadata and deterministic key format
    * lists messages in **newest‑first** order (server‐side sort)
    * updates existing message; returns null for unknown key
    * clears all messages; purge older than N seconds
    * rejects invalid keys (assertions/exceptions)
* **StoreManager (Unit)**

    * resolves default `file` driver
    * throws on unsupported driver
    * accepts **custom resolver** via config and returns custom store instance
* **Storage\FileStorage (Unit)**

    * persists and retrieves JSON payloads
    * `keys()` filters by timestamp; `delete()` removes files
    * `update()` merges atomically without data loss
    * `clear()` wipes namespace safely
* **Transport\MailboxTransport (Unit)**

    * normalizes and saves message when `enabled=true` and sets `storedKey`
    * decorates underlying transport when provided
    * no‑op capture when disabled; still delegates if decorated
* **Support\MessageNormalizer (Unit)**

    * converts `SentMessage` to canonical structure (from, to, cc, subject, date, text, html, attachments, raw)
    * handles edge cases: missing headers, non‑UTF8, multiple parts
* **Http Controllers (Feature)**

    * `MailboxController` returns paginated list and sorts newest‑first
    * `SendTestMailController` sends sample mail through mailbox transport
    * `ClearMailboxController` empties store and returns success
    * `SeenController` toggles `seen_at` and returns updated entity
    * `AssetController` serves versioned assets with correct headers
* **Middleware (Feature)**

    * `AuthorizeMailboxMiddleware` denies/permits based on config/closure/gate
* **Service Provider & Command (Feature)**

    * `MailboxServiceProvider` registers bindings, publishes assets/config/routes
    * `InstallCommand` publishes resources and prints next steps
* **Config (Arch)**

    * test no `env()` usage outside `config/`
    * config keys exist; defaults are sensible; validation for required options

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

* “Add newest‑first sorting to `CaptureService::all()` and cover with unit & feature tests.”
* “Implement a `S3Storage` driver behind the `MessageStore` contract with contract tests mirrored from `FileStorage`.”
* “Generate Vue components for Mailbox list with read/unread states;
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
