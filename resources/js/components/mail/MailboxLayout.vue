<script setup lang="ts">
import { ref, computed, watch } from "vue";
import axios from "axios";
import {
    Message,
    TabType,
    PaginationMeta,
} from "@/types/mailbox";
import { store, mailboxUrl } from "@/lib/mailboxStore";
import MailboxFilterBar from "@/components/mail/MailboxFilterBar.vue";
import MailboxList from "@/components/mail/MailboxList.vue";
import MailboxPreview from "@/components/mail/MailboxPreview.vue";
import { useMailboxPolling } from "@/composables/useMailboxPolling";
import { Button } from "@/components/ui/button";
import Logo from "@mailbox/images/logo.svg";

const selectedMessageId = ref<string | null>(null);
const selectedRecipient = ref<string>("all");
const searchQuery = ref<string>(store.search ?? "");
const activeTab = ref<TabType>("html");
const isLoadingMore = ref(false);

useMailboxPolling(store.polling);

interface ListResponse {
    messages: Message[];
    pagination: PaginationMeta;
    search: string;
}

function syncQueryString(query: Record<string, string | number>): void {
    const url = new URL(window.location.href);
    url.search = "";
    Object.entries(query).forEach(([key, value]) => {
        if (value === "" || value === null || value === undefined) return;
        url.searchParams.set(key, String(value));
    });
    window.history.replaceState({}, "", url.toString());
}

function loadMoreMessages() {
    if (isLoadingMore.value || !store.pagination.has_more) {
        return;
    }

    isLoadingMore.value = true;

    const nextPage = store.pagination.current_page + 1;
    const activeSearch = searchQuery.value.trim();
    const query: Record<string, string | number> = { page: nextPage };
    if (activeSearch !== "") {
        query.search = activeSearch;
    }

    axios
        .get<ListResponse>(mailboxUrl(), { params: query })
        .then(({ data }) => {
            const map = new Map<string, Message>();
            store.messages.forEach((msg) => map.set(msg.id, msg));
            data.messages.forEach((msg) => map.set(msg.id, msg));
            store.messages = Array.from(map.values()).sort(
                (a, b) =>
                    new Date(b.created_at).getTime() -
                    new Date(a.created_at).getTime(),
            );
            store.pagination = data.pagination;
        })
        .catch((error) => {
            console.error("Failed to load more messages", error);
        })
        .finally(() => {
            isLoadingMore.value = false;
        });
}

const recipients = computed(() => {
    const set = new Set<string>();

    store.messages.forEach((msg) => {
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
        return store.messages;
    }

    return store.messages.filter((msg) =>
        msg.to.includes(recipient),
    );
});

const selectedMessage = computed<Message | null>(() => {
    return (
        store.messages.find((msg) => msg.id === selectedMessageId.value) ||
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

        if (next === (store.search ?? "").trim()) {
            return;
        }

        const params: Record<string, string | number> =
            next === "" ? { page: 1 } : { search: next, page: 1 };

        axios
            .get<ListResponse>(mailboxUrl(), { params })
            .then(({ data }) => {
                store.messages = [...data.messages];
                store.pagination = data.pagination;
                store.search = data.search;

                if (
                    selectedMessageId.value !== null &&
                    !data.messages.some((m) => m.id === selectedMessageId.value)
                ) {
                    selectedMessageId.value = null;
                }

                syncQueryString(params);
            })
            .catch((error) => {
                console.error("Search failed", error);
            });
    }, 300);
};

// Keep the input in sync if the store's search value is mutated externally
// (e.g. after a full-page reload that seeded a fresh search).
watch(
    () => store.search,
    (next) => {
        if (next !== searchQuery.value) {
            searchQuery.value = next;
        }
    },
);

const handleSelectMessage = (id: string) => {
    selectedMessageId.value = id;

    const msg = store.messages.find((m) => m.id === id);
    if (!msg || msg.seen_at) return;

    axios
        .post(mailboxUrl(`messages/${id}/seen`))
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
                    {{ store.title }}
                </h1>
                <p class="body-sm text-on-surface-variant">
                    {{ store.subtitle }}
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

                <div v-if="store.pagination.has_more" class="p-4 text-center">
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
