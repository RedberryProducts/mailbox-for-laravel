---
description: Rules for working on Vue/TS/CSS frontend code in resources/
globs: resources/**/*.{vue,ts,js,css}
---

# Frontend Rules

The package runs a fully isolated Vue 3 application that must not interfere with the host app. **Inertia.js is not used** — the Vue app is standalone and talks to the package's own JSON endpoints via axios.

- Entry point: `resources/js/dashboard.js` — creates its own Vue app instance
- Initial state is embedded as JSON by the Blade layout (`<script id="mailbox-data" type="application/json">`) and parsed at boot
- Shared state lives in `resources/js/lib/mailboxStore.ts` (a plain reactive object) — components read/mutate the store directly, no prop drilling
- All assets build to `public/vendor/mailbox/` (NOT `public/build/`)
- Hot file at `public/vendor/mailbox/mailbox.hot`
- UI components use Reka UI (radix-vue successor)
- TailwindCSS v4 — classes must be scoped/prefixed to avoid host app collisions
- Use `<script setup>` with TypeScript for all new files
- Define data interfaces in `resources/js/types/mailbox.ts`
- The Vue app must NOT import anything from the host Laravel application
- Use `axios` for all server interactions; build URLs with `mailboxUrl(path)` from the store so the configurable `mailbox.path` prefix is respected
- Accessibility: use semantic elements, keyboard navigation, focus management
