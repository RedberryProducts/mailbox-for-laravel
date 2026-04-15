<script setup lang="ts">
import { ref, watch } from 'vue'
import {
    Tabs,
    TabsList,
    TabsTrigger,
} from '@/components/ui/tabs'

type TabType = 'html' | 'text' | 'raw'

const props = defineProps<{
    activeView: TabType
}>()

const emit = defineEmits<{
    (e: 'change', view: TabType): void
}>()

const current = ref<TabType>(props.activeView)

watch(
    () => props.activeView,
    (val) => {
        current.value = val
    },
)

watch(current, (val) => {
    emit('change', val)
})
</script>

<template>
    <Tabs v-model="current" class="bg-surface px-6">
        <TabsList class="w-full justify-start rounded-none bg-transparent p-0 gap-1">
            <TabsTrigger
                value="html"
                class="label-md rounded-none border-b-2 border-transparent bg-transparent data-[state=active]:border-primary data-[state=active]:bg-transparent data-[state=active]:text-on-surface text-on-surface-variant"
            >
                HTML
            </TabsTrigger>
            <TabsTrigger
                value="text"
                class="label-md rounded-none border-b-2 border-transparent bg-transparent data-[state=active]:border-primary data-[state=active]:bg-transparent data-[state=active]:text-on-surface text-on-surface-variant"
            >
                Text
            </TabsTrigger>
            <TabsTrigger
                value="raw"
                class="label-md rounded-none border-b-2 border-transparent bg-transparent data-[state=active]:border-primary data-[state=active]:bg-transparent data-[state=active]:text-on-surface text-on-surface-variant"
            >
                Raw
            </TabsTrigger>
        </TabsList>
    </Tabs>
</template>
