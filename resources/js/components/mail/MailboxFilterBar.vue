<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import MailboxRecipientFilterDropdown from '@/components/mail/MailboxRecipientFilterDropdown.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog'
import { Search, Trash2, X } from 'lucide-vue-next'

const props = defineProps<{
    recipients: string[]
    selectedRecipient: string
    searchQuery: string
}>()

const emit = defineEmits<{
    (e: 'recipient-change', recipient: string): void
    (e: 'search-change', query: string): void
}>()

const isClearing = ref(false)
const showClearDialog = ref(false)

const handleChange = (recipient: string) => {
    emit('recipient-change', recipient)
}

const handleSearchInput = (value: string | number) => {
    emit('search-change', String(value))
}

const clearSearch = () => emit('search-change', '')

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
    <div class="bg-surface-container-low px-4 py-3 flex flex-col gap-3">
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
                        >
                            {{ isClearing ? 'Clearing...' : 'Clear Inbox' }}
                        </AlertDialogAction>
                    </div>
                </AlertDialogContent>
            </AlertDialog>
        </div>

        <!--
            Search input lives below the recipient + clear-inbox row.
            `type="text"` (not "search") — Chrome/Safari render a native clear
            "x" on type=search inputs, which would stack on top of our own
            clear button. Using "text" keeps a single shadcn-style clear.
        -->
        <div class="relative">
            <Search
                class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-on-surface-variant pointer-events-none"
                aria-hidden="true"
            />
            <Input
                :model-value="props.searchQuery"
                @update:model-value="handleSearchInput"
                type="text"
                placeholder="Search emails…"
                aria-label="Search emails"
                class="pl-9 pr-9"
            />
            <button
                v-if="props.searchQuery"
                type="button"
                class="absolute right-2 top-1/2 -translate-y-1/2 p-1 rounded-md text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high transition-colors"
                aria-label="Clear search"
                @click="clearSearch"
            >
                <X class="h-4 w-4" />
            </button>
        </div>
    </div>
</template>
