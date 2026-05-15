# HTTP & Authorization

## Routes

All routes are mounted under `config('mailbox.path', 'mailbox')` with two middleware:

- `web` — session, CSRF, cookie encryption
- `mailbox.authorize` — wraps the `viewMailbox` gate

Use named routes in tests and views, never hardcoded paths. The package exposes:

```php
route('mailbox.index')                              // GET   /
route('mailbox.messages.clear')                     // DELETE /messages
route('mailbox.messages.destroy', $id)              // DELETE /messages/{id}
route('mailbox.messages.seen', $id)                 // POST  /messages/{id}/seen
route('mailbox.messages.attachments', $messageId)   // GET   /messages/{messageId}/attachments
route('mailbox.attachments.download', $id)          // GET   /attachments/{id}/download
route('mailbox.attachments.inline', $id)            // GET   /attachments/{id}/inline
route('mailbox.test-email')                         // POST  /test-email
```

There is no per-message `show` route — single-message views are rendered client-side from the dashboard payload.

## Authorization

The `viewMailbox` gate ships with a default closure that allows all access in non-production environments. Override it in your `AuthServiceProvider` before exposing the dashboard publicly:

```php
Gate::define('viewMailbox', function (?User $user) {
    return $user?->isAdmin() === true;
});
```

## Controller responses

`MailboxController` is content-negotiation aware:

- Browser request → returns the Blade view `mailbox::app` with `data` payload
- `$request->wantsJson()` → returns the same payload as JSON

Tests should assert both shapes:

```php
// Initial page load
$this->get(route('mailbox.index'))
    ->assertViewIs('mailbox::app')
    ->assertViewHas('data', fn ($data) => /* ... */);

// AJAX
$this->getJson(route('mailbox.index'))
    ->assertJsonPath('messages.0.subject', 'Welcome');
```

## Middleware

`AuthorizeMailboxMiddleware` lives in `src/Http/Middleware/`. It is the only place that consults the gate — controllers should not duplicate the check.
