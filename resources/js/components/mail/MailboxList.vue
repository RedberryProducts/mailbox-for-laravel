<script setup lang="ts">
import { ScrollArea } from '@/components/ui/scroll-area'
import MailboxListItem from '@/components/mail/MailboxListItem.vue'

interface Message {
    id: string
    subject: string
    from: string
    to: string[]
    created_at: string
}

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
    <div class="flex-1 overflow-hidden flex flex-col">
        <div
            v-if="props.messages.length === 0"
            class="flex-1 flex items-center justify-center p-4 text-center"
        >
            <div class="text-muted-foreground">
                <p class="text-sm">No messages for this filter.</p>
            </div>
        </div>
        <ScrollArea v-else class="flex-1">
            <div class="space-y-0">
                <MailboxListItem
                    v-for="message in props.messages"
                    :key="message.id"
                    :message="message"
                    :is-selected="message.id === props.selectedId"
                    @click="handleSelect(message.id)"
                />
            </div>
        </ScrollArea>
    </div>
</template>
