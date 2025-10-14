import '../../css/portal.css';
import 'vue-sonner/style.css';

import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import { createPinia } from 'pinia';
import App from './App.vue';
import Login from './views/Login.vue';
import ClientUpload from './views/ClientUpload.vue';
import ClientLibrary from './views/ClientLibrary.vue';
import AdminDashboard from './views/AdminDashboard.vue';
import AdminReview from './views/AdminReview.vue';
import SettingsIntegrations from './views/SettingsIntegrations.vue';

import { isAdmin } from './services/auth';

const routes = [
  { path: '/', redirect: '/login' },
  { path: '/login', component: Login, meta: { requiresGuest: true } },
  { path: '/client/upload', component: ClientUpload, meta: { requiresAuth: true } },
  { path: '/client/library', component: ClientLibrary, meta: { requiresAuth: true } },
  { path: '/admin/dashboard', component: AdminDashboard, meta: { requiresAuth: true, requiresAdmin: true } },
  { path: '/admin/review', component: AdminReview, meta: { requiresAuth: true, requiresAdmin: true } },
  { path: '/settings/integrations', component: SettingsIntegrations, meta: { requiresAuth: true } },
];

const router = createRouter({ history: createWebHistory('/portal'), routes });

// Navigation guard
router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('token');
  const isAuthenticated = !!token;

  if (to.meta.requiresAuth && !isAuthenticated) {
    // Redirect to login if not authenticated
    next('/login');
    return;
  }

  if (to.meta.requiresAdmin && !isAdmin()) {
    next('/client/upload');
    return;
  }

  if (to.meta.requiresGuest && isAuthenticated) {
    // Redirect based on role if already logged in
    next(isAdmin() ? '/admin/dashboard' : '/client/upload');
    return;
  }

  next();
});

createApp(App).use(router).use(createPinia()).mount('#app');
