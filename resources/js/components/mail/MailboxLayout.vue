<script setup lang="ts">
import {ref, computed, watch} from 'vue'
import axios from 'axios'
import {usePage, router} from '@inertiajs/vue3'
import {Message, TabType, PaginationMeta, PollingConfig} from '@/types/mailbox'
import MailboxFilterBar from '@/components/mail/MailboxFilterBar.vue'
import MailboxList from '@/components/mail/MailboxList.vue'
import MailboxPreview from '@/components/mail/MailboxPreview.vue'
import {useMailboxPolling} from '@/composables/useMailboxPolling'
import {Button} from '@/components/ui/button'
import Logo from '@mailbox/images/logo.svg'

const props = defineProps<{
    messages: Message[]
    pagination: PaginationMeta
    polling: PollingConfig
    title: string
    subtitle: string
}>()

// Typed Inertia page props
const page = usePage<{
    messages: Message[]
    pagination: PaginationMeta
    polling: PollingConfig
    title: string
    subtitle: string
}>()

const localMessages = ref<Message[]>([...props.messages])

const currentPage = ref<number>(props.pagination.current_page)
const hasMore = ref<boolean>(props.pagination.has_more)

const selectedMessageId = ref<string | null>(null)
const selectedRecipient = ref<string>('all')
const activeTab = ref<TabType>('html')
const isLoadingMore = ref(false)

useMailboxPolling(props.polling, props.pagination.latest_timestamp)

/**
 * Deduplicate and merge messages by ID.
 * - Polling: new messages (top).
 * - Load-more: more pages (bottom).
 */
function mergeMessages(newMessages: Message[]) {
    const map = new Map<string, Message>()

    localMessages.value.forEach((msg) => {
        map.set(msg.id, msg)
    })

    newMessages.forEach((msg) => {
        map.set(msg.id, msg)
    })

    localMessages.value = Array.from(map.values()).sort((a, b) => {
        return new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
    })
}

/**
 * Whenever Inertia sends updated `messages` props (polling OR load-more),
 * merge them into local state instead of replacing.
 *
 * IMPORTANT:
 * - Polling should *not* send `pagination` (we only listen to messages here).
 * - Load-more will send messages + pagination, but we still merge messages here.
 */
watch(
    () => page.props.messages as Message[] | undefined,
    (newMessages) => {
        if (!newMessages) return
        mergeMessages(newMessages)
    },
)

function loadMoreMessages() {
    if (isLoadingMore.value || !hasMore.value) {
        return
    }

    isLoadingMore.value = true

    const nextPage = currentPage.value + 1

    router.get(
        '/mailbox',
        {page: nextPage},
        {
            only: ['messages', 'pagination'],
            preserveScroll: true,
            preserveState: true,
            preserveUrl: true,
            replace: true,
            onSuccess: () => {
                const pagination = page.props.pagination as PaginationMeta
                currentPage.value = pagination.current_page
                hasMore.value = pagination.has_more
            },
            onFinish: () => {
                isLoadingMore.value = false
            },
        },
    )
}

const recipients = computed(() => {
    const set = new Set<string>()

    localMessages.value.forEach((msg) => {
        msg.to.forEach((r) => set.add(r))
    })

    return Array.from(set).sort()
})

const filteredMessages = computed(() => {
    if (selectedRecipient.value === 'all') {
        return localMessages.value
    }

    return localMessages.value.filter((msg) =>
        msg.to.includes(selectedRecipient.value),
    )
})

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
    if (!msg || msg.seen_at) return

    // Plain JSON endpoint – axios is fine here.
    axios
        .post(`/mailbox/messages/${id}/seen`)
        .then((response) => {
            msg.seen_at = response.data.seen_at
        })
        .catch((error) => {
            console.error('Failed to mark message as seen', error)
        })
}

const handleViewChange = (view: TabType) => {
    activeTab.value = view
}
</script>

<template>
    <div class="h-screen flex flex-col bg-background">
        <!-- Header -->
        <div class="border-b border-border bg-card p-4 flex items-center gap-4">
            <img :src="Logo" alt="Redberry International" class="h-16 w-16" />
            <div>
                <h1 class="text-2xl font-bold text-foreground">
                    {{ props.title }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ props.subtitle }}
                </p>
            </div>
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
                    <div v-if="hasMore" class="p-4 text-center">
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
