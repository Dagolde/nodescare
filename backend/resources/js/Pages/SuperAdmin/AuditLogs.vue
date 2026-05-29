<template>
  <SuperAdminLayout>
    <div class="audit-logs">
      <div class="header">
        <h1>Audit Logs</h1>
        <div class="header-actions">
          <button @click="exportCSV" class="btn btn-secondary" :disabled="exporting">
            {{ exporting ? 'Exporting...' : 'Export CSV' }}
          </button>
          <a :href="route('admin.dashboard')" class="btn btn-secondary">Back to Dashboard</a>
        </div>
      </div>

    <div class="filters-panel">
      <div class="filters-row">
        <div class="filter-group">
          <label for="start_date">Start Date</label>
          <input
            id="start_date"
            v-model="filters.start_date"
            type="date"
            @change="fetchLogs"
          />
        </div>

        <div class="filter-group">
          <label for="end_date">End Date</label>
          <input
            id="end_date"
            v-model="filters.end_date"
            type="date"
            @change="fetchLogs"
          />
        </div>

        <div class="filter-group">
          <label for="hospital">Hospital</label>
          <input
            id="hospital"
            v-model="filters.hospital"
            type="text"
            placeholder="Filter by hospital name..."
            @input="debouncedFetch"
          />
        </div>

        <div class="filter-group">
          <label for="action">Action Type</label>
          <input
            id="action"
            v-model="filters.action"
            type="text"
            placeholder="e.g., patient.created"
            @input="debouncedFetch"
          />
        </div>

        <div class="filter-group">
          <button @click="clearFilters" class="btn btn-sm btn-secondary">Clear Filters</button>
        </div>
      </div>
    </div>

    <div class="logs-table">
      <div v-if="loading" class="loading">Loading audit logs...</div>
      
      <table v-else-if="logs.length > 0">
        <thead>
          <tr>
            <th>Timestamp</th>
            <th>Hospital</th>
            <th>Actor</th>
            <th>Action</th>
            <th>Resource</th>
            <th>IP Address</th>
            <th>Description</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="log in logs" :key="log.id">
            <td>{{ formatDateTime(log.created_at) }}</td>
            <td>{{ log.tenant_name || 'System' }}</td>
            <td>
              <div class="actor-info">
                <span class="actor-role">{{ log.actor_role || 'System' }}</span>
              </div>
            </td>
            <td><code>{{ log.action }}</code></td>
            <td>
              <span v-if="log.resource_type">
                {{ log.resource_type }}
                <small v-if="log.resource_id">({{ log.resource_id.substring(0, 8) }}...)</small>
              </span>
              <span v-else>-</span>
            </td>
            <td>{{ log.ip_address || '-' }}</td>
            <td class="description">{{ log.description }}</td>
          </tr>
        </tbody>
      </table>

      <div v-else class="no-data">
        No audit logs found for the selected filters.
      </div>
    </div>

    <div v-if="pagination.total > 0" class="pagination">
      <button
        @click="changePage(pagination.current_page - 1)"
        :disabled="pagination.current_page === 1"
        class="page-btn"
      >
        Previous
      </button>
      <span class="page-info">
        Page {{ pagination.current_page }} of {{ pagination.last_page }}
        ({{ pagination.total }} total)
      </span>
      <button
        @click="changePage(pagination.current_page + 1)"
        :disabled="pagination.current_page === pagination.last_page"
        class="page-btn"
      >
        Next
      </button>
    </div>
    </div>
  </SuperAdminLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import SuperAdminLayout from '../../Layouts/SuperAdminLayout.vue';

const props = defineProps({
  filters: Object,
});

const filters = reactive({
  start_date: props.filters.start_date || '',
  end_date: props.filters.end_date || '',
  hospital: props.filters.hospital || '',
  action: props.filters.action || '',
});

const logs = ref([]);
const loading = ref(false);
const exporting = ref(false);
const pagination = reactive({
  current_page: 1,
  per_page: 20,
  total: 0,
  last_page: 1,
});

let debounceTimer = null;

const fetchLogs = async (page = 1) => {
  loading.value = true;
  try {
    const params = {
      ...filters,
      page,
      per_page: pagination.per_page,
    };
    
    const response = await axios.get('/admin/api/audit-logs', { params });
    logs.value = response.data.data;
    
    if (response.data.meta?.pagination) {
      Object.assign(pagination, response.data.meta.pagination);
    }
  } catch (error) {
    console.error('Failed to fetch audit logs:', error);
    alert('Failed to load audit logs');
  } finally {
    loading.value = false;
  }
};

const debouncedFetch = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    fetchLogs(1);
  }, 500);
};

const changePage = (page) => {
  if (page >= 1 && page <= pagination.last_page) {
    fetchLogs(page);
  }
};

const clearFilters = () => {
  filters.start_date = '';
  filters.end_date = '';
  filters.hospital = '';
  filters.action = '';
  fetchLogs(1);
};

const exportCSV = async () => {
  exporting.value = true;
  try {
    const params = { ...filters };
    const response = await axios.get('/admin/api/audit-logs', {
      params: { ...params, export: 'csv' },
      responseType: 'blob',
    });
    
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `audit-logs-${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    link.remove();
  } catch (error) {
    console.error('Failed to export CSV:', error);
    alert('Failed to export audit logs');
  } finally {
    exporting.value = false;
  }
};

const formatDateTime = (dateString) => {
  return new Date(dateString).toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const route = (name) => {
  const routes = {
    'admin.dashboard': '/admin/dashboard',
  };
  return routes[name] || '/';
};

onMounted(() => {
  fetchLogs();
});
</script>

<style scoped>
.audit-logs {
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

.header-actions {
  display: flex;
  gap: 1rem;
}

h1 {
  font-size: 2rem;
  color: #2c3e50;
  margin: 0;
}

.filters-panel {
  background: white;
  padding: 1.5rem;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  margin-bottom: 2rem;
}

.filters-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  align-items: end;
}

.filter-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: #495057;
  font-size: 0.875rem;
}

.filter-group input {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #ced4da;
  border-radius: 4px;
  font-size: 0.875rem;
}

.logs-table {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  overflow-x: auto;
}

.loading,
.no-data {
  padding: 3rem;
  text-align: center;
  color: #6c757d;
}

table {
  width: 100%;
  border-collapse: collapse;
  min-width: 1200px;
}

thead {
  background: #f8f9fa;
}

th,
td {
  padding: 0.75rem;
  text-align: left;
  border-bottom: 1px solid #e9ecef;
  font-size: 0.875rem;
}

th {
  font-weight: 600;
  color: #495057;
}

code {
  background: #f8f9fa;
  padding: 0.25rem 0.5rem;
  border-radius: 3px;
  font-size: 0.8125rem;
  color: #e83e8c;
}

.actor-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.actor-role {
  font-size: 0.75rem;
  color: #6c757d;
  text-transform: uppercase;
}

.description {
  max-width: 300px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 1rem;
  margin-top: 2rem;
}

.page-btn {
  padding: 0.5rem 1rem;
  border: 1px solid #dee2e6;
  background: white;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s;
}

.page-btn:hover:not(:disabled) {
  background: #e9ecef;
}

.page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.page-info {
  color: #6c757d;
  font-size: 0.875rem;
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

.btn-secondary {
  background: #6c757d;
  color: white;
}

.btn-secondary:hover:not(:disabled) {
  background: #5a6268;
}

.btn-secondary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-sm {
  padding: 0.375rem 0.75rem;
  font-size: 0.8125rem;
}
</style>
