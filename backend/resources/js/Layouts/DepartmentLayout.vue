<template>
  <div class="department-layout">
    <nav class="navbar">
      <div class="navbar-brand">
        <h2>{{ department?.name || 'Department' }} - HOD Dashboard</h2>
      </div>
      <div class="navbar-menu">
        <a :href="route('department.dashboard')" :class="{ active: isActive('department.dashboard') }">
          Dashboard
        </a>
        <a :href="route('department.queue')" :class="{ active: isActive('department.queue') }">
          Queue
        </a>
        <a :href="route('department.staff')" :class="{ active: isActive('department.staff') }">
          Staff Management
        </a>
        <a :href="route('department.lab-results')" :class="{ active: isActive('department.lab-results') }">
          Lab Results
        </a>
        <a :href="route('department.prescriptions')" :class="{ active: isActive('department.prescriptions') }">
          Prescriptions
        </a>
        <a :href="route('department.reports')" :class="{ active: isActive('department.reports') }">
          Reports
        </a>
        <a :href="route('department.settings')" :class="{ active: isActive('department.settings') }">
          Settings
        </a>
      </div>
      <div class="navbar-actions">
        <span class="user-info">{{ currentUser?.name }}</span>
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

const props = defineProps({
  department: Object,
  currentUser: Object,
});

const isActive = (routeName) => {
  return window.location.pathname.includes(route(routeName));
};

const handleLogout = async () => {
  try {
    await axios.post('/department/logout');
    router.visit('/department/login');
  } catch (error) {
    console.error('Logout failed:', error);
    window.location.href = '/department/login';
  }
};

const route = (name) => {
  const routes = {
    'department.dashboard': '/department/dashboard',
    'department.queue': '/department/queue',
    'department.staff': '/department/staff',
    'department.lab-results': '/department/lab-results',
    'department.prescriptions': '/department/prescriptions',
    'department.reports': '/department/reports',
    'department.settings': '/department/settings',
  };
  return routes[name] || '/';
};
</script>

<style scoped>
.department-layout {
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
  align-items: center;
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

.user-info {
  color: #495057;
  font-weight: 500;
  font-size: 0.875rem;
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
