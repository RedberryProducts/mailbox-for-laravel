<script setup>
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppHeader from '../Components/AppHeader.vue'
import MailListItem from '../Components/MailListItem.vue'
import EmailDialog from '../Components/EmailDialog.vue'

const props = defineProps({
    messages: { type: [Object, Array], default: () => ({}) },
    title: { type: String, default: 'Mailbox' },
    subtitle: { type: String, default: '' },
    mailboxPrefix: { type: String, default: 'mailbox' },
    csrfToken: { type: String, default: '' },
})

const query = ref('')

/**
 * Local "just-seen" tracker to avoid mutating props.
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

        // Fire-and-forget marking request using Inertia's router
        router.post(
            `/${props.mailboxPrefix}/messages/${encodeURIComponent(row.id)}/seen`,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                onError: () => {
                    // If server fails, we can revert local state
                    locallySeen.value.delete(row.id)
                }
            }
        )
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
    // Create a simple but more robust HTML stripper
    let result = String(s)
    // Remove script tags and their content
    result = result.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
    // Remove style tags and their content
    result = result.replace(/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/gi, '')
    // Remove all remaining HTML tags
    result = result.replace(/<[^>]+>/g, '')
    // Decode common HTML entities
    result = result.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&')
    return result
}

function sendTestMail() {
    router.post(`/${props.mailboxPrefix}/test-email`, {}, {
        preserveState: false,
        onSuccess: () => {
            alert('Test mail sent! It should arrive in a few seconds.')
        },
        onError: () => {
            alert('Error sending test mail')
        }
    })
}

function refreshMailbox() {
    router.reload()
}

function clearMessages() {
    if (!confirm('Are you sure you want to clear all messages? This action cannot be undone.')) {
        return
    }
    router.post(`/${props.mailboxPrefix}/clear`, {}, {
        preserveState: false,
        onSuccess: () => {
            alert('All messages cleared.')
        },
        onError: () => {
            alert('Error clearing messages')
        }
    })
}
</script>

<template>
    <div class="min-h-screen bg-slate-50 dark:bg-slate-950">
        <AppHeader
            :title="title"
            :subtitle="subtitle"
            :searchable="true"
            v-model:query="query"
            search-placeholder="Search subject, sender, or body…"
        >
            <template #actions>
                <div class="flex items-center gap-2">
                    <button
                        @click="sendTestMail"
                        class="rounded-full bg-blue-500 px-3 py-1 text-xs text-white hover:bg-blue-600 dark:bg-blue-600 dark:hover:bg-blue-700">
                        Send Test Mail
                    </button>

                    <button
                        @click="refreshMailbox"
                        class="rounded-full bg-slate-200 px-3 py-1 text-xs text-slate-700 hover:bg-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">
                        Refresh
                    </button>

                    <button
                        @click="clearMessages"
                        class="rounded-full bg-red-500 px-3 py-1 text-xs text-white hover:bg-red-600 dark:bg-red-600 dark:hover:bg-red-700">
                        Clear
                    </button>
                </div>
            </template>

            <template #extra>
                <div class="flex items-center gap-2">
          <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300">
            {{ filtered.length }} message{{ filtered.length === 1 ? '' : 's' }}
          </span>
                    <span
                        v-if="unreadCount"
                        class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/40 dark:text-blue-200">
            {{ unreadCount }} unread
          </span>
                </div>
            </template>
        </AppHeader>

        <main class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
            <div
                v-if="filtered.length"
                class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
            >
                <ul role="list" class="divide-y divide-slate-200 dark:divide-slate-800">
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
                class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-500 dark:border-slate-700 dark:text-slate-400"
            >
                No messages match your search.
            </div>
        </main>

        <!-- Modal -->
        <EmailDialog :open="dialogOpen" :message="selected" @close="dialogOpen=false"/>
    </div>
</template>
