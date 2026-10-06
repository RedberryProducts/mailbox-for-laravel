# Artisan Commands

The package ships four `mailbox:*` Artisan commands.

## `mailbox:install`

```
mailbox:install [--force] [--refresh] [--dev]
```

Run once after `composer require`. Publishes the package's compiled assets to `public/vendor/mailbox/`, publishes `config/mailbox.php`, and runs the package migrations.

- `--dev` — symlinks assets instead of copying (calls `mailbox:dev-link` internally). Use when working **inside** the package repo, not in a consumer app.
- `--force` — overwrite already-published assets **and** `config/mailbox.php`.
- `--refresh` — **destructive.** Runs `migrate:refresh` on the mailbox connection, dropping and recreating the mailbox tables; with the default SQLite connection it deletes the database file first. Every captured message is lost. Never suggest it as a harmless re-install.

## `mailbox:clear`

```
mailbox:clear [--outdated]
```

Deletes stored messages and their attachments through the configured storage drivers.

- Without flags: clears everything.
- `--outdated`: prunes only messages older than `config('mailbox.retention')` (default 86400 seconds = 24 h). The retention scheduler runs this nightly only when `config('mailbox.retention_schedule')` is true — it defaults to `false`, so auto-pruning is opt-in.

## `mailbox:dev-link`

```
mailbox:dev-link
```

Symlinks the package's `public/` into the host app's `public/vendor/mailbox/` and removes any stale copies first. Only useful when developing the package itself (the package is path-installed in the host app).

## `mailbox:upgrade`

```
mailbox:upgrade [--fresh]
```

One-shot upgrade helper for v1 → v2. It **detects and prints** stale config keys (`mailbox.route` → `mailbox.path`, `mailbox.retention.seconds` → `mailbox.retention`, …) and stale `.env` variables (`MAILBOX_DASHBOARD_ROUTE` → `MAILBOX_PATH`, etc.); it never edits `config/mailbox.php` or `.env`, so the user still renames them by hand. It then asks whether to refresh the schema and calls `mailbox:install --force` (with `--refresh` if confirmed), which re-publishes the config over the user's copy. `--fresh` skips the prompt and always refreshes, so captured mail is lost.

Existing v2 installs don't need this; it's safe to skip.

## Notes

- Always pass `--no-interaction` when invoking these from automation or other commands.
- New commands belong in `src/Commands/` with signatures namespaced under `mailbox:` and corresponding feature tests in `tests/Commands/`.
