# Things to Avoid

- **Don't call `env()` outside `config/` files.** Reads outside the config layer break `config:cache`.
- **Don't use facades in core services** — use interfaces + constructor injection so the package stays test-friendly and Octane-safe.
- **Don't add external services or self-hosted SMTP.** This package captures locally only.
- **Don't render raw HTML without sanitization** in the dashboard.
- **Don't write to arbitrary paths.** Storage drivers constrain paths to the package directory.
- **Don't bypass the `MessageStore` contract.** All storage goes through `CaptureService`.
- **Don't add global state or singletons** (except bindings declared in the service provider).
- **Don't import from the host app's frontend.** The Vue app is fully isolated by design.
- **Don't hardcode absolute file paths** in package code.
- **Don't leave `dd()`, `dump()`, `ray()`, or `var_dump()`** in committed code.
- **Don't mix message and attachment store drivers** across the pair (e.g. `DatabaseMessageStore` with `FileAttachmentStore`).
- **Don't modify migrations** that have already shipped — add a new migration instead.
