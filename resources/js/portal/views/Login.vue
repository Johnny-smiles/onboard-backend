<template>
  <div class="flex min-h-[70vh] items-center justify-center px-4">
    <div class="w-full max-w-md space-y-6">
      <div class="text-center">
        <h2 class="text-2xl font-semibold text-[var(--text)]">Welcome back</h2>
        <p class="mt-2 text-sm text-[var(--text-2)]">Sign in to manage your brand assets.</p>
      </div>
      <div class="card space-y-6">
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
    setTimeout(() => {
      router.replace('/client/upload');
    }, 500);
  } catch (e: any) {
    console.error('Login error:', e);
    error.value = e?.response?.data?.message || e?.message || 'Login failed. Please check your credentials.';
  } finally {
    busy.value = false;
  }
}
</script>
