# Coding Conventions

## PHP

- **Namespace**: `Redberry\MailboxForLaravel`
- **No `env()` outside `config/`** — use `config()` everywhere else
- **Interfaces + constructor injection** — avoid facades in core services
- **Readonly / immutable** where possible; prefer DTOs and value objects over loose arrays
- **Stateless services** — transport may store last key but no global singletons (only service-provider bindings)
- **Throw domain exceptions** for invalid states; no silent failures
- **File IO via storage drivers only** — never `storage_path()` directly in controllers
- **No hardcoded absolute paths** in package code

## Vue / Frontend

- `<script setup>` syntax
- TypeScript for new files
- Scoped or prefixed Tailwind classes — never leak styles into the host app
- Components live under `resources/js/`:
  - `Pages/Dashboard.vue` — main page
  - `components/mail/` — domain components (list, preview, filters)
  - `components/ui/` — reusable UI primitives
  - `composables/` — shared reactive logic
  - `types/mailbox.ts` — TypeScript interfaces

## Testing policy

- Every new class or feature MUST have tests
- **Unit** for services, contracts, drivers, DTOs
- **Feature** for HTTP routes, middleware, commands
- **Architecture** tests for dependency boundaries (already 26 rules in `tests/Architecture/`)
- Coverage target: **90%+ lines, 80%+ branches**
- Pest `describe()` blocks and dataset-driven cases preferred

## Commits & PRs

- **Conventional Commits**: `feat:`, `fix:`, `chore:`, `test:`, `refactor:`, `docs:`
- README and CHANGELOG updated for user-facing changes
- `composer format` (Pint), `composer analyse` (PHPStan level 5), `composer test` (Pest) all green before merge
