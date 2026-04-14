---
description: Rules for writing and modifying tests
globs: tests/**/*.php
---

# Testing Rules

- Framework: **Pest** (not PHPUnit directly)
- Base class: `Redberry\MailboxForLaravel\Tests\TestCase` (extends Orchestra Testbench)
- `TestCase` auto-configures: in-memory SQLite, mock Vite manifest, package providers, Spatie Data config
- Test file naming: `{ClassName}Test.php` in a directory matching its domain (`Unit/`, `Feature/`, `Commands/`, `Architecture/`)
- Use `describe()` blocks and `it()` functions — Pest style, not PHPUnit methods
- Use `beforeEach()` for shared setup within a describe block
- Feature tests use named routes: `$this->get(route('mailbox.index'))`
- Inertia assertions: `$response->assertInertia(fn (Assert $page) => $page->component('mailbox::Dashboard')->has('messages'))`
- Use Pest datasets for data-driven test cases with realistic data
- For storage tests, go through the `MessageStore` contract — never manipulate file paths directly
- Architecture tests in `tests/Architecture/` enforce dependency boundaries (26 rules)
- Run a single test: `vendor/bin/pest --filter="test name"`
- Run a directory: `vendor/bin/pest tests/Unit/`
- Coverage target: **90%+ lines, 80%+ branches**
