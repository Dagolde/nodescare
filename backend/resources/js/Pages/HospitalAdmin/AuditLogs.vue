<template>
  <HospitalAdminLayout>
    <div class="audit-logs-page">
      <div class="header">
        <h1>Audit Logs</h1>
        <button @click="exportLogs" class="btn btn-primary">Export CSV</button>
      </div>

      <div class="filters-card">
        <div class="filters">
          <div class="form-group">
            <label>Start Date</label>
            <input v-model="filters.start_date" type="date" @change="applyFilters" />
          </div>
          <div class="form-group">
            <label>End Date</label>
            <input v-model="filters.end_date" type="date" @change="applyFilters" />
          </div>
          <div class="form-group">
            <label>Action</label>
            <input v-model="filters.action" type="text" placeholder="Search action..." @input="applyFilters" />
          </div>
          <div class="form-group">
            <label>User</label>
            <select v-model="filters.actor_id" @change="applyFilters">
              <option value="">All Users</option>
              <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
            </select>
          </div>
        </div>
      </div>

      <div class="logs-table">
        <table>
          <thead>
            <tr>
              <th>Timestamp</th>
              <th>User</th>
              <th>Role</th>
              <th>Action</th>
              <th>Resource</th>
              <th>Description</th>
              <th>IP Address</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in logs.data" :key="log.id">
              <td>{{ formatDate(log.created_at) }}</td>
              <td>{{ log.actor?.name || 'System' }}</td>
              <td><span class="role-badge">{{ formatRole(log.actor_role) }}</span></td>
              <td><code class="action-code">{{ log.action }}</code></td>
              <td>{{ log.resource_type || 'N/A' }}</td>
              <td class="description">{{ log.description }}</td>
              <td><code>{{ log.ip_address }}</code></td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pagination">
        <button
          v-for="link in logs.links"
          :key="link.label"
          @click="changePage(link.url)"
          :disabled="!link.url"
          :class="['page-btn', { active: link.active }]"
          v-html="link.label"
        ></button>
      </div>
    </div>
  </HospitalAdminLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import HospitalAdminLayout from '../../Layouts/HospitalAdminLayout.vue';

const props = defineProps({
  logs: Object,
  users: Array,
  filters: Object,
});

const filters = reactive({
  start_date: props.filters.start_date || '',
  end_date: props.filters.end_date || '',
  action: props.filters.action || '',
  actor_id: props.filters.actor_id || '',
});

const applyFilters = () => {
  router.get('/hospital-admin/audit-logs', filters, {
    preserveState: true,
    preserveScroll: true,
  });
};

const changePage = (url) => {
  if (!url) return;
  router.get(url, {}, {
    preserveState: true,
    preserveScroll: true,
  });
};

const exportLogs = () => {
  const params = new URLSearchParams(filters);
  window.location.href = `/hospital-admin/api/audit-logs/export?${params.toString()}`;
};

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const formatRole = (role) => {
  if (!role) return 'N/A';
  return role.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};
</script>

<style scoped>
.audit-logs-page {
  padding: 2rem;
  max-width: 1600px;
  margin: 0 auto;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

h1 {
  font-size: 2rem;
  color: #2c3e50;
  margin: 0;
}

.filters-card {
  background: white;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  margin-bottom: 2rem;
}

.filters {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: #495057;
  font-size: 0.9375rem;
}

.form-group input,
.form-group select {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #ced4da;
  border-radius: 4px;
  font-size: 1rem;
  box-sizing: border-box;
}

.btn {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 4px;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-primary {
  background: #007bff;
  color: white;
}

.btn-primary:hover {
  background: #0056b3;
}

.logs-table {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
  min-width: 1200px;
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
  font-size: 0.9375rem;
}

td {
  font-size: 0.875rem;
}

.role-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.8125rem;
  background: #e7f3ff;
  color: #0066cc;
  white-space: nowrap;
}

code {
  background: #f8f9fa;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-family: monospace;
  font-size: 0.8125rem;
}

.action-code {
  color: #e83e8c;
}

.description {
  max-width: 300px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

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
  font-size: 0.875rem;
}

.page-btn:hover:not(:disabled) {
  background: #e9ecef;
}

.page-btn.active {
  background: #007bff;
  color: white;
  border-color: #007bff;
}

.page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
