<template>
    <Teleport to="body">
        <div
            v-show="open"
            class="fixed inset-0 z-[100]"
            @keydown.esc.prevent="$emit('close')"
        >
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-slate-900/60" @click="$emit('close')"/>

            <!-- Panel -->
            <div class="fixed inset-0 flex items-start justify-center overflow-y-auto p-4 sm:p-6">
                <div
                    class="mt-10 w-full max-w-4xl rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="mail-subject"
                >
                    <!-- Header -->
                    <div
                        class="flex items-start justify-between gap-4 border-b border-slate-200 p-4 dark:border-slate-800">
                        <div class="min-w-0">
                            <h2 id="mail-subject"
                                class="truncate text-lg font-semibold text-slate-900 dark:text-slate-100">
                                {{ subject }}
                            </h2>
                            <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-300">
                                {{ fromLine }}
                                <span v-if="dateIso"> • {{ prettyDate(dateIso) }}</span>
                            </p>
                        </div>
                        <div class="shrink-0 space-x-2">
                            <a
                                v-if="message?.eml_url || message?.raw_url"
                                :href="message.eml_url || message.raw_url"
                                class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                            >
                                Download EML
                            </a>
                            <button
                                class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                                @click="$emit('close')" autofocus>Close
                            </button>
                        </div>
                    </div>

                    <!-- Meta -->
                    <section class="p-4">
                        <dl class="grid grid-cols-1 gap-3 md:grid-cols-12 md:items-start">
                            <dt class="md:col-span-2 text-xs font-medium text-slate-500 dark:text-slate-400">From</dt>
                            <dd class="md:col-span-10">
                                <div class="flex flex-wrap gap-1.5">
                                    <span v-for="(p,i) in people.from" :key="'f'+i"
                                          class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{
                                            prettyPerson(p)
                                        }}</span>
                                </div>
                            </dd>

                            <dt class="md:col-span-2 text-xs font-medium text-slate-500 dark:text-slate-400">To</dt>
                            <dd class="md:col-span-10">
                                <div class="flex flex-wrap gap-1.5">
                                    <span v-for="(p,i) in people.to" :key="'t'+i"
                                          class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{
                                            prettyPerson(p)
                                        }}</span>
                                </div>
                            </dd>

                            <template v-if="people.cc.length">
                                <dt class="md:col-span-2 text-xs font-medium text-slate-500 dark:text-slate-400">Cc</dt>
                                <dd class="md:col-span-10">
                                    <div class="flex flex-wrap gap-1.5">
                                        <span v-for="(p,i) in people.cc" :key="'c'+i"
                                              class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{
                                                prettyPerson(p)
                                            }}</span>
                                    </div>
                                </dd>
                            </template>

                            <template v-if="people.bcc.length">
                                <dt class="md:col-span-2 text-xs font-medium text-slate-500 dark:text-slate-400">Bcc
                                </dt>
                                <dd class="md:col-span-10">
                                    <div class="flex flex-wrap gap-1.5">
                                        <span v-for="(p,i) in people.bcc" :key="'b'+i"
                                              class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{
                                                prettyPerson(p)
                                            }}</span>
                                    </div>
                                </dd>
                            </template>

                            <template v-if="message?.message_id">
                                <dt class="md:col-span-2 text-xs font-medium text-slate-500 dark:text-slate-400">
                                    Message-ID
                                </dt>
                                <dd class="md:col-span-10 text-xs text-slate-500 dark:text-slate-400">
                                    {{ message.message_id }}
                                </dd>
                            </template>
                        </dl>
                    </section>

                    <!-- Body Tabs -->
                    <section class="border-t border-slate-200 dark:border-slate-800">
                        <div
                            class="flex items-center justify-between border-b border-slate-200 px-4 py-2 dark:border-slate-800">
                            <div class="flex gap-1">
                                <button
                                    v-for="tab in tabs"
                                    :key="tab.key"
                                    class="rounded-md px-3 py-1.5 text-sm"
                                    :class="tab.key === activeTab
                    ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900'
                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
                                    @click="activeTab = tab.key"
                                    :disabled="!tab.enabled"
                                >
                                    {{ tab.label }}
                                </button>
                            </div>
                            <button
                                class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                                @click="copyCurrent">Copy
                            </button>
                        </div>

                        <div class="p-4">
                            <!-- HTML -->
                            <div v-if="activeTab==='html'">
                                <article
                                    class="rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
                                    <div class="email-html p-4 overflow-x-auto" v-html="sanitizedHtml"/>
                                </article>
                            </div>

                            <!-- Text -->
                            <pre
                                v-else-if="activeTab==='text'"
                                class="overflow-x-auto whitespace-pre-wrap rounded-lg bg-slate-50 p-4 text-sm text-slate-800 ring-1 ring-slate-200"
                            >{{ message?.text || message?.html || '' }}</pre>

                            <!-- JSON -->
                            <pre
                                v-else
                                class="overflow-x-auto rounded-lg bg-slate-50 p-4 text-xs text-slate-700 ring-1 ring-slate-200"
                            >{{ rawJson }}</pre>
                        </div>
                    </section>

                    <!-- Attachments -->
                    <section
                        v-if="attachments.length"
                        class="border-t border-slate-200 p-4 dark:border-slate-800"
                    >
                        <h3 class="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-100">
                            Attachments ({{ attachments.length }})
                        </h3>
                        <ul class="grid gap-2 sm:grid-cols-2">
                            <li v-for="(a,i) in attachments" :key="i"
                                class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-800">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-slate-800 dark:text-slate-100">
                                        {{ a.filename || a.name || 'file' }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ a.content_type || a.mimetype || 'application/octet-stream' }}
                                        <span v-if="a.size">• {{ formatBytes(a.size) }}</span>
                                    </p>
                                </div>
                                <a v-if="a.url" :href="a.url"
                                   class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                                >Download</a>
                            </li>
                        </ul>
                    </section>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import {computed, ref, watch} from 'vue'

const props = defineProps({
    open: {type: Boolean, default: false},
    message: {type: Object, default: null},
})
defineEmits(['close'])

/* derived basics */
const subject = computed(() => props.message?.subject || '(no subject)')
const dateIso = computed(() =>
    props.message?.date
    || props.message?.saved_at
    || (props.message?.timestamp ? new Date(props.message.timestamp * 1000).toISOString() : '')
)
const people = computed(() => ({
    from: parsePeople(props.message?.from),
    to: parsePeople(props.message?.to),
    cc: parsePeople(props.message?.cc),
    bcc: parsePeople(props.message?.bcc),
}))
const fromLine = computed(() => {
    const p = people.value.from[0]
    return p ? prettyPerson(p) : 'Unknown sender'
})

/* tabs */
const hasHtml = computed(() => !!props.message?.html)
const hasText = computed(() => !!props.message?.text)
const tabs = computed(() => ([
    {key: 'html', label: 'HTML', enabled: hasHtml.value},
    {key: 'text', label: 'Text', enabled: hasText.value},
    {key: 'raw', label: 'JSON', enabled: true},
]))
const activeTab = ref('raw')
watch(() => props.open, (o) => {
    if (!o) return
    activeTab.value = hasHtml.value ? 'html' : hasText.value ? 'text' : 'raw'
})

/* content */
const sanitizedHtml = computed(() => sanitizeHtml(String(props.message?.html || '')))
const rawJson = computed(() => JSON.stringify(props.message || {}, null, 2))
const attachments = computed(() => Array.isArray(props.message?.attachments) ? props.message.attachments : [])

/* actions */
async function copyCurrent() {
    const text = activeTab.value === 'html'
        ? stripHtml(sanitizedHtml.value)
        : activeTab.value === 'text'
            ? String(props.message?.text || '')
            : rawJson.value
    try {
        await navigator.clipboard.writeText(text)
    } catch {
    }
}

/* utils */
function parsePeople(arr) {
    if (!Array.isArray(arr)) return []
    return arr.map(p => ({name: p.name || '', address: p.address || p.email || ''}))
}

function prettyPerson(p) {
    return p.name && p.address ? `${p.name} <${p.address}>` : (p.name || p.address || 'Unknown')
}

function prettyDate(isoLike) {
    const d = new Date(isoLike);
    if (isNaN(d)) return ''
    return d.toLocaleString(undefined, {month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'})
}

function formatBytes(n) {
    if (!n && n !== 0) return ''
    const u = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    let v = n
    while (v >= 1024 && i < u.length - 1) {
        v /= 1024;
        i++
    }
    return `${v.toFixed(v < 10 && i > 0 ? 1 : 0)} ${u[i]}`
}

function sanitizeHtml(html) {
    return html
        .replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, '')
        .replace(/\son\w+="[^"]*"/gi, '')
        .replace(/\s(href|src)=["']javascript:[^"']*["']/gi, '$1="#"')
}

function stripHtml(s) {
    return String(s)
        .replace(/<style[^>]*>[\s\S]*?<\/style>/gi, '')
        .replace(/<script[^>]*>[\s\S]*?<\/script>/gi, '')
        .replace(/<[^>]+>/g, '')
        .replace(/\s+/g, ' ')
        .trim()
}
</script>

<style scoped>
.email-html :where(img, video, iframe) {
    max-width: 100%;
    height: auto;
}
</style>
