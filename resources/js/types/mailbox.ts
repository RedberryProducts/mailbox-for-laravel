// @/types/mailbox.ts

export interface Attachment {
    id: string
    filename: string
    mime_type: string
    size: number
    is_inline: boolean
    download_url: string
    inline_url: string
}

export interface Message {
    id: string
    subject: string
    from: string
    to: string[]
    created_at: string
    html_body: string
    text_body: string
    raw_body: string
    seen_at: string | null
    attachments: Attachment[]
}

export type TabType = 'html' | 'text' | 'raw'

export interface PaginationMeta {
    total: number
    per_page: number
    current_page: number
    has_more: boolean
    latest_timestamp: number | null
}

export interface PollingConfig {
    enabled: boolean
    interval: number
}
