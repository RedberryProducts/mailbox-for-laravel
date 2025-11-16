<script setup lang="ts">
import { computed } from 'vue'
import { formatDistanceToNow } from 'date-fns'

interface Message {
    id: string
    subject: string
    from: string
    to: string[]
    created_at: string
}

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
    props.isSelected ? 'bg-primary text-primary-foreground hover:bg-primary/90' : 'hover:bg-muted',
])

const subjectClasses = computed(() => [
    'font-semibold truncate text-sm',
    props.isSelected ? 'text-primary-foreground' : 'text-foreground',
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
        <p :class="toClasses">
            {{ 'to: ' + props.message.to[0] }}
        </p>
    </button>
</template>
