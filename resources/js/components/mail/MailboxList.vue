<script setup lang="ts">
import { Message } from '@/types/mailbox'
import MailboxListItem from '@/components/mail/MailboxListItem.vue'

const props = defineProps<{
    messages: Message[]
    selectedId: string | null
}>()

const emit = defineEmits<{
    (e: 'select', id: string): void
}>()

const handleSelect = (id: string) => emit('select', id)
</script>

<template>
    <div class="flex-1 flex flex-col">
        <div
            v-if="props.messages.length === 0"
            class="flex-1 flex items-center justify-center p-8 text-center"
        >
            <p class="body-md text-on-surface-variant">
                No messages for this filter.
            </p>
        </div>
        <!--
            Cards, not rows: each message floats as its own elevated card on
            the list column. Separation is whitespace + shadow, never a line.
        -->
        <div v-else class="flex flex-col gap-3 px-4 py-4">
            <MailboxListItem
                v-for="message in props.messages"
                :key="message.id"
                :message="message"
                :is-selected="message.id === props.selectedId"
                @click="handleSelect(message.id)"
            />
        </div>
    </div>
</template>
