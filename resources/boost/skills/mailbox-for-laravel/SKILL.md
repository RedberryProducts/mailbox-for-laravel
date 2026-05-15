---
name: mailbox-for-laravel
description: Use this skill when working with the redberry/mailbox-for-laravel package — capturing, inspecting, or asserting against outgoing mail in development. Activates when writing tests that use the InteractsWithMailbox trait or Mailbox facade (assertSent, assertSentTo, assertNothingSent, firstSent and per-message assertions like assertHasSubject, assertSeeInHtml, assertHasAttachment); when configuring the mailbox transport in config/mailbox.php; when picking a storage driver (sqlite/database/file) or pairing message and attachment stores; when extending MessageStore (10 methods) or AttachmentStore (8 methods) contracts with a custom driver via config('mailbox.store.resolvers'); when adding routes/middleware behind the viewMailbox gate; when building UI inside the self-contained Vue 3 dashboard under resources/js (no Inertia — standalone Vue app talking to JSON endpoints); when running mailbox:install, mailbox:clear, mailbox:dev-link, or mailbox:upgrade; or when troubleshooting captured mail, CID-rewritten inline images, or the dedicated SQLite store at storage/app/mailbox/mailbox.sqlite. Do not use for generic Laravel mail (Mail::send, Mailables) without the mailbox package.
license: MIT
metadata:
  author: redberry
---

# Mailbox for Laravel

Local in-app inbox that intercepts outgoing mail via a Symfony transport, stores it through pluggable drivers, and serves it via a self-contained Vue 3 dashboard. Zero coupling to the host app's frontend stack.

## When to activate

- Writing tests that capture sent mail via `InteractsWithMailbox` or the `Mailbox` facade
- Configuring `config/mailbox.php` (driver, path, gate, polling, retention)
- Using or extending the `mailbox` mail transport
- Implementing custom `MessageStore` / `AttachmentStore` drivers (both halves of the pair)
- Working with inline images via `Support\CidRewriter`
- Touching the dashboard Vue app under `resources/js/` (note: Inertia is **not** used)
- Running package commands: `mailbox:install`, `mailbox:clear`, `mailbox:dev-link`, `mailbox:upgrade`

## Quick reference

### 1. Test assertions → `rules/testing.md`

- Use `InteractsWithMailbox` trait — auto-clears between tests, exposes `$this->mailbox()`
- Collection-level: `assertSent`, `assertNotSent`, `assertNothingSent`, `assertSentCount`, `assertSentTo`, `assertNotSentTo`, `sent`, `firstSent`
- Per-message fluent (off `firstSent()`): `assertFrom`, `assertHasTo`, `assertHasSubject`, `assertSeeInHtml`, `assertHasAttachment`, `assertHasHeader`, etc.

### 2. Capture pipeline & custom drivers → `rules/architecture.md`

- `MailboxTransport` → `MessageNormalizer` → `CaptureService` → `MessageStore` driver
- `CaptureService` is the storage-driver-agnostic entrypoint — store, list, find, update, delete, purge
- `StoreManager` resolves drivers: `sqlite` (default — dedicated SQLite file), `database` (bring-your-own-connection — same Eloquent store), `file` (JSON on disk)
- `MessageStore` has **10 methods**, `AttachmentStore` has 8 — both halves are paired, both must be implemented for a custom driver
- Custom drivers register through `config('mailbox.store.resolvers')` (singular `store`), not `Manager::extend()`
- `Support\CidRewriter` resolves inline `cid:` references through `AttachmentStore`, regardless of driver

### 3. HTTP & authorization → `rules/http.md`

- Routes mounted under `config('mailbox.path', 'mailbox')` with `web` + `mailbox.authorize` middleware
- Authorization through the `viewMailbox` gate — define your own gate before exposing in production
- `MailboxController` returns Blade for browser requests, JSON when `$request->wantsJson()` is true

### 4. Dashboard frontend → `rules/frontend.md`

- Standalone Vue 3 app — **Inertia is not used**; talks to package's own JSON endpoints via axios
- Shared state in `resources/js/lib/mailboxStore.ts`; use `mailboxUrl()` from the store to respect the configurable path prefix
- Vite builds into `public/vendor/mailbox/` (hot file at `public/vendor/mailbox/mailbox.hot`)
- Reka UI primitives, TailwindCSS v4 — keep classes scoped/prefixed

### 5. Artisan commands → `rules/commands.md`

- `mailbox:install` — run once after `composer require` (publishes assets/config, runs migrations)
- `mailbox:clear` — clear stored mail; `--outdated` honors retention
- `mailbox:dev-link` — symlink package assets when developing inside the package itself
- `mailbox:upgrade` — v1 → v2 config/env rewriter

### 6. Conventions → `rules/conventions.md`

- Namespace `Redberry\MailboxForLaravel`
- No `env()` outside `config/`
- Constructor injection of interfaces; avoid facades inside core services
- Vue: `<script setup>`, TypeScript for new files, scoped/prefixed Tailwind classes
- File IO only through storage drivers

### 7. Things to avoid → `rules/avoid.md`

- Don't bypass the `MessageStore` contract
- Don't add external services or self-hosted SMTP
- Don't render unsanitized HTML in the dashboard
- Don't import from the host app's frontend
- Don't leave `dd()`, `dump()`, `ray()`, `var_dump()` in committed code

## How to apply

1. Identify the task surface (test, config, driver, dashboard, route) and read the matching rule file.
2. Check sibling files in the package for the established pattern — match it.
3. For exact API syntax of installed Laravel/Pest versions, verify with `search-docs`.

## Definition of done

1. Tests added and passing (`composer test`)
2. PHPStan passes (`composer analyse`)
3. Pint passes (`composer format`)
4. No new `env()` outside `config/`
5. README/CHANGELOG updated for user-facing changes
6. Conventional Commits format used (`feat:`, `fix:`, `chore:`, `test:`, `refactor:`, `docs:`)
