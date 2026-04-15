<script setup lang="ts">
import { AlertDialogOverlay } from 'radix-vue'
import { cn } from '@/lib/utils'

defineProps<{
  class?: string
}>()
</script>

<template>
  <AlertDialogOverlay
    :class="
      cn(
        // Tinted overlay using on-surface @ low opacity — never pure black.
        'fixed inset-0 z-50 bg-on-surface/30 backdrop-blur-sm data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0',
        $props.class,
      )
    "
  />
</template>
