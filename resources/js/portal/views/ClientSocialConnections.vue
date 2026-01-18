<template>
  <div class="space-y-6">
    <section class="page-header flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div class="space-y-2">
        <p class="eyebrow">Social studio</p>
        <h2 class="text-xl font-semibold text-[var(--text)]">
          {{ clientName ? `${clientName} social connections` : 'Client social connections' }}
        </h2>
        <p class="text-sm text-[var(--text-2)]">
          Manage OAuth connections for Meta, Google, and WordPress. Use the redirect stub while the real
          Socialite flow is being finalized.
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <Button variant="ghost" type="button" @click="goBack">
          Back to admin dashboard
        </Button>
        <span v-if="clientId" class="chip">
          Client ID: {{ clientId }}
        </span>
      </div>
    </section>

    <section v-if="loading" class="grid gap-4 md:grid-cols-2">
      <div v-for="index in 3" :key="index" class="card h-56 animate-pulse space-y-3">
        <div class="h-6 w-2/3 rounded bg-[var(--surface-3)]" />
        <div class="h-4 w-3/4 rounded bg-[var(--surface-3)]" />
        <div class="h-4 w-1/2 rounded bg-[var(--surface-3)]" />
        <div class="mt-auto h-9 w-full rounded bg-[var(--surface-3)]" />
      </div>
    </section>

    <section v-else-if="error" class="card border border-danger/40 bg-danger/5 text-danger">
      {{ error }}
    </section>

    <section v-else class="space-y-6">
      <p class="text-sm text-[var(--text-2)]">
        Connected providers appear with their account details below. Disconnecting removes stored tokens.
        Use the redirect URL to preview the upcoming OAuth handshake.
      </p>

      <div class="grid gap-4 md:grid-cols-2">
        <article
          v-for="provider in providers"
          :key="provider.key"
          class="card flex h-full flex-col space-y-4 border border-[var(--border)] transition hover:border-primary/40"
        >
          <header class="space-y-2">
            <div class="flex items-center justify-between gap-2">
              <h3 class="text-lg font-semibold text-[var(--text)]">{{ provider.label }}</h3>
              <span
                class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold"
                :class="integrationMap[provider.key] ? 'bg-green-500/10 text-green-600' : 'bg-[var(--surface-2)] text-[var(--text-3)]'"
              >
                {{ integrationMap[provider.key] ? 'Connected' : 'Not connected' }}
              </span>
            </div>
            <p class="text-sm text-[var(--text-2)]">
              {{ provider.description }}
            </p>
          </header>

          <div v-if="integrationMap[provider.key]" class="space-y-3 rounded-2xl bg-[var(--surface-2)] p-4 text-sm text-[var(--text-2)]">
            <div class="flex items-center justify-between gap-3">
              <span class="font-medium text-[var(--text)]">Account</span>
              <span>{{ integrationMap[provider.key]?.account_name || 'Not provided' }}</span>
            </div>
            <div class="flex items-center justify-between gap-3">
              <span class="font-medium text-[var(--text)]">Status</span>
              <span>{{ integrationMap[provider.key]?.status || 'active' }}</span>
            </div>
            <div class="flex items-center justify-between gap-3">
              <span class="font-medium text-[var(--text)]">Connected at</span>
              <span>{{ formatDate(integrationMap[provider.key]?.connected_at) }}</span>
            </div>
            <div class="flex items-center justify-between gap-3">
              <span class="font-medium text-[var(--text)]">Expires at</span>
              <span>{{ formatDate(integrationMap[provider.key]?.expires_at, 'datetime', 'No expiration') }}</span>
            </div>
            <div v-if="integrationMap[provider.key]?.scopes?.length" class="space-y-1">
              <span class="font-medium text-[var(--text)]">Scopes</span>
              <ul class="list-disc space-y-1 pl-5">
                <li v-for="scope in integrationMap[provider.key]?.scopes" :key="scope">{{ scope }}</li>
              </ul>
            </div>
            <div v-if="hasExternalIds(provider.key)" class="space-y-1">
              <span class="font-medium text-[var(--text)]">External IDs</span>
              <pre class="whitespace-pre-wrap rounded-2xl bg-[var(--surface-3)] p-3 text-xs text-[var(--text-3)]">
{{ formatExternalIds(integrationMap[provider.key]?.external_ids) }}</pre>
            </div>
            <Button
              class="w-full"
              variant="danger"
              type="button"
              :disabled="isDeleting(provider.key)"
              @click="disconnect(provider.key)"
            >
              {{ isDeleting(provider.key) ? 'Removing...' : 'Disconnect provider' }}
            </Button>
          </div>

          <div class="space-y-3 rounded-2xl bg-[var(--surface-2)] p-4 text-sm text-[var(--text-2)]">
            <label class="block text-xs font-semibold uppercase tracking-wide text-[var(--text-3)]" :for="`account-${provider.key}`">
              Account label (optional)
            </label>
            <input
              class="input-control"
              :id="`account-${provider.key}`"
              type="text"
              v-model="accountNames[provider.key]"
              placeholder="e.g. Main Brand Page"
            />
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
              <Button
                type="button"
                :disabled="isSaving(provider.key)"
                @click="connect(provider.key)"
              >
                {{ isSaving(provider.key) ? 'Saving...' : integrationMap[provider.key] ? 'Update details' : 'Save connection' }}
              </Button>
              <Button
                variant="ghost"
                type="button"
                :disabled="isRedirecting(provider.key)"
                @click="loadRedirect(provider.key)"
              >
                {{ isRedirecting(provider.key) ? 'Loading redirect...' : 'Get redirect URL' }}
              </Button>
            </div>
            <p v-if="redirectUrls[provider.key]" class="rounded-2xl bg-[var(--surface-3)] p-3 text-xs text-[var(--text-3)]">
              Redirect preview: <span class="font-mono">{{ redirectUrls[provider.key] }}</span>
            </p>
            <p v-if="providerErrors[provider.key]" class="text-sm text-danger">
              {{ providerErrors[provider.key] }}
            </p>
          </div>
        </article>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../services/api';
import Button from '../ui/Button.vue';

type ClientRecord = {
  id: number;
  name: string;
};

type SocialIntegration = {
  id: number;
  client_id: number;
  provider: string;
  account_name?: string | null;
  external_ids?: Record<string, unknown> | Array<unknown> | null;
  scopes?: string[] | null;
  status?: string | null;
  connected_at?: string | null;
  expires_at?: string | null;
};

type ProviderConfig = {
  key: ProviderKey;
  label: string;
  description: string;
};

type ProviderKey = 'meta' | 'google' | 'wordpress';

const providers: ProviderConfig[] = [
  {
    key: 'meta',
    label: 'Meta (Facebook & Instagram)',
    description: 'Connect Meta Business Suite for Facebook pages and Instagram business accounts.',
  },
  {
    key: 'google',
    label: 'Google Business Profile',
    description: 'Manage Google Business Profile locations for publishing updates and responding to reviews.',
  },
  {
    key: 'wordpress',
    label: 'WordPress',
    description: 'Publish articles directly to the connected WordPress sites using stored credentials.',
  },
];

const router = useRouter();
const route = useRoute();

const clientIdParam = route.params.id;
const clientId = typeof clientIdParam === 'string' ? Number.parseInt(clientIdParam, 10) : Number(clientIdParam);

const clientName = ref('');
const integrations = ref<SocialIntegration[]>([]);
const loading = ref(true);
const error = ref('');

const accountNames = reactive<Record<ProviderKey, string>>({
  meta: '',
  google: '',
  wordpress: '',
});

const providerErrors = reactive<Record<ProviderKey, string>>({
  meta: '',
  google: '',
  wordpress: '',
});

const redirectUrls = reactive<Record<ProviderKey, string>>({
  meta: '',
  google: '',
  wordpress: '',
});

const savingState = reactive<Record<ProviderKey, boolean>>({
  meta: false,
  google: false,
  wordpress: false,
});

const deletingState = reactive<Record<ProviderKey, boolean>>({
  meta: false,
  google: false,
  wordpress: false,
});

const redirectState = reactive<Record<ProviderKey, boolean>>({
  meta: false,
  google: false,
  wordpress: false,
});

const integrationMap = computed<Record<ProviderKey, SocialIntegration | undefined>>(() => {
  return integrations.value.reduce((acc, integration) => {
    if (['meta', 'google', 'wordpress'].includes(integration.provider)) {
      acc[integration.provider as ProviderKey] = integration;
    }
    return acc;
  }, {} as Record<ProviderKey, SocialIntegration | undefined>);
});

initialize();

async function initialize(): Promise<void> {
  if (Number.isNaN(clientId)) {
    error.value = 'The client id in the URL is not valid.';
    loading.value = false;
    return;
  }

  loading.value = true;
  error.value = '';

  try {
    const [clientResponse, integrationsResponse] = await Promise.all([
      api.get<ClientRecord>(`/clients/${clientId}`),
      api.get<SocialIntegration[]>(`/clients/${clientId}/integrations`),
    ]);

    clientName.value = clientResponse.data.name;
    integrations.value = integrationsResponse.data;
    syncAccountNames();
  } catch (err: any) {
    console.error(err);
    error.value =
      err?.response?.data?.message || 'Unable to load client integrations. Please try again shortly.';
  } finally {
    loading.value = false;
  }
}

async function fetchIntegrations(): Promise<void> {
  try {
    const { data } = await api.get<SocialIntegration[]>(`/clients/${clientId}/integrations`);
    integrations.value = data;
    syncAccountNames();
  } catch (err: any) {
    console.error(err);
    error.value =
      err?.response?.data?.message || 'Unable to refresh integrations for this client.';
  }
}

function syncAccountNames(): void {
  providers.forEach((provider) => {
    const integration = integrations.value.find((item) => item.provider === provider.key);
    accountNames[provider.key] = integration?.account_name || '';
  });
}

async function connect(provider: ProviderKey): Promise<void> {
  providerErrors[provider] = '';
  savingState[provider] = true;

  try {
    const payload: Record<string, unknown> = { provider };
    const accountName = accountNames[provider].trim();

    if (accountName) {
      payload.account_name = accountName;
    }

    await api.post(`/clients/${clientId}/integrations`, payload);
    await fetchIntegrations();
  } catch (err: any) {
    console.error(err);
    providerErrors[provider] =
      err?.response?.data?.message ||
      'We could not save the integration details. Please try again.';
  } finally {
    savingState[provider] = false;
  }
}

async function disconnect(provider: ProviderKey): Promise<void> {
  const integration = integrationMap.value[provider];

  if (!integration) {
    return;
  }

  providerErrors[provider] = '';
  deletingState[provider] = true;

  try {
    await api.delete(`/clients/${clientId}/integrations/${integration.id}`);
    await fetchIntegrations();
  } catch (err: any) {
    console.error(err);
    providerErrors[provider] =
      err?.response?.data?.message ||
      'We were unable to disconnect this provider. Please try again.';
  } finally {
    deletingState[provider] = false;
  }
}

async function loadRedirect(provider: ProviderKey): Promise<void> {
  providerErrors[provider] = '';
  redirectState[provider] = true;

  try {
    const { data } = await api.get<{ url: string }>(
      `/clients/${clientId}/integrations/${provider}/redirect`
    );
    redirectUrls[provider] = data.url;
  } catch (err: any) {
    console.error(err);
    providerErrors[provider] =
      err?.response?.data?.message ||
      'Unable to generate a redirect URL for this provider right now.';
  } finally {
    redirectState[provider] = false;
  }
}

function goBack(): void {
  router.push('/admin/dashboard');
}

function isSaving(provider: ProviderKey): boolean {
  return savingState[provider];
}

function isDeleting(provider: ProviderKey): boolean {
  return deletingState[provider];
}

function isRedirecting(provider: ProviderKey): boolean {
  return redirectState[provider];
}

function hasExternalIds(provider: ProviderKey): boolean {
  const value = integrationMap.value[provider]?.external_ids;
  if (!value) {
    return false;
  }

  if (Array.isArray(value)) {
    return value.length > 0;
  }

  return Object.keys(value).length > 0;
}

function formatExternalIds(value: unknown): string {
  if (!value) {
    return '';
  }

  if (typeof value === 'string') {
    return value;
  }

  try {
    return JSON.stringify(value, null, 2);
  } catch (err) {
    console.error(err);
    return String(value);
  }
}

function formatDate(
  input?: string | null,
  mode: 'date' | 'datetime' = 'date',
  fallback = 'Not captured'
): string {
  if (!input) {
    return fallback;
  }

  const parsed = new Date(input);

  if (Number.isNaN(parsed.getTime())) {
    return fallback;
  }

  const options: Intl.DateTimeFormatOptions =
    mode === 'date'
      ? { dateStyle: 'medium' }
      : { dateStyle: 'medium', timeStyle: 'short' };

  return new Intl.DateTimeFormat(undefined, options).format(parsed);
}
</script>
