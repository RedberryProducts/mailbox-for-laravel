import {createApp} from 'vue'
import '../css/mailbox.css'
import InboxApp from './Components/InboxApp.vue'

function readHydratedProps() {
    const el = document.getElementById('mailbox-props')
    if (!el) return {}
    try {
        return JSON.parse(el.textContent || '{}')
    } catch {
        return {}
    }
}

const initial = readHydratedProps()
// Optional: expose the same URL for XHR updates (same route as Blade page)
window.mailboxPageUrl = window.mailboxPageUrl || window.location.pathname + window.location.search

createApp(InboxApp, initial).mount('#app')
