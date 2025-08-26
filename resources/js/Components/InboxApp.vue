<script setup>
import { computed, ref } from 'vue'
import AppHeader from './AppHeader.vue'
import MailListItem from './MailListItem.vue'
import EmailDialog from './EmailDialog.vue'

const props = defineProps({
    messages: { type: [Object, Array], default: () => ({}) },
    title: { type: String, default: 'Mailbox' },
    subtitle: { type: String, default: '' },
})

const query = ref('')

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

/* modal state */
const dialogOpen = ref(false)
const selected = ref(null)
function openItem(row) {
    selected.value = row.__raw || row
    dialogOpen.value = true
}

/* helpers */
function normalizeMessage(input, idx) {
    const id = input.__k || input.id || input.message_id || `msg_${idx}`

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

    return {
        id, subject, fromName, fromEmail, date: dateStr, snippet, hasAttachments,
        __raw: input, // <- keep full original for modal
    }
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
            <template #extra>
        <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300">
          {{ filtered.length }} message{{ filtered.length === 1 ? '' : 's' }}
        </span>
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
        <EmailDialog :open="dialogOpen" :message="selected" @close="dialogOpen=false" />
    </div>
</template>
