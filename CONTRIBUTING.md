# Contributing

Thanks for your interest in improving Mailbox for Laravel. This guide covers local setup, testing, and the coding standards the project enforces in CI.

## Local setup

```bash
git clone https://github.com/RedberryProducts/mailbox-for-laravel.git
cd mailbox-for-laravel
composer install
npm install
```

The package runs against Orchestra Testbench for HTTP and database integration, so there is no separate test application to boot.

## Running checks

The full QA sweep (Pint → PHPStan → Pest) runs via:

```bash
bin/check
```

Individually:

```bash
composer format        # Laravel Pint (PSR-12)
composer analyse       # PHPStan (level 5)
composer test          # Pest
composer test-coverage # Pest with coverage
```

Run a single test or directory:

```bash
vendor/bin/pest --filter="test name"
vendor/bin/pest tests/Unit/
```

Coverage target: **90%+ lines, 80%+ branches.** CI will fail a PR that drops below the threshold.

## Frontend assets

The dashboard is a scoped Vue 3 app that bootstraps from a JSON payload embedded in the Blade layout and talks to the package's own JSON endpoints via axios — no Inertia, no shared frontend runtime with the host. See [ARCHITECTURE.md](ARCHITECTURE.md) for the deep-dive.

```bash
npm run dev    # Vite watch mode with HMR
npm run build  # Production build into public/vendor/mailbox/
```

When you consume the package via a local path repository and want hot reload without re-copying assets on every change, use:

```bash
php artisan mailbox:install --dev
```

This symlinks the package's `public/vendor/mailbox` into the host app instead of copying it.

## Coding standards

- All PHP files start with `declare(strict_types=1)`.
- Public methods carry full parameter and return types.
- Domain services depend on contracts (`MessageStore`, `AttachmentStore`) — never on concrete drivers.
- `env()` lives only in `config/mailbox.php`. Everywhere else reads `config('mailbox.*')`.
- `MailboxServiceProvider` is the only place that reads config to create bindings.
- DTOs use Spatie Laravel Data.
- No `dd()`, `dump()`, `ray()`, or `var_dump()` in committed code.

See [CLAUDE.md](CLAUDE.md) for the full list of project conventions.

## Tests are not optional

Every new class or feature ships with tests:

- Unit tests (`tests/Unit/`) for services, contracts, drivers, DTOs.
- Feature tests (`tests/Feature/`) for routes, middleware, and HTTP behavior.
- Architecture tests (`tests/Architecture/`) enforce dependency boundaries.

Tests use Pest's `describe()` / `it()` style. For email-related tests, use the `InteractsWithMailbox` trait from `src/Testing/` — it auto-clears the mailbox between tests and exposes the same assertion API documented in the README.

## Commit messages

The project uses [Conventional Commits](https://www.conventionalcommits.org/):

- `feat:` — user-facing features
- `fix:` — bug fixes
- `docs:` — documentation only
- `test:` — test additions or changes
- `refactor:` — internal restructuring with no behavior change
- `chore:` — tooling, dependencies, CI

## Pull request checklist

Before opening a PR:

1. `bin/check` passes locally
2. New code has tests and maintains coverage
3. README / CHANGELOG updated for user-facing changes
4. Commit messages follow the convention above

CI runs on PHP 8.3 and 8.4 with Laravel 11 and 12, on both Ubuntu and Windows.

## Security

Please do not open public issues for security vulnerabilities. Email **security@redberry.ge** and we'll respond quickly.
