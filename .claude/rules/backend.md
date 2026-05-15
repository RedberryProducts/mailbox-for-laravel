---
description: Rules for working on PHP code in src/
globs: src/**/*.php
---

# Backend Rules

- All files must have `declare(strict_types=1)` at the top
- Use readonly properties where the value is set once in the constructor
- Services must depend on the `MessageStore` contract, never on `DatabaseMessageStore` or `FileStorage` directly
- Controllers are invokable (`__invoke`) for single-action, or use standard resource methods
- Constructor injection only — no `app()` or `resolve()` calls inside methods
- `CaptureService` is the single entry point for all storage operations — don't bypass it
- `MailboxServiceProvider` is the ONLY place that reads `config()` and creates bindings
- Transport depends on `CaptureService` + `AttachmentStore`, not on `MessageStore` directly
- DTOs are plain PHP classes using constructor property promotion (no Spatie Laravel Data); `fromArray()` factories rehydrate stored payloads
- Models use configurable connection via `config('mailbox.store.database.connection')`
- Return types and parameter types are mandatory on all public methods
- Throw domain exceptions for invalid states — no silent failures
- No `env()` calls — use `config()` (env is only read in `config/mailbox.php`)
