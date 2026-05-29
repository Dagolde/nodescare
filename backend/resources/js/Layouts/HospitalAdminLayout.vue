<template>
  <div class="hospital-admin-layout">
    <nav class="navbar">
      <div class="navbar-brand">
        <h2>HHMS Hospital Admin</h2>
      </div>
      <div class="navbar-menu">
        <a :href="route('hospital-admin.dashboard')" :class="{ active: isActive('hospital-admin.dashboard') }">
          Dashboard
        </a>
        <a :href="route('hospital-admin.departments')" :class="{ active: isActive('hospital-admin.departments') }">
          Departments
        </a>
        <a :href="route('hospital-admin.staff')" :class="{ active: isActive('hospital-admin.staff') }">
          Staff
        </a>
        <a :href="route('hospital-admin.workflow-templates')" :class="{ active: isActive('hospital-admin.workflow-templates') }">
          Workflows
        </a>
        <a :href="route('accounts.queue')" :class="{ active: isActive('accounts.queue') }">
          Accounts
        </a>
        <a :href="route('department.queue')" :class="{ active: isActive('department.queue') }">
          Department
        </a>
        <div class="dropdown">
          <button class="dropdown-toggle" @click="toggleReportsDropdown">
            Reports
            <span class="arrow">▼</span>
          </button>
          <div v-if="showReportsDropdown" class="dropdown-menu">
            <a :href="route('hospital-admin.reports.department')">Department Activity</a>
            <a :href="route('hospital-admin.reports.financial')">Financial</a>
          </div>
        </div>
        <a :href="route('hospital-admin.audit-logs')" :class="{ active: isActive('hospital-admin.audit-logs') }">
          Audit Logs
        </a>
        <a :href="route('hospital-admin.wallet')" :class="{ active: isActive('hospital-admin.wallet') }">
          💰 Wallet
        </a>
        <a :href="route('hospital-admin.ward')" :class="{ active: isActive('hospital-admin.ward') }">
          🏥 Ward
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
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

const showReportsDropdown = ref(false);

const isActive = (routeName) => {
  return window.location.pathname.includes(route(routeName));
};

const toggleReportsDropdown = () => {
  showReportsDropdown.value = !showReportsDropdown.value;
};

const handleLogout = async () => {
  try {
    await axios.post('/hospital-admin/logout');
    router.visit('/hospital-admin/login');
  } catch (error) {
    console.error('Logout failed:', error);
    window.location.href = '/hospital-admin/login';
  }
};

const route = (name) => {
  const routes = {
    'hospital-admin.dashboard': '/hospital-admin/dashboard',
    'hospital-admin.departments': '/hospital-admin/departments',
    'hospital-admin.staff': '/hospital-admin/staff',
    'hospital-admin.workflow-templates': '/hospital-admin/workflow-templates',
    'hospital-admin.reports.department': '/hospital-admin/reports/department-activity',
    'hospital-admin.reports.financial': '/hospital-admin/reports/financial',
    'hospital-admin.audit-logs': '/hospital-admin/audit-logs',
    'hospital-admin.wallet':     '/hospital-admin/wallet',
    'hospital-admin.ward':       '/hospital-admin/ward',
    'accounts.queue': '/accounts/queue',
    'department.queue': '/department/queue',
  };
  return routes[name] || '/';
};
</script>

<style scoped>
.hospital-admin-layout {
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

.navbar-menu a,
.dropdown-toggle {
  color: #6c757d;
  text-decoration: none;
  font-weight: 500;
  padding: 0.5rem 0;
  border-bottom: 2px solid transparent;
  transition: all 0.2s;
  background: none;
  border: none;
  cursor: pointer;
  font-size: 1rem;
}

.navbar-menu a:hover,
.dropdown-toggle:hover {
  color: #007bff;
}

.navbar-menu a.active {
  color: #007bff;
  border-bottom-color: #007bff;
}

.dropdown {
  position: relative;
}

.dropdown-toggle {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.arrow {
  font-size: 0.75rem;
}

.dropdown-menu {
  position: absolute;
  top: 100%;
  left: 0;
  background: white;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  border-radius: 4px;
  margin-top: 0.5rem;
  min-width: 200px;
  z-index: 1000;
}

.dropdown-menu a {
  display: block;
  padding: 0.75rem 1rem;
  color: #495057;
  text-decoration: none;
  border-bottom: none;
  transition: background 0.2s;
}

.dropdown-menu a:hover {
  background: #f8f9fa;
  color: #007bff;
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
