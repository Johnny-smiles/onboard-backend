<template>
  <div class="space-y-6">
    <section class="page-header space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="space-y-2">
          <p class="eyebrow">Upload studio</p>
          <h2 class="text-2xl font-semibold text-[var(--text)]">Drop a new batch</h2>
          <p class="text-sm text-[var(--text-2)]">
            Keep your brand library fresh with consistent tags and captions.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <span class="badge">JPEG + PNG</span>
          <span class="badge">Auto resize</span>
          <span class="badge">Batch ready</span>
        </div>
      </div>
    </section>

    <div class="hero-grid">
      <Uploader @upload="onUpload" />

      <aside class="section-card">
        <header class="space-y-2">
          <div class="flex items-center justify-between gap-2">
            <h3 class="section-title">Quick metadata</h3>
            <span class="chip">Batch apply</span>
          </div>
          <p class="text-sm text-[var(--text-2)]">Applied to every photo in the current upload batch.</p>
        </header>
        <div class="space-y-3">
          <div class="space-y-2">
            <label class="text-sm font-medium text-[var(--text-2)]" for="tags">Tags</label>
            <input
              id="tags"
              v-model="tags"
              class="input-control"
              placeholder="before, team"
              type="text"
            />
            <p class="text-xs text-[var(--text-2)]">Separate tags with commas to add multiple.</p>
          </div>
          <div class="space-y-2">
            <label class="text-sm font-medium text-[var(--text-2)]" for="caption">Caption</label>
            <input
              id="caption"
              v-model="caption"
              class="input-control"
              placeholder="Describe the photo…"
              type="text"
            />
          </div>
          <div class="space-y-2">
            <label class="text-sm font-medium text-[var(--text-2)]" for="client">Client ID</label>
            <input
              id="client"
              v-model.number="clientId"
              class="input-control"
              min="1"
              type="number"
            />
          </div>
        </div>
      </aside>
    </div>

    <div
      v-if="ok"
      class="panel border border-success/30 bg-success/5 text-success"
    >
      Uploaded! Your photos are processing.
    </div>
    <div
      v-if="err"
      class="panel border border-danger/30 bg-danger/5 text-danger"
    >
      {{ err }}
    </div>
  </div>
</template>
<script setup lang="ts">
import Uploader from '../components/Uploader.vue';
import api from '../services/api';
import { ref } from 'vue';
const tags = ref(''); const caption = ref(''); const clientId = ref<number>(1);
const ok = ref(false); const err = ref('');
async function onUpload(items:{blob:Blob,name:string}[]) {
  ok.value=false; err.value='';
  try{
    for(const it of items){
      const form = new FormData();
      form.append('file', it.blob, it.name);
      form.append('client_id', String(clientId.value));
      if (caption.value) form.append('caption', caption.value);
      if (tags.value) form.append('tags', tags.value);
      await api.post('/photos', form);
    }
    ok.value=true;
  } catch(e:any){ err.value = e?.response?.data?.message || 'Upload failed'; }
}
</script>
