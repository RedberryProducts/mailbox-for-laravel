<script setup lang="ts">
import {ref, computed, watch} from 'vue'
import axios from 'axios'
import { usePage } from '@inertiajs/vue3'
import MailboxFilterBar from '@/components/mail/MailboxFilterBar.vue'
import MailboxList from '@/components/mail/MailboxList.vue'
import MailboxPreview from '@/components/mail/MailboxPreview.vue'
import { useMailboxPolling } from '@/composables/useMailboxPolling'
import { Button } from '@/components/ui/button'

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

interface PaginationMeta {
    total: number
    per_page: number
    current_page: number
    has_more: boolean
    latest_timestamp: number | null
}

interface PollingConfig {
    enabled: boolean
    interval: number
}

const props = defineProps<{
    messages: Message[]
    pagination: PaginationMeta
    polling: PollingConfig
    title: string
    subtitle: string
}>()

// Local state for messages (merged from polling + load more)
const localMessages = ref<Message[]>([...props.messages])

const selectedMessageId = ref<string | null>(null)
const selectedRecipient = ref<string>('all')
const activeTab = ref<TabType>('html')
const isLoadingMore = ref(false)

// Get the Inertia page props to access the updated messages from server
const page = usePage()

// Watch for changes in the Inertia page props and merge messages
watch(() => page.props.messages, (newMessages) => {
    if (newMessages) {
        mergeMessages(newMessages as Message[])
    }
}, { deep: true })

// Watch for changes in props.messages on initial load
watch(() => props.messages, (newMessages) => {
    localMessages.value = [...newMessages]
}, { immediate: true })

// Setup polling using the composable
const { isPolling } = useMailboxPolling(
    props.polling,
    props.pagination.latest_timestamp
)

// Deduplicate and merge messages by ID
// Polling adds new messages at the top, load more button appends at bottom
function mergeMessages(newMessages: Message[]) {
    const messageMap = new Map<string, Message>()
    
    // Add existing messages to map
    localMessages.value.forEach(msg => {
        messageMap.set(msg.id, msg)
    })
    
    // Add/update with new messages (newer ones override)
    newMessages.forEach(msg => {
        messageMap.set(msg.id, msg)
    })
    
    // Convert back to array and sort by timestamp (newest first)
    localMessages.value = Array.from(messageMap.values()).sort((a, b) => {
        return new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
    })
}

// Load more messages when button is clicked
async function loadMoreMessages() {
    if (isLoadingMore.value || !props.pagination.has_more) {
        return
    }
    
    isLoadingMore.value = true
    
    const nextPage = props.pagination.current_page + 1
    
    try {
        // Use axios to fetch the next page
        const response = await axios.get(`/mailbox?page=${nextPage}`)
        
        // Append the new messages (at bottom)
        if (response.data.props?.messages) {
            const newMessages = response.data.props.messages as Message[]
            localMessages.value = [...localMessages.value, ...newMessages]
        }
    } catch (error) {
        console.error('Failed to load more messages', error)
    } finally {
        isLoadingMore.value = false
    }
}

// unique recipients from local messages
const recipients = computed(() => {
    const set = new Set<string>()

    localMessages.value.forEach((msg) => {
        msg.to.forEach((r) => set.add(r))
    })

    return Array.from(set).sort()
})

// filtered messages (by recipient)
const filteredMessages = computed(() => {
    if (selectedRecipient.value === 'all') {
        return localMessages.value
    }

    return localMessages.value.filter((msg) =>
        msg.to.includes(selectedRecipient.value),
    )
})

// selected message (from all messages)
const selectedMessage = computed<Message | null>(() => {
    return (
        localMessages.value.find(
            (msg) => msg.id === selectedMessageId.value,
        ) || null
    )
})

const handleRecipientChange = (recipient: string) => {
    selectedRecipient.value = recipient
}

const handleSelectMessage = (id: string) => {
    selectedMessageId.value = id

    const msg = localMessages.value.find((m) => m.id === id)
    if (!msg || msg.seen_at) {
        return
    }

    // Use axios (part of Inertia stack) for JSON API endpoint
    axios
        .post(`/mailbox/messages/${id}/seen`)
        .then((response) => {
            // Update local state with the seen_at timestamp
            msg.seen_at = response.data.seen_at
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

                <div class="flex-1 overflow-y-auto">
                    <MailboxList
                        :messages="filteredMessages"
                        :selected-id="selectedMessageId"
                        @select="handleSelectMessage"
                    />
                    
                    <!-- Load More button -->
                    <div v-if="props.pagination.has_more" class="p-4 text-center">
                        <Button 
                            @click="loadMoreMessages" 
                            :disabled="isLoadingMore"
                            variant="outline"
                            class="w-full"
                        >
                            {{ isLoadingMore ? 'Loading...' : 'Load More' }}
                        </Button>
                    </div>
                </div>
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
