<template>
  <section class="section-card">
    <header class="space-y-2">
      <div class="flex items-center justify-between gap-3">
        <h3 class="section-title">Publish selection</h3>
        <span class="chip">Ready to launch</span>
      </div>
      <p class="text-xs text-[var(--text-2)]">
        Schedule these assets for downstream channels. Integrations are mocked until backend services are ready.
      </p>
    </header>

    <div class="space-y-2">
      <label class="text-sm font-medium text-[var(--text-2)]" for="service">Service</label>
      <select
        id="service"
        v-model="service"
        class="input-control"
      >
        <option value="wordpress">WordPress</option>
        <option value="meta">Meta / Instagram</option>
        <option value="gbp">Google Business Profile</option>
      </select>
    </div>

    <div class="space-y-2">
      <label class="text-sm font-medium text-[var(--text-2)]" for="when">When (optional)</label>
      <input
        id="when"
        v-model="when"
        class="input-control"
        type="datetime-local"
      />
    </div>

    <div class="flex flex-wrap items-center gap-3">
      <Button size="md" @click="queue">Queue</Button>
      <Button size="md" variant="ghost" @click="$emit('close')">Close</Button>
    </div>
    <p class="text-xs text-[var(--text-2)]">
      Stubs only — publish flows will sync once integrations are wired up.
    </p>
  </section>
</template>
<script setup lang="ts">
import { ref } from 'vue';
import api from '../services/api';
import Button from '../ui/Button.vue';

const props = defineProps<{ photoIds: number[] }>();
const emit = defineEmits<{ (e: 'queued'): void; (e: 'close'): void }>();

const service = ref<'wordpress' | 'meta' | 'gbp'>('wordpress');
const when = ref('');

async function queue() {
  const body: Record<string, unknown> = { photo_ids: props.photoIds };

  if (when.value) {
    body.when = when.value;
  }

  const path =
    service.value === 'wordpress'
      ? '/publish/wordpress'
      : service.value === 'meta'
        ? '/publish/meta'
        : '/publish/gbp';

  await api.post(path, body);
  emit('queued');
}
</script>
