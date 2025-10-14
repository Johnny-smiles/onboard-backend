<template>
  <div class="card">
    <img :src="fileUrl(photo.file_path)" style="width:100%;border-radius:.5rem" />
    <div style="margin-top:.5rem;display:flex;gap:.5rem;flex-wrap:wrap">
      <span class="badge">#{{ photo.id }}</span>
      <span class="badge">Client {{ photo.client_id }}</span>
      <span class="badge">Score {{ photo.quality_score ?? '-' }}</span>
      <span class="badge" :style="{borderColor: photo.approved?'#16a34a':'#f59e0b', color: photo.approved?'#166534':'#92400e'}">
        {{ photo.approved ? 'Approved' : 'Pending' }}
      </span>
    </div>
    <div style="margin-top:.25rem">{{ photo.caption || 'No caption' }}</div>
    <div style="margin-top:.5rem;display:flex;gap:.5rem;flex-wrap:wrap">
      <span v-for="t in (photo.tags || [])" :key="t.id" class="badge">{{ t.name }}</span>
    </div>
    <div style="margin-top:.5rem;display:flex;gap:.5rem">
      <button v-if="!photo.approved" @click="$emit('approve', photo)">Approve</button>
      <button class="secondary" @click="$emit('delete', photo)">Delete</button>
      <button class="secondary" @click="$emit('comment', photo)">Comments</button>
      <button class="secondary" @click="$emit('tag', photo)">Tags</button>
      <button class="secondary" @click="$emit('publish', photo)">Publish</button>
    </div>
  </div>
</template>
<script setup lang="ts">
defineProps<{photo:any}>();
function fileUrl(p:string){ return `/storage/${p.replace(/^public\//,'')}` }
</script>
