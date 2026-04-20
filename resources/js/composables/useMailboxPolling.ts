import {ref, onMounted, onUnmounted} from 'vue'
import axios from 'axios'
import {store, mailboxUrl} from '@/lib/mailboxStore'
import type {Message, PaginationMeta} from '@/types/mailbox'

interface PollingConfig {
    enabled: boolean
    interval: number
}

interface PollingResponse {
    messages: Message[]
    pagination: PaginationMeta
}

/**
 * Polls the mailbox index endpoint for new messages on an interval.
 *
 * Quiet while the tab is hidden. New messages are merged into the shared
 * store so the list re-renders without replacing the user's selection.
 */
export function useMailboxPolling(config: PollingConfig) {
    const isPolling = ref(false)
    let pollInterval: ReturnType<typeof setInterval> | null = null

    const poll = async () => {
        if (document.visibilityState === 'hidden') {
            return
        }

        isPolling.value = true

        try {
            const query: Record<string, string | number> = {page: 1}
            if (store.search) {
                query.search = store.search
            }

            const {data} = await axios.get<PollingResponse>(mailboxUrl(), {params: query})

            mergeIntoStore(data.messages)
            store.pagination.latest_timestamp = data.pagination.latest_timestamp
        } catch (error) {
            console.error('Mailbox polling failed', error)
        } finally {
            isPolling.value = false
        }
    }

    const startPolling = () => {
        if (!config.enabled || pollInterval !== null) {
            return
        }

        pollInterval = setInterval(poll, config.interval)
    }

    const stopPolling = () => {
        if (pollInterval !== null) {
            clearInterval(pollInterval)
            pollInterval = null
        }
    }

    const handleVisibilityChange = () => {
        if (document.visibilityState === 'visible') {
            startPolling()
        } else {
            stopPolling()
        }
    }

    onMounted(() => {
        if (config.enabled) {
            startPolling()
            document.addEventListener('visibilitychange', handleVisibilityChange)
        }
    })

    onUnmounted(() => {
        stopPolling()
        document.removeEventListener('visibilitychange', handleVisibilityChange)
    })

    return {
        isPolling,
        startPolling,
        stopPolling,
    }
}

function mergeIntoStore(incoming: Message[]) {
    const map = new Map<string, Message>()

    store.messages.forEach((msg) => map.set(msg.id, msg))
    incoming.forEach((msg) => map.set(msg.id, msg))

    store.messages = Array.from(map.values()).sort(
        (a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime(),
    )
}
