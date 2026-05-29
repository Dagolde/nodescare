<template>
  <SuperAdminLayout>
    <div class="super-admin-dashboard">
      <div class="header">
        <h1>Super Admin Dashboard</h1>
        <a :href="route('admin.hospitals.create')" class="btn btn-primary">Create Hospital</a>
      </div>

      <!-- Stats Bar -->
      <div class="stats-bar">
        <div class="stat-card">
          <div class="stat-value">{{ stats.total }}</div>
          <div class="stat-label">Total Hospitals</div>
        </div>
        <div class="stat-card stat-active">
          <div class="stat-value">{{ stats.active }}</div>
          <div class="stat-label">Active</div>
        </div>
        <div class="stat-card stat-suspended">
          <div class="stat-value">{{ stats.suspended }}</div>
          <div class="stat-label">Suspended</div>
        </div>
      </div>

      <div class="filters">
        <input
          v-model="filters.search"
          type="text"
          placeholder="Search by name or subdomain..."
          class="search-input"
          @input="applyFilters"
        />
        <select v-model="filters.status" class="status-filter" @change="applyFilters">
          <option value="">All Statuses</option>
          <option value="active">Active</option>
          <option value="suspended">Suspended</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>

      <div class="hospitals-table">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Subdomain</th>
              <th>Plan</th>
              <th>Status</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="hospital in hospitals.data" :key="hospital.id">
              <td>{{ hospital.name }}</td>
              <td>{{ hospital.subdomain }}</td>
              <td>{{ hospital.plan?.name || 'N/A' }}</td>
              <td>
                <span :class="['status-badge', `status-${hospital.status}`]">
                  {{ hospital.status }}
                </span>
              </td>
              <td>{{ formatDate(hospital.created_at) }}</td>
              <td class="actions">
                <a :href="`/admin/hospitals/${hospital.id}`" class="btn btn-info btn-sm">
                  View Details
                </a>
                <button
                  v-if="hospital.status === 'active'"
                  @click="confirmAction('suspend', hospital)"
                  class="btn btn-warning btn-sm"
                >
                  Suspend
                </button>
                <button
                  v-if="hospital.status === 'suspended'"
                  @click="confirmAction('activate', hospital)"
                  class="btn btn-success btn-sm"
                >
                  Activate
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pagination">
        <button
          v-for="link in hospitals.links"
          :key="link.label"
          @click="changePage(link.url)"
          :disabled="!link.url"
          :class="['page-btn', { active: link.active }]"
          v-html="link.label"
        ></button>
      </div>

      <!-- Confirmation Modal -->
      <div v-if="showModal" class="modal-overlay" @click="closeModal">
        <div class="modal" @click.stop>
          <h3>Confirm {{ modalAction }}</h3>
          <p>
            Are you sure you want to {{ modalAction }} <strong>{{ selectedHospital?.name }}</strong>?
          </p>
          <div class="modal-actions">
            <button @click="closeModal" class="btn btn-secondary">Cancel</button>
            <button @click="executeAction" class="btn btn-primary">Confirm</button>
          </div>
        </div>
      </div>
    </div>
  </SuperAdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import SuperAdminLayout from '../../Layouts/SuperAdminLayout.vue';

const props = defineProps({
  hospitals: Object,
  stats: Object,
  filters: Object,
});

const filters = reactive({
  search: props.filters.search,
  status: props.filters.status,
});

const showModal = ref(false);
const modalAction = ref('');
const selectedHospital = ref(null);

const applyFilters = () => {
  router.get(route('admin.dashboard'), filters, {
    preserveState: true,
    preserveScroll: true,
  });
};

const changePage = (url) => {
  if (!url) return;
  router.get(url, {}, { preserveState: true, preserveScroll: true });
};

const confirmAction = (action, hospital) => {
  modalAction.value = action;
  selectedHospital.value = hospital;
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  modalAction.value = '';
  selectedHospital.value = null;
};

const executeAction = async () => {
  try {
    const endpoint = `/admin/api/hospitals/${selectedHospital.value.id}/${modalAction.value}`;
    await axios.patch(endpoint);
    closeModal();
    router.reload({ only: ['hospitals', 'stats'] });
  } catch (error) {
    console.error('Action failed:', error);
    alert('Failed to ' + modalAction.value + ' hospital');
  }
};

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

const route = (name) => {
  const routes = {
    'admin.dashboard':       '/admin/dashboard',
    'admin.hospitals.create': '/admin/hospitals/create',
  };
  return routes[name] || '/';
};
</script>

<style scoped>
.super-admin-dashboard {
  padding: 2rem;
  max-width: 1400px;
  margin: 0 auto;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

h1 {
  font-size: 2rem;
  color: #2c3e50;
  margin: 0;
}

/* Stats Bar */
.stats-bar {
  display: flex;
  gap: 1rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: white;
  border-radius: 8px;
  padding: 1.25rem 2rem;
  box-shadow: 0 2px 4px rgba(0,0,0,0.08);
  min-width: 140px;
  text-align: center;
}

.stat-value {
  font-size: 2rem;
  font-weight: 700;
  color: #2c3e50;
}

.stat-label {
  font-size: 0.875rem;
  color: #6c757d;
  margin-top: 0.25rem;
}

.stat-active .stat-value { color: #28a745; }
.stat-suspended .stat-value { color: #ffc107; }

.filters {
  display: flex;
  gap: 1rem;
  margin-bottom: 2rem;
}

.search-input,
.status-filter {
  padding: 0.5rem 1rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 1rem;
}

.search-input {
  flex: 1;
  max-width: 400px;
}

.status-filter {
  min-width: 150px;
}

.hospitals-table {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

table {
  width: 100%;
  border-collapse: collapse;
}

thead {
  background: #f8f9fa;
}

th, td {
  padding: 1rem;
  text-align: left;
  border-bottom: 1px solid #e9ecef;
}

th {
  font-weight: 600;
  color: #495057;
}

.status-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.875rem;
  font-weight: 500;
  text-transform: capitalize;
}

.status-active    { background: #d4edda; color: #155724; }
.status-suspended { background: #fff3cd; color: #856404; }
.status-inactive  { background: #f8d7da; color: #721c24; }

.actions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.btn {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 4px;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  text-decoration: none;
  display: inline-block;
  transition: all 0.2s;
}

.btn-primary   { background: #007bff; color: white; }
.btn-primary:hover { background: #0056b3; }
.btn-warning   { background: #ffc107; color: #212529; }
.btn-warning:hover { background: #e0a800; }
.btn-success   { background: #28a745; color: white; }
.btn-success:hover { background: #218838; }
.btn-secondary { background: #6c757d; color: white; }
.btn-secondary:hover { background: #5a6268; }
.btn-info      { background: #17a2b8; color: white; }
.btn-info:hover { background: #138496; }
.btn-sm { padding: 0.375rem 0.75rem; font-size: 0.8125rem; }

.pagination {
  display: flex;
  gap: 0.5rem;
  justify-content: center;
  margin-top: 2rem;
}

.page-btn {
  padding: 0.5rem 0.75rem;
  border: 1px solid #dee2e6;
  background: white;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s;
}

.page-btn:hover:not(:disabled) { background: #e9ecef; }
.page-btn.active { background: #007bff; color: white; border-color: #007bff; }
.page-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.modal-overlay {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal {
  background: white;
  padding: 2rem;
  border-radius: 8px;
  max-width: 500px;
  width: 90%;
}

.modal h3 { margin-top: 0; margin-bottom: 1rem; text-transform: capitalize; }
.modal p  { margin-bottom: 1.5rem; color: #495057; }

.modal-actions {
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
}
</style>
