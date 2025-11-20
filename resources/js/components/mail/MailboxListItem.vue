<script setup lang="ts">
import { computed } from 'vue'
import { formatDistanceToNow } from 'date-fns'
import { Message } from '@/types/mailbox'

const props = defineProps<{
    message: Message
    isSelected: boolean
}>()

const emit = defineEmits<{
    (e: 'click'): void
}>()

const timestamp = computed(() =>
    formatDistanceToNow(new Date(props.message.created_at), {
        addSuffix: true,
    }),
)

const rootClasses = computed(() => [
    'w-full px-4 py-3 text-left border-b border-border transition-colors cursor-pointer',
    props.isSelected ? 'bg-primary text-primary-foreground hover:bg-primary/90' : props.message.seen_at ? 'hover:bg-muted' : 'bg-accent/40 hover:bg-muted',
])

const subjectClasses = computed(() => [
    'truncate text-sm',
    props.isSelected ? 'text-primary-foreground font-semibold' : props.message.seen_at ? 'font-normal text-foreground' : 'font-semibold text-foreground',
])

const timestampClasses = computed(() => [
    'text-xs whitespace-nowrap',
    props.isSelected ? 'text-primary-foreground/70' : 'text-muted-foreground',
])

const toClasses = computed(() => [
    'text-xs truncate',
    props.isSelected ? 'text-primary-foreground/80' : 'text-muted-foreground',
])
</script>

<template>
    <button :class="rootClasses" @click="emit('click')">
        <div class="flex items-start justify-between gap-2 mb-1">
            <h3 :class="subjectClasses">
                {{ props.message.subject }}
            </h3>
            <span :class="timestampClasses">
        {{ timestamp }}
      </span>
        </div>
        <div class="flex items-center justify-between gap-2">
            <p :class="toClasses">
                {{ 'to: ' + props.message.to[0] }}
            </p>
            <span
                v-if="!props.message.seen_at"
                :class="['h-2 w-2 rounded-full bg-primary flex-shrink-0', props.isSelected ? 'bg-primary-foreground' : 'bg-primary']"
            />
        </div>
    </button>
</template>
