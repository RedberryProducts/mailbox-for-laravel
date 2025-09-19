<template>
    <header
        class="mbx-sticky mbx-top-0 mbx-z-30 mbx-w-full mbx-border-b mbx-border-slate-200 mbx-bg-white/80 mbx-backdrop-blur mbx-supports-[backdrop-filter]:bg-white/60 dark:mbx-border-slate-800 dark:mbx-bg-slate-900/70"
        aria-label="App header"
    >
        <div class="mbx-mx-auto mbx-max-w-7xl mbx-px-4 sm:mbx-px-6 lg:mbx-px-8 mbx-py-3">
            <div
                class="mbx-grid mbx-grid-cols-1 mbx-gap-3 md:mbx-grid-cols-12 md:mbx-items-center"
            >
                <!-- Title / Subtitle -->
                <div class="md:mbx-col-span-5">
                    <h1 class="mbx-text-xl mbx-font-semibold mbx-tracking-tight mbx-text-slate-900 dark:mbx-text-slate-100">
                        {{ title }}
                    </h1>
                    <p
                        v-if="subtitle"
                        class="mbx-mt-0.5 mbx-text-sm mbx-text-slate-500 dark:mbx-text-slate-400"
                    >
                        {{ subtitle }}
                    </p>
                </div>

                <!-- Optional search -->
                <div v-if="searchable" class="md:mbx-col-span-4">
                    <label class="mbx-sr-only" for="inbox-search">Search</label>
                    <div class="mbx-relative">
                        <input
                            id="inbox-search"
                            :placeholder="searchPlaceholder"
                            v-model="q"
                            @input="$emit('update:query', q)"
                            class="mbx-block mbx-w-full mbx-rounded-xl mbx-border mbx-border-slate-300 mbx-bg-white mbx-px-3 mbx-py-2 mbx-text-sm mbx-text-slate-900 mbx-shadow-sm mbx-outline-none mbx-ring-0 placeholder:mbx-text-slate-400 focus:mbx-border-indigo-500 focus:mbx-ring-2 focus:mbx-ring-indigo-500 dark:mbx-border-slate-700 dark:mbx-bg-slate-800 dark:mbx-text-slate-100"
                            type="search"
                            autocomplete="off"
                        />
                        <div class="mbx-pointer-events-none mbx-absolute mbx-inset-y-0 mbx-right-3 mbx-flex mbx-items-center">
                            <svg class="mbx-h-4 mbx-w-4 mbx-text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
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
                <div class="md:mbx-col-span-3">
                    <div class="mbx-flex mbx-items-center mbx-justify-start mbx-gap-2 md:mbx-justify-end">
                        <slot name="actions" />
                    </div>
                </div>

                <!-- Extra (breadcrumbs, tags, etc.) -->
                <div class="md:mbx-col-span-12">
                    <div class="mbx-mt-1 mbx-flex mbx-flex-wrap mbx-items-center mbx-gap-2">
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
