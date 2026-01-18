<template>
  <div class="space-y-6">
    <section class="page-header space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="space-y-2">
          <p class="eyebrow">Capture agenda</p>
          <h2 class="text-2xl font-semibold text-[var(--text)]">Plan the next shoot</h2>
          <p class="text-sm text-[var(--text-2)]">
            See what we still need, then upload with context so the team can move fast.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <span class="badge">Shot recipes</span>
          <span class="badge">Auto tagging</span>
          <span class="badge">On schedule</span>
        </div>
      </div>
    </section>

    <section class="section-card">
      <header class="space-y-2">
        <h2 class="section-title">What we still need from you</h2>
        <p class="text-sm text-[var(--text-2)]">
          These recipe steps are outstanding. Capture these shots to keep us on schedule.
        </p>
      </header>
      <div
        v-if="loading"
        class="rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] px-4 py-3 text-sm text-[var(--text-2)]"
      >
        Loading capture guidance…
      </div>
      <div
        v-else-if="needs.length === 0"
        class="rounded-2xl border border-success/40 bg-success/5 px-4 py-3 text-sm text-success"
      >
        You're caught up! All recipe steps have at least one matching upload.
      </div>
      <div v-else class="space-y-4">
        <article
          v-for="item in needs"
          :key="item.recipe_id"
          class="rounded-2xl border border-warning/30 bg-warning/5 px-4 py-3"
        >
          <h3 class="text-sm font-semibold text-warning/90">
            {{ item.recipe_name }}
          </h3>
          <ul class="mt-2 space-y-1 text-sm text-warning/90">
            <li
              v-for="step in item.missing_steps"
              :key="step.label"
              class="flex items-start gap-2"
            >
              <span class="mt-1 h-1.5 w-1.5 min-w-[6px] rounded-full bg-warning" />
              <div>
                <p class="font-medium text-[var(--text)]">{{ step.label }}</p>
                <p class="text-xs text-[var(--text-2)]">
                  Shot type: <span class="font-mono text-warning/90">{{ step.shot_type }}</span>
                  <span v-if="step.job_name"> · Job: {{ step.job_name }}</span>
                </p>
                <p v-if="step.notes" class="text-xs text-[var(--text-2)]">
                  {{ step.notes }}
                </p>
              </div>
            </li>
          </ul>
        </article>
      </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
      <section class="section-card">
        <header class="space-y-2">
          <h2 class="section-title">Shot recipes</h2>
          <p class="text-sm text-[var(--text-2)]">
            Use these recipes for guidance. Select a recipe to preload its steps when uploading.
          </p>
        </header>

        <div class="space-y-4">
          <details
            v-for="recipe in recipes"
            :key="recipe.id"
            :open="recipe.id === selectedRecipeId"
            class="rounded-2xl border border-[var(--border)] bg-[var(--surface-2)]"
          >
            <summary
              class="flex cursor-pointer items-center justify-between gap-4 px-4 py-3 text-sm font-medium"
              @click.prevent="toggleRecipe(recipe.id)"
            >
              <span class="text-[var(--text)]">{{ recipe.name }}</span>
              <span class="text-xs text-[var(--text-2)]">
                {{ recipe.steps?.length || 0 }} steps
              </span>
            </summary>
            <div class="space-y-3 border-t border-[var(--border)] px-4 py-3 text-sm text-[var(--text-2)]">
              <p v-if="recipe.description">{{ recipe.description }}</p>
              <ol class="space-y-2">
                <li
                  v-for="step in recipe.steps"
                  :key="step.label"
                  class="rounded-2xl border border-[var(--border)] bg-[var(--surface)] px-3 py-2"
                >
                  <div class="flex items-center justify-between text-sm font-medium text-[var(--text)]">
                    <span>{{ step.label }}</span>
                    <span class="font-mono text-xs uppercase tracking-wide text-primary">
                      {{ step.shot_type }}
                    </span>
                  </div>
                  <p v-if="step.notes" class="mt-1 text-xs text-[var(--text-2)]">{{ step.notes }}</p>
                  <p v-if="step.job_name" class="mt-1 text-xs text-[var(--text-2)] font-medium">
                    Suggested job: {{ step.job_name }}
                  </p>
                </li>
              </ol>
            </div>
          </details>
        </div>
      </section>

      <aside class="space-y-4">
        <section class="section-card">
          <header class="space-y-2">
            <div class="flex items-center justify-between gap-2">
              <h2 class="section-title">Upload with context</h2>
              <span class="chip">Auto tag</span>
            </div>
            <p class="text-sm text-[var(--text-2)]">
              Pick a recipe step, then upload images. We'll tag them automatically.
            </p>
          </header>

          <div class="space-y-3">
            <div class="space-y-1">
              <label class="text-xs font-semibold uppercase tracking-wide text-[var(--text-2)]" for="recipe">
                Recipe
              </label>
              <select
                id="recipe"
                v-model.number="selectedRecipeId"
                class="input-control"
              >
                <option :value="0">Select a recipe…</option>
                <option
                  v-for="recipe in recipes"
                  :key="recipe.id"
                  :value="recipe.id"
                >
                  {{ recipe.name }}
                </option>
              </select>
            </div>

            <div class="space-y-1">
              <label class="text-xs font-semibold uppercase tracking-wide text-[var(--text-2)]" for="step">
                Step
              </label>
              <select
                id="step"
                v-model.number="selectedStepIndex"
                class="input-control"
                :disabled="!currentSteps.length"
                @change="applyStepDefaults"
              >
                <option :value="-1">Select a step…</option>
                <option
                  v-for="(step, idx) in currentSteps"
                  :key="`${step.label}-${idx}`"
                  :value="idx"
                >
                  {{ step.label }} — {{ step.shot_type }}
                </option>
              </select>
            </div>

            <div class="space-y-1">
              <label class="text-xs font-semibold uppercase tracking-wide text-[var(--text-2)]" for="job">
                Job / Campaign
              </label>
              <input
                id="job"
                v-model="form.jobName"
                class="input-control"
                placeholder="Kitchen remodel — March"
                type="text"
              >
            </div>

            <div class="space-y-1">
              <label class="text-xs font-semibold uppercase tracking-wide text-[var(--text-2)]" for="location">
                Location
              </label>
              <input
                id="location"
                v-model="form.location"
                class="input-control"
                placeholder="123 Market St, Springfield"
                type="text"
              >
            </div>

            <div class="space-y-1">
              <label class="text-xs font-semibold uppercase tracking-wide text-[var(--text-2)]" for="shotType">
                Shot type
              </label>
              <input
                id="shotType"
                v-model="form.shotType"
                class="input-control font-mono uppercase"
                placeholder="before"
                type="text"
              >
            </div>

            <div class="space-y-1">
              <label class="text-xs font-semibold uppercase tracking-wide text-[var(--text-2)]" for="notes">
                Notes for AMs
              </label>
              <textarea
                id="notes"
                v-model="form.notes"
                class="input-control min-h-[72px]"
                placeholder="Anything the team should know about this shot."
              />
            </div>
          </div>

          <p v-if="error" class="panel border border-danger/40 bg-danger/5 text-sm text-danger">
            {{ error }}
          </p>
          <p v-if="success" class="panel border border-success/40 bg-success/5 text-sm text-success">
            Upload complete! We saved your metadata.
          </p>
        </section>

        <Uploader @upload="handleUpload" />
      </aside>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import Uploader from '../components/Uploader.vue';
import api from '../services/api';
import { currentUser } from '../services/auth';

type RecipeStep = {
  label: string;
  shot_type: string;
  notes?: string;
  job_name?: string;
};

type Recipe = {
  id: number;
  name: string;
  description?: string | null;
  steps: RecipeStep[];
};

type NeedItem = {
  recipe_id: number;
  recipe_name: string;
  missing_steps: RecipeStep[];
};

const recipes = ref<Recipe[]>([]);
const needs = ref<NeedItem[]>([]);
const selectedRecipeId = ref<number>(0);
const selectedStepIndex = ref<number>(-1);
const loading = ref(false);
const error = ref('');
const success = ref(false);

const form = ref({
  jobName: '',
  location: '',
  shotType: '',
  notes: '',
});

const clientId = computed(() => currentUser()?.client_id || null);

const currentRecipe = computed<Recipe | null>(() => {
  return recipes.value.find((recipe) => recipe.id === selectedRecipeId.value) || null;
});

const currentSteps = computed<RecipeStep[]>(() => currentRecipe.value?.steps || []);

function toggleRecipe(id: number) {
  selectedRecipeId.value = selectedRecipeId.value === id ? 0 : id;
}

function applyStepDefaults() {
  success.value = false;
  error.value = '';

  const step = currentSteps.value[selectedStepIndex.value];

  if (!step) {
    form.value.shotType = '';
    form.value.notes = '';
    form.value.jobName = '';
    return;
  }

  form.value.shotType = step.shot_type || '';
  form.value.notes = step.notes || '';
  form.value.jobName = step.job_name || '';
}

async function loadData() {
  if (!clientId.value) {
    error.value = 'Unable to determine your client record.';
    return;
  }

  loading.value = true;
  error.value = '';

  try {
    const [recipesRes, needsRes] = await Promise.all([
      api.get('/capture/recipes'),
      api.get('/capture/needs'),
    ]);

    recipes.value = recipesRes.data || [];
    needs.value = needsRes.data || [];
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to load capture data.';
  } finally {
    loading.value = false;
  }
}

async function handleUpload(items: { blob: Blob; name: string }[]) {
  error.value = '';
  success.value = false;

  if (!clientId.value) {
    error.value = 'Missing client context — please log in again.';
    return;
  }

  if (!form.value.shotType) {
    error.value = 'Select a recipe step so we know what kind of shot this is.';
    return;
  }

  try {
    for (const item of items) {
      const formData = new FormData();
      formData.append('file', item.blob, item.name);
      formData.append('client_id', String(clientId.value));
      formData.append('shot_type', form.value.shotType);
      if (form.value.jobName) formData.append('job_name', form.value.jobName);
      if (form.value.location) formData.append('location', form.value.location);
      if (form.value.notes) formData.append('notes', form.value.notes);
      if (currentRecipe.value?.name) formData.append('caption', currentRecipe.value.name);

      await api.post('/photos', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
    }

    success.value = true;
    await loadData();
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Upload failed. Please try again.';
  }
}

onMounted(loadData);
watch(selectedRecipeId, () => {
  selectedStepIndex.value = -1;
  applyStepDefaults();
});
watch(selectedStepIndex, () => applyStepDefaults());
</script>
