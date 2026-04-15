<script setup lang="ts">
import { computed } from 'vue'
import { formatDistanceToNow } from 'date-fns'
import { Paperclip } from 'lucide-vue-next'
import { Message } from '@/types/mailbox'

const props = defineProps<{
    message: Message
    isSelected: boolean
}>()

const emit = defineEmits<{
    (e: 'click'): void
}>()

const timestamp = computed(() => {
    const date = new Date(props.message.created_at)
    const ageInMs = Date.now() - date.getTime()
    const oneWeek = 7 * 24 * 60 * 60 * 1000

    // Anything older than a week reads better as an absolute short date
    // ("Apr 14") — matches the mockup's handling of older messages.
    if (ageInMs > oneWeek) {
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
        })
    }

    return formatDistanceToNow(date, { addSuffix: false })
        .replace('about ', '')
        .replace('less than a minute', '1 minute')
        + ' ago'
})

// Every row is a card floating on the list column (surface-container-low):
// default — white card (surface-container-lowest) + ambient shadow
// selected — same card + 4px primary left stripe as per the mockup
// Hover lifts slightly brighter via subtle overlay.
const rootClasses = computed(() => [
    'relative block w-full text-left cursor-pointer overflow-hidden rounded-xl bg-surface-container-lowest shadow-ambient transition-[transform,box-shadow] hover:-translate-y-px',
    'px-5 py-4',
    props.isSelected ? 'ring-0' : '',
])

// Subject weight doubles as the read/unread signal — bold for unread,
// regular for read — so we don't need a separate dot indicator.
const subjectClasses = computed(() => [
    'headline-sm text-on-surface truncate pr-3',
    props.message.seen_at ? 'font-medium' : 'font-semibold',
])

// Prefer the plain-text body. When the sender only shipped HTML, strip
// <style> / <script> / <head> blocks *including their contents* before
// removing the remaining tags — otherwise CSS rules from inlined
// stylesheets leak into the snippet ("body { color:#333 } Hi there…").
// This mirrors how Mailtrap / Gmail build list previews.
const snippet = computed(() => {
    if (props.message.text_body) {
        return truncate(collapseWhitespace(props.message.text_body))
    }

    const html = props.message.html_body || ''
    if (!html) return ''

    const withoutNonContent = html
        .replace(/<head\b[\s\S]*?<\/head>/gi, ' ')
        .replace(/<style\b[\s\S]*?<\/style>/gi, ' ')
        .replace(/<script\b[\s\S]*?<\/script>/gi, ' ')
        .replace(/<!--([\s\S]*?)-->/g, ' ')

    const stripped = collapseWhitespace(withoutNonContent.replace(/<[^>]*>/g, ' '))
    return truncate(stripped)
})

const hasAttachments = computed(() => (props.message.attachments?.length ?? 0) > 0)

function collapseWhitespace(s: string): string {
    return s.replace(/\s+/g, ' ').trim()
}

function truncate(s: string): string {
    return s.length > 160 ? s.slice(0, 160) + '…' : s
}
</script>

<template>
    <button :class="rootClasses" @click="emit('click')">
        <!-- 4px primary accent stripe on the selected card's left edge. -->
        <span
            v-if="props.isSelected"
            class="absolute top-0 bottom-0 left-0 w-1 rounded-r-full bg-primary"
            aria-hidden="true"
        />

        <div class="flex items-start justify-between gap-3 mb-1.5">
            <h3 :class="subjectClasses">
                {{ props.message.subject }}
            </h3>
            <span class="flex items-center gap-1.5 body-sm whitespace-nowrap text-on-surface-variant shrink-0">
                <Paperclip
                    v-if="hasAttachments"
                    class="h-3.5 w-3.5 text-on-surface-variant"
                    :aria-label="`${props.message.attachments.length} attachment${props.message.attachments.length === 1 ? '' : 's'}`"
                />
                {{ timestamp }}
            </span>
        </div>

        <p class="mb-2 truncate body-sm text-on-surface-variant">
            {{ props.message.from }}
        </p>

        <p
            v-if="snippet"
            class="body-sm text-on-surface-variant line-clamp-2"
        >
            {{ snippet }}
        </p>
    </button>
</template>
