# Dashboard Architecture

This document explains how the package's dashboard works and why it is deliberately decoupled from the host application's frontend stack. Earlier versions of the package shipped with an isolated Inertia.js app; that was removed so the package no longer pins the host app to any particular Inertia or protocol version.

## Design goals

1. **Zero interference with the host app.** Installing the package must not force a particular frontend stack (Inertia, Livewire, React, Blade) on the host, nor conflict with whatever the host already uses.
2. **Single dependency footprint.** No shared JS runtime, no shared bundle, no protocol to keep in sync across versions.
3. **Feature parity with the previous Inertia-based dashboard.** Live polling, search, load-more pagination, delete/clear-inbox, and read-state tracking must continue to work.

## High-level flow

```
Browser GET /mailbox
  └─> MailboxController returns:
        • view('mailbox::app', ['data' => $props])   // HTML request
        • response()->json($props)                    // AJAX request (wantsJson)
```

The Blade layout (`resources/views/app.blade.php`) embeds the initial `$props` payload as a `<script id="mailbox-data" type="application/json">` block. The bundled Vue app (`resources/js/dashboard.js`) parses it, hydrates a shared reactive store, and mounts a plain `createApp()` onto `<div id="mailbox-app">`.

All subsequent interactions — polling for new mail, changing the search query, loading the next page, marking a message as seen, deleting a message, clearing the inbox — go through the same `MailboxController` (or the existing JSON-returning sibling controllers like `SeenController`), using axios with an explicit `Accept: application/json` header. No Inertia, no protocol version to worry about.

## Key components

### Backend

| File | Role |
| --- | --- |
| `src/Http/Controllers/MailboxController.php` | Dual-mode: returns the Blade view for browser requests, JSON for `wantsJson()` requests |
| `src/Http/Controllers/ClearMailboxController.php` | Returns a redirect for browser, JSON for `wantsJson()` |
| `src/Http/Controllers/DeleteMailboxMessageController.php` | Same dual-mode as Clear |
| `src/Http/Controllers/SeenController.php` | Always JSON (only ever called from axios) |
| `src/Http/Controllers/AttachmentController.php` | Binary downloads / inline serving (unchanged) |
| `resources/views/app.blade.php` | Root Blade layout. Embeds the initial payload and mounts `#mailbox-app` |
| `routes/mailbox.php` | Prefixed with `config('mailbox.path', 'mailbox')`. Middleware: `web` + `mailbox.authorize` |

There is **no Inertia middleware**. The package removed `HandleInertiaRequests` and the `mailbox.inertia` alias.

### Frontend

| File | Role |
| --- | --- |
| `resources/js/dashboard.js` | Entry point. Reads the embedded JSON blob, seeds the store, configures axios, mounts Vue |
| `resources/js/lib/mailboxStore.ts` | Shared reactive store (`reactive<MailboxData>`) exposed to all components. Also exports `mailboxUrl(path)` for building prefix-aware URLs |
| `resources/js/Pages/Dashboard.vue` | Thin page shell — delegates to `MailboxLayout` |
| `resources/js/components/mail/MailboxLayout.vue` | Orchestrates list + preview, handles search and load-more via axios, syncs query string with `history.replaceState` |
| `resources/js/components/mail/MailboxFilterBar.vue` | Recipient filter + search input + clear-inbox confirm; `axios.delete` to clear |
| `resources/js/components/mail/MailboxPreviewHeader.vue` | Per-message header with delete confirm; `axios.delete` to remove |
| `resources/js/composables/useMailboxPolling.ts` | Interval-based polling via `axios.get`; merges new messages into the store without replacing selection |

### Asset pipeline

The Vite build is unchanged in spirit: scoped to `public/vendor/mailbox/` with a dedicated hot file.

```js
// vite.config.js
laravel({
    hotFile: 'public/vendor/mailbox/mailbox.hot',
    buildDirectory: 'vendor/mailbox',
    input: ['resources/js/dashboard.js'],
    refresh: true,
})
```

The published assets live under `public/vendor/mailbox/` so they cannot collide with the host app's `public/build/`.

## Isolation mechanisms

### 1. Dedicated mount point and Vue instance

The dashboard mounts on `<div id="mailbox-app">` and creates its own Vue app. Nothing from the host app's frontend touches it, and vice versa.

### 2. Scoped assets

All bundled JS/CSS publishes to `public/vendor/mailbox/`. The package's Vite manifest is separate from the host's.

### 3. No shared JavaScript runtime

The dashboard no longer ships `@inertiajs/vue3`. The only runtime library it pulls in is Vue 3 itself (plus small helpers: axios, date-fns, reka-ui, lucide-vue-next). The host app's Inertia/React/Livewire setup — whatever it happens to be — is untouched.

### 4. No Composer dependency on `inertiajs/inertia-laravel`

Previously the package required `inertiajs/inertia-laravel: ^3.0`, which forced the host app's Inertia version to intersect with that constraint. That dependency has been removed entirely.

## Data payload shape

The JSON blob embedded in `app.blade.php` — and the payload returned from `MailboxController` on `wantsJson()` — has this structure:

```ts
interface MailboxData {
    messages: Message[]
    pagination: {
        total: number
        per_page: number
        current_page: number
        has_more: boolean
        latest_timestamp: number | null
    }
    polling: { enabled: boolean; interval: number }
    search: string
    mailboxPrefix: string    // dynamic — matches config('mailbox.path')
    csrfToken: string | null // rotated per session; axios sets X-CSRF-TOKEN from this
    title: string
    subtitle: string
}
```

## Testing strategy

### Mock Vite manifest

`tests/TestCase.php` still creates a fake manifest so the `Vite::useBuildDirectory(...)` call in the Blade layout does not need real built assets:

```php
file_put_contents($manifestPath.'/manifest.json', json_encode([
    'resources/js/dashboard.js' => [
        'file' => 'assets/dashboard.js',
        'src' => 'resources/js/dashboard.js',
        'isEntry' => true,
        'css' => ['assets/dashboard.css'],
    ],
]));
```

### HTTP-level assertions

For the initial HTML response:

```php
$response->assertViewIs('mailbox::app');
$response->assertViewHas('data', fn (array $data) => count($data['messages']) === 2);
```

For axios/AJAX responses:

```php
$this->getJson(route('mailbox.index'))
    ->assertJsonPath('messages.0.subject', 'Test Email')
    ->assertJsonPath('pagination.total', 1);
```

## Storage architecture: paired drivers

Capture is split into two driver-shaped interfaces that are always resolved as a pair:

- **`Contracts\MessageStore`** — persists the canonical message payload (subject, headers, html/text, timestamps).
- **`Contracts\AttachmentStore`** — persists attachment metadata + content. Returns `DTO\StoredAttachment` value objects so callers never see the underlying Eloquent model or sidecar shape.

`MailboxServiceProvider` reads `mailbox.store.driver` and binds the matching pair:

| `mailbox.store.driver` | MessageStore             | AttachmentStore             | Metadata location                                         |
| ---------------------- | ------------------------ | --------------------------- | --------------------------------------------------------- |
| `sqlite` (default)     | `DatabaseMessageStore`   | `DatabaseAttachmentStore`   | `mailbox_messages` + `mailbox_attachments` (cascade FK)   |
| `database`             | `DatabaseMessageStore`   | `DatabaseAttachmentStore`   | Same as `sqlite` — alias for bring-your-own-connection    |
| `file`                 | `FileStorage`            | `FileAttachmentStore`       | `storage/app/mail-inbox/{id}.json` + per-message sidecars |

In both cases the attachment **content bytes** live on the configured `mailbox.attachments.disk`, so download/inline URLs are identical regardless of driver.

`CaptureService` is the only consumer that knows about both halves of the pair and is responsible for cascade cleanup on `delete($id)`, `clearAll()`, and `purgeOlderThan($seconds)` — the `MessageStore::idsOlderThan()` method exists specifically so the service can collect victim ids before delegating purge.

Custom drivers can plug in either half (or both) by binding the relevant contracts in their own service provider; nothing in the package depends on the concrete classes.

## Mail capture pipeline

Outbound mail flows through the custom `mailbox` transport before any network driver sees it:

```
Mail::send(...)
  → MailboxTransport::doSend($message, $envelope)
  → MessageNormalizer::normalize($original, $envelope, $raw, true)
  → CaptureService::store($payload)
  → MessageStore + AttachmentStore (paired driver)
```

The normalized payload is a flat associative array keyed by the fields the dashboard and testing assertions read back:

```json
{
    "from": "sender@example.com",
    "to": ["recipient@example.com"],
    "cc": [],
    "bcc": [],
    "subject": "Test Email",
    "date": "2025-11-19T10:30:00+00:00",
    "text": "Plain text body",
    "html": "<html>HTML body</html>",
    "attachments": [],
    "raw": "Full RFC 822 message",
    "timestamp": 1732017000,
    "saved_at": "2025-11-19T10:30:00.000000Z",
    "seen_at": null
}
```

The transport is stateless — if a previous transport is chained (via `mail.mailers.mailbox.transport`), it is invoked after capture, so capture is always best-effort and never blocks real delivery.

## Summary

- Dashboard is a plain Vue 3 SPA that bootstraps from JSON embedded in a Blade view
- Subsequent interactions are vanilla axios calls to JSON endpoints — no Inertia, no protocol negotiation
- Host app can use any frontend stack (or none) without conflict
- The package no longer depends on `inertiajs/inertia-laravel` at any version
