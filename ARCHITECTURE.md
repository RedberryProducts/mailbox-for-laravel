# Scoped Inertia.js Integration Architecture

This document explains how this package implements a fully isolated Inertia.js dashboard that works independently from the host application.

## Overview

The package provides a self-contained Inertia.js-powered dashboard for viewing captured emails. The implementation ensures complete isolation from the host application's frontend stack, allowing it to work with any Laravel application regardless of whether it uses Inertia, Vue, React, Blade, or any other frontend framework.

## Key Components

### 1. Backend: Isolated Inertia Stack

#### Middleware: `HandleInertiaRequests`

Located at `src/Http/Middleware/HandleInertiaRequests.php`, this middleware:

- Extends Inertia's base middleware
- Configures the root view as `mailbox::layout`
- Shares package-specific data (mailbox prefix, CSRF token)
- Operates independently from any host application Inertia middleware

```php
protected $rootView = 'mailbox::layout';

public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'mailboxPrefix' => config('mailbox.route', 'mailbox'),
        'csrfToken' => csrf_token(),
    ]);
}
```

#### Controllers

All controllers return Inertia responses with namespaced component names:

```php
// MailboxController.php
return Inertia::render('mailbox::Dashboard', [
    'messages' => $messages,
    'title' => 'Mailbox for Laravel',
]);
```

The `mailbox::` prefix ensures component resolution is scoped to this package.

#### Routes

Routes are configured with both authorization and Inertia middleware:

```php
Route::middleware([
    config('mailbox.middleware', ['web']),
    ['mailbox.inertia', 'mailbox.authorize']
])
```

### 2. Frontend: Scoped Vue + Inertia Application

#### Layout: `resources/views/layout.blade.php`

The root Blade template for the Inertia application:

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title inertia>Mailbox for Laravel</title>
    
    {{ Vite::useHotFile('vendor/mailbox/mailbox.hot')
        ->useBuildDirectory("vendor/mailbox")
        ->withEntryPoints(['resources/js/dashboard.js']) }}
    
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
```

Key features:
- Uses `@inertia` directive to mount the app
- References scoped Vite build directory
- Loads the package's dedicated entry point

#### Entry Point: `resources/js/dashboard.js`

Creates an isolated Inertia application:

```javascript
import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'

createInertiaApp({
    resolve: (name) => {
        // Strip the mailbox:: prefix and load from Pages
        const pageName = name.replace(/^mailbox::/, '')
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })
        return pages[`./Pages/${pageName}.vue`]
    },
    
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el)
    },
})
```

Key features:
- Resolves `mailbox::ComponentName` to `./Pages/ComponentName.vue`
- Uses Vite's glob imports for component loading
- Creates its own Vue application instance

#### Pages: `resources/js/Pages/Dashboard.vue`

Inertia page components use the `router` from `@inertiajs/vue3`:

```vue
<script setup>
import { router } from '@inertiajs/vue3'

function clearMessages() {
    router.post(`/${props.mailboxPrefix}/clear`, {}, {
        preserveState: false,
        onSuccess: () => alert('All messages cleared.')
    })
}
</script>
```

### 3. Build Process: Vite Configuration

The `vite.config.js` is configured for package builds:

```javascript
laravel({
    hotFile: 'public/vendor/mailbox/mailbox.hot',
    buildDirectory: 'vendor/mailbox',
    input: ['resources/js/dashboard.js'],
    refresh: true,
})
```

This configuration:
- Outputs to `public/vendor/mailbox/` instead of the standard `public/build/`
- Uses a dedicated hot file for HMR during development
- Bundles only the dashboard entry point

### 4. Service Provider Registration

The `MailboxServiceProvider` registers the Inertia middleware:

```php
$this->app->make(Router::class)
    ->aliasMiddleware('mailbox.authorize', AuthorizeMailboxMiddleware::class)
    ->aliasMiddleware('mailbox.inertia', Http\Middleware\HandleInertiaRequests::class);
```

## Isolation Mechanisms

### 1. Namespaced Components

All Inertia renders use the `mailbox::` prefix:
- Backend: `Inertia::render('mailbox::Dashboard')`
- Frontend: Component resolver strips `mailbox::` and loads from `./Pages/`

### 2. Scoped Assets

Assets are built to `public/vendor/mailbox/`:
- Manifest: `public/vendor/mailbox/manifest.json`
- JS: `public/vendor/mailbox/assets/dashboard-[hash].js`
- CSS: `public/vendor/mailbox/assets/dashboard-[hash].css`

### 3. Dedicated Middleware Stack

The package registers its own Inertia middleware (`mailbox.inertia`) which:
- Doesn't interfere with host app's Inertia middleware (if any)
- Uses its own root view (`mailbox::layout`)
- Shares package-specific data

### 4. Independent Vue Application

The dashboard creates its own Vue app instance:
- Doesn't mount to the host app's root element
- Uses its own Inertia plugin instance
- Has its own component resolution logic

## Compatibility

### Works With Blade-Only Apps
- Host app doesn't need Inertia installed
- Package bundles its own Inertia dependencies
- Assets are self-contained

### Works With Existing Inertia Apps
- Different middleware instances
- Different root elements
- Different component namespaces
- No shared state or conflicts

### Works With Vue/React (Non-Inertia)
- Package's Inertia stack is isolated
- No interference with host app's Vue/React setup
- Different mount points

## Testing Strategy

### Mock Vite Manifest
Tests create a fake manifest to avoid requiring built assets:

```php
protected function setUp(): void
{
    parent::setUp();
    
    $manifestPath = base_path('public/vendor/mailbox');
    mkdir($manifestPath, 0755, true);
    
    file_put_contents($manifestPath.'/manifest.json', json_encode([
        'resources/js/dashboard.js' => [
            'file' => 'assets/dashboard.js',
            'src' => 'resources/js/dashboard.js',
            'isEntry' => true,
            'css' => ['assets/dashboard.css'],
        ],
    ]));
}
```

### Inertia Test Assertions
Tests use Inertia's testing helpers:

```php
$response->assertInertia(fn (Assert $page) => $page
    ->component('mailbox::Dashboard')
    ->has('messages', 2)
    ->has('title')
);
```

## Dependencies

### Backend
- `inertiajs/inertia-laravel`: Installed as a package dependency (not peer dependency)
- Automatically available when package is installed

### Frontend
- `@inertiajs/vue3`: Installed as package dependency
- `vue`: Package dependency
- Host app doesn't need to install these

## Summary

This architecture ensures:
- ✅ Complete isolation from host application
- ✅ No conflicts with existing Inertia setups
- ✅ Works with any frontend stack
- ✅ Self-contained and easy to install
- ✅ Minimal host configuration required
- ✅ Follows Laravel package best practices
