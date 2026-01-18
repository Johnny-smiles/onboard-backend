<template>
  <div class="space-y-6">
    <section class="page-header space-y-3">
      <p class="eyebrow">Capture playbooks</p>
      <h1 class="text-2xl font-semibold text-[var(--text)]">Shot recipes</h1>
      <p class="text-sm text-[var(--text-2)]">
        Define capture playbooks for clients. Leave client blank to create a global recipe.
      </p>
    </section>

    <section class="section-card">
      <div class="grid gap-3 md:grid-cols-2">
        <div class="space-y-1">
          <label class="text-xs font-semibold uppercase tracking-wide text-[var(--text-2)]" for="client">
            Client
          </label>
          <select id="client" v-model.number="filterClientId" class="input-control" @change="loadRecipes">
            <option :value="0">All clients</option>
            <option v-for="client in clients" :key="client.id" :value="client.id">
              {{ client.name }}
            </option>
          </select>
        </div>
        <div class="flex items-end justify-end gap-3">
          <Button variant="secondary" @click="resetForm">New recipe</Button>
        </div>
      </div>

      <div class="table-shell">
        <table class="min-w-full divide-y divide-[var(--border)] text-left text-sm">
          <thead class="bg-[var(--surface-2)] text-xs uppercase tracking-wide text-[var(--text-2)]">
            <tr>
              <th class="px-4 py-3">Name</th>
              <th class="px-4 py-3">Scope</th>
              <th class="px-4 py-3">Steps</th>
              <th class="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[var(--border)] bg-[var(--surface)]">
            <tr v-if="recipes.length === 0">
              <td class="px-4 py-6 text-center text-sm text-[var(--text-2)]" colspan="4">
                No recipes yet. Create one using the form below.
              </td>
            </tr>
            <tr v-for="recipe in recipes" :key="recipe.id" class="table-row">
              <td class="px-4 py-3 font-medium text-[var(--text)]">
                {{ recipe.name }}
              </td>
              <td class="px-4 py-3 text-sm text-[var(--text-2)]">
                {{ recipe.client ? recipe.client.name : 'Global' }}
              </td>
              <td class="px-4 py-3 text-sm text-[var(--text-2)]">
                {{ recipe.steps?.length || 0 }}
              </td>
              <td class="px-4 py-3 text-right text-sm">
                <div class="flex justify-end gap-2">
                  <Button size="sm" variant="secondary" @click="editRecipe(recipe)">
                    Edit
                  </Button>
                  <Button size="sm" variant="ghost" class="text-danger" @click="confirmDelete(recipe)">
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
          {{ form.id ? 'Edit recipe' : 'Create recipe' }}
        </h2>
        <p class="text-sm text-[var(--text-2)]">
          Define steps as JSON. Each step must include <code class="font-mono">label</code> and
          <code class="font-mono">shot_type</code>; <code class="font-mono">notes</code> and
          <code class="font-mono">job_name</code> are optional.
        </p>
      </header>

      <div class="grid gap-4 md:grid-cols-2">
        <div class="space-y-2">
          <label class="text-xs font-semibold uppercase tracking-wide text-[var(--text-2)]" for="formClient">
            Assigned client
          </label>
          <select id="formClient" v-model="form.client_id" class="input-control">
            <option :value="null">Global</option>
            <option v-for="client in clients" :key="client.id" :value="client.id">
              {{ client.name }}
            </option>
          </select>
        </div>
        <div class="space-y-2">
          <label class="text-xs font-semibold uppercase tracking-wide text-[var(--text-2)]" for="formName">
            Name
          </label>
          <input
            id="formName"
            v-model="form.name"
            class="input-control"
            placeholder="Spring Campaign — Before & After"
            type="text"
          >
        </div>
        <div class="space-y-2 md:col-span-2">
          <label class="text-xs font-semibold uppercase tracking-wide text-[var(--text-2)]" for="formDesc">
            Description
          </label>
          <textarea
            id="formDesc"
            v-model="form.description"
            class="input-control min-h-[80px]"
            placeholder="Optional context for the client."
          />
        </div>
        <div class="space-y-2 md:col-span-2">
          <label class="text-xs font-semibold uppercase tracking-wide text-[var(--text-2)]" for="formSteps">
            Steps JSON
          </label>
          <textarea
            id="formSteps"
            v-model="form.stepsText"
            class="input-control font-mono"
            rows="8"
          />
          <p class="text-xs text-[var(--text-2)]">
            Example:
            <code>[
              { "label": "Before — wide", "shot_type": "before" },
              { "label": "After — detail", "shot_type": "after", "notes": "Capture hardware" }
            ]</code>
          </p>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <Button :disabled="saving" @click="saveRecipe">
          {{ saving ? 'Saving…' : form.id ? 'Update recipe' : 'Create recipe' }}
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
type Recipe = {
  id: number;
  name: string;
  description?: string | null;
  steps: any[];
  client?: Client | null;
};

const clients = ref<Client[]>([]);
const recipes = ref<Recipe[]>([]);
const filterClientId = ref<number>(0);

const form = reactive<{
  id: number | null;
  client_id: number | null;
  name: string;
  description: string;
  stepsText: string;
}>({
  id: null,
  client_id: null,
  name: '',
  description: '',
  stepsText: '[\n  { "label": "Before — wide", "shot_type": "before" }\n]',
});

const saving = ref(false);
const formError = ref('');
const formSuccess = ref('');

function resetForm() {
  form.id = null;
  form.client_id = null;
  form.name = '';
  form.description = '';
  form.stepsText = '[\n  { "label": "Before — wide", "shot_type": "before" }\n]';
  formError.value = '';
  formSuccess.value = '';
}

async function loadClients() {
  const { data } = await api.get('/clients');
  clients.value = data || [];
}

async function loadRecipes() {
  const params = filterClientId.value ? { client_id: filterClientId.value } : {};
  const { data } = await api.get('/shot-recipes', { params });
  recipes.value = data || [];
}

function editRecipe(recipe: Recipe) {
  form.id = recipe.id;
  form.client_id = recipe.client?.id ?? null;
  form.name = recipe.name;
  form.description = recipe.description || '';
  form.stepsText = JSON.stringify(recipe.steps || [], null, 2);
  formError.value = '';
  formSuccess.value = '';
}

async function confirmDelete(recipe: Recipe) {
  if (!window.confirm(`Delete ${recipe.name}?`)) {
    return;
  }

  await api.delete(`/shot-recipes/${recipe.id}`);
  await loadRecipes();
}

async function saveRecipe() {
  formError.value = '';
  formSuccess.value = '';

  let parsedSteps: any;

  try {
    parsedSteps = JSON.parse(form.stepsText);
    if (!Array.isArray(parsedSteps) || parsedSteps.length === 0) {
      throw new Error('Steps must be a non-empty array.');
    }
  } catch (err: any) {
    formError.value = err?.message || 'Steps JSON is invalid.';
    return;
  }

  saving.value = true;

  try {
    const payload = {
      client_id: form.client_id,
      name: form.name,
      description: form.description,
      steps: parsedSteps,
    };

    if (form.id) {
      await api.put(`/shot-recipes/${form.id}`, payload);
      formSuccess.value = 'Recipe updated.';
    } else {
      await api.post('/shot-recipes', payload);
      formSuccess.value = 'Recipe created.';
    }

    await loadRecipes();
  } catch (err: any) {
    formError.value = err?.response?.data?.message || 'Failed to save recipe.';
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  await Promise.all([loadClients(), loadRecipes()]);
});
</script>
