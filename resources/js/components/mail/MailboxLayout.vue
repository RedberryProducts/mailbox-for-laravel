<script setup lang="ts">
import { ref, computed } from 'vue'
import MailboxFilterBar from '@/components/mail/MailboxFilterBar.vue'
import MailboxList from '@/components/mail/MailboxList.vue'
import MailboxPreview from '@/components/mail/MailboxPreview.vue'

type TabType = 'html' | 'text' | 'raw'

interface Message {
    id: string
    subject: string
    from: string
    to: string[]
    created_at: string
    html_body: string
    text_body: string
    raw_body: string
    seen_at: string | null
}

const props = defineProps<{
    messages: Message[]
    title: string
    subtitle: string
}>()

const selectedMessageId = ref<string | null>(
    props.messages[0]?.id ?? null,
)
const selectedRecipient = ref<string>('all')
const activeTab = ref<TabType>('html')

// unique recipients from messages
const recipients = computed(() => {
    const set = new Set<string>()

    props.messages.forEach((msg) => {
        msg.to.forEach((r) => set.add(r))
    })

    return Array.from(set).sort()
})

// filtered messages (by recipient)
const filteredMessages = computed(() => {
    if (selectedRecipient.value === 'all') {
        return props.messages
    }

    return props.messages.filter((msg) =>
        msg.to.includes(selectedRecipient.value),
    )
})

// selected message (from all messages, same as React version)
const selectedMessage = computed<Message | null>(() => {
    return (
        props.messages.find(
            (msg) => msg.id === selectedMessageId.value,
        ) || null
    )
})

const handleRecipientChange = (recipient: string) => {
    selectedRecipient.value = recipient
}

const handleSelectMessage = (id: string) => {
    selectedMessageId.value = id

    const msg = props.messages.find((m) => m.id === id)
    if (!msg || msg.seen_at) {
        return
    }

    // Call backend endpoint to mark as seen using Inertia's axios
    // Since this is a JSON API endpoint (not an Inertia response),
    // we use router.post with async handling
    fetch(`/mailbox/messages/${id}/seen`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
    })
        .then((response) => response.json())
        .then((data) => {
            // Update local state with the seen_at timestamp
            msg.seen_at = data.seen_at
        })
        .catch((error) => {
            console.error('Failed to mark message as seen', error)
            // We do NOT revert selection; worst case the message appears unread until next reload.
        })
}

const handleViewChange = (view: TabType) => {
    activeTab.value = view
}
</script>

<template>
    <div class="h-screen flex flex-col bg-background">
        <!-- Header -->
        <div class="border-b border-border bg-card p-4">
            <h1 class="text-2xl font-bold text-foreground">
                {{ props.title }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ props.subtitle }}
            </p>
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 flex overflow-hidden">
            <!-- Sidebar -->
            <div
                class="w-full md:w-2/5 lg:w-1/3 flex flex-col border-r border-border bg-card"
            >
                <MailboxFilterBar
                    :recipients="recipients"
                    :selected-recipient="selectedRecipient"
                    @recipient-change="handleRecipientChange"
                />

                <MailboxList
                    :messages="filteredMessages"
                    :selected-id="selectedMessageId"
                    @select="handleSelectMessage"
                />
            </div>

            <!-- Preview Panel -->
            <div
                class="hidden md:flex flex-1 flex-col bg-background overflow-hidden"
            >
                <MailboxPreview
                    :message="selectedMessage"
                    :active-view="activeTab"
                    @view-change="handleViewChange"
                />
            </div>
        </div>
    </div>
</template>
