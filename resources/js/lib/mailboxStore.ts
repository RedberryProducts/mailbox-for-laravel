import { reactive } from 'vue'
import type { Message, PaginationMeta, PollingConfig } from '@/types/mailbox'

export interface MailboxData {
    messages: Message[]
    pagination: PaginationMeta
    polling: PollingConfig
    search: string
    mailboxPrefix: string
    csrfToken: string | null
    title: string
    subtitle: string
}

export const store = reactive<MailboxData>({
    messages: [],
    pagination: {
        total: 0,
        per_page: 20,
        current_page: 1,
        has_more: false,
        latest_timestamp: null,
    },
    polling: { enabled: false, interval: 5000 },
    search: '',
    mailboxPrefix: 'mailbox',
    csrfToken: null,
    title: 'Mailbox for Laravel',
    subtitle: '',
})

export function hydrateStore(data: Partial<MailboxData>): void {
    Object.assign(store, data)
}

export function mailboxUrl(path = ''): string {
    const prefix = store.mailboxPrefix.replace(/^\/|\/$/g, '')
    const suffix = path.replace(/^\//, '')
    return `/${prefix}${suffix ? `/${suffix}` : ''}`
}
