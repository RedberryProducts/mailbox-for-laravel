<script setup lang="ts">
import { Message, TabType } from '@/types/mailbox'
import MailboxPreviewHeader from '@/components/mail/MailboxPreviewHeader.vue'
import MailboxPreviewTabs from '@/components/mail/MailboxPreviewTabs.vue'
import MailboxPreviewBody from '@/components/mail/MailboxPreviewBody.vue'

const props = defineProps<{
    message: Message | null
    activeView: TabType
}>()

const emit = defineEmits<{
    (e: 'view-change', view: TabType): void
}>()

const handleChange = (view: TabType) => {
    emit('view-change', view)
}
</script>

<template>
    <div
        v-if="!props.message"
        class="flex-1 flex items-center justify-center p-4"
    >
        <div class="text-center text-muted-foreground">
            <p>Select a message to preview</p>
        </div>
    </div>
    <div v-else class="flex-1 flex flex-col overflow-hidden">
        <MailboxPreviewHeader
            :subject="props.message.subject"
            :from="props.message.from"
            :to="props.message.to"
            :sent-at="props.message.created_at"
            :message-id="props.message.id"
            :attachments="props.message.attachments"
        />
        <MailboxPreviewTabs
            :active-view="props.activeView"
            @change="handleChange"
        />
        <MailboxPreviewBody
            :view="props.activeView"
            :message="props.message"
        />
    </div>
</template>
