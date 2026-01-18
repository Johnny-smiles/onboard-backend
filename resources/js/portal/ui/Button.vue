<template>
  <button v-bind="attrs" :class="classes">
    <slot />
  </button>
</template>

<script setup lang="ts">
import { computed, useAttrs } from 'vue';

const props = withDefaults(
  defineProps<{
    variant?: 'primary' | 'secondary' | 'danger' | 'ghost';
    size?: 'sm' | 'md' | 'lg';
  }>(),
  {
    variant: 'primary',
    size: 'md',
  }
);

const attrs = useAttrs();

const classes = computed(() => [
  'btn',
  props.variant === 'secondary'
    ? 'btn-secondary'
    : props.variant === 'danger'
      ? 'btn-danger'
      : props.variant === 'ghost'
        ? 'btn-ghost'
        : 'btn-primary',
  props.size === 'sm'
    ? 'h-8 px-2 text-xs'
    : props.size === 'lg'
      ? 'h-11 px-4 text-base'
      : 'h-10 px-3 text-sm',
  attrs.class,
]);
</script>
