<template>
  <section class="section-card">
    <header class="space-y-2">
      <div class="flex items-center justify-between">
        <h3 class="section-title">Comments</h3>
        <span class="chip">Team notes</span>
      </div>
      <p class="text-xs text-[var(--text-2)]">Discuss edits or approvals with your team.</p>
    </header>
    <div
      v-if="items.length === 0"
      class="rounded-2xl border border-dashed border-[var(--border)] bg-[var(--surface-2)] px-4 py-6 text-center text-sm text-[var(--text-2)]"
    >
      No comments yet.
    </div>
    <ul v-else class="space-y-3">
      <li
        v-for="comment in items"
        :key="comment.id"
        class="space-y-1 rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-3"
      >
        <p class="text-xs font-semibold uppercase tracking-wide text-primary">
          {{ comment.user?.name || 'User' }}
        </p>
        <p class="text-sm text-[var(--text-2)]">
          {{ comment.body }}
        </p>
      </li>
    </ul>
    <form class="flex flex-col gap-3 sm:flex-row" @submit.prevent="post">
      <input
        v-model="draft"
        class="input-control sm:flex-1"
        placeholder="Write a comment…"
        type="text"
      />
      <Button :disabled="!draft.trim()" size="md" type="submit">Send</Button>
    </form>
  </section>
</template>
<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';
import Button from '../ui/Button.vue';
const props = defineProps<{photoId:number}>();
const items = ref<any[]>([]);
const draft = ref('');
async function load(){ const {data} = await api.get(`/photos/${props.photoId}/comments`); items.value = data; }
async function post(){
  if(!draft.value.trim()) return;
  await api.post(`/photos/${props.photoId}/comments`, { body: draft.value.trim() });
  draft.value=''; await load();
}
onMounted(load);
</script>
