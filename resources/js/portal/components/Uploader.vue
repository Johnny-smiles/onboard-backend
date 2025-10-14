<template>
  <section class="card space-y-6">
    <header class="space-y-1">
      <h3 class="text-xl font-semibold text-[var(--text)]">Upload photos</h3>
      <p class="text-sm text-[var(--text-2)]">Drag &amp; drop JPEGs/PNGs or select files from your device.</p>
    </header>
    <div
      class="flex min-h-[180px] cursor-pointer flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-[var(--border)] bg-[var(--surface-2)] px-6 py-10 text-center transition-colors duration-brand ease-brand hover:border-primary hover:bg-[var(--surface-3)]"
      @click="pick"
      @dragover.prevent
      @drop.prevent="onDrop"
    >
      <p v-if="files.length === 0" class="text-sm text-[var(--text-2)]">
        Drop images here or click to browse
      </p>
      <ul v-else class="w-full space-y-2 text-left">
        <li
          v-for="file in files"
          :key="file.id"
          class="flex items-center justify-between gap-3 rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-2 text-sm text-[var(--text-2)]"
        >
          <span class="truncate">
            {{ file.file.name }} ({{ Math.round(file.file.size / 1024) }} KB)
          </span>
          <div class="flex items-center gap-2">
            <span v-if="file.progress >= 0" class="text-xs text-[var(--text-2)]">{{ file.progress }}%</span>
            <span v-if="file.error" class="text-xs font-medium text-danger">{{ file.error }}</span>
          </div>
        </li>
      </ul>
    </div>
    <input ref="inp" class="hidden" accept="image/*" multiple type="file" @change="onPick">
    <div class="flex flex-wrap items-center gap-3">
      <Button
        :disabled="busy || files.length === 0"
        size="lg"
        @click="start"
      >
        {{ busy ? 'Uploading…' : 'Upload' }}
      </Button>
      <Button :disabled="busy" size="lg" variant="secondary" @click="clear">
        Clear
      </Button>
    </div>
  </section>
</template>
<script setup lang="ts">
import { ref } from 'vue';
import Button from '../ui/Button.vue';
const emit = defineEmits<{(e:'upload', payload: {blob:Blob, name:string}[]):void}>();
const files = ref<{id:number,file:File,progress:number,error?:string}[]>([]);
const busy = ref(false);
const inp = ref<HTMLInputElement|null>(null);

function pick(){ inp.value?.click(); }
function onPick(e:any){ pushFiles(e.target.files); e.target.value=''; }
function onDrop(e:DragEvent){ if(e.dataTransfer?.files) pushFiles(e.dataTransfer.files); }
let _id=1;
function pushFiles(list: FileList){ for(const f of Array.from(list)){ if(f.type.startsWith('image/')) files.value.push({id:_id++, file:f, progress:0}); } }
function clear(){ files.value=[]; }
async function resizeBlob(file: File, maxW=2048): Promise<Blob> {
  const img = new Image();
  const url = URL.createObjectURL(file);
  await new Promise<void>((res, rej)=>{ img.onload=()=>res(); img.onerror=rej; img.src=url; });
  const scale = Math.min(1, maxW / img.width);
  const w = Math.round(img.width * scale), h = Math.round(img.height * scale);
  const canvas = document.createElement('canvas'); canvas.width=w; canvas.height=h;
  const ctx = canvas.getContext('2d')!; ctx.drawImage(img,0,0,w,h);
  return await new Promise<Blob>((res)=> canvas.toBlob(b=>res(b!), 'image/jpeg', 0.85));
}
async function start(){
  busy.value=true;
  const payload: {blob:Blob,name:string}[] = [];
  for(const f of files.value){
    try{
      const blob = await resizeBlob(f.file);
      payload.push({blob, name: f.file.name.replace(/\.[^.]+$/,'')+'.jpg'});
      f.progress=100;
    }catch(err:any){ f.error = 'Failed to process'; }
  }
  emit('upload', payload);
  busy.value=false;
}
</script>
