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
    <Tabs v-model="current" class="border-b border-border">
        <TabsList class="w-full justify-start rounded-none bg-card p-0">
            <TabsTrigger
                value="html"
                class="rounded-none border-b-2 border-transparent data-[state=active]:border-primary"
            >
                HTML
            </TabsTrigger>
            <TabsTrigger
                value="text"
                class="rounded-none border-b-2 border-transparent data-[state=active]:border-primary"
            >
                Text
            </TabsTrigger>
            <TabsTrigger
                value="raw"
                class="rounded-none border-b-2 border-transparent data-[state=active]:border-primary"
            >
                Raw
            </TabsTrigger>
        </TabsList>
    </Tabs>
</template>
