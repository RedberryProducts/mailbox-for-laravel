<script setup lang="ts">
import { AlertDialogAction } from 'radix-vue'
import { cn } from '@/lib/utils'
import { buttonVariants } from '@/components/ui/button'

defineProps<{
  class?: string
}>()
</script>

<template>
  <AlertDialogAction :class="cn(buttonVariants(), $props.class)">
    <slot />
  </AlertDialogAction>
</template>
