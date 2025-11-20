<script setup lang="ts">
import { Attachment } from '@/types/mailbox'
import { Button } from '@/components/ui/button'
import { Download, Eye, Paperclip } from 'lucide-vue-next'

const props = defineProps<{
    attachments: Attachment[]
}>()

const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 B'
    const k = 1024
    const sizes = ['B', 'KB', 'MB', 'GB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
}

const getFileIcon = (mimeType: string): string => {
    if (mimeType.startsWith('image/')) return '🖼️'
    if (mimeType.startsWith('video/')) return '🎥'
    if (mimeType.startsWith('audio/')) return '🎵'
    if (mimeType === 'application/pdf') return '📄'
    if (mimeType.includes('word')) return '📝'
    if (mimeType.includes('sheet') || mimeType.includes('excel')) return '📊'
    if (mimeType.includes('zip') || mimeType.includes('rar')) return '🗜️'
    return '📎'
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

// Filter out inline attachments (CID images) as they're embedded in HTML
const regularAttachments = props.attachments.filter(a => !a.is_inline)
</script>

<template>
    <div
        v-if="regularAttachments.length > 0"
        class="border-t border-border pt-4 mt-4"
    >
        <div class="flex items-center gap-2 mb-3">
            <Paperclip class="h-4 w-4 text-muted-foreground" />
            <h3 class="text-sm font-medium text-foreground">
                Attachments ({{ regularAttachments.length }})
            </h3>
        </div>

        <div class="space-y-2">
            <div
                v-for="attachment in regularAttachments"
                :key="attachment.id"
                class="flex items-center justify-between p-3 rounded-lg border border-border bg-muted/30 hover:bg-muted/50 transition-colors"
            >
                <div class="flex items-center gap-3 flex-1 min-w-0">
                    <span class="text-2xl flex-shrink-0">
                        {{ getFileIcon(attachment.mime_type) }}
                    </span>
                    <div class="flex-1 min-w-0">
                        <p
                            class="text-sm font-medium text-foreground truncate"
                            :title="attachment.filename"
                        >
                            {{ attachment.filename }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ formatFileSize(attachment.size) }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 flex-shrink-0">
                    <Button
                        v-if="canPreview(attachment.mime_type)"
                        size="sm"
                        variant="ghost"
                        @click="handlePreview(attachment)"
                        title="Preview"
                    >
                        <Eye class="h-4 w-4" />
                    </Button>
                    <Button
                        size="sm"
                        variant="ghost"
                        @click="handleDownload(attachment)"
                        title="Download"
                    >
                        <Download class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
