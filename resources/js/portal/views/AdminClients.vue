<template>
  <div class="space-y-6">
    <section class="page-header">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="space-y-2">
          <p class="eyebrow">Client studio</p>
          <h1 class="text-2xl font-bold text-[var(--text)]">Client management</h1>
          <p class="text-sm text-[var(--text-2)]">Onboard and manage your clients</p>
        </div>
        <Button @click="showCreateModal = true">
          <span class="mr-2">+</span> Onboard New Client
        </Button>
      </div>
    </section>

    <!-- Clients List -->
    <div v-if="loading" class="text-center py-12">
      <p class="text-[var(--text-2)]">Loading clients...</p>
    </div>

    <div v-else-if="clients.length === 0" class="empty-state">
      <div class="chip">No clients yet</div>
      <p class="text-[var(--text-2)]">Click "Onboard New Client" to get started.</p>
    </div>

    <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
      <div
        v-for="client in clients"
        :key="client.id"
        class="card transition-all hover:shadow-lg cursor-pointer"
        @click="$router.push(`/admin/clients/${client.id}`)"
      >
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <h3 class="text-lg font-semibold text-[var(--text)]">{{ client.name }}</h3>
            <p v-if="client.contact_email" class="mt-1 text-sm text-[var(--text-2)]">
              {{ client.contact_email }}
            </p>
          </div>
          <div
            v-if="client.brand_color"
            class="h-8 w-8 rounded-full border-2 border-[var(--border)]"
            :style="{ backgroundColor: client.brand_color }"
          ></div>
        </div>

        <div class="mt-4 grid grid-cols-3 gap-2 text-center">
          <div class="stat-tile">
            <div class="stat-value text-xl">{{ client.projects_count || 0 }}</div>
            <div class="stat-label">Projects</div>
          </div>
          <div class="stat-tile">
            <div class="stat-value text-xl">{{ client.photos_count || 0 }}</div>
            <div class="stat-label">Photos</div>
          </div>
          <div class="stat-tile">
            <div class="stat-value text-xl">{{ client.users_count || 0 }}</div>
            <div class="stat-label">Users</div>
          </div>
        </div>

        <div class="mt-4 flex gap-2" @click.stop>
          <Button size="sm" variant="secondary" @click="editClient(client)" class="flex-1">
            Edit
          </Button>
          <Button
            size="sm"
            variant="secondary"
            @click="$router.push(`/admin/review?client=${client.id}`)"
            class="flex-1"
          >
            Review
          </Button>
          <Button
            size="sm"
            variant="secondary"
            @click="$router.push(`/admin/clients/${client.id}/social`)"
            class="flex-1"
          >
            Socials
          </Button>
        </div>
      </div>
    </div>

    <!-- Create/Edit Client Modal -->
    <div
      v-if="showCreateModal || showEditModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      @click.self="closeModal"
    >
      <div class="w-full max-w-lg rounded-[1.75rem] border border-[var(--border)] bg-[var(--surface)] p-6 shadow-xl">
        <h2 class="text-xl font-bold text-[var(--text)]">
          {{ showEditModal ? 'Edit Client' : 'Onboard New Client' }}
        </h2>
        <p class="mt-1 text-sm text-[var(--text-2)]">
          {{ showEditModal ? 'Update client information' : 'Create a new client account' }}
        </p>

        <form @submit.prevent="submitClient" class="mt-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-[var(--text)]">Company Name *</label>
            <input
              v-model="form.name"
              type="text"
              required
              class="input-control mt-1"
              placeholder="Acme Corp"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-[var(--text)]">Contact Email</label>
            <input
              v-model="form.contact_email"
              type="email"
              class="input-control mt-1"
              placeholder="contact@acme.com"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-[var(--text)]">Contact Phone</label>
            <input
              v-model="form.contact_phone"
              type="tel"
              class="input-control mt-1"
              placeholder="555-0000"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-[var(--text)]">Brand Color</label>
            <div class="mt-1 flex gap-2">
              <input
                v-model="form.brand_color"
                type="color"
                class="h-10 w-16 rounded-2xl border border-[var(--border)] bg-[var(--surface-2)]"
              />
              <input
                v-model="form.brand_color"
                type="text"
                class="input-control flex-1"
                placeholder="#3B82F6"
              />
            </div>
          </div>

          <div>
            <label class="flex items-center gap-2">
              <input v-model="form.watermark_enabled" type="checkbox" class="rounded" />
              <span class="text-sm font-medium text-[var(--text)]">Enable Watermark</span>
            </label>
          </div>

          <div>
            <label class="block text-sm font-medium text-[var(--text)]">Notes</label>
            <textarea
              v-model="form.notes"
              rows="3"
              class="input-control mt-1"
              placeholder="Internal notes about this client..."
            ></textarea>
          </div>

          <!-- User Creation (only for new clients) -->
          <div v-if="!showEditModal" class="border-t border-[var(--border)] pt-4">
            <h3 class="text-sm font-semibold text-[var(--text)]">Create User Account</h3>
            <p class="mt-1 text-xs text-[var(--text-2)]">A user account will be created for this client</p>

            <div class="mt-3">
              <label class="block text-sm font-medium text-[var(--text)]">User Email *</label>
              <input
                v-model="form.user_email"
                type="email"
                required
                class="input-control mt-1"
                placeholder="user@acme.com"
              />
            </div>

            <div class="mt-3">
              <label class="block text-sm font-medium text-[var(--text)]">User Password *</label>
              <input
                v-model="form.user_password"
                type="text"
                required
                class="input-control mt-1"
                placeholder="password"
              />
            </div>
          </div>

          <div class="flex gap-3 pt-4">
            <Button type="button" variant="secondary" @click="closeModal" class="flex-1">
              Cancel
            </Button>
            <Button type="submit" :disabled="submitting" class="flex-1">
              {{ submitting ? 'Saving...' : showEditModal ? 'Update Client' : 'Create Client' }}
            </Button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { toast } from 'vue-sonner';
import api from '../services/api';
import Button from '../ui/Button.vue';

const route = useRoute();

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
}

const clients = ref<Client[]>([]);
const loading = ref(true);
const showCreateModal = ref(false);
const showEditModal = ref(false);
const submitting = ref(false);
const editingClient = ref<Client | null>(null);

const form = ref({
  name: '',
  contact_email: '',
  contact_phone: '',
  brand_color: '#3B82F6',
  watermark_enabled: false,
  notes: '',
  user_email: '',
  user_password: 'password',
});

async function loadClients() {
  try {
    loading.value = true;
    const { data } = await api.get('/clients');
    clients.value = data;
    
    // After loading, check if we need to open edit modal
    if (route.query.edit) {
      const clientId = Number(route.query.edit);
      const client = clients.value.find(c => c.id === clientId);
      if (client) {
        setTimeout(() => editClient(client), 300);
      }
    }
  } catch (error) {
    console.error('Failed to load clients:', error);
    toast.error('Failed to load clients');
  } finally {
    loading.value = false;
  }
}

function editClient(client: Client) {
  editingClient.value = client;
  form.value = {
    name: client.name,
    contact_email: client.contact_email || '',
    contact_phone: client.contact_phone || '',
    brand_color: client.brand_color || '#3B82F6',
    watermark_enabled: client.watermark_enabled,
    notes: client.notes || '',
    user_email: '',
    user_password: '',
  };
  showEditModal.value = true;
}

function closeModal() {
  showCreateModal.value = false;
  showEditModal.value = false;
  editingClient.value = null;
  form.value = {
    name: '',
    contact_email: '',
    contact_phone: '',
    brand_color: '#3B82F6',
    watermark_enabled: false,
    notes: '',
    user_email: '',
    user_password: 'password',
  };
}

async function submitClient() {
  try {
    submitting.value = true;

    if (showEditModal.value && editingClient.value) {
      // Update existing client
      await api.put(`/clients/${editingClient.value.id}`, {
        name: form.value.name,
        contact_email: form.value.contact_email,
        contact_phone: form.value.contact_phone,
        brand_color: form.value.brand_color,
        watermark_enabled: form.value.watermark_enabled,
        notes: form.value.notes,
      });
      toast.success('Client updated successfully!');
    } else {
      // Create new client
      const { data: client } = await api.post('/clients', {
        name: form.value.name,
        contact_email: form.value.contact_email,
        contact_phone: form.value.contact_phone,
        brand_color: form.value.brand_color,
        watermark_enabled: form.value.watermark_enabled,
        notes: form.value.notes,
      });

      // Create user for the client
      await api.post('/register', {
        name: `${form.value.name} User`,
        email: form.value.user_email,
        password: form.value.user_password,
        password_confirmation: form.value.user_password,
        role: 'client',
        client_id: client.id,
      });

      toast.success(`Client onboarded! Login: ${form.value.user_email} / ${form.value.user_password}`);
    }

    closeModal();
    loadClients();
  } catch (error: any) {
    console.error('Failed to save client:', error);
    toast.error(error.response?.data?.message || 'Failed to save client');
  } finally {
    submitting.value = false;
  }
}

onMounted(() => {
  loadClients();
});
</script>
