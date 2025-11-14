import {createApp, h} from 'vue'
import {createInertiaApp} from '@inertiajs/vue3'
import '../css/mailbox.css'

// Create a scoped Inertia application for the mailbox package
createInertiaApp({
    // Resolve page components with the mailbox:: prefix
    resolve: (name) => {
        // Strip the mailbox:: prefix if present
        const pageName = name.replace(/^mailbox::/, '')
        const pages = import.meta.glob('./Pages/**/*.vue', {eager: true})
        return pages[`./Pages/${pageName}.vue`]
    },

    setup({el, App, props, plugin}) {
        createApp({render: () => h(App, props)})
            .use(plugin)
            .mount(el)
    },

    // Use a unique progress bar color for this package
    progress: {
        color: '#3b82f6',
    },
})
