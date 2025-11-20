<script setup lang="ts">
import { AlertDialogTitle } from 'radix-vue'
import { cn } from '@/lib/utils'

defineProps<{
  class?: string
}>()
</script>

<template>
  <AlertDialogTitle :class="cn('text-lg font-semibold', $props.class)">
    <slot />
  </AlertDialogTitle>
</template>
