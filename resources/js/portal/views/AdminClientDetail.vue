<template>
  <div class="space-y-6">
    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <p class="text-[var(--text-2)]">Loading client...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="panel border border-danger/40 bg-danger/5 text-danger">
      {{ error }}
    </div>

    <!-- Client Details -->
    <div v-else>
      <!-- Header -->
      <section class="page-header">
        <div class="flex items-start justify-between gap-4">
          <div class="flex items-start gap-4">
            <div
              v-if="client.brand_color"
              class="h-16 w-16 rounded-2xl border-2 border-[var(--border)] flex-shrink-0"
              :style="{ backgroundColor: client.brand_color }"
            ></div>
            <div v-else class="h-16 w-16 rounded-2xl border-2 border-[var(--border)] bg-[var(--surface-2)] flex-shrink-0"></div>
            <div>
              <p class="eyebrow">Client profile</p>
              <h1 class="text-3xl font-bold text-[var(--text)]">{{ client.name }}</h1>
              <p v-if="client.contact_email" class="mt-2 text-[var(--text-2)]">
                <span class="font-medium">Email:</span> {{ client.contact_email }}
              </p>
              <p v-if="client.contact_phone" class="mt-1 text-[var(--text-2)]">
                <span class="font-medium">Phone:</span> {{ client.contact_phone }}
              </p>
              <div v-if="client.notes" class="mt-3 text-sm text-[var(--text-2)]">
                {{ client.notes }}
              </div>
            </div>
          </div>
          <div class="flex gap-2">
            <Button variant="ghost" @click="$router.push('/admin/clients')">
              ← Back
            </Button>
          </div>
        </div>

        <!-- Stats -->
        <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
          <div class="stat-tile">
            <div class="stat-value">{{ client.projects_count || 0 }}</div>
            <div class="stat-label">Projects</div>
          </div>
          <div class="stat-tile">
            <div class="stat-value">{{ client.photos_count || 0 }}</div>
            <div class="stat-label">Photos</div>
          </div>
          <div class="stat-tile">
            <div class="stat-value">{{ client.users_count || 0 }}</div>
            <div class="stat-label">Users</div>
          </div>
          <div class="stat-tile">
            <div class="stat-value">{{ socialIntegrations.length }}</div>
            <div class="stat-label">Integrations</div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="mt-6 flex flex-wrap gap-3">
          <Button @click="$router.push(`/admin/review?client=${client.id}`)">
            Review Photos
          </Button>
          <Button variant="secondary" @click="$router.push(`/admin/clients/${client.id}/social`)">
            Manage Social Connections
          </Button>
          <Button variant="secondary" @click="showEditModal = true">
            Edit Client
          </Button>
        </div>
      </section>

      <!-- Social Integrations -->
      <section v-if="socialIntegrations.length > 0" class="section-card">
        <h2 class="section-title mb-4">Connected Social Accounts</h2>
        <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="integration in socialIntegrations"
            :key="integration.id"
            class="rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] p-4"
          >
            <div class="flex items-center justify-between mb-2">
              <span class="font-semibold text-[var(--text)] capitalize">{{ integration.provider }}</span>
              <span class="rounded-full bg-green-500/10 px-2 py-1 text-xs font-semibold text-green-600">
                Connected
              </span>
            </div>
            <p v-if="integration.account_name" class="text-sm text-[var(--text-2)]">
              {{ integration.account_name }}
            </p>
            <p v-if="integration.connected_at" class="mt-1 text-xs text-[var(--text-3)]">
              Connected {{ formatDate(integration.connected_at) }}
            </p>
          </div>
        </div>
      </section>

      <!-- Recent Projects -->
      <section v-if="client.projects && client.projects.length > 0" class="section-card">
        <h2 class="section-title mb-4">Recent Projects</h2>
        <div class="space-y-3">
          <div
            v-for="project in client.projects"
            :key="project.id"
            class="rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] p-4"
          >
            <h3 class="font-semibold text-[var(--text)]">{{ project.name }}</h3>
            <div class="mt-2 flex items-center gap-4 text-sm text-[var(--text-2)]">
              <span v-if="project.start_date">Start: {{ formatDate(project.start_date) }}</span>
              <span v-if="project.end_date">End: {{ formatDate(project.end_date) }}</span>
            </div>
          </div>
        </div>
      </section>

      <!-- Users -->
      <section v-if="client.users && client.users.length > 0" class="section-card">
        <h2 class="section-title mb-4">Users</h2>
        <div class="space-y-3">
          <div
            v-for="user in client.users"
            :key="user.id"
            class="flex items-center justify-between rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] p-4"
          >
            <div>
              <h3 class="font-semibold text-[var(--text)]">{{ user.name }}</h3>
              <p class="text-sm text-[var(--text-2)]">{{ user.email }}</p>
            </div>
            <span class="rounded-full bg-[var(--surface-3)] px-3 py-1 text-xs font-semibold text-[var(--text-2)]">
              {{ user.role }}
            </span>
          </div>
        </div>
      </section>

      <!-- Recent Photos -->
      <section v-if="recentPhotos.length > 0" class="section-card">
        <div class="flex items-center justify-between mb-4">
          <h2 class="section-title">Recent Photos</h2>
          <Button variant="ghost" size="sm" @click="$router.push(`/admin/review?client=${client.id}`)">
            View All
          </Button>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div
            v-for="photo in recentPhotos"
            :key="photo.id"
            class="relative aspect-square rounded-2xl overflow-hidden border border-[var(--border)]"
          >
            <img
              :src="fileUrl(photo.file_path)"
              :alt="photo.caption || 'Photo'"
              class="h-full w-full object-cover"
            />
            <div
              v-if="photo.approved"
              class="absolute top-2 right-2 rounded-full bg-green-500 px-2 py-1 text-xs font-semibold text-white"
            >
              ✓ Approved
            </div>
          </div>
        </div>
      </section>
    </div>

    <!-- Edit Client Modal -->
    <div
      v-if="showEditModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      @click.self="closeEditModal"
    >
      <div class="w-full max-w-lg rounded-[1.75rem] border border-[var(--border)] bg-[var(--surface)] p-6 shadow-xl">
        <h2 class="text-xl font-bold text-[var(--text)]">Edit Client</h2>
        <p class="mt-1 text-sm text-[var(--text-2)]">Update client information</p>

        <form @submit.prevent="submitEdit" class="mt-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-[var(--text)]">Company Name *</label>
            <input
              v-model="editForm.name"
              type="text"
              required
              class="input-control mt-1"
              placeholder="Acme Corp"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-[var(--text)]">Contact Email</label>
            <input
              v-model="editForm.contact_email"
              type="email"
              class="input-control mt-1"
              placeholder="contact@acme.com"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-[var(--text)]">Contact Phone</label>
            <input
              v-model="editForm.contact_phone"
              type="tel"
              class="input-control mt-1"
              placeholder="555-0000"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-[var(--text)]">Brand Color</label>
            <div class="mt-1 flex gap-2">
              <input
                v-model="editForm.brand_color"
                type="color"
                class="h-10 w-16 rounded-2xl border border-[var(--border)] bg-[var(--surface-2)]"
              />
              <input
                v-model="editForm.brand_color"
                type="text"
                class="input-control flex-1"
                placeholder="#3B82F6"
              />
            </div>
          </div>

          <div>
            <label class="flex items-center gap-2">
              <input v-model="editForm.watermark_enabled" type="checkbox" class="rounded" />
              <span class="text-sm font-medium text-[var(--text)]">Enable Watermark</span>
            </label>
          </div>

          <div>
            <label class="block text-sm font-medium text-[var(--text)]">Notes</label>
            <textarea
              v-model="editForm.notes"
              rows="3"
              class="input-control mt-1"
              placeholder="Internal notes about this client..."
            ></textarea>
          </div>

          <div class="flex gap-3 pt-4">
            <Button type="button" variant="secondary" @click="closeEditModal" class="flex-1">
              Cancel
            </Button>
            <Button type="submit" :disabled="submitting" class="flex-1">
              {{ submitting ? 'Saving...' : 'Update Client' }}
            </Button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { toast } from 'vue-sonner';
import api from '../services/api';
import Button from '../ui/Button.vue';

interface Client {
  id: number;
  name: string;
  contact_email: string | null;
  contact_phone: string | null;
  logo_url: string | null;
  brand_color: string | null;
  watermark_enabled: boolean;
  notes: string | null;
  projects_count: number;
  photos_count: number;
  users_count: number;
  projects?: any[];
  users?: any[];
}

interface SocialIntegration {
  id: number;
  provider: string;
  account_name: string | null;
  connected_at: string | null;
}

interface Photo {
  id: number;
  file_path: string;
  caption: string | null;
  approved: boolean;
}

const route = useRoute();
const router = useRouter();
const clientId = Number(route.params.id);

const client = ref<Client>({} as Client);
const socialIntegrations = ref<SocialIntegration[]>([]);
const recentPhotos = ref<Photo[]>([]);
const loading = ref(true);
const error = ref('');
const showEditModal = ref(false);
const submitting = ref(false);

const editForm = ref({
  name: '',
  contact_email: '',
  contact_phone: '',
  brand_color: '#3B82F6',
  watermark_enabled: false,
  notes: '',
});

async function loadClient() {
  try {
    loading.value = true;
    error.value = '';

    const [clientRes, integrationsRes, photosRes] = await Promise.all([
      api.get(`/clients/${clientId}`),
      api.get(`/clients/${clientId}/integrations`).catch(() => ({ data: [] })),
      api.get(`/photos`, { params: { 'filter[client_id]': clientId } }).catch(() => ({ data: { data: [] } })),
    ]);

    client.value = clientRes.data;
    socialIntegrations.value = integrationsRes.data;
    recentPhotos.value = (photosRes.data?.data || photosRes.data || []).slice(0, 8);
    
    // Populate edit form
    editForm.value = {
      name: client.value.name,
      contact_email: client.value.contact_email || '',
      contact_phone: client.value.contact_phone || '',
      brand_color: client.value.brand_color || '#3B82F6',
      watermark_enabled: client.value.watermark_enabled,
      notes: client.value.notes || '',
    };
  } catch (err: any) {
    console.error('Failed to load client:', err);
    error.value = err.response?.data?.message || 'Failed to load client details';
  } finally {
    loading.value = false;
  }
}

function closeEditModal() {
  showEditModal.value = false;
}

async function submitEdit() {
  try {
    submitting.value = true;
    
    await api.put(`/clients/${clientId}`, {
      name: editForm.value.name,
      contact_email: editForm.value.contact_email,
      contact_phone: editForm.value.contact_phone,
      brand_color: editForm.value.brand_color,
      watermark_enabled: editForm.value.watermark_enabled,
      notes: editForm.value.notes,
    });
    
    toast.success('Client updated successfully!');
    closeEditModal();
    loadClient(); // Reload to show changes
  } catch (err: any) {
    console.error('Failed to update client:', err);
    toast.error(err.response?.data?.message || 'Failed to update client');
  } finally {
    submitting.value = false;
  }
}

function fileUrl(path: string): string {
  return `/storage/${path.replace(/^public\//, '')}`;
}

function formatDate(dateString: string | null): string {
  if (!dateString) return 'N/A';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

onMounted(() => {
  if (isNaN(clientId)) {
    error.value = 'Invalid client ID';
    loading.value = false;
    return;
  }
  loadClient();
});
</script>
