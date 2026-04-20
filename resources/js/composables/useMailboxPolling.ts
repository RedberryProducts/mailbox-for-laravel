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
    let pollTimeout: ReturnType<typeof setTimeout> | null = null
    let active = false

    const poll = async () => {
        if (isPolling.value || document.visibilityState === 'hidden') {
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
            if (active) {
                pollTimeout = setTimeout(poll, config.interval)
            }
        }
    }

    const startPolling = () => {
        if (!config.enabled || active) {
            return
        }

        active = true
        pollTimeout = setTimeout(poll, config.interval)
    }

    const stopPolling = () => {
        active = false
        if (pollTimeout !== null) {
            clearTimeout(pollTimeout)
            pollTimeout = null
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
