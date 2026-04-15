<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { useVModel } from "@vueuse/core"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
  defaultValue?: string | number
  modelValue?: string | number
}>()

const emits = defineEmits<{
  (e: "update:modelValue", payload: string | number): void
}>()

const modelValue = useVModel(props, "modelValue", emits, {
  passive: true,
  defaultValue: props.defaultValue,
})
</script>

<template>
  <!-- Tonal field per design.md — no opaque border, focus lifts to lowest tier. -->
  <textarea v-model="modelValue" :class="cn('flex min-h-[60px] w-full rounded-md bg-surface-container-highest text-on-surface px-3 py-2 body-md transition-[background-color,box-shadow] placeholder:text-on-surface-variant focus-visible:outline-none focus-visible:bg-surface-container-lowest focus-visible:shadow-ambient disabled:cursor-not-allowed disabled:opacity-50', props.class)" />
</template>
