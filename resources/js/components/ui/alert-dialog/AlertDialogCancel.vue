<script setup lang="ts">
import { AlertDialogCancel } from 'radix-vue'
import { cn } from '@/lib/utils'
import { buttonVariants } from '@/components/ui/button'

defineProps<{
  class?: string
}>()
</script>

<template>
  <AlertDialogCancel
    :class="cn(buttonVariants({ variant: 'outline' }), 'mt-2 sm:mt-0', $props.class)"
  >
    <slot />
  </AlertDialogCancel>
</template>
