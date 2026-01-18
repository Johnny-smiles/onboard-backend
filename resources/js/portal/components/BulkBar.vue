<template>
  <div
    v-if="count > 0"
    class="panel flex flex-wrap items-center justify-between gap-4"
  >
    <div class="text-sm font-medium text-[var(--text-2)]">
      <span class="badge">Selection</span>
      <span class="ml-2 font-semibold text-[var(--text)]">{{ count }}</span> ready to action
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <Button @click="$emit('approve')">Approve</Button>
      <Button variant="secondary" @click="$emit('delete')">Delete</Button>
      <Button variant="secondary" @click="$emit('export')">Export ZIP</Button>
      <input v-model="tag" class="input-control w-44" placeholder="Add tag" type="text" />
      <Button variant="secondary" @click="$emit('tag', tag)">Add Tag</Button>
      <Button variant="secondary" @click="$emit('publish')">Publish…</Button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import Button from '../ui/Button.vue';

defineProps<{ count: number }>();

const tag = ref('');
</script>
