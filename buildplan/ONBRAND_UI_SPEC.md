# On Brand — Modern UI Spec (YAML + Implementation Guide)

This single document contains:
- A **design system YAML** (`ui_theme.yaml`) you can paste into your repo.
- A **how‑to guide** for wiring the tokens into Tailwind + Vue and using the components.

You can keep this file at the root of your project (e.g., `ONBRAND_UI_SPEC.md`) and copy sections as needed.

---

## 1) Design System — `ui_theme.yaml`

```yaml
meta:
  name: On Brand UI
  version: 1.0.0
  description: Design tokens and UI recipes for a modern, accessible Vue + Tailwind portal
  brand: On Brand
  license: MIT

themes:
  default:
    mode: system # system | light | dark
    color:
      primary:   "#0B5FFF"  # brand blue
      primary-600: "#0A54E6"
      primary-700: "#094BCC"
      secondary: "#7C3AED"  # accent purple
      success:   "#16A34A"
      warning:   "#D97706"
      danger:    "#DC2626"
      info:      "#0891B2"
      surface:   "#FFFFFF"
      surface-2: "#F8FAFC"
      surface-3: "#EEF2F6"
      text:      "#0F172A"  # slate-900
      text-2:    "#334155"  # slate-700
      border:    "#E2E8F0"  # slate-200
      ring:      "#93C5FD"  # light ring color
    radius:
      sm: 0.375rem
      md: 0.75rem
      lg: 1rem
      xl: 1.25rem
      full: 9999px
    shadow:
      sm:  "0 1px 2px 0 rgb(0 0 0 / 0.05)"
      md:  "0 4px 12px -2px rgb(0 0 0 / 0.08), 0 2px 4px -2px rgb(0 0 0 / 0.06)"
      lg:  "0 10px 25px -5px rgb(0 0 0 / 0.12)"
    spacing:
      xs: 0.25rem
      sm: 0.5rem
      md: 0.75rem
      base: 1rem
      lg: 1.5rem
      xl: 2rem
      2xl: 3rem
    typography:
      font-family: "Inter, ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Noto Sans, Helvetica, Arial, Apple Color Emoji, Segoe UI Emoji"
      sizes:
        xs: 0.75rem
        sm: 0.875rem
        base: 1rem
        lg: 1.125rem
        xl: 1.25rem
        2xl: 1.5rem
        3xl: 1.875rem
      leading:
        snug: 1.2
        normal: 1.5
        relaxed: 1.7
    motion:
      ease: "cubic-bezier(0.22, 1, 0.36, 1)" # standard ease-out
      fast: 120ms
      base: 200ms
      slow: 320ms
    layout:
      container:
        width: 1280px
        gutter: 1rem
      grid:
        gap: 1rem
        columns: 12
    z:
      header: 50
      overlay: 100
      modal: 1000
      toast: 1100
    breakpoints:
      sm: 640px
      md: 768px
      lg: 1024px
      xl: 1280px

  dark:
    inherit: default
    overrides:
      color:
        surface:   "#0B1120"
        surface-2: "#0F172A"
        surface-3: "#111827"
        text:      "#E5E7EB"
        text-2:    "#CBD5E1"
        border:    "#1F2937"
        ring:      "#1D4ED8"

# Tailwind mappings (optional)
tailwind:
  content:
    - "./resources/**/*.blade.php"
    - "./resources/**/*.vue"
    - "./resources/**/*.ts"
    - "./resources/**/*.js"
  plugins:
    - "@tailwindcss/typography"
    - "@tailwindcss/forms"
  extend:
    colors:
      primary: "{themes.default.color.primary}"
      secondary: "{themes.default.color.secondary}"
      success: "{themes.default.color.success}"
      warning: "{themes.default.color.warning}"
      danger:  "{themes.default.color.danger}"
      info:    "{themes.default.color.info}"

components:
  button:
    base: "inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg border text-sm font-medium transition-all focus:outline-none focus-visible:ring-2"
    variants:
      primary:   "bg-[var(--c-primary)] border-[var(--c-primary)] text-white hover:brightness-[.95] active:brightness-[.9] focus-visible:ring-[var(--c-ring)]"
      secondary: "bg-white border-[var(--c-primary)] text-[var(--c-primary)] hover:bg-[var(--surface-3)] active:bg-[var(--surface-2)]"
      ghost:     "bg-transparent border-transparent text-[var(--c-primary)] hover:bg-[var(--surface-3)]"
      danger:    "bg-[var(--c-danger)] border-[var(--c-danger)] text-white hover:brightness-[.95]"
    sizes:
      sm: "h-8 px-2 text-xs"
      md: "h-10 px-3 text-sm"
      lg: "h-11 px-4 text-base"
    disabled: "opacity-60 cursor-not-allowed"

  input:
    base: "w-full rounded-lg border bg-white text-[var(--text)] placeholder-slate-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--c-ring)]"
    sizes:
      md: "h-10 px-3 text-sm"
      lg: "h-11 px-3 text-base"
    states:
      invalid: "border-[var(--c-danger)] focus-visible:ring-[var(--c-danger)]"

  textarea:
    base: "w-full rounded-lg border bg-white text-[var(--text)] placeholder-slate-400 min-h-[120px] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--c-ring)]"

  select:
    base: "w-full rounded-lg border bg-white text-[var(--text)] h-10 px-3 pr-8 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--c-ring)]"

  card:
    base: "bg-[var(--surface)] border border-[var(--border)] rounded-2xl shadow-sm p-4"
    header: "flex items-center justify-between gap-2 mb-2"
    title: "text-[var(--text)] text-base font-semibold"
    body: "text-[var(--text-2)] text-sm"

  badge:
    base: "inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs"
    variants:
      neutral: "bg-[var(--surface-2)] border-[var(--border)] text-[var(--text-2)]"
      success: "bg-green-50 border-green-200 text-green-700"
      info:    "bg-sky-50 border-sky-200 text-sky-700"
      warning: "bg-amber-50 border-amber-200 text-amber-700"
      danger:  "bg-red-50 border-red-200 text-red-700"

  modal:
    overlay: "fixed inset-0 bg-black/50 backdrop-blur-sm z-[var(--z-modal)]"
    panel:   "bg-[var(--surface)] border border-[var(--border)] rounded-2xl shadow-lg p-6 w-full max-w-lg mx-auto"
    motion:
      enter: "data-[state=open]:animate-in data-[state=open]:fade-in data-[state=open]:zoom-in-95"
      exit:  "data-[state=closed]:animate-out data-[state=closed]:fade-out data-[state=closed]:zoom-out-95"

  toast:
    container: "fixed top-4 right-4 z-[var(--z-toast)] flex flex-col gap-2"
    item: "rounded-lg px-4 py-3 shadow-md text-sm border"
    success: "bg-green-600 text-white border-green-700"
    error:   "bg-red-600 text-white border-red-700"
    neutral: "bg-[var(--surface)] text-[var(--text)] border-[var(--border)]"

  table:
    wrap: "overflow-x-auto"
    table: "w-full text-sm"
    th: "text-left font-semibold text-[var(--text)] border-b border-[var(--border)] py-2"
    td: "text-[var(--text-2)] border-b border-[var(--border)] py-2"
    row-hover: "hover:bg-[var(--surface-3)]"

a11y:
  focus-visible: true
  target-size-min: "44x44"
  contrast-minimum: "WCAG AA"
  motion-reduce: true
  semantics:
    - All interactive elements must have discernible text
    - Non-text content has alt text
    - Color is not the only means of conveying state

utilities:
  css-vars:
    "--c-primary":      "{themes.default.color.primary}"
    "--c-danger":       "{themes.default.color.danger}"
    "--c-ring":         "{themes.default.color.ring}"
    "--surface":        "{themes.default.color.surface}"
    "--surface-2":      "{themes.default.color.surface-2}"
    "--surface-3":      "{themes.default.color.surface-3}"
    "--text":           "{themes.default.color.text}"
    "--text-2":         "{themes.default.color.text-2}"
    "--border":         "{themes.default.color.border}"
    "--radius":         "{themes.default.radius.md}"
```

---

## 2) Implementation Guide — Wire it into Tailwind + Vue

### 2.1 Install deps
```bash
npm i -D tailwindcss postcss autoprefixer @tailwindcss/typography @tailwindcss/forms
npm i lucide-vue-next vue-sonner pinia @tanstack/vue-table
npx tailwindcss init -p
```

### 2.2 Tailwind config (`tailwind.config.js`)
```js
/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class', // toggle <html class="dark"> for dark mode
  content: ['./resources/**/*.{blade.php,vue,ts,js}'],
  theme: {
    extend: {
      boxShadow: {
        'sm-brand': '0 1px 2px 0 rgb(0 0 0 / 0.05)',
        'md-brand': '0 4px 12px -2px rgb(0 0 0 / 0.08), 0 2px 4px -2px rgb(0 0 0 / 0.06)',
        'lg-brand': '0 10px 25px -5px rgb(0 0 0 / 0.12)',
      },
      borderRadius: {
        brand: '0.75rem',
        xl: '1.25rem',
      },
    }
  },
  plugins: [require('@tailwindcss/typography'), require('@tailwindcss/forms')],
}
```

### 2.3 Global CSS tokens → `resources/css/portal.css`
```css
@tailwind base;
@tailwind components;
@tailwind utilities;

:root {
  --c-primary:  #0B5FFF;
  --c-primary-600: #0A54E6;
  --c-danger:   #DC2626;
  --c-ring:     #93C5FD;

  --surface:    #FFFFFF;
  --surface-2:  #F8FAFC;
  --surface-3:  #EEF2F6;

  --text:       #0F172A;
  --text-2:     #334155;

  --border:     #E2E8F0;

  --radius: .75rem;
}

:root.dark {
  --surface:   #0B1120;
  --surface-2: #0F172A;
  --surface-3: #111827;

  --text:   #E5E7EB;
  --text-2: #CBD5E1;

  --border: #1F2937;
}

/* Primitives */
.card   { @apply bg-[var(--surface)] border border-[var(--border)] rounded-2xl p-4 shadow-sm; }
.badge  { @apply inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs text-[var(--text-2)] border-[var(--border)]; }
.btn    { @apply inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg border text-sm font-medium transition-all focus:outline-none focus-visible:ring-2; }
.btn-primary   { @apply text-white; background: var(--c-primary); border-color: var(--c-primary); }
.btn-primary:hover { filter: brightness(.95); }
.btn-secondary { @apply bg-white; color: var(--c-primary); border-color: var(--c-primary); }
.btn-secondary:hover { background: var(--surface-3); }

input[type="text"], input[type="email"], input[type="password"], select, textarea {
  @apply w-full rounded-lg border bg-white text-[var(--text)] placeholder-slate-400 focus:outline-none focus-visible:ring-2;
  box-shadow: none;
}
```

Then import it at the top of `resources/js/portal/main.ts`:
```ts
import '../../css/portal.css'
```

### 2.4 Reusable components

**Button.vue**
```vue
<template>
  <button :class="classes" v-bind="$attrs"><slot /></button>
</template>
<script setup lang="ts">
import { computed } from 'vue'
const props = withDefaults(defineProps<{ variant?: 'primary'|'secondary', size?: 'sm'|'md'|'lg' }>(), { variant: 'primary', size: 'md' })
const classes = computed(() => [
  'btn',
  props.variant === 'secondary' ? 'btn-secondary' : 'btn-primary',
  props.size === 'sm' ? 'h-8 px-2 text-xs' : props.size === 'lg' ? 'h-11 px-4 text-base' : 'h-10 px-3 text-sm'
])
</script>
```

**Card shell**
```vue
<template><div class="card"><slot /></div></template>
```

**Toast host**
```vue
<script setup lang="ts">
import { Toaster } from 'vue-sonner'
</script>
<Toaster richColors position="top-right" />
```

**Icons**
```vue
<script setup lang="ts">
import { Camera } from 'lucide-vue-next'
</script>
<Camera class="w-4 h-4" />
```

### 2.5 Patterns & layout
- **Layout**: `.container mx-auto px-4` with `max-w-screen-xl` on main content.
- **Grid**: `grid grid-cols-1 md:grid-cols-2 gap-4` for galleries/forms.
- **Tables**: `overflow-x-auto` wrapper; headers `border-b`, rows `hover:bg-[var(--surface-3)]`.
- **Empty states**: center content `text-center p-8`, icon + title + helper text.
- **Forms**: label small text `text-sm text-[var(--text-2)]`; inline errors in `text-red-600`.
- **Sticky CTA footer**: `sticky bottom-0 bg-[var(--surface)] border-t p-3`.

### 2.6 Accessibility & motion
- Use `:focus-visible` rings on all interactive elements.
- Respect reduced motion; keep transitions subtle (≤ 200ms).
- Minimum target size: `h-10` or padding `py-2 px-3`.

### 2.7 Dark mode
- Toggle via `<html class="dark">` or build a system switcher using `window.matchMedia('(prefers-color-scheme: dark)')` and a class toggle.
- CSS variables make components automatically adapt.

### 2.8 Build & verify
```bash
npm run build
# Visit /portal and verify: buttons, cards, inputs, toast, icons
```

---

## 3) Copy‑paste checklist
- [ ] Add this file to your repo as `ONBRAND_UI_SPEC.md`.
- [ ] Create `resources/css/portal.css` from the snippet.
- [ ] Import CSS in `resources/js/portal/main.ts`.
- [ ] Add `Button.vue` (and optional Card shell) to `resources/js/portal/ui/`.
- [ ] Mount `<Toaster />` in `App.vue`.
- [ ] Rebuild assets; verify light/dark tokens and focus rings.

---

**End of spec.**
