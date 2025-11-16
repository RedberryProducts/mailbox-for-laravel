<script setup lang="ts">
import { computed } from 'vue'
import { format } from 'date-fns'

const props = defineProps<{
    subject: string
    from: string
    to: string[]
    sentAt: string
}>()

const formattedDate = computed(() =>
    format(new Date(props.sentAt), 'PPpp'),
)
</script>

<template>
    <div class="border-b border-border p-6 bg-card">
        <h2 class="text-2xl font-bold text-foreground mb-4">
            {{ props.subject }}
        </h2>
        <div class="space-y-2 text-sm">
            <div class="flex items-center gap-2">
                <span class="text-muted-foreground font-medium">From:</span>
                <span class="text-foreground">{{ props.from }}</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-muted-foreground font-medium">To:</span>
                <span class="text-foreground">{{ props.to.join(', ') }}</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-muted-foreground font-medium">Date:</span>
                <span class="text-foreground">{{ formattedDate }}</span>
            </div>
        </div>
    </div>
</template>
