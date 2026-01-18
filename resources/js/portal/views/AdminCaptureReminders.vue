<template>
  <div class="space-y-6">
    <section class="page-header space-y-3">
      <p class="eyebrow">Nudge engine</p>
      <h1 class="text-2xl font-semibold text-[var(--text)]">Capture reminders</h1>
      <p class="text-sm text-[var(--text-2)]">
        Schedule nudges that keep clients sending fresh content. Reminders are sent via email or SMS.
      </p>
    </section>

    <section class="section-card">
      <div class="grid gap-3 md:grid-cols-[2fr,1fr,auto]">
        <div class="space-y-1">
          <label class="text-xs font-semibold uppercase tracking-wide text-[var(--text-2)]" for="remClient">
            Client
          </label>
          <select id="remClient" v-model.number="filterClientId" class="input-control" @change="loadReminders">
            <option :value="0">All clients</option>
            <option v-for="client in clients" :key="client.id" :value="client.id">
              {{ client.name }}
            </option>
          </select>
        </div>
        <div class="space-y-1">
          <label class="text-xs font-semibold uppercase tracking-wide text-[var(--text-2)]" for="remStatus">
            Status
          </label>
          <select id="remStatus" v-model="filterStatus" class="input-control" @change="loadReminders">
            <option value="all">All reminders</option>
            <option value="active">Active only</option>
            <option value="paused">Paused</option>
          </select>
        </div>
        <div class="flex items-end justify-end gap-2">
          <Button variant="secondary" @click="resetForm">New reminder</Button>
          <Button variant="ghost" @click="runDue">Run due now</Button>
        </div>
      </div>

      <div class="table-shell">
        <table class="min-w-full divide-y divide-[var(--border)] text-left text-sm">
          <thead class="bg-[var(--surface-2)] text-xs uppercase tracking-wide text-[var(--text-2)]">
            <tr>
              <th class="px-4 py-3">Title</th>
              <th class="px-4 py-3">Client</th>
              <th class="px-4 py-3">Channel</th>
              <th class="px-4 py-3">Next send</th>
              <th class="px-4 py-3">Repeat</th>
              <th class="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[var(--border)] bg-[var(--surface)]">
            <tr v-if="reminders.length === 0">
              <td colspan="6" class="px-4 py-6 text-center text-sm text-[var(--text-2)]">
                No reminders configured. Create one below.
              </td>
            </tr>
            <tr v-for="reminder in reminders" :key="reminder.id" class="table-row">
              <td class="px-4 py-3 font-medium text-[var(--text)]">
                {{ reminder.title }}
              </td>
              <td class="px-4 py-3 text-sm text-[var(--text-2)]">
                {{ reminder.client?.name || 'Unknown' }}
              </td>
              <td class="px-4 py-3 text-sm text-[var(--text-2)] uppercase">
                {{ reminder.channel }}
              </td>
              <td class="px-4 py-3 text-sm text-[var(--text-2)]">
                {{ formatDisplayDate(reminder.send_at) }}
              </td>
              <td class="px-4 py-3 text-sm text-[var(--text-2)] uppercase">
                {{ reminder.repeat_interval || 'once' }}
              </td>
              <td class="px-4 py-3 text-right text-sm">
                <div class="flex justify-end gap-2">
                  <Button size="sm" variant="secondary" @click="editReminder(reminder)">
                    Edit
                  </Button>
                  <Button size="sm" variant="ghost" class="text-danger" @click="confirmDelete(reminder)">
                    Delete
                  </Button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="section-card">
      <header class="space-y-2">
        <h2 class="section-title">
          {{ form.id ? 'Edit reminder' : 'Create reminder' }}
        </h2>
        <p class="text-sm text-[var(--text-2)]">
          Reminders queue a background job (stubbed for now). We'll wire Twilio/Mailgun later.
        </p>
      </header>

      <div class="grid gap-4 md:grid-cols-2">
        <div class="space-y-1">
          <label class="text-xs font-semibold uppercase tracking-wide text-[var(--text-2)]" for="formClient">
            Client
          </label>
          <select id="formClient" v-model="form.client_id" class="input-control">
            <option disabled value="">Select a client…</option>
            <option v-for="client in clients" :key="client.id" :value="client.id">
              {{ client.name }}
            </option>
          </select>
        </div>

        <div class="space-y-1">
          <label class="text-xs font-semibold uppercase tracking-wide text-[var(--text-2)]" for="formRecipe">
            Optional recipe
          </label>
          <select id="formRecipe" v-model="form.shot_recipe_id" class="input-control">
            <option :value="null">None</option>
            <option v-for="recipe in recipeOptions" :key="recipe.id" :value="recipe.id">
              {{ recipe.name }}
            </option>
          </select>
        </div>

        <div class="space-y-1 md:col-span-2">
          <label class="text-xs font-semibold uppercase tracking-wide text-[var(--text-2)]" for="formTitle">
            Title
          </label>
          <input
            id="formTitle"
            v-model="form.title"
            class="input-control"
            placeholder="Weekly shot check-in"
            type="text"
          >
        </div>

        <div class="space-y-1 md:col-span-2">
          <label class="text-xs font-semibold uppercase tracking-wide text-[var(--text-2)]" for="formMessage">
            Message
          </label>
          <textarea
            id="formMessage"
            v-model="form.message"
            class="input-control min-h-[80px]"
            placeholder="Friendly reminder text."
          />
        </div>

        <div class="space-y-1">
          <label class="text-xs font-semibold uppercase tracking-wide text-[var(--text-2)]" for="formChannel">
            Channel
          </label>
          <select id="formChannel" v-model="form.channel" class="input-control">
            <option value="email">Email</option>
            <option value="sms">SMS</option>
          </select>
        </div>

        <div class="space-y-1">
          <label class="text-xs font-semibold uppercase tracking-wide text-[var(--text-2)]" for="formTarget">
            Target (optional)
          </label>
          <input
            id="formTarget"
            v-model="form.target"
            class="input-control"
            placeholder="contact@example.com"
            type="text"
          >
        </div>

        <div class="space-y-1">
          <label class="text-xs font-semibold uppercase tracking-wide text-[var(--text-2)]" for="formSendAt">
            Send at
          </label>
          <input
            id="formSendAt"
            v-model="form.send_at"
            class="input-control"
            type="datetime-local"
          >
        </div>

        <div class="space-y-1">
          <label class="text-xs font-semibold uppercase tracking-wide text-[var(--text-2)]" for="formRepeat">
            Repeat
          </label>
          <select id="formRepeat" v-model="form.repeat_interval" class="input-control">
            <option value="">Once</option>
            <option value="daily">Daily</option>
            <option value="weekly">Weekly</option>
            <option value="monthly">Monthly</option>
          </select>
        </div>

        <div class="flex items-center gap-2 md:col-span-2">
          <input id="formActive" v-model="form.is_active" type="checkbox" class="h-4 w-4 accent-primary">
          <label class="text-sm text-[var(--text-2)]" for="formActive">Active</label>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <Button :disabled="saving" @click="saveReminder">
          {{ saving ? 'Saving…' : form.id ? 'Update reminder' : 'Create reminder' }}
        </Button>
        <Button variant="secondary" @click="resetForm">Cancel</Button>
      </div>

      <p v-if="formError" class="rounded border border-danger/40 bg-danger/5 px-3 py-2 text-sm text-danger">
        {{ formError }}
      </p>
      <p v-if="formSuccess" class="rounded border border-success/40 bg-success/5 px-3 py-2 text-sm text-success">
        {{ formSuccess }}
      </p>
    </section>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import Button from '../ui/Button.vue';
import api from '../services/api';

type Client = { id: number; name: string };
type Reminder = {
  id: number;
  client_id: number;
  client?: Client;
  shot_recipe_id?: number | null;
  shotRecipe?: { id: number; name: string } | null;
  title: string;
  message?: string | null;
  channel: string;
  target?: string | null;
  send_at: string;
  repeat_interval?: string | null;
  is_active: boolean;
};

type RecipeOption = { id: number; name: string };

const clients = ref<Client[]>([]);
const reminders = ref<Reminder[]>([]);
const recipeOptions = ref<RecipeOption[]>([]);

const filterClientId = ref<number>(0);
const filterStatus = ref<'all' | 'active' | 'paused'>('all');

const form = reactive<{
  id: number | null;
  client_id: number | '';
  shot_recipe_id: number | null;
  title: string;
  message: string;
  channel: 'email' | 'sms';
  target: string;
  send_at: string;
  repeat_interval: '' | 'daily' | 'weekly' | 'monthly';
  is_active: boolean;
}>({
  id: null,
  client_id: '',
  shot_recipe_id: null,
  title: '',
  message: '',
  channel: 'email',
  target: '',
  send_at: formatForInput(new Date().toISOString()),
  repeat_interval: '',
  is_active: true,
});

const saving = ref(false);
const formError = ref('');
const formSuccess = ref('');

function resetForm() {
  form.id = null;
  form.client_id = '';
  form.shot_recipe_id = null;
  form.title = '';
  form.message = '';
  form.channel = 'email';
  form.target = '';
  form.send_at = formatForInput(new Date().toISOString());
  form.repeat_interval = '';
  form.is_active = true;
  formError.value = '';
  formSuccess.value = '';
}

async function loadClients() {
  const { data } = await api.get('/clients');
  clients.value = data || [];
}

async function loadRecipes() {
  const { data } = await api.get('/shot-recipes');
  recipeOptions.value = (data || []).map((recipe: any) => ({
    id: recipe.id,
    name: recipe.name,
  }));
}

async function loadReminders() {
  const params: Record<string, any> = {};
  if (filterClientId.value) params.client_id = filterClientId.value;

  const { data } = await api.get('/capture-reminders', { params });
  let items: Reminder[] = data || [];

  if (filterStatus.value === 'active') {
    items = items.filter((item) => item.is_active);
  }
  if (filterStatus.value === 'paused') {
    items = items.filter((item) => !item.is_active);
  }

  reminders.value = items;
}

async function runDue() {
  await api.post('/capture-reminders/run-due');
  await loadReminders();
}

function editReminder(reminder: Reminder) {
  form.id = reminder.id;
  form.client_id = reminder.client_id;
  form.shot_recipe_id = reminder.shot_recipe_id ?? null;
  form.title = reminder.title;
  form.message = reminder.message || '';
  form.channel = (reminder.channel as 'email' | 'sms') || 'email';
  form.target = reminder.target || '';
  form.send_at = formatForInput(reminder.send_at);
  form.repeat_interval = (reminder.repeat_interval as any) || '';
  form.is_active = !!reminder.is_active;
  formError.value = '';
  formSuccess.value = '';
}

async function confirmDelete(reminder: Reminder) {
  if (!window.confirm(`Delete reminder "${reminder.title}"?`)) {
    return;
  }

  await api.delete(`/capture-reminders/${reminder.id}`);
  await loadReminders();
}

async function saveReminder() {
  formError.value = '';
  formSuccess.value = '';

  if (!form.client_id) {
    formError.value = 'Choose a client.';
    return;
  }

  if (!form.send_at) {
    formError.value = 'Choose a send time.';
    return;
  }

  saving.value = true;

  try {
    const payload = {
      client_id: form.client_id,
      shot_recipe_id: form.shot_recipe_id,
      title: form.title,
      message: form.message,
      channel: form.channel,
      target: form.target || null,
      send_at: new Date(form.send_at).toISOString(),
      repeat_interval: form.repeat_interval || null,
      is_active: form.is_active,
    };

    if (form.id) {
      await api.put(`/capture-reminders/${form.id}`, payload);
      formSuccess.value = 'Reminder updated.';
    } else {
      await api.post('/capture-reminders', payload);
      formSuccess.value = 'Reminder created.';
    }

    await loadReminders();
  } catch (err: any) {
    formError.value = err?.response?.data?.message || 'Failed to save reminder.';
  } finally {
    saving.value = false;
  }
}

function formatDisplayDate(date: string): string {
  if (!date) return '—';
  try {
    return new Date(date).toLocaleString();
  } catch {
    return date;
  }
}

function formatForInput(date: string): string {
  const d = new Date(date);
  const pad = (num: number) => num.toString().padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

onMounted(async () => {
  await Promise.all([loadClients(), loadRecipes(), loadReminders()]);
});
</script>
