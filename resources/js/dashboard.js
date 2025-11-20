import {createApp, h} from 'vue'
import {createInertiaApp} from '@inertiajs/vue3'
import '../css/mailbox.css'

createInertiaApp({
    resolve: (name) => {
        const pageName = name.replace(/^mailbox::/, '')
        const pages = import.meta.glob('./Pages/**/*.vue', {eager: true})
        return pages[`./Pages/${pageName}.vue`]
    },

    setup({el, App, props, plugin}) {
        createApp({render: () => h(App, props)})
            .use(plugin)
            .mount(el)
    },

    progress: {
        color: '#3b82f6',
    },
})
