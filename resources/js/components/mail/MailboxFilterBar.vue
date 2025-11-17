<script setup lang="ts">
import MailboxRecipientFilterDropdown from '@/components/mail/MailboxRecipientFilterDropdown.vue'

const props = defineProps<{
    recipients: string[]
    selectedRecipient: string
}>()

const emit = defineEmits<{
    (e: 'recipient-change', recipient: string): void
}>()

const handleChange = (recipient: string) => {
    emit('recipient-change', recipient)
}
</script>

<template>
    <div class="border-b border-border p-4 bg-card">
        <div class="flex items-center gap-2">
            <MailboxRecipientFilterDropdown
                :recipients="props.recipients"
                :selected-recipient="props.selectedRecipient"
                @change="handleChange"
            />
        </div>
    </div>
</template>
