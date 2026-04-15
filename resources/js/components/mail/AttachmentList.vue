<script setup lang="ts">
import { Attachment } from '@/types/mailbox'
import { Button } from '@/components/ui/button'
import { useAttachmentIcon } from '@/composables/useAttachmentIcon'
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
        class="flex items-center gap-3"
    >
        <span class="label-sm text-on-surface-variant">
            {{ regularAttachments.length }}
            attachment<span v-if="regularAttachments.length !== 1">s</span>
        </span>

        <!-- Pills. Each attachment gets a type-specific icon + color
             (PDF → red, spreadsheets → green, Word → blue, images → violet,
             archives → amber, etc.) so a row of mixed attachments is
             scannable at a glance. -->
        <div class="flex gap-1 flex-wrap">
            <Button
                v-for="attachment in regularAttachments"
                :key="attachment.id"
                variant="ghost"
                size="sm"
                class="h-7 px-2 gap-1.5 bg-surface-container-lowest hover:bg-surface-container text-on-surface"
                :title="`${attachment.filename} (${formatFileSize(attachment.size)})`"
                @click="handleClick(attachment)"
            >
                <component
                    :is="useAttachmentIcon(attachment.mime_type, attachment.filename).icon"
                    :class="['w-3.5 h-3.5 shrink-0', useAttachmentIcon(attachment.mime_type, attachment.filename).color]"
                />
                <span class="body-sm truncate max-w-40">
                    {{ attachment.filename }}
                </span>
            </Button>
        </div>
    </div>
</template>
