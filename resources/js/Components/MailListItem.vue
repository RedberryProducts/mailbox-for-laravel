<template>
    <li
        class="group cursor-default px-4 py-4 sm:px-6 hover:bg-slate-50 dark:hover:bg-slate-800/50"
        @click="$emit('open', message)"
    >
        <div class="flex items-start gap-3">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                  <span class="truncate text-sm font-medium text-slate-900 dark:text-slate-100">
                    {{ message.fromName }}
                  </span>
                    <span class="hidden text-xs text-slate-500 dark:text-slate-400 sm:inline">• {{
                            message.fromEmail
                        }} </span>
                    <span
                        v-if="message.hasAttachments"
                        class="ml-2 inline-flex items-center rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                    >
                    Attachments
                  </span>
                </div>

                <div class="mt-0.5 flex items-center gap-2">
                    <p class="truncate text-[15px] font-semibold text-slate-900 dark:text-slate-100">
                        {{ message.subject }}
                    </p>
                </div>

                <p class="mt-0.5 line-clamp-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ message.snippet }}
                </p>
            </div>

            <div class="shrink-0 pl-3 text-right">
                <time
                    v-if="message.date"
                    :datetime="message.date"
                    class="whitespace-nowrap text-xs text-slate-500 dark:text-slate-400"
                >
                    {{ prettyDate(message.date) }}
                </time>
            </div>
        </div>
    </li>
</template>

<script setup>
const props = defineProps({
    message: {
        type: Object,
        required: true,
    },
})

// Human-ish short date
function prettyDate(isoLike) {
    const d = new Date(isoLike)
    if (isNaN(d.getTime())) return ''
    return d.toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}
</script>
