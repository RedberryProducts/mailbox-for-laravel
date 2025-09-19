<script setup>
import { computed, ref } from 'vue'
import AppHeader from './AppHeader.vue'
import MailListItem from './MailListItem.vue'
import EmailDialog from './EmailDialog.vue'
import { get, post } from '../api'

const props = defineProps({
    messages: { type: [Object, Array], default: () => ({}) },
    title: { type: String, default: 'Mailbox' },
    subtitle: { type: String, default: '' },
})

const query = ref('')

/**
 * Local “just-seen” tracker to avoid mutating props.
 * If an item has no seen_at from server but gets opened now,
 * we put its id into this Set to render as read immediately.
 */
const locallySeen = ref(new Set())

/** normalize list but keep original in __raw for modal */
const rows = computed(() => {
    const src = Array.isArray(props.messages)
        ? props.messages
        : Object.entries(props.messages || {}).map(([id, m]) => ({ __k: id, ...m }))

    return (src || []).map((m, idx) => normalizeMessage(m, idx))
})

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase()
    if (!q) return rows.value
    return rows.value.filter((m) =>
        [m.subject, m.fromName, m.fromEmail, m.snippet].some((field) =>
            (field || '').toLowerCase().includes(q)
        )
    )
})

/** unread counter for header */
const unreadCount = computed(() =>
    rows.value.reduce((acc, m) => acc + (isUnread(m) ? 1 : 0), 0)
)

/* modal state */
const dialogOpen = ref(false)
const selected = ref(null)

async function openItem(row) {
    selected.value = row.__raw || row
    dialogOpen.value = true

    // Optimistically mark as seen locally (only if not already seen on server)
    if (isUnread(row)) {
        locallySeen.value.add(row.id)

        // Fire-and-forget marking request
        // Adjust the URL to your backend route (example below).
        // Expected: backend sets seen_at for this message id.
        post(`/messages/${encodeURIComponent(row.id)}/seen`)
            .catch(() => {
                // If server fails, we can revert local state
                locallySeen.value.delete(row.id)
            })
    }
}

/* helpers */
function normalizeMessage(input, idx) {
    const id = input.id

    const fromArr = Array.isArray(input.from) ? input.from : []
    const firstFrom = fromArr[0] || {}
    const fromEmail = firstFrom.address || firstFrom.email || ''
    const fromName = firstFrom.name || fromEmail || 'Unknown sender'

    const iso = input.saved_at || null
    const rfc = input.date || null
    const ts = input.timestamp ? new Date(input.timestamp * 1000).toISOString() : null
    const dateStr = rfc || iso || ts

    const subject = input.subject || '(no subject)'
    const snippet = makeSnippet(input.text, input.html)
    const hasAttachments = Array.isArray(input.attachments) && input.attachments.length > 0

    // seen_at is the source of truth for read/unread (server), with local override
    const seenAt = input.seen_at || null
    const unread = !seenAt && !locallySeen.value.has(id)

    return {
        id,
        subject,
        fromName,
        fromEmail,
        date: dateStr,
        snippet,
        hasAttachments,
        unread,     // <- convenient flag for UI
        seen_at: seenAt, // for completeness
        __raw: input,
    }
}

function isUnread(m) {
    // Prefer the normalized unread flag, fallback to raw rule
    return typeof m.unread === 'boolean'
        ? m.unread
        : !(m.seen_at || (m.__raw && m.__raw.seen_at)) && !locallySeen.value.has(m.id)
}

function makeSnippet(text, html) {
    const raw =
        (text && String(text)) ||
        (html && stripHtml(String(html))) ||
        ''
    return raw.replace(/\s+/g, ' ').trim().slice(0, 180)
}

function stripHtml(s) {
    return String(s)
        .replace(/<style[^>]*>[\s\S]*?<\/style>/gi, '')
        .replace(/<script[^>]*>[\s\S]*?<\/script>/gi, '')
        .replace(/<[^>]+>/g, '')
}

function sendTestMail() {
    post('/test-email')
        .then((r) => {
            if (r.status !== 200) throw new Error('Failed to send test mail')
            alert('Test mail sent! It should arrive in a few seconds.')
            location.reload()
        })
        .catch((e) => {
            console.error(e)
            alert('Error: ' + e.message)
        })
}

function refreshInbox() {
    location.reload()
}

function clearMessages() {
    if (!confirm('Are you sure you want to clear all messages? This action cannot be undone.')) {
        return
    }
    post('/clear', { method: 'POST' })
        .then((r) => {
            if (r.status !== 200) throw new Error('Failed to clear messages')
            alert('All messages cleared.')
            location.reload()
        })
        .catch((e) => {
            console.error(e)
            alert('Error: ' + e.message)
        })
}
</script>

<template>
    <div class="mbx-min-h-screen mbx-bg-slate-50 dark:mbx-bg-slate-950">
        <AppHeader
            :title="title"
            :subtitle="subtitle"
            :searchable="true"
            v-model:query="query"
            search-placeholder="Search subject, sender, or body…"
        >
            <template #actions>
                <div class="mbx-flex mbx-items-center mbx-gap-2">
                    <button
                        @click="sendTestMail"
                        class="mbx-rounded-full mbx-bg-blue-500 mbx-px-3 mbx-py-1 mbx-text-xs mbx-text-white hover:mbx-bg-blue-600 dark:mbx-bg-blue-600 dark:hover:mbx-bg-blue-700">
                        Send Test Mail
                    </button>

                    <button
                        @click="refreshInbox"
                        class="mbx-rounded-full mbx-bg-slate-200 mbx-px-3 mbx-py-1 mbx-text-xs mbx-text-slate-700 hover:mbx-bg-slate-300 dark:mbx-bg-slate-800 dark:mbx-text-slate-300 dark:hover:mbx-bg-slate-700">
                        Refresh
                    </button>

                    <button
                        @click="clearMessages"
                        class="mbx-rounded-full mbx-bg-red-500 mbx-px-3 mbx-py-1 mbx-text-xs mbx-text-white hover:mbx-bg-red-600 dark:mbx-bg-red-600 dark:hover:mbx-bg-red-700">
                        Clear
                    </button>
                </div>
            </template>

            <template #extra>
                <div class="mbx-flex mbx-items-center mbx-gap-2">
          <span class="mbx-rounded-full mbx-bg-slate-200 mbx-px-2 mbx-py-0.5 mbx-text-xs mbx-text-slate-700 dark:mbx-bg-slate-800 dark:mbx-text-slate-300">
            {{ filtered.length }} message{{ filtered.length === 1 ? '' : 's' }}
          </span>
                    <span
                        v-if="unreadCount"
                        class="mbx-rounded-full mbx-bg-blue-100 mbx-px-2 mbx-py-0.5 mbx-text-xs mbx-font-medium mbx-text-blue-700 dark:mbx-bg-blue-900/40 dark:mbx-text-blue-200">
            {{ unreadCount }} unread
          </span>
                </div>
            </template>
        </AppHeader>

        <main class="mbx-mx-auto mbx-max-w-7xl mbx-px-4 sm:mbx-px-6 lg:mbx-px-8 mbx-py-6">
            <div
                v-if="filtered.length"
                class="mbx-overflow-hidden mbx-rounded-xl mbx-border mbx-border-slate-200 mbx-bg-white dark:mbx-border-slate-800 dark:mbx-bg-slate-900"
            >
                <ul role="list" class="mbx-divide-y mbx-divide-slate-200 dark:mbx-divide-slate-800">
                    <MailListItem
                        v-for="m in filtered"
                        :key="m.id"
                        :message="m"
                        :unread="m.unread"
                        @open="openItem"
                    />
                </ul>
            </div>

            <div
                v-else
                class="mbx-rounded-xl mbx-border mbx-border-dashed mbx-border-slate-300 mbx-p-10 mbx-text-center mbx-text-slate-500 dark:mbx-border-slate-700 dark:mbx-text-slate-400"
            >
                No messages match your search.
            </div>
        </main>

        <!-- Modal -->
        <EmailDialog :open="dialogOpen" :message="selected" @close="dialogOpen=false"/>
    </div>
</template>
