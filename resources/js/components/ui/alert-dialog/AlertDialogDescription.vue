<script setup lang="ts">
import { AlertDialogDescription } from 'radix-vue'
import { cn } from '@/lib/utils'

defineProps<{
  class?: string
}>()
</script>

<template>
  <AlertDialogDescription :class="cn('body-md text-on-surface-variant', $props.class)">
    <slot />
  </AlertDialogDescription>
</template>
