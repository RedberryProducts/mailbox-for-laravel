<script setup lang="ts">
import { ScrollArea } from '@/components/ui/scroll-area'

type TabType = 'html' | 'text' | 'raw'

interface Message {
    id: string
    subject: string
    from: string
    to: string[]
    created_at: string
    html_body: string
    text_body: string
    raw_body: string
}

const props = defineProps<{
    view: TabType
    message: Message
}>()
</script>

<template>
    <ScrollArea class="flex-1">
        <div class="p-6">
            <div
                v-if="props.view === 'html'"
                class="prose prose-sm max-w-none bg-muted p-4 rounded-lg overflow-auto"
                v-html="props.message.html_body"
            />
            <pre
                v-else-if="props.view === 'text'"
                class="bg-muted p-4 rounded-lg text-sm text-foreground overflow-auto font-mono whitespace-pre-wrap break-words"
            >
{{ props.message.text_body }}
      </pre>
            <pre
                v-else
                class="bg-muted p-4 rounded-lg text-sm text-foreground overflow-auto font-mono whitespace-pre-wrap break-words"
            >
{{ props.message.raw_body }}
      </pre>
        </div>
    </ScrollArea>
</template>
