<template>
  <div class="min-h-screen bg-[var(--surface-2)] text-[var(--text)] transition-colors duration-brand">
    <header
      v-if="isLoggedIn"
      class="sticky top-0 z-50 border-b border-[var(--border)] bg-[var(--surface)]/90 backdrop-blur"
    >
      <div class="container mx-auto flex max-w-screen-xl items-center justify-between gap-6 py-4">
        <div>
          <h1 class="text-lg font-semibold text-[var(--text)]">On Brand — Portal</h1>
          <p class="text-xs font-medium text-[var(--text-2)]">Manage uploads, approvals, and brand-ready assets.</p>
        </div>
        <nav class="flex items-center gap-3 text-sm font-medium text-[var(--text-2)]">
          <a class="rounded-md px-2 py-1 transition-colors hover:text-primary" href="/portal/client/upload">
            Client: Upload
          </a>
          <a class="rounded-md px-2 py-1 transition-colors hover:text-primary" href="/portal/client/library">
            Client: Library
          </a>
          <a class="rounded-md px-2 py-1 transition-colors hover:text-primary" href="/portal/admin/review">
            Admin: Review
          </a>
          <a class="rounded-md px-2 py-1 transition-colors hover:text-primary" href="/portal/settings/integrations">
            Settings
          </a>
          <Button class="ml-1" size="sm" variant="secondary" @click="handleLogout">
            Logout
          </Button>
        </nav>
      </div>
    </header>
    <main class="container mx-auto max-w-screen-xl space-y-6 py-8">
      <router-view />
    </main>
    <Toaster rich-colors position="top-right" />
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Toaster } from 'vue-sonner';
import Button from './ui/Button.vue';
import { logout } from './services/auth';

const router = useRouter();
const route = useRoute();
const isLoggedIn = ref(false);

function checkAuth() {
  isLoggedIn.value = !!localStorage.getItem('token');
}

function handleLogout() {
  logout();
  isLoggedIn.value = false;
  router.push('/login');
}

onMounted(checkAuth);
watch(
  () => route.path,
  () => checkAuth()
);
</script>
