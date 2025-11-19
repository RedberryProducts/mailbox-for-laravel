<script setup lang="ts">
import { defineProps } from "vue"
import MailboxLayout from "@/components/mail/MailboxLayout.vue"

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
    messages: any[]
    pagination: PaginationMeta
    polling: PollingConfig
    title: string
    subtitle: string
}>()
</script>

<template>
    <MailboxLayout
        :messages="props.messages"
        :pagination="props.pagination"
        :polling="props.polling"
        :title="props.title"
        :subtitle="props.subtitle"
    />
</template>
