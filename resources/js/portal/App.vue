<template>
  <div class="app-shell min-h-screen text-[var(--text)] transition-colors duration-brand">
    <header
      v-if="isLoggedIn"
      class="glass-panel sticky top-0 z-50 border-b border-[var(--border)]"
    >
      <div class="container mx-auto flex max-w-screen-xl flex-wrap items-center justify-between gap-6 py-4">
        <div class="space-y-2">
          <div class="flex flex-wrap items-center gap-3">
            <p class="eyebrow">On Brand Studio</p>
            <span v-if="isLoggedIn" class="chip">{{ roleLabel }}</span>
          </div>
          <h1 class="brand-title text-2xl font-semibold text-[var(--text)]">Creative Command Center</h1>
          <div class="accent-bar"></div>
          <p class="text-xs font-medium text-[var(--text-2)]">
            Orchestrate uploads, approvals, and brand-ready stories in one playful hub.
          </p>
        </div>
        <nav class="flex flex-wrap items-center gap-2 text-sm font-medium text-[var(--text-2)]">
          <template v-for="link in navLinks" :key="link.href">
            <a class="nav-link" :href="link.href">
              {{ link.label }}
            </a>
          </template>
          <Button class="ml-1" size="sm" variant="ghost" @click="toggleTheme">
            {{ themeToggleLabel }}
          </Button>
          <Button class="ml-1" size="sm" variant="secondary" @click="handleLogout">
            Logout
          </Button>
        </nav>
      </div>
    </header>
    <div v-if="!isLoggedIn" class="container mx-auto flex max-w-screen-xl justify-end pt-6">
      <Button size="sm" variant="ghost" @click="toggleTheme">
        {{ themeToggleLabel }}
      </Button>
    </div>
    <main class="container mx-auto max-w-screen-xl space-y-6 py-8 relative z-10">
      <router-view />
    </main>
    <Toaster rich-colors position="top-right" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Toaster } from 'vue-sonner';
import Button from './ui/Button.vue';
import { isAdmin, logout } from './services/auth';

const router = useRouter();
const route = useRoute();
const isLoggedIn = ref(false);
const isAdminUser = ref(false);
const isDark = ref(false);
const navLinks = computed(() => {
  if (!isLoggedIn.value) {
    return [];
  }

  if (isAdminUser.value) {
    return [
      { label: 'Dashboard', href: '/portal/admin/dashboard' },
      { label: 'Clients', href: '/portal/admin/clients' },
      { label: 'Review', href: '/portal/admin/review' },
      { label: 'Shot Recipes', href: '/portal/admin/shot-recipes' },
      { label: 'Capture Reminders', href: '/portal/admin/capture-reminders' },
    ];
  }

  return [
    { label: 'Capture', href: '/portal/client/capture' },
    { label: 'Upload', href: '/portal/client/upload' },
    { label: 'Library', href: '/portal/client/library' },
  ];
});

const roleLabel = computed(() => (isAdminUser.value ? 'Admin view' : 'Client view'));
const themeToggleLabel = computed(() => (isDark.value ? 'Light mode' : 'Dark mode'));

function checkAuth() {
  isLoggedIn.value = !!localStorage.getItem('token');
  isAdminUser.value = isLoggedIn.value && isAdmin();
}

function applyTheme(): void {
  document.documentElement.classList.toggle('dark', isDark.value);
  localStorage.setItem('theme', isDark.value ? 'dark' : 'light');
}

function toggleTheme(): void {
  isDark.value = !isDark.value;
  applyTheme();
}

function handleLogout() {
  logout();
  isLoggedIn.value = false;
  isAdminUser.value = false;
  router.push('/login');
}

onMounted(() => {
  checkAuth();
  const storedTheme = localStorage.getItem('theme');
  if (storedTheme) {
    isDark.value = storedTheme === 'dark';
  } else if (window.matchMedia) {
    isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches;
  }
  applyTheme();
});
watch(
  () => route.path,
  () => checkAuth()
);
</script>
