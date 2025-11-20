<script setup lang="ts">
import { Attachment } from '@/types/mailbox'
import { Button } from '@/components/ui/button'
import { Download, Paperclip } from 'lucide-vue-next'
import { computed } from 'vue'

const props = defineProps<{
    attachments: Attachment[]
}>()

const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 B'
    const k = 1024
    const sizes = ['B', 'KB', 'MB', 'GB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i]
}

const handleDownload = (attachment: Attachment) => {
    window.open(attachment.download_url, '_blank')
}

const handlePreview = (attachment: Attachment) => {
    window.open(attachment.inline_url, '_blank')
}

const canPreview = (mimeType: string): boolean => {
    return mimeType.startsWith('image/') || mimeType === 'application/pdf'
}

// Only show non-inline attachments, like in your original code
const regularAttachments = computed(() =>
    props.attachments.filter(a => !a.is_inline),
)

// When clicking the pill:
// - if previewable (image/pdf) → open preview
// - otherwise → download
const handleClick = (attachment: Attachment) => {
    if (canPreview(attachment.mime_type)) {
        handlePreview(attachment)
    } else {
        handleDownload(attachment)
    }
}
</script>

<template>
    <div
        v-if="regularAttachments.length > 0"
        class="flex items-center gap-1 border-l border-border"
    >
        <!-- “X attachments” text -->
        <span class="text-xs text-muted-foreground">
            {{ regularAttachments.length }}
            attachment<span v-if="regularAttachments.length !== 1">s</span>
        </span>

        <!-- Pills -->
        <div class="flex gap-1">
            <Button
                v-for="attachment in regularAttachments"
                :key="attachment.id"
                variant="ghost"
                size="sm"
                class="h-6 px-2 gap-1"
                :title="`${attachment.filename} (${formatFileSize(attachment.size)})`"
                @click="handleClick(attachment)"
            >
                <Paperclip class="w-3 h-3" />
                <span
                    class="text-xs truncate max-w-[100px]"
                >
                    {{ attachment.filename }}
                </span>
                <Download class="w-3 h-3" />
            </Button>
        </div>
    </div>
</template>
