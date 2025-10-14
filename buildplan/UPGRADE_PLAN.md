# On Brand — UPGRADE PLAN (Niceties Pack)

**Run from the folder that contains `onbrand-backend/`.**  
This adds:
- Roles & permissions, activity log, query builder (Spatie)
- Tailwind (+ Typography & Forms), Pinia, validation, icons, toasts, table utils
- Small UI kit, toast host, CSS wiring
- Seeds admin role and user
- Rebuilds assets, runs migrations

---

## 0) Enter the project
```bash
cd onbrand-backend
php -v
composer -V
node -v
npm -v
```

## 1) Backend niceties (roles/audit/query builder)
```bash
composer require spatie/laravel-permission spatie/laravel-activitylog spatie/laravel-query-builder

php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="permission-migrations"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-config"

php artisan migrate

# Enable HasRoles on User (idempotent)
if ! grep -q "Spatie\\\\Permission\\\\Traits\\\\HasRoles" app/Models/User.php; then
  perl -0777 -pe "s/use Laravel\\\\\\\\Sanctum\\\\\\\\HasApiTokens;/use Laravel\\\\\\\\Sanctum\\\\\\\\HasApiTokens;\\nuse Spatie\\\\\\\\Permission\\\\\\\\Traits\\\\\\\\HasRoles;/" -i app/Models/User.php
  perl -0777 -pe "s/use HasApiTokens, HasFactory, Notifiable;/use HasApiTokens, HasFactory, Notifiable, HasRoles;/" -i app/Models/User.php
fi

# Seed base roles
php artisan tinker --execute="use Spatie\Permission\Models\Role; Role::findOrCreate('admin'); Role::findOrCreate('client');"
```

## 2) Frontend niceties (Tailwind + tooling)
```bash
npm i -D tailwindcss postcss autoprefixer @tailwindcss/typography @tailwindcss/forms
npm i pinia zod vee-validate dayjs hotkeys-js lucide-vue-next @tanstack/vue-table vue-sonner

npx tailwindcss init -p

cat > tailwind.config.js <<'TW'
/** @type {import("tailwindcss").Config} */
export default {
  content: ["./resources/**/*.blade.php","./resources/**/*.vue","./resources/**/*.ts","./resources/**/*.js"],
  theme: { extend: { container: { center: true, padding: "1rem" } } },
  plugins: [require("@tailwindcss/typography"), require("@tailwindcss/forms")],
};
TW

cat > postcss.config.js <<'PC'
export default { plugins: { tailwindcss: {}, autoprefixer: {} } };
PC

mkdir -p resources/css
cat > resources/css/portal.css <<'CSS'
@tailwind base;
@tailwind components;
@tailwind utilities;

.btn { @apply inline-flex items-center gap-2 px-3 py-2 rounded-lg border text-sm font-medium transition; }
.btn-primary { @apply bg-blue-600 border-blue-600 text-white hover:bg-blue-700; }
.btn-secondary { @apply bg-white border-blue-600 text-blue-700 hover:bg-blue-50; }

.card { @apply bg-white border border-slate-200 rounded-2xl p-4 shadow-sm; }
.badge { @apply inline-block px-2 py-0.5 rounded-md border text-xs text-slate-700 border-slate-300; }
CSS

# Ensure Tailwind CSS and Pinia are wired
if ! grep -q "portal.css" resources/js/portal/main.ts 2>/dev/null; then
  sed -i.bak "1i import \\\"../../css/portal.css\\\";" resources/js/portal/main.ts || true
fi
if ! grep -q "createPinia" resources/js/portal/main.ts 2>/dev/null; then
  sed -i.bak "1i import { createPinia } from 'pinia';" resources/js/portal/main.ts
  perl -0777 -pe "s/createApp\\(App\\)\\.use\\(router\\)\\.mount\\('#app'\\);/createApp(App).use(router).use(createPinia()).mount('#app');/" -i resources/js/portal/main.ts
fi
```

## 3) UI kit & toast host
```bash
mkdir -p resources/js/portal/ui

cat > resources/js/portal/ui/Button.vue <<'VUE'
<template>
  <button :class="[variant==='secondary' ? 'btn btn-secondary' : 'btn btn-primary', $attrs.class]" v-bind="$attrs">
    <slot />
  </button>
</template>
<script setup lang="ts">
const { variant = 'primary' } = defineProps<{ variant?: 'primary' | 'secondary' }>();
</script>
VUE

# Add Toaster to App.vue (idempotent)
if ! grep -q "vue-sonner" resources/js/portal/App.vue 2>/dev/null; then
  awk '1; /<script setup/{print "import { Toaster } from '\''vue-sonner'\''"}' resources/js/portal/App.vue > resources/js/portal/App.tmp.vue || true
  mv resources/js/portal/App.tmp.vue resources/js/portal/App.vue
  perl -0777 -pe "s#</main>#</main><Toaster richColors position=\\\"top-right\\\" />#g" -i resources/js/portal/App.vue
fi
```

## 4) Optional: swap common buttons to the `<Button/>` component (safe to skip)
```bash
# Example for AdminReview.vue (no-op if already changed):
perl -0777 -pe "s/<button([^>]*)>(Approve)<\\/button>/<Button>\\2<\\/Button>/g" -i resources/js/portal/views/AdminReview.vue || true
perl -0777 -pe "s/<button([^>]*)class=\\"secondary\\"([^>]*)>(Delete)<\\/button>/<Button variant=\\"secondary\\">\\3<\\/Button>/g" -i resources/js/portal/views/AdminReview.vue || true
```

## 5) Rebuild, migrate (re-run), seed demo admin, start server
```bash
npm run build
php artisan migrate

php artisan tinker --execute="$c=\App\Models\Client::firstOrCreate(['name'=>'Demo Co']); $u=\App\Models\User::updateOrCreate(['email'=>'admin@example.com'],['name'=>'Admin','password'=>bcrypt('password'),'role'=>'admin','client_id'=>$c->id]); if(class_exists(\Spatie\Permission\Models\Role::class)){ $u->syncRoles(['admin']); } echo 'Admin token: '.$u->createToken('api')->plainTextToken.PHP_EOL;"

php artisan serve
# Open http://127.0.0.1:8000/portal  (admin@example.com / password)
```
