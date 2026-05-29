<template>
  <div class="super-admin-layout">
    <nav class="navbar">
      <div class="navbar-brand">
        <h2>HHMS Super Admin</h2>
      </div>
      <div class="navbar-menu">
        <a :href="route('admin.dashboard')" :class="{ active: isActive('/admin/dashboard') }">
          Dashboard
        </a>
        <a :href="route('admin.system-health')" :class="{ active: isActive('/admin/system-health') }">
          System Health
        </a>
        <a :href="route('admin.plans')" :class="{ active: isActive('/admin/plans') }">
          Plans
        </a>
        <a :href="route('admin.wallets')" :class="{ active: isActive('/admin/wallets') }">
          Wallets
        </a>
        <a :href="route('admin.settings')" :class="{ active: isActive('/admin/settings') }">
          Settings
        </a>
        <a :href="route('admin.audit-logs')" :class="{ active: isActive('/admin/audit-logs') }">
          Audit Logs
        </a>
      </div>
      <div class="navbar-actions">
        <form @submit.prevent="handleLogout" style="display: inline;">
          <button type="submit" class="btn btn-logout">Logout</button>
        </form>
      </div>
    </nav>
    <main class="main-content">
      <slot />
    </main>
  </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import axios from 'axios';

const isActive = (path) => {
  return window.location.pathname.startsWith(path);
};

const handleLogout = async () => {
  try {
    await axios.post('/admin/logout');
    router.visit('/admin/login');
  } catch (error) {
    console.error('Logout failed:', error);
    window.location.href = '/admin/login';
  }
};

const route = (name) => {
  const routes = {
    'admin.dashboard':     '/admin/dashboard',
    'admin.audit-logs':    '/admin/audit-logs',
    'admin.settings':      '/admin/settings',
    'admin.system-health': '/admin/system-health',
    'admin.plans':         '/admin/plans',
    'admin.wallets':       '/admin/wallets',
  };
  return routes[name] || '/';
};
</script>

<style scoped>
.super-admin-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background: #f5f7fa;
}

.navbar {
  background: white;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  padding: 0 2rem;
  display: flex;
  align-items: center;
  height: 64px;
  position: sticky;
  top: 0;
  z-index: 100;
}

.navbar-brand h2 {
  margin: 0;
  font-size: 1.25rem;
  color: #2c3e50;
  font-weight: 600;
}

.navbar-menu {
  display: flex;
  gap: 2rem;
  margin-left: 3rem;
  flex: 1;
}

.navbar-menu a {
  color: #6c757d;
  text-decoration: none;
  font-weight: 500;
  padding: 0.5rem 0;
  border-bottom: 2px solid transparent;
  transition: all 0.2s;
}

.navbar-menu a:hover {
  color: #007bff;
}

.navbar-menu a.active {
  color: #007bff;
  border-bottom-color: #007bff;
}

.navbar-actions {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.btn-logout {
  padding: 0.5rem 1rem;
  border: 1px solid #dc3545;
  background: white;
  color: #dc3545;
  border-radius: 4px;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-logout:hover {
  background: #dc3545;
  color: white;
}

.main-content {
  flex: 1;
}
</style>
