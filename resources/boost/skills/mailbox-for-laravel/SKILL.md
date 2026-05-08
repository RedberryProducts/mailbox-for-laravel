---
name: mailbox-for-laravel
description: Use this skill when working with the redberry/mailbox-for-laravel package — capturing, inspecting, or asserting against outgoing mail in development. Activates when writing tests that use the InteractsWithMailbox trait or Mailbox facade (assertSent, assertSentTo, assertNothingSent, firstSent and per-message assertions like assertHasSubject, assertSeeInHtml, assertHasAttachment); when configuring the mailbox transport in config/mailbox.php; when picking a storage driver (sqlite/database/file) or pairing message and attachment stores; when extending MessageStore or AttachmentStore contracts with a custom driver; when adding routes/middleware behind the viewMailbox gate; when building UI inside the self-contained Vue 3 dashboard under resources/js; when running mailbox:install, mailbox:clear, or mailbox:dev-link; or when troubleshooting captured mail, CID-rewritten inline images, or the dedicated SQLite store at storage/app/mailbox/mailbox.sqlite. Do not use for generic Laravel mail (Mail::send, Mailables) without the mailbox package.
license: MIT
metadata:
  author: redberry
---

# Mailbox for Laravel

Local in-app inbox that intercepts outgoing mail via a Symfony transport, stores it through pluggable drivers, and serves it via a self-contained Vue 3 dashboard. Zero coupling to the host app's frontend stack.

## When to activate

- Writing tests that capture sent mail via `InteractsWithMailbox` or the `Mailbox` facade
- Configuring `config/mailbox.php` (driver, path, gate, polling)
- Using or extending the `mailbox` mail transport
- Implementing custom `MessageStore` / `AttachmentStore` drivers
- Touching the dashboard Vue app under `resources/js/`
- Running package commands: `mailbox:install`, `mailbox:clear`, `mailbox:dev-link`

## Quick reference

### 1. Test assertions → `rules/testing.md`

- Use `InteractsWithMailbox` trait — auto-clears between tests, exposes `$this->mailbox()`
- Collection-level: `assertSent`, `assertNotSent`, `assertNothingSent`, `assertSentCount`, `assertSentTo`, `assertNotSentTo`, `sent`, `firstSent`
- Per-message fluent (off `firstSent()`): `assertFrom`, `assertHasTo`, `assertHasSubject`, `assertSeeInHtml`, `assertHasAttachment`, `assertHasHeader`, etc.

### 2. Capture pipeline → `rules/architecture.md`

- `MailboxTransport` → `MessageNormalizer` → `CaptureService` → `MessageStore` driver
- `CaptureService` is the storage-driver-agnostic entrypoint — store, list, find, update, delete, purge
- `StoreManager` resolves drivers: `sqlite` (default), `database`, `file`
- Message and attachment stores are paired — never mix drivers across the pair

### 3. HTTP & authorization → `rules/http.md`

- Routes mounted under `config('mailbox.path', 'mailbox')` with `web` + `mailbox.authorize` middleware
- Authorization through the `viewMailbox` gate — define your own gate before exposing in production
- `MailboxController` returns Blade for browser requests, JSON when `$request->wantsJson()` is true

### 4. Conventions → `rules/conventions.md`

- Namespace `Redberry\MailboxForLaravel`
- No `env()` outside `config/`
- Constructor injection of interfaces; avoid facades inside core services
- Vue: `<script setup>`, TypeScript for new files, scoped/prefixed Tailwind classes
- File IO only through storage drivers

### 5. Things to avoid → `rules/avoid.md`

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
