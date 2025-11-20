<script setup lang="ts">
import { AlertDialogTrigger } from 'radix-vue'

defineProps<{
  asChild?: boolean
}>()
</script>

<template>
  <AlertDialogTrigger :as-child="asChild">
    <slot />
  </AlertDialogTrigger>
</template>
