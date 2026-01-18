<template>
  <div class="flex min-h-[75vh] items-center justify-center px-4">
    <div class="w-full max-w-5xl">
      <div class="grid gap-8 lg:grid-cols-[1.2fr_0.8fr] lg:items-center">
        <section class="page-header space-y-4">
          <p class="eyebrow">Brand Studio Portal</p>
          <h2 class="display-title font-semibold text-[var(--text)]">
            Build bold brand moments for small businesses.
          </h2>
          <p class="text-sm text-[var(--text-2)]">
            Curate visual stories, keep shoots on track, and deliver publish-ready assets without the chaos.
          </p>
          <div class="flex flex-wrap gap-2">
            <span class="badge">Capture plans</span>
            <span class="badge">Approval flow</span>
            <span class="badge">Instant publishing</span>
          </div>
        </section>

        <div class="card card-glow space-y-6">
        <form class="space-y-4" @submit.prevent="doLogin">
          <div class="space-y-2">
            <label class="text-sm font-medium text-[var(--text-2)]" for="email">Email</label>
            <input
              id="email"
              v-model="email"
              autofocus
              class="input-control"
              placeholder="admin@example.com"
              required
              type="email"
            />
          </div>
          <div class="space-y-2">
            <label class="text-sm font-medium text-[var(--text-2)]" for="password">Password</label>
            <input
              id="password"
              v-model="password"
              class="input-control"
              placeholder="password"
              required
              type="password"
            />
          </div>
          <Button class="w-full" :disabled="busy" size="lg" type="submit">
            {{ busy ? 'Signing in…' : 'Sign in' }}
          </Button>
        </form>
        <p v-if="error" class="text-sm text-danger">{{ error }}</p>
        <p v-if="success" class="text-sm text-success">✓ Login successful! Redirecting…</p>
        <p class="text-xs text-[var(--text-2)]">
          Tip: use <span class="font-semibold">admin@example.com</span> / <span class="font-semibold">password</span> for
          the demo account.
        </p>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { login } from '../services/auth';
import Button from '../ui/Button.vue';

const router = useRouter();
const email = ref('admin@example.com');
const password = ref('password');
const busy = ref(false);
const error = ref('');
const success = ref(false);

async function doLogin() {
  if (!email.value || !password.value) {
    error.value = 'Please enter email and password';
    return;
  }
  
  busy.value = true;
  error.value = '';
  success.value = false;
  
  try {
    const result = await login(email.value, password.value);
    
    if (!result?.token) {
      throw new Error('No token received');
    }
    
    success.value = true;
    
    // Give user feedback before redirecting
    const destination = result?.user?.role === 'admin' ? '/admin/dashboard' : '/client/upload';

    setTimeout(() => {
      router.replace(destination);
    }, 500);
  } catch (e: any) {
    console.error('Login error:', e);
    error.value = e?.response?.data?.message || e?.message || 'Login failed. Please check your credentials.';
  } finally {
    busy.value = false;
  }
}
</script>
