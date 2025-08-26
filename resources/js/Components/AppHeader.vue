<template>
    <header
        class="sticky top-0 z-30 w-full border-b border-slate-200 bg-white/80 backdrop-blur supports-[backdrop-filter]:bg-white/60 dark:border-slate-800 dark:bg-slate-900/70"
        aria-label="App header"
    >
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-3">
            <div
                class="grid grid-cols-1 gap-3 md:grid-cols-12 md:items-center"
            >
                <!-- Title / Subtitle -->
                <div class="md:col-span-5">
                    <h1 class="text-xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">
                        {{ title }}
                    </h1>
                    <p
                        v-if="subtitle"
                        class="mt-0.5 text-sm text-slate-500 dark:text-slate-400"
                    >
                        {{ subtitle }}
                    </p>
                </div>

                <!-- Optional search -->
                <div v-if="searchable" class="md:col-span-4">
                    <label class="sr-only" for="inbox-search">Search</label>
                    <div class="relative">
                        <input
                            id="inbox-search"
                            :placeholder="searchPlaceholder"
                            v-model="q"
                            @input="$emit('update:query', q)"
                            class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none ring-0 placeholder:text-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                            type="search"
                            autocomplete="off"
                        />
                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                            <svg class="h-4 w-4 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path
                                    fill-rule="evenodd"
                                    d="M12.9 14.32a8 8 0 1 1 1.414-1.414l3.387 3.387a1 1 0 0 1-1.414 1.414l-3.387-3.387ZM14 8a6 6 0 1 1-12 0 6 6 0 0 1 12 0Z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="md:col-span-3">
                    <div class="flex items-center justify-start gap-2 md:justify-end">
                        <slot name="actions" />
                    </div>
                </div>

                <!-- Extra (breadcrumbs, tags, etc.) -->
                <div class="md:col-span-12">
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <slot name="extra" />
                    </div>
                </div>
            </div>
        </div>
    </header>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
    title: { type: String, default: 'Mailbox' },
    subtitle: { type: String, default: '' },
    searchable: { type: Boolean, default: false },
    modelValue: { type: String, default: '' }, // v-model:query
    searchPlaceholder: { type: String, default: 'Search…' },
})

defineEmits(['update:query'])

const q = ref(props.modelValue)
watch(
    () => props.modelValue,
    (v) => {
        if (v !== q.value) q.value = v
    }
)
</script>
