<template>
  <div class="card space-y-4">
    <div class="relative">
      <img
        :src="fileUrl(photo.file_path)"
        class="h-56 w-full rounded-2xl border border-[var(--border)]/60 object-cover"
      />
      <span
        class="absolute left-4 top-4 badge"
        :class="photo.approved ? 'badge-success' : 'badge-warning'"
      >
        {{ photo.approved ? 'Approved' : 'Pending' }}
      </span>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <span class="badge">#{{ photo.id }}</span>
      <span class="badge">Client {{ photo.client_id }}</span>
      <span class="badge">Score {{ photo.quality_score ?? '-' }}</span>
    </div>
    <div class="text-sm text-[var(--text-2)]">{{ photo.caption || 'No caption' }}</div>
    <div class="flex flex-wrap gap-2">
      <span v-for="t in (photo.tags || [])" :key="t.id" class="badge">{{ t.name }}</span>
    </div>
    <div class="flex flex-wrap gap-2">
      <Button v-if="!photo.approved" size="sm" @click="$emit('approve', photo)">Approve</Button>
      <Button size="sm" variant="secondary" @click="$emit('comment', photo)">Comments</Button>
      <Button size="sm" variant="secondary" @click="$emit('tag', photo)">Tags</Button>
      <Button size="sm" variant="secondary" @click="$emit('publish', photo)">Publish</Button>
      <Button size="sm" variant="danger" @click="$emit('delete', photo)">Delete</Button>
    </div>
  </div>
</template>
<script setup lang="ts">
import Button from '../ui/Button.vue';
defineProps<{photo:any}>();
function fileUrl(p:string){ return `/storage/${p.replace(/^public\//,'')}` }
</script>
