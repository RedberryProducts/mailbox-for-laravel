---
description: Rules for working on Vue/TS/CSS frontend code in resources/
globs: resources/**/*.{vue,ts,js,css}
---

# Frontend Rules

The package runs a fully isolated Inertia.js application that must not interfere with the host app.

- Entry point: `resources/js/dashboard.js` — creates its own Vue app instance
- Pages resolve via `mailbox::` prefix stripping (`mailbox::Dashboard` → `./Pages/Dashboard.vue`)
- All assets build to `public/vendor/mailbox/` (NOT `public/build/`)
- Hot file at `public/vendor/mailbox/mailbox.hot`
- UI components use Reka UI (radix-vue successor)
- TailwindCSS v4 — classes must be scoped/prefixed to avoid host app collisions
- Use `<script setup>` with TypeScript for all new files
- Define data interfaces in `resources/js/types/mailbox.ts`
- The Vue app must NOT import anything from the host Laravel application
- Use Inertia's `router` for navigation, not axios directly (except for polling in `useMailboxPolling`)
- Routes are constructed with the dynamic `mailboxPrefix` from shared Inertia data
- Accessibility: use semantic elements, keyboard navigation, focus management
