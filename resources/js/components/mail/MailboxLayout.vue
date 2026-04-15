<script setup lang="ts">
import { ref, computed, watch } from "vue";
import axios from "axios";
import { usePage, router } from "@inertiajs/vue3";
import {
    Message,
    TabType,
    PaginationMeta,
    PollingConfig,
} from "@/types/mailbox";
import MailboxFilterBar from "@/components/mail/MailboxFilterBar.vue";
import MailboxList from "@/components/mail/MailboxList.vue";
import MailboxPreview from "@/components/mail/MailboxPreview.vue";
import { useMailboxPolling } from "@/composables/useMailboxPolling";
import { Button } from "@/components/ui/button";
import Logo from "@mailbox/images/logo.svg";

const props = defineProps<{
    messages: Message[];
    pagination: PaginationMeta;
    polling: PollingConfig;
    title: string;
    subtitle: string;
    search: string;
}>();

// Typed Inertia page props
const page = usePage<{
    messages: Message[];
    pagination: PaginationMeta;
    polling: PollingConfig;
    title: string;
    subtitle: string;
    search: string;
}>();

const localMessages = ref<Message[]>([...props.messages]);

const currentPage = ref<number>(props.pagination.current_page);
const hasMore = ref<boolean>(props.pagination.has_more);

const selectedMessageId = ref<string | null>(null);
const selectedRecipient = ref<string>("all");
const searchQuery = ref<string>(props.search ?? "");
const activeTab = ref<TabType>("html");
const isLoadingMore = ref(false);

useMailboxPolling(props.polling, props.pagination.latest_timestamp);

/**
 * Deduplicate and merge messages by ID.
 * - Polling: new messages (top).
 * - Load-more: more pages (bottom).
 */
function mergeMessages(newMessages: Message[]) {
    const map = new Map<string, Message>();

    localMessages.value.forEach((msg) => {
        map.set(msg.id, msg);
    });

    newMessages.forEach((msg) => {
        map.set(msg.id, msg);
    });

    localMessages.value = Array.from(map.values()).sort((a, b) => {
        return (
            new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
        );
    });
}

/**
 * Whenever Inertia sends updated `messages` props:
 *
 * - Polling / load-more keep the same `search` value → merge into state.
 * - Search changes → the server returns a fresh scoped set; replace state
 *   wholesale (merging would leak stale "no-longer-matching" messages).
 */
let lastAppliedSearch = props.search ?? "";

watch(
    () => page.props.messages as Message[] | undefined,
    (newMessages) => {
        if (!newMessages) return;

        const incomingSearch = (page.props.search as string | undefined) ?? "";

        if (incomingSearch !== lastAppliedSearch) {
            localMessages.value = [...newMessages];
            currentPage.value = (page.props.pagination as PaginationMeta).current_page;
            hasMore.value = (page.props.pagination as PaginationMeta).has_more;
            // If the selected message dropped out of the new set, clear it.
            if (
                selectedMessageId.value !== null
                && !newMessages.some((m) => m.id === selectedMessageId.value)
            ) {
                selectedMessageId.value = null;
            }
            lastAppliedSearch = incomingSearch;
            return;
        }

        mergeMessages(newMessages);
    },
);

function loadMoreMessages() {
    if (isLoadingMore.value || !hasMore.value) {
        return;
    }

    isLoadingMore.value = true;

    const nextPage = currentPage.value + 1;

    const activeSearch = searchQuery.value.trim();
    const query: Record<string, string | number> = { page: nextPage };
    if (activeSearch !== "") {
        query.search = activeSearch;
    }

    router.get("/mailbox", query, {
        only: ["messages", "pagination"],
        preserveScroll: true,
        preserveState: true,
        preserveUrl: true,
        replace: true,
        onSuccess: () => {
            const pagination = page.props.pagination as PaginationMeta;
            currentPage.value = pagination.current_page;
            hasMore.value = pagination.has_more;
        },
        onFinish: () => {
            isLoadingMore.value = false;
        },
    });
}

const recipients = computed(() => {
    const set = new Set<string>();

    localMessages.value.forEach((msg) => {
        msg.to.forEach((r) => set.add(r));
    });

    return Array.from(set).sort();
});

// Recipient is still a client-side filter over the already-loaded page.
// Search is resolved on the server (see handleSearchChange), so we don't
// re-apply it here — the loaded set is already scoped to the active search.
const filteredMessages = computed(() => {
    const recipient = selectedRecipient.value;

    if (recipient === "all") {
        return localMessages.value;
    }

    return localMessages.value.filter((msg) =>
        msg.to.includes(recipient),
    );
});

const selectedMessage = computed<Message | null>(() => {
    return (
        localMessages.value.find((msg) => msg.id === selectedMessageId.value) ||
        null
    );
});

const handleRecipientChange = (recipient: string) => {
    selectedRecipient.value = recipient;
};

// Debounced server round-trip for search. Every keystroke updates the local
// ref for controlled-input purposes, but the network request fires once the
// user pauses typing so we don't hammer the backend.
let searchDebounce: ReturnType<typeof setTimeout> | null = null;

const handleSearchChange = (query: string) => {
    searchQuery.value = query;

    if (searchDebounce !== null) {
        clearTimeout(searchDebounce);
    }

    searchDebounce = setTimeout(() => {
        const next = searchQuery.value.trim();

        // Skip the round-trip when the effective query is unchanged.
        if (next === (lastAppliedSearch ?? "").trim()) {
            return;
        }

        router.get(
            "/mailbox",
            next === "" ? { page: 1 } : { search: next, page: 1 },
            {
                only: ["messages", "pagination", "search"],
                preserveScroll: true,
                preserveState: true,
                preserveUrl: false,
                replace: true,
            },
        );
    }, 300);
};

const handleSelectMessage = (id: string) => {
    selectedMessageId.value = id;

    const msg = localMessages.value.find((m) => m.id === id);
    if (!msg || msg.seen_at) return;

    // Plain JSON endpoint – axios is fine here.
    axios
        .post(`/mailbox/messages/${id}/seen`)
        .then((response) => {
            msg.seen_at = response.data.seen_at;
        })
        .catch((error) => {
            console.error("Failed to mark message as seen", error);
        });
};

const handleViewChange = (view: TabType) => {
    activeTab.value = view;
};
</script>

<template>
    <div
        class="grid h-screen bg-surface grid-rows-[auto_1fr] grid-cols-1 md:grid-cols-[minmax(0,2fr)_minmax(0,3fr)] lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] [grid-template-areas:'header''inbox'] md:[grid-template-areas:'header_reading''inbox_reading']"
    >
        <header
            class="[grid-area:header] flex items-center gap-4 px-6 py-5 bg-surface-container-low"
        >
            <img :src="Logo" alt="Redberry International" class="w-12 h-12" />
            <div>
                <h1 class="headline-md text-on-surface">
                    {{ props.title }}
                </h1>
                <p class="body-sm text-on-surface-variant">
                    {{ props.subtitle }}
                </p>
            </div>
        </header>

        <aside
            class="[grid-area:inbox] flex flex-col min-h-0 bg-surface-container-low"
        >
            <MailboxFilterBar
                :recipients="recipients"
                :selected-recipient="selectedRecipient"
                :search-query="searchQuery"
                @recipient-change="handleRecipientChange"
                @search-change="handleSearchChange"
            />

            <div class="flex-1 overflow-y-auto">
                <MailboxList
                    :messages="filteredMessages"
                    :selected-id="selectedMessageId"
                    @select="handleSelectMessage"
                />

                <div v-if="hasMore" class="p-4 text-center">
                    <Button
                        @click="loadMoreMessages"
                        :disabled="isLoadingMore"
                        variant="outline"
                        class="w-full"
                    >
                        {{ isLoadingMore ? "Loading..." : "Load More" }}
                    </Button>
                </div>
            </div>
        </aside>

        <section
            class="[grid-area:reading] hidden md:flex flex-col min-h-0 overflow-hidden bg-surface"
        >
            <MailboxPreview
                :message="selectedMessage"
                :active-view="activeTab"
                @view-change="handleViewChange"
            />
        </section>
    </div>
</template>
