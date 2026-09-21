# Testing with Mailbox for Laravel

Mailbox provides Laravel-idiomatic assertion helpers for verifying captured emails. Works with both Pest and PHPUnit.

## Setup

Add the trait to your test class (or use it in a Pest `beforeEach`):

```php
use Redberry\MailboxForLaravel\Testing\InteractsWithMailbox;

uses(InteractsWithMailbox::class);
```

The trait auto-clears the mailbox between tests and exposes `$this->mailbox()` for collection-level assertions.

## Test environment

Assertions only see mail that went through the `mailbox` transport into a working store. Laravel's default `phpunit.xml` sets `MAIL_MAILER=array`, so nothing is captured, and the default `sqlite` store needs tables that do not exist on a fresh checkout. The trait also clears whatever store is configured before every test, so never point tests at the development inbox. Use the `file` driver at a dedicated path, which needs no migrations:

```xml
<env name="MAIL_MAILER" value="mailbox"/>
<env name="MAILBOX_STORE_DRIVER" value="file"/>
<env name="MAILBOX_STORE_FILE_PATH" value="storage/framework/testing/mailbox"/>
```

Attachment contents go to the `mailbox` filesystem disk regardless of driver; call `Storage::fake('mailbox')` in tests that assert on attachments. To keep the `sqlite` or `database` driver in tests, run `php artisan mailbox:install` before the test command (in CI too) so the tables exist; `RefreshDatabase` does not create them because the package runs its own migrations rather than publishing them.

## Collection-level assertions

Available via `$this->mailbox()` or the `Mailbox` facade:

- `assertSent(Closure $callback, ?int $expectedCount = null)`
- `assertNotSent(Closure $callback)`
- `assertNothingSent()`
- `assertSentCount(int $count)`
- `assertSentTo(string $email, ?Closure $callback = null)`
- `assertNotSentTo(string $email, ?Closure $callback = null)`
- `sent(?Closure $callback = null): Collection` — raw query, no assertion
- `firstSent(?Closure $callback = null): PendingMailboxMessageAssertion` — chain into per-message assertions

## Per-message fluent assertions

Chain off `firstSent()`:

**Recipients & headers**

- `assertFrom`, `assertHasTo`, `assertHasCc`, `assertHasBcc`, `assertHasReplyTo`
- `assertHasHeader`

**Subject**

- `assertHasSubject`, `assertSubjectContains`

**Body content**

- `assertSeeInHtml`, `assertDontSeeInHtml`
- `assertSeeInText`, `assertDontSeeInText`
- `assertSeeInOrderInHtml`, `assertSeeInOrderInText`

**Attachments**

- `assertHasAttachment`, `assertHasNoAttachments`, `assertAttachmentCount`

## Example

```php
use Redberry\MailboxForLaravel\Facades\Mailbox;

it('sends the welcome email', function () {
    Mail::to('user@example.com')->send(new WelcomeMail);

    Mailbox::assertSentTo('user@example.com');

    $this->mailbox()
        ->firstSent()
        ->assertHasSubject('Welcome')
        ->assertSeeInHtml('Get started')
        ->assertHasAttachment('terms.pdf');
});
```

## Notes

- For queued mailables, prefer Laravel's `Mail::assertQueued()` — Mailbox captures only what actually goes through the transport.
- Run a single test: `vendor/bin/pest --filter="sends the welcome email"`.
- Run the package's own test suite: `composer test` (or `bin/check` for Pint + PHPStan + Pest).
