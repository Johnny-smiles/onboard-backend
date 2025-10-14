<template>
  <section class="card space-y-6">
    <header class="space-y-2">
      <h2 class="text-xl font-semibold text-[var(--text)]">Integrations (local)</h2>
      <p class="text-sm text-[var(--text-2)]">
        Store keys in local storage while we finalize server-side persistence. These simple settings keep your WordPress
        and social exports ready.
      </p>
    </header>
    <form class="space-y-4" @submit.prevent="save">
      <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-2">
          <label class="text-sm font-medium text-[var(--text-2)]" for="wp-url">WordPress URL</label>
          <input
            id="wp-url"
            v-model="wpUrl"
            class="input-control"
            placeholder="https://example.com"
            type="text"
          />
        </div>
        <div class="space-y-2">
          <label class="text-sm font-medium text-[var(--text-2)]" for="wp-token">WordPress Token</label>
          <input
            id="wp-token"
            v-model="wpToken"
            class="input-control"
            type="text"
          />
        </div>
        <div class="space-y-2">
          <label class="text-sm font-medium text-[var(--text-2)]" for="meta-token">Meta Token</label>
          <input
            id="meta-token"
            v-model="metaToken"
            class="input-control"
            type="text"
          />
        </div>
        <div class="space-y-2">
          <label class="text-sm font-medium text-[var(--text-2)]" for="gbp-token">GBP Token</label>
          <input
            id="gbp-token"
            v-model="gbpToken"
            class="input-control"
            type="text"
          />
        </div>
      </div>
      <div class="flex items-center gap-3">
        <Button size="lg" type="submit">Save</Button>
        <span v-if="ok" class="text-sm font-medium text-success">Saved</span>
      </div>
    </form>
  </section>
</template>
<script setup lang="ts">
import { ref, onMounted } from 'vue';
import Button from '../ui/Button.vue';
const wpUrl=ref(''), wpToken=ref(''), metaToken=ref(''), gbpToken=ref(''), ok=ref(false);
function save(){
  localStorage.setItem('onbrand_wp_url', wpUrl.value);
  localStorage.setItem('onbrand_wp_token', wpToken.value);
  localStorage.setItem('onbrand_meta_token', metaToken.value);
  localStorage.setItem('onbrand_gbp_token', gbpToken.value);
  ok.value = true; setTimeout(()=> ok.value=false, 1000);
}
onMounted(()=>{
  wpUrl.value = localStorage.getItem('onbrand_wp_url') || '';
  wpToken.value = localStorage.getItem('onbrand_wp_token') || '';
  metaToken.value = localStorage.getItem('onbrand_meta_token') || '';
  gbpToken.value = localStorage.getItem('onbrand_gbp_token') || '';
});
</script>
