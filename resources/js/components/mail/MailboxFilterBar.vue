<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import MailboxRecipientFilterDropdown from '@/components/mail/MailboxRecipientFilterDropdown.vue'
import { Button } from '@/components/ui/button'
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog'
import { Trash2 } from 'lucide-vue-next'

const props = defineProps<{
    recipients: string[]
    selectedRecipient: string
}>()

const emit = defineEmits<{
    (e: 'recipient-change', recipient: string): void
}>()

const isClearing = ref(false)
const showClearDialog = ref(false)

const handleChange = (recipient: string) => {
    emit('recipient-change', recipient)
}

const handleClearInbox = () => {
    isClearing.value = true

    router.delete('/mailbox/messages', {
        preserveState: false,
        onSuccess: () => {
            showClearDialog.value = false
        },
        onFinish: () => {
            isClearing.value = false
        },
    })
}
</script>

<template>
    <div class="border-b border-border p-4 bg-card">
        <div class="flex items-center gap-2 justify-between">
            <MailboxRecipientFilterDropdown
                :recipients="props.recipients"
                :selected-recipient="props.selectedRecipient"
                @change="handleChange"
            />

            <AlertDialog v-model:open="showClearDialog">
                <AlertDialogTrigger as-child>
                    <Button
                        variant="destructive"
                        size="sm"
                        class="flex items-center gap-2"
                    >
                        <Trash2 class="h-4 w-4" />
                        Clear Inbox
                    </Button>
                </AlertDialogTrigger>
                <AlertDialogContent>
                    <AlertDialogTitle>Clear inbox?</AlertDialogTitle>
                    <AlertDialogDescription>
                        This action cannot be undone. All messages in your
                        mailbox will be permanently deleted.
                    </AlertDialogDescription>
                    <div class="flex justify-end gap-3">
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            @click="handleClearInbox"
                            :disabled="isClearing"
                            class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            {{ isClearing ? 'Clearing...' : 'Clear Inbox' }}
                        </AlertDialogAction>
                    </div>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    </div>
</template>
