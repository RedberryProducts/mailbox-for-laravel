import { ref, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'

interface PaginationMeta {
    total: number
    per_page: number
    current_page: number
    has_more: boolean
    latest_timestamp: number | null
}

/**
 * Composable for infinite scroll using Inertia.
 * 
 * Loads the next page of messages when the user scrolls near the bottom
 * of the container. Uses IntersectionObserver to detect when a sentinel
 * element becomes visible.
 * 
 * Uses Inertia's router.get with preserveState and preserveScroll to
 * append new messages to the existing list.
 */
export function useInfiniteScroll(
    containerRef: { value: HTMLElement | null },
    pagination: PaginationMeta,
    onLoadMore: () => void
) {
    const isLoadingMore = ref(false)
    let observer: IntersectionObserver | null = null

    const loadNextPage = () => {
        if (isLoadingMore.value || !pagination.has_more) {
            return
        }

        isLoadingMore.value = true

        const nextPage = pagination.current_page + 1

        // Use Inertia to load the next page
        router.get(
            `/mailbox?page=${nextPage}`,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: ['messages', 'pagination'],
                onSuccess: () => {
                    // Callback to merge messages on the frontend
                    onLoadMore()
                },
                onFinish: () => {
                    isLoadingMore.value = false
                },
            }
        )
    }

    const setupObserver = () => {
        if (!containerRef.value) {
            return
        }

        // Create a sentinel element at the bottom of the list
        const sentinel = document.createElement('div')
        sentinel.setAttribute('data-infinite-scroll-sentinel', 'true')
        sentinel.style.height = '1px'

        containerRef.value.appendChild(sentinel)

        // Set up IntersectionObserver
        observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting && !isLoadingMore.value) {
                        loadNextPage()
                    }
                })
            },
            {
                root: containerRef.value,
                rootMargin: '200px', // Start loading 200px before the bottom
                threshold: 0,
            }
        )

        observer.observe(sentinel)
    }

    onMounted(() => {
        setupObserver()
    })

    onUnmounted(() => {
        if (observer) {
            observer.disconnect()
        }
    })

    return {
        isLoadingMore,
        loadNextPage,
    }
}
