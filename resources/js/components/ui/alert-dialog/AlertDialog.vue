<script setup lang="ts">
import { AlertDialogRoot } from 'radix-vue'

const open = defineModel<boolean>('open')
</script>

<template>
  <AlertDialogRoot v-model:open="open">
    <slot />
  </AlertDialogRoot>
</template>
