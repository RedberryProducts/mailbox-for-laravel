---
description: Rules for writing and modifying tests
globs: tests/**/*.php
---

# Testing Rules

- Framework: **Pest** (not PHPUnit directly)
- Base class: `Redberry\MailboxForLaravel\Tests\TestCase` (extends Orchestra Testbench)
- `TestCase` auto-configures: in-memory SQLite, mock Vite manifest, package providers, mailbox config defaults
- Test file naming: `{ClassName}Test.php` in a directory matching its domain (`Unit/`, `Feature/`, `Commands/`, `Architecture/`)
- Use `describe()` blocks and `it()` functions — Pest style, not PHPUnit methods
- Use `beforeEach()` for shared setup within a describe block
- Feature tests use named routes: `$this->get(route('mailbox.index'))`
- View assertions for the initial HTML page load: `$response->assertViewIs('mailbox::app')->assertViewHas('data', fn (array $data) => count($data['messages']) > 0)`
- JSON assertions for axios/AJAX requests: `$this->getJson(route('mailbox.index'))->assertJsonPath('messages.0.subject', '...')`
- Use Pest datasets for data-driven test cases with realistic data
- For storage tests, go through the `MessageStore` contract — never manipulate file paths directly
- Architecture tests in `tests/Architecture/ArchitectureTest.php` enforce layer boundaries, naming, strict types and code hygiene with Pest `arch()` rules. Add a real rule when you introduce a new boundary; never add placeholder tests. Give each target its own `arch()` call — Pest silently passes an `expect([...])` array that mixes a class with a namespace. Only use `arch()` for the package's own namespaces: for vendor namespaces use the `filesReferencing()` source scan, because Pest autoloads every class in a vendor namespace it is given and some of them fatal on `prefer-lowest` installs
- Run a single test: `vendor/bin/pest --filter="test name"`
- Run a directory: `vendor/bin/pest tests/Unit/`
- Coverage target: **90%+ lines, 80%+ branches**

## Testing Assertions API

For tests that verify captured emails, use the package's built-in testing utilities in `src/Testing/`:

- **`InteractsWithMailbox` trait** — Add to test class (via `uses()` in Pest). Auto-clears mailbox before each test. Provides `$this->mailbox()` for assertions.
- **`Mailbox` facade** — Supports `Mailbox::assertSent()`, `Mailbox::assertSentTo()`, `Mailbox::assertNothingSent()`, etc.
- **`PendingMailboxMessageAssertion`** — Returned by `firstSent()`. Fluent chainable: `->assertHasSubject()->assertSeeInHtml()->assertHasAttachment()`
- **`MailboxAssertions`** — Returned by `$this->mailbox()`. Collection-level: `assertSent()`, `assertSentCount()`, `sent()`, `firstSent()`

Example pattern:
```php
uses(InteractsWithMailbox::class);

it('sends email', function () {
    Mail::to('user@test.com')->send(new SomeMailable);
    Mailbox::assertSentTo('user@test.com');
    Mailbox::firstSent()->assertHasSubject('Expected Subject')->assertSeeInHtml('expected content');
});
```
