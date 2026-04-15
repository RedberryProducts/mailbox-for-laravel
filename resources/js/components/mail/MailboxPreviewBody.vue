<script setup lang="ts">
import {ScrollArea} from '@/components/ui/scroll-area'
import {Message, TabType} from '@/types/mailbox'
import HtmlIframeViewer from "@/components/mail/HtmlIframeViewer.vue";

const props = defineProps<{
    view: TabType
    message: Message
}>()
</script>

<template>
    <ScrollArea class="flex-1">
        <div class="p-2">
            <HtmlIframeViewer
                v-if="props.view === 'html'"
                class="prose prose-sm max-w-none bg-surface-container-lowest rounded-lg overflow-auto"
                :html="props.message.html_body"
            />
            <pre
                v-else-if="props.view === 'text'"
                class="bg-surface-container-low p-4 rounded-lg body-sm text-on-surface overflow-auto font-mono whitespace-pre-wrap wrap-break-word"
            >{{ props.message.text_body }}</pre>
            <pre
                v-else
                class="bg-surface-container-low p-4 rounded-lg body-sm text-on-surface overflow-auto font-mono whitespace-pre-wrap wrap-break-word"
            >{{ props.message.raw_body }}</pre>
        </div>
    </ScrollArea>
</template>
