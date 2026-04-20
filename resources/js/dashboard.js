import { createApp } from 'vue'
import axios from 'axios'
import Dashboard from './Pages/Dashboard.vue'
import { hydrateStore } from './lib/mailboxStore'
import '../css/mailbox.css'

const dataElement = document.getElementById('mailbox-data')
const initialData = dataElement ? JSON.parse(dataElement.textContent) : {}

hydrateStore(initialData)

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
axios.defaults.headers.common['Accept'] = 'application/json'
if (initialData.csrfToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = initialData.csrfToken
}

createApp(Dashboard).mount('#mailbox-app')
