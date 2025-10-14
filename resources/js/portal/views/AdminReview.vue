<template>
  <div class="space-y-6">
    <BulkBar
      :count="selected.size"
      @approve="bulkApprove"
      @delete="bulkDelete"
      @export="bulkExport"
      @publish="openPublish"
      @tag="bulkTag"
    />

    <div class="card space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <h2 class="text-xl font-semibold text-slate-900">Admin Review</h2>

        <div class="flex flex-wrap items-center gap-3 text-sm">
          <label class="font-medium text-slate-600">Approved</label>
          <select v-model="approved" class="input-control w-36 sm:w-44">
            <option :value="null">All</option>
            <option :value="0">Pending</option>
            <option :value="1">Approved</option>
          </select>
          <Button variant="secondary" @click="load">Refresh</Button>
        </div>
      </div>

      <div class="grid gap-4 md:grid-cols-2">
        <div v-for="photo in photos" :key="photo.id" class="card space-y-4">
          <div class="flex items-center justify-between gap-3">
            <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
              <input
                :checked="selected.has(photo.id)"
                class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                type="checkbox"
                @change="toggle(photo.id)"
              />
              Select
            </label>
            <Button variant="secondary" @click="suggest(photo)">Suggest caption</Button>
          </div>

          <img
            :alt="photo.caption || 'Uploaded photo'"
            :src="fileUrl(photo.file_path)"
            class="w-full rounded-xl border border-slate-200 object-cover"
          />

          <div class="flex flex-wrap gap-2">
            <span class="badge">#{{ photo.id }}</span>
            <span class="badge">Client {{ photo.client_id }}</span>
            <span class="badge">Score {{ photo.quality_score ?? '-' }}</span>
            <span class="badge">Approved: {{ photo.approved ? 'Yes' : 'No' }}</span>
          </div>

          <p class="text-sm text-slate-600">
            {{ photo.caption || 'No caption provided yet.' }}
          </p>

          <div class="flex items-center gap-2">
            <input v-model="tagDraft" class="input-control w-full sm:w-48" placeholder="Add tag…" type="text" />
            <Button variant="secondary" @click="addTag(photo)">Add</Button>
          </div>

          <div class="flex flex-wrap gap-2">
            <span v-for="tag in photo.tags || []" :key="tag.id" class="badge">{{ tag.name }}</span>
          </div>

          <div class="flex flex-wrap gap-2">
            <Button v-if="!photo.approved" @click="approve(photo)">Approve</Button>
            <Button variant="secondary" @click="remove(photo)">Delete</Button>
            <Button variant="secondary" @click="openComments(photo)">Comments</Button>
            <Button variant="secondary" @click="openPublish([photo.id])">Publish…</Button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="showComments" class="card">
      <CommentsPanel :photo-id="activePhotoId" />
    </div>

    <div v-if="showPublish" class="card">
      <PublishDrawer :photo-ids="Array.from(selected)" @close="showPublish = false" @queued="onQueued" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import BulkBar from '../components/BulkBar.vue';
import CommentsPanel from '../components/CommentsPanel.vue';
import PublishDrawer from '../components/PublishDrawer.vue';
import api from '../services/api';
import Button from '../ui/Button.vue';

type PhotoRecord = Record<string, any>;

const photos = ref<PhotoRecord[]>([]);
const approved = ref<number | null>(null);
const selected = ref<Set<number>>(new Set());
const tagDraft = ref('');
const showComments = ref(false);
const activePhotoId = ref<number | null>(null);
const showPublish = ref(false);

function fileUrl(path: string): string {
  return `/storage/${path.replace(/^public\//, '')}`;
}

function refreshSelection(): void {
  selected.value = new Set(selected.value);
}

function toggle(id: number): void {
  if (selected.value.has(id)) {
    selected.value.delete(id);
  } else {
    selected.value.add(id);
  }

  refreshSelection();
}

async function load(): Promise<void> {
  const params: Record<string, unknown> = {};

  if (approved.value !== null) {
    params.approved = approved.value;
  }

  const { data } = await api.get('/photos', { params });

  photos.value = data?.data ?? data ?? [];
  photos.value.forEach((photo) => {
    photo.tags = photo.tags || [];
  });
}

async function approve(photo: PhotoRecord): Promise<void> {
  try {
    await api.post(`/photos/${photo.id}/approve`);
    toast.success('Photo approved.');
    await load();
  } catch (error) {
    toast.error('Unable to approve photo.');
    console.error(error);
  }
}

async function remove(photo: PhotoRecord): Promise<void> {
  try {
    await api.delete(`/photos/${photo.id}`);
    toast.success('Photo deleted.');
    await load();
  } catch (error) {
    toast.error('Unable to delete photo.');
    console.error(error);
  }
}

async function addTag(photo: PhotoRecord): Promise<void> {
  const value = tagDraft.value.trim();

  if (!value) {
    toast.error('Enter a tag before adding.');
    return;
  }

  try {
    await api.post(`/photos/${photo.id}/tags`, { tag: value });
    toast.success(`Tag "${value}" added.`);
    tagDraft.value = '';
    await load();
  } catch (error) {
    toast.error('Unable to add tag.');
    console.error(error);
  }
}

async function openComments(photo: PhotoRecord): Promise<void> {
  activePhotoId.value = photo.id;
  showComments.value = true;
}

async function suggest(photo: PhotoRecord): Promise<void> {
  try {
    const { data } = await api.post(`/photos/${photo.id}/suggest-caption`);
    toast.info(`Suggested: ${data.caption}`);
  } catch (error) {
    toast.error('Unable to generate a caption.');
    console.error(error);
  }
}

async function bulkApprove(): Promise<void> {
  if (!selected.value.size) {
    return;
  }

  try {
    await api.post('/photos/bulk/approve', { photo_ids: Array.from(selected.value) });
    selected.value.clear();
    toast.success('Selected photos approved.');
    await load();
  } catch (error) {
    toast.error('Unable to approve selected photos.');
    console.error(error);
  }
}

async function bulkDelete(): Promise<void> {
  if (!selected.value.size) {
    return;
  }

  try {
    await api.post('/photos/bulk/delete', { photo_ids: Array.from(selected.value) });
    selected.value.clear();
    toast.success('Selected photos deleted.');
    await load();
  } catch (error) {
    toast.error('Unable to delete selected photos.');
    console.error(error);
  }
}

async function bulkExport(): Promise<void> {
  if (!selected.value.size) {
    return;
  }

  try {
    const { data } = await api.post('/photos/bulk/export', { photo_ids: Array.from(selected.value) });
    toast.success('Export started in a new tab.');
    window.open(data.url, '_blank');
  } catch (error) {
    toast.error('Unable to export photos.');
    console.error(error);
  }
}

function bulkTag(tag: string): void {
  const trimmed = tag.trim();

  if (!trimmed || !selected.value.size) {
    return;
  }

  Promise.all(
    Array.from(selected.value).map((id) => api.post(`/photos/${id}/tags`, { tag: trimmed }))
  )
    .then(async () => {
      toast.success(`Tag "${trimmed}" added to selected photos.`);
      await load();
    })
    .catch((error) => {
      toast.error('Unable to tag selected photos.');
      console.error(error);
    });
}

function openPublish(photoIds?: number[]): void {
  if (photoIds?.length) {
    selected.value = new Set(photoIds);
  }

  showPublish.value = true;
}

function onQueued(): void {
  showPublish.value = false;
  toast.success('Publish job queued. It will publish at the scheduled time.');
}

watch(approved, load);
onMounted(load);
</script>
