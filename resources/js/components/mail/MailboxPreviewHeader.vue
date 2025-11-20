<script setup lang="ts">
import {ref, computed} from 'vue'
import {router} from '@inertiajs/vue3'
import {format} from 'date-fns'
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

    router.delete(`/mailbox/messages/${props.messageId}`, {
        preserveState: false,
        onSuccess: () => {
            showDeleteDialog.value = false
        },
        onFinish: () => {
            isDeleting.value = false
        },
    })
}
</script>

<template>
    <div class="border-b border-border p-6 bg-card">
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
                <h2 class="text-2xl font-bold text-foreground mb-4">
                    {{ props.subject }}
                </h2>
                <div class="space-y-2 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="text-muted-foreground font-medium"
                        >From:</span
                        >
                        <span class="text-foreground">{{ props.from }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-muted-foreground font-medium"
                        >To:</span
                        >
                        <span class="text-foreground">{{
                                props.to.join(', ')
                            }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-muted-foreground font-medium"
                        >Date:</span
                        >
                        <span class="text-foreground">{{
                                formattedDate
                            }}</span>
                    </div>
                    <div v-if="props.attachments.length" class="flex items-center gap-2">
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
                            class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            {{ isDeleting ? 'Deleting...' : 'Delete' }}
                        </AlertDialogAction>
                    </div>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    </div>
</template>
