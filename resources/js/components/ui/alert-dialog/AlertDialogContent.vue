<script setup lang="ts">
import {
  AlertDialogContent,
  AlertDialogPortal,
} from 'radix-vue'
import { cn } from '@/lib/utils'
import AlertDialogOverlay from './AlertDialogOverlay.vue'

defineOptions({
  inheritAttrs: false,
})

defineProps<{
  class?: string
}>()
</script>

<template>
  <AlertDialogPortal>
    <!--
      Use the *local* AlertDialogOverlay wrapper (not radix-vue's primitive)
      so the tinted + backdrop-blurred overlay styles actually apply.
    -->
    <AlertDialogOverlay />
    <AlertDialogContent
      v-bind="$attrs"
      :class="
        cn(
          // Glassmorphism per design.md: surface-container-high @ 80% +
          // 20px backdrop blur. Ambient shadow only — no opaque border.
          'fixed left-1/2 top-1/2 z-50 grid w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 gap-5 rounded-xl bg-surface-container-high/80 backdrop-blur-[20px] p-6 shadow-ambient duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95',
          $props.class,
        )
      "
    >
      <slot />
    </AlertDialogContent>
  </AlertDialogPortal>
</template>
