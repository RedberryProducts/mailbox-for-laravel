<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { useVModel } from "@vueuse/core"
import { cn } from "@/lib/utils"

const props = defineProps<{
  defaultValue?: string | number
  modelValue?: string | number
  class?: HTMLAttributes["class"]
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
  <!--
    No-line input per design.md "Search Inputs" spec: tonal background on
    surface-container-highest, lifts to lowest + ambient shadow on focus.
  -->
  <input v-model="modelValue" :class="cn('flex h-9 w-full rounded-md bg-surface-container-highest text-on-surface px-3 py-1 body-md transition-[background-color,box-shadow] file:border-0 file:bg-transparent file:body-sm file:font-medium placeholder:text-on-surface-variant focus-visible:outline-none focus-visible:bg-surface-container-lowest focus-visible:shadow-ambient disabled:cursor-not-allowed disabled:opacity-50', props.class)">
</template>
