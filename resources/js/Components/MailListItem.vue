<template>
    <li
        class="mbx-group mbx-cursor-pointer mbx-px-4 mbx-py-4 sm:mbx-px-6 hover:mbx-bg-slate-50 dark:hover:mbx-bg-slate-800/50"
        @click="$emit('open', message)"
    >
        <div class="mbx-flex mbx-items-start mbx-gap-3">
            <!-- unread dot -->
            <div class="mbx-pt-2">
        <span
            v-if="unread"
            class="mbx-inline-block mbx-h-2 mbx-w-2 mbx-rounded-full mbx-bg-blue-500"
            aria-label="Unread"
        />
            </div>

            <div class="mbx-min-w-0 mbx-flex-1">
                <div class="mbx-flex mbx-items-center mbx-gap-2">
          <span
              class="mbx-truncate mbx-text-sm mbx-font-medium mbx-text-slate-900 dark:mbx-text-slate-100"
          >
            {{ message.fromName }}
          </span>
                    <span class="mbx-hidden mbx-text-xs mbx-text-slate-500 dark:mbx-text-slate-400 sm:mbx-inline">
            • {{ message.fromEmail }}
          </span>
                    <span
                        v-if="message.hasAttachments"
                        class="mbx-ml-2 mbx-inline-flex mbx-items-center mbx-rounded-md mbx-bg-slate-100 mbx-px-1.5 mbx-py-0.5 mbx-text-[10px] mbx-font-medium mbx-text-slate-700 dark:mbx-bg-slate-800 dark:mbx-text-slate-300"
                    >
            Attachments
          </span>
                </div>

                <div class="mbx-mt-0.5 mbx-flex mbx-items-center mbx-gap-2">
                    <p
                        class="mbx-truncate mbx-text-[15px] dark:mbx-text-slate-100"
                        :class="unread ? 'mbx-font-semibold mbx-text-slate-900' : 'mbx-font-normal mbx-text-slate-700'"
                    >
                        {{ message.subject }}
                    </p>
                </div>

                <p
                    class="mbx-mt-0.5 mbx-line-clamp-1 mbx-text-sm"
                    :class="unread ? 'mbx-text-slate-600 dark:mbx-text-slate-300' : 'mbx-text-slate-500 dark:mbx-text-slate-400'"
                >
                    {{ message.snippet }}
                </p>
            </div>

            <div class="mbx-shrink-0 mbx-pl-3 mbx-text-right">
                <time
                    v-if="message.date"
                    :datetime="message.date"
                    class="mbx-whitespace-nowrap mbx-text-xs mbx-text-slate-500 dark:mbx-text-slate-400"
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
    unread: {
        type: Boolean,
        default: false,
    },
})

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
