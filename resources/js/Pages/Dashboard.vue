<script setup lang="ts">
import { defineProps } from "vue"
import MailboxLayout from "@/components/mail/MailboxLayout.vue"

const props = defineProps<{
    messages: any[]
    title: string
    subtitle: string
}>()
</script>

<template>
    <MailboxLayout
        :messages="props.messages"
        :title="props.title"
        :subtitle="props.subtitle"
    />
</template>
