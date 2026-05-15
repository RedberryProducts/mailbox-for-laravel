# Dashboard Frontend

The dashboard is a **standalone Vue 3 app** that ships with the package. It is intentionally decoupled from the host application — do not import host code into it, do not assume Inertia, and do not let its styles leak.

## Boot sequence

1. `MailboxController` renders `mailbox::app` (Blade) on first load.
2. The Blade view embeds the initial payload as `<script id="mailbox-data" type="application/json">…</script>`.
3. `resources/js/dashboard.js` parses that script tag, hydrates `resources/js/lib/mailboxStore.ts`, and mounts the Vue app.
4. Subsequent requests hit the same controllers but return JSON (because `$request->wantsJson()` is true).

**Inertia is not used.** The Vue app talks to the package's own JSON endpoints via `axios`.

## Shared state

`resources/js/lib/mailboxStore.ts` is a plain `reactive()` object — components read and mutate it directly. No prop drilling, no Pinia, no Vuex. The store also exports `mailboxUrl(path = '')` — always use it to build URLs so the configurable `config('mailbox.path')` prefix is respected.

```ts
import { store, mailboxUrl } from '../lib/mailboxStore';

const url = mailboxUrl(`messages/${id}`); // respects custom path prefix
```

## Build output and Vite

- Vite config: `vite.config.js` builds into `public/vendor/mailbox/` (NOT `public/build/`).
- Hot file: `public/vendor/mailbox/mailbox.hot` — referenced by `Vite::useHotFile()` in `resources/views/app.blade.php`.
- If a frontend change isn't reflected, run `npm run build` (production) or `npm run dev` (watch).

## Stack and conventions

- Vue 3 with `<script setup>` and TypeScript for all new files.
- UI primitives from **Reka UI** (the successor to radix-vue; `radix-vue` is still present for back-compat). Prefer Reka UI imports for new components.
- TailwindCSS v4 — keep classes scoped or prefixed so styles do not leak into the host app.
- Type interfaces live in `resources/js/types/mailbox.ts`.

## Component layout

- `Pages/Dashboard.vue` — the only page component.
- `components/mail/` — domain components (11): list, list-item, preview (body/header/tabs), filter bar, recipient filter dropdown, attachment list, HTML iframe viewer, layout shell.
- `components/ui/` — reusable primitives (button, input, tabs, select, …).
- `composables/useMailboxPolling.ts` — auto-refresh polling.
- `composables/useAttachmentIcon.ts` — file-type icon resolution.
- `lib/mailboxStore.ts`, `lib/mail-data.ts`, `lib/utils.ts`.

## Things to avoid

- Don't import anything from the host Laravel app.
- Don't render user-supplied HTML without sanitization (HTML preview lives inside a sandboxed iframe — `HtmlIframeViewer.vue`).
- Don't introduce global Tailwind classes that could affect the host.
- Don't add a router — the dashboard is single-page; navigation is state-driven through the store.
