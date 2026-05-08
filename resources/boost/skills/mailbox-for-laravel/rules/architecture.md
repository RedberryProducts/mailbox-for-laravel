# Architecture

## Mail capture pipeline

```
MailboxTransport → MessageNormalizer → CaptureService → MessageStore driver
```

1. **`MailboxTransport`** (`src/Transport/`) — registered as the `mailbox` mail driver. Intercepts sent mail and optionally decorates another transport. Toggleable.
2. **`MessageNormalizer`** (`src/Support/`) — converts Symfony `Email` / `RawMessage` into a canonical array, extracting attachments as `AttachmentData` DTOs.
3. **`CaptureService`** (`src/CaptureService.php`) — high-level API: `store`, `list`, `find`, `update`, `delete`, `purge`. Returns `MailboxMessageData` DTOs. Storage-driver-agnostic. Cascades attachment cleanup automatically on `delete`, `clearAll`, `purgeOlderThan`.
4. **`StoreManager`** (`src/StoreManager.php`) — extends Laravel's `Manager`. Resolves driver: `sqlite` (default), `database`, or `file`.
5. **Storage drivers** (`src/Storage/`) — `DatabaseMessageStore` (Eloquent, dedicated SQLite at `storage/app/mailbox/mailbox.sqlite`) and `FileStorage` (JSON on disk). Both implement `Contracts\MessageStore`.
6. **Attachment store pair** — `DatabaseAttachmentStore` or `FileAttachmentStore` is bound alongside the chosen `MessageStore`. Both implement `Contracts\AttachmentStore` and return `StoredAttachment` DTOs. `CidRewriter` resolves inline `cid:` references through the contract regardless of driver.

## Self-contained Vue dashboard

The dashboard is **completely isolated** from the host app:

- The Blade root view (`mailbox::app`) embeds the initial page payload as a `<script type="application/json">` blob.
- `dashboard.js` parses it, hydrates a shared reactive store, and mounts a plain Vue 3 app.
- All subsequent interactions (polling, search, pagination, delete) hit the same `MailboxController` — HTML on first load, JSON on AJAX (`$request->wantsJson()`).
- Own Vite build output at `public/vendor/mailbox/`. Own Vue app instance. Zero host-app coupling.

See `ARCHITECTURE.md` in the repo root for the full deep-dive.

## Extending with a custom driver

1. Implement `Contracts\MessageStore` (9 methods including `idsOlderThan`) and `Contracts\AttachmentStore` (8 methods).
2. Register the driver via `StoreManager::extend()` inside a service provider.
3. Add config entry under `config('mailbox.stores')`.
4. Add unit tests for each contract method and architecture tests for boundary rules.
