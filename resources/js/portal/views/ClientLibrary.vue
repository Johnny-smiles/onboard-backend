<template>
  <section class="space-y-6">
    <section class="page-header">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="space-y-2">
          <p class="eyebrow">Brand library</p>
          <h2 class="text-2xl font-semibold text-[var(--text)]">Your visual archive</h2>
          <p class="text-sm text-[var(--text-2)]">Browse approved and pending assets for this client.</p>
        </div>
        <Button size="sm" variant="ghost" @click="load">Refresh</Button>
      </div>
    </section>

    <div v-if="photos.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <article
        v-for="photo in photos"
        :key="photo.id"
        class="card space-y-3"
      >
        <img
          :alt="photo.caption || 'Uploaded photo'"
          :src="fileUrl(photo.file_path)"
          class="h-56 w-full rounded-2xl object-cover"
        />
        <div class="flex flex-wrap gap-2">
          <span class="badge">#{{ photo.id }}</span>
          <span class="badge" :class="photo.approved ? 'badge-success' : 'badge-warning'">
            {{ photo.approved ? 'Approved' : 'Pending' }}
          </span>
          <span class="badge">Score {{ photo.quality_score ?? '-' }}</span>
        </div>
        <p class="text-sm text-[var(--text-2)]">
          {{ photo.caption || 'No caption provided yet.' }}
        </p>
      </article>
    </div>

    <div v-else class="empty-state">
      <div class="chip">No uploads yet</div>
      <p class="text-sm text-[var(--text-2)]">Start uploading to populate your brand library.</p>
    </div>
  </section>
</template>
<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';
import Button from '../ui/Button.vue';
const photos = ref<any[]>([]);
function fileUrl(p:string){ return `/storage/${p.replace(/^public\//,'')}`; }
async function load(){ const {data} = await api.get('/photos'); photos.value = data?.data || data || []; }
onMounted(load);
</script>
