import {createApp} from 'vue'
import '../css/mailbox.css'
import MailboxApp from './Components/MailboxApp.vue'

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

createApp(MailboxApp, initial).mount('#app')
