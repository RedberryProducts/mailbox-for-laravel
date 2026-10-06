# Issue Backlog Roadmap

Working order for the open GitHub issues as of 2026-10-06. Priorities were checked against the code, not taken from the labels. Each group is meant to ship as one PR (or one release for group 7).

## Decisions

- **Default gate (#106):** the built-in `viewMailbox` gate allows only `app()->isLocal()`. Any other environment must define its own gate. Breaking for staging users, so it is noted in CHANGELOG and UPGRADE.
- **Laravel 10 (#98):** support dropped. `illuminate/contracts` and `orchestra/testbench` constraints no longer allow it.
- **Send test email (#110):** the button sends a real `Email` through `MailboxTransport` capture only. It is never forwarded to a decorated (real) mailer.

## 1. Security

- [x] #106 Default gate opens the dashboard to everyone wherever the package is enabled (label: high, confirmed; root cause is code, not only docs)
- [ ] #96 Boost `commands.md` says `--refresh` is harmless and `mailbox:upgrade` rewrites config (medium)

## 2. Quick bug fixes (patch release)

- [ ] #104 `Content-Disposition` built by string concatenation (label: low → medium; UTF-8 filenames are common)
- [ ] #105 `bin/check` installed into every consumer's `vendor/bin` (label: low → medium)
- [ ] #103 `normalizeRaw()` stores the `RawMessage` object (label: medium → low; only reachable with a bare `RawMessage`, `message_id` part already fixed)

## 3. Install commands

- [ ] #102 `mailbox:install --dev` / `mailbox:dev-link` only work inside the monorepo (medium)
- [ ] #109 Hardcoded `vendor/redberry/...` migration path (label: medium → low)

## 4. CI

- [ ] #108 CI does not type-check, build or verify the frontend bundle (label: medium → high)
- [ ] #98 (part) Laravel 10 is allowed by `composer.json` but never tested

## 5. Architecture tests

- [ ] #107 31 stub architecture tests that assert nothing (medium). Done before group 7 so the refactors are guarded.

## 6. Docs sweep

- [ ] #97 `ARCHITECTURE.md` is stale (label: medium → low; the best-effort bullet was fixed by #101)
- [ ] #98 (rest) README env vars, dev-link caveat, screenshot TODO
- [ ] #99 Packagist / npm metadata is placeholder text (low)
- [ ] #100 `unauthorized_redirect` and iframe sandbox comments (low)

## 7. Next minor release (contract changes)

- [ ] #113 Remove dead code (`PublicAssetController`, `dist/`, `mail-data.ts`, `Storage\AttachmentStore` shim) (low)
- [ ] #111 Batch attachment lookups per page (`findByMessages`) (low)
- [ ] #112 Make `AttachmentData::$content` explicit instead of detecting base64 by heuristic (low)
- [ ] #110 Send test email through the real capture pipeline (label: medium → low)
