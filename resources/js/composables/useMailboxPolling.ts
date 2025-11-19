import { ref, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'

interface PollingConfig {
    enabled: boolean
    interval: number
}

/**
 * Composable for polling mailbox messages using Inertia.
 * 
 * Polls for new messages at a configurable interval, but only when:
 * - Polling is enabled
 * - The page/tab is visible (not hidden)
 * 
 * Uses Inertia's router.reload with preserveState and preserveScroll
 * to avoid disrupting the user experience.
 */
export function useMailboxPolling(config: PollingConfig, latestTimestamp: number | null) {
    const isPolling = ref(false)
    let pollInterval: ReturnType<typeof setInterval> | null = null

    const startPolling = () => {
        if (!config.enabled || pollInterval !== null) {
            return
        }

        pollInterval = setInterval(() => {
            // Only poll if the page is visible
            if (document.visibilityState === 'hidden') {
                return
            }

            isPolling.value = true

            // Use Inertia's router to reload only the messages and pagination props
            router.reload({
                only: ['messages', 'pagination'],
                preserveState: true,
                preserveScroll: true,
                onFinish: () => {
                    isPolling.value = false
                },
            })
        }, config.interval)
    }

    const stopPolling = () => {
        if (pollInterval !== null) {
            clearInterval(pollInterval)
            pollInterval = null
        }
    }

    // Handle visibility change - pause polling when tab is hidden
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
