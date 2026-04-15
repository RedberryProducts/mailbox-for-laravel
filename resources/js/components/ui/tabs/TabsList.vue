<script setup lang="ts">
import type { TabsListProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { TabsList } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<TabsListProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")
</script>

<template>
  <TabsList
    v-bind="delegatedProps"
    :class="cn(
      // Default is a tonal segmented list; consumers can override (e.g. the
      // mail preview uses a borderless underline-style tab strip).
      'inline-flex items-center justify-center rounded-lg bg-surface-container-low p-1 text-on-surface-variant',
      props.class,
    )"
  >
    <slot />
  </TabsList>
</template>
