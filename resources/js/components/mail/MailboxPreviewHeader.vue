<script setup lang="ts">
import {ref, computed} from 'vue'
import axios from 'axios'
import {format} from 'date-fns'
import {store, mailboxUrl} from '@/lib/mailboxStore'
import {Button} from '@/components/ui/button'
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog'
import {Paperclip, Trash2} from 'lucide-vue-next'
import AttachmentList from "@/components/mail/AttachmentList.vue";
import {Attachment} from "@/types/mailbox";

const props = defineProps<{
    subject: string
    from: string
    to: string[]
    sentAt: string
    messageId: string
    attachments: Attachment[]
}>()

const isDeleting = ref(false)
const showDeleteDialog = ref(false)

const formattedDate = computed(() =>
    format(new Date(props.sentAt), 'PPpp'),
)

const handleDeleteMessage = () => {
    isDeleting.value = true

    axios
        .delete(mailboxUrl(`messages/${props.messageId}`))
        .then(() => {
            store.messages = store.messages.filter((m) => m.id !== props.messageId)
            store.pagination.total = Math.max(0, store.pagination.total - 1)
            showDeleteDialog.value = false
        })
        .catch((error) => {
            console.error('Failed to delete message', error)
        })
        .finally(() => {
            isDeleting.value = false
        })
}
</script>

<template>
    <div class="bg-surface px-8 pt-8 pb-6">
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
                <h2 class="headline-md text-on-surface mb-4">
                    {{ props.subject }}
                </h2>
                <!--
                    Field labels use the uppercase label-sm scale so the
                    metadata reads as a typographic system, not as form labels.
                -->
                <div class="space-y-2">
                    <div class="flex items-baseline gap-3">
                        <span class="label-sm text-on-surface-variant w-12 shrink-0">From</span>
                        <span class="body-md text-on-surface">{{ props.from }}</span>
                    </div>
                    <div class="flex items-baseline gap-3">
                        <span class="label-sm text-on-surface-variant w-12 shrink-0">To</span>
                        <span class="body-md text-on-surface">{{ props.to.join(', ') }}</span>
                    </div>
                    <div class="flex items-baseline gap-3">
                        <span class="label-sm text-on-surface-variant w-12 shrink-0">Date</span>
                        <span class="body-md text-on-surface">{{ formattedDate }}</span>
                    </div>
                    <div v-if="props.attachments.length" class="flex items-baseline gap-3">
                        <span class="label-sm text-on-surface-variant w-12 shrink-0">Files</span>
                        <AttachmentList :attachments="props.attachments || []"/>
                    </div>
                </div>
            </div>

            <AlertDialog v-model:open="showDeleteDialog">
                <AlertDialogTrigger as-child>
                    <Button
                        variant="destructive"
                        size="icon"
                        class="flex-shrink-0"
                        title="Delete message"
                    >
                        <Trash2 class="h-4 w-4 "/>
                    </Button>
                </AlertDialogTrigger>
                <AlertDialogContent>
                    <AlertDialogTitle>Delete this message?</AlertDialogTitle>
                    <AlertDialogDescription>
                        This action cannot be undone. This message will be
                        permanently deleted from your mailbox.
                    </AlertDialogDescription>
                    <div class="flex justify-end gap-3">
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            @click="handleDeleteMessage"
                            :disabled="isDeleting"
                        >
                            {{ isDeleting ? 'Deleting...' : 'Delete' }}
                        </AlertDialogAction>
                    </div>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    </div>
</template>
