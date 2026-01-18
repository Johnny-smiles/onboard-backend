<template>
  <div class="space-y-6">
    <section class="page-header space-y-3">
      <p class="eyebrow">Admin overview</p>
      <h2 class="text-2xl font-semibold text-[var(--text)]">Client overview</h2>
      <p class="text-sm text-[var(--text-2)]">
        Welcome back, {{ adminUser?.name || 'Admin' }}. Here is a quick snapshot of the clients you manage.
      </p>
    </section>

    <section v-if="loading" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      <div v-for="index in 3" :key="index" class="card animate-pulse space-y-4">
        <div class="h-6 w-2/3 rounded bg-[var(--surface-3)]" />
        <div class="h-4 w-1/2 rounded bg-[var(--surface-3)]" />
        <div class="flex gap-3">
          <div class="h-3 w-16 rounded bg-[var(--surface-3)]" />
          <div class="h-3 w-14 rounded bg-[var(--surface-3)]" />
        </div>
        <div class="h-20 rounded bg-[var(--surface-3)]" />
      </div>
    </section>

    <div v-else-if="error" class="card border border-danger/40 bg-danger/5 text-danger">
      {{ error }}
    </div>

    <section v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      <article
        v-for="client in clients"
        :key="client.id"
        class="card flex h-full flex-col space-y-4 border border-[var(--border)] transition hover:border-primary/60 hover:shadow-sm cursor-pointer"
        @click="$router.push(`/admin/clients/${client.id}`)"
      >
        <header class="space-y-2">
          <div class="flex items-center justify-between gap-3">
            <h3 class="text-lg font-semibold text-[var(--text)]">{{ client.name }}</h3>
            <span
              class="inline-flex h-3 w-3 rounded-full"
              :style="{ backgroundColor: client.brand_color || '#FF4D5A' }"
            />
          </div>
          <p v-if="client.contact_email" class="text-sm text-[var(--text-2)]">
            <span class="font-medium text-[var(--text)]">Email:</span> {{ client.contact_email }}
          </p>
          <p v-if="client.contact_phone" class="text-sm text-[var(--text-2)]">
            <span class="font-medium text-[var(--text)]">Phone:</span> {{ client.contact_phone }}
          </p>
        </header>

        <dl class="grid grid-cols-2 gap-3 text-sm">
          <div class="stat-tile">
            <dt class="stat-label">Projects</dt>
            <dd class="stat-value">{{ client.projects_count }}</dd>
          </div>
          <div class="stat-tile">
            <dt class="stat-label">Photos</dt>
            <dd class="stat-value">{{ client.photos_count }}</dd>
          </div>
          <div class="stat-tile">
            <dt class="stat-label">Users</dt>
            <dd class="stat-value">{{ client.users_count }}</dd>
          </div>
          <div class="stat-tile">
            <dt class="stat-label">Last upload</dt>
            <dd class="text-sm font-medium text-[var(--text)]">
              {{ formatDate(client.latest_photo_uploaded_at) }}
            </dd>
          </div>
        </dl>

        <div v-if="client.projects?.length" class="space-y-2">
          <h4 class="text-xs font-semibold uppercase tracking-wide text-[var(--text-2)]">
            Active projects
          </h4>
          <ul class="space-y-1 text-sm text-[var(--text-2)]">
            <li
              v-for="project in client.projects"
              :key="project.id"
              class="flex items-center justify-between gap-3 rounded-2xl bg-[var(--surface-2)] px-3 py-2"
            >
              <span class="font-medium text-[var(--text)]">{{ project.name }}</span>
              <span class="text-xs text-[var(--text-3)]">
                {{ formatDate(project.start_date, 'date') }}
                <template v-if="project.end_date">
                  &ndash; {{ formatDate(project.end_date, 'date') }}
                </template>
              </span>
            </li>
          </ul>
        </div>

        <p v-if="client.notes" class="rounded-2xl bg-[var(--surface-2)] p-3 text-sm text-[var(--text-2)]">
          {{ client.notes }}
        </p>

        <footer class="mt-auto text-xs text-[var(--text-3)]">
          Managing admins:
          <span v-if="client.admins?.length" class="font-medium text-[var(--text)]">
            {{ client.admins.map((admin) => admin.name).join(', ') }}
          </span>
          <span v-else class="font-medium text-[var(--text)]">Unassigned</span>
        </footer>
      </article>
    </section>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import api from '../services/api';
import { currentUser as getCurrentUser } from '../services/auth';

type ClientRecord = {
  id: number;
  name: string;
  contact_email?: string | null;
  contact_phone?: string | null;
  brand_color?: string | null;
  notes?: string | null;
  projects_count: number;
  photos_count: number;
  users_count: number;
  latest_photo_uploaded_at?: string | null;
  admins?: Array<{ id: number; name: string; email?: string }>;
  projects?: Array<{
    id: number;
    name: string;
    start_date?: string | null;
    end_date?: string | null;
  }>;
};

const clients = ref<ClientRecord[]>([]);
const loading = ref(true);
const error = ref('');
const adminUser = getCurrentUser();

async function loadClients(): Promise<void> {
  loading.value = true;
  error.value = '';

  try {
    const { data } = await api.get<ClientRecord[]>('/clients');
    clients.value = data;
  } catch (err: any) {
    console.error(err);
    error.value =
      err?.response?.data?.message ||
      'We were unable to load your client list. Please try again shortly.';
  } finally {
    loading.value = false;
  }
}

function formatDate(input?: string | null, mode: 'date' | 'datetime' = 'datetime'): string {
  if (!input) {
    return 'No uploads yet';
  }

  const date = new Date(input);

  if (Number.isNaN(date.getTime())) {
    return 'Unknown';
  }

  if (mode === 'date') {
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(date);
  }

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date);
}

onMounted(loadClients);
</script>
