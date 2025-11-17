<script setup lang="ts">
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'

const props = defineProps<{
    recipients: string[]
    selectedRecipient: string
}>()

const emit = defineEmits<{
    (e: 'change', value: string): void
}>()

const handleChange = (value: string) => {
    emit('change', value)
}
</script>

<template>
    <Select :model-value="props.selectedRecipient" @update:model-value="handleChange">
        <SelectTrigger class="w-full md:w-64">
            <SelectValue placeholder="Filter by recipient" />
        </SelectTrigger>
        <SelectContent>
            <SelectItem value="all">All recipients</SelectItem>
            <SelectItem
                v-for="recipient in props.recipients"
                :key="recipient"
                :value="recipient"
            >
                {{ recipient }}
            </SelectItem>
        </SelectContent>
    </Select>
</template>
