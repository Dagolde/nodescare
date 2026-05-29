<template>
  <HospitalAdminLayout>
    <div class="accounts-queue">
      <div class="header">
        <h1>Accounts Queue</h1>
        <p class="subtitle">Pending payment records awaiting invoice creation and settlement</p>
      </div>

      <div class="filters">
        <input
          v-model="filters.search"
          type="text"
          placeholder="Search by patient name or number..."
          class="search-input"
          @input="applyFilters"
        />
        <input
          v-model="filters.start_date"
          type="date"
          class="date-input"
          @change="applyFilters"
        />
        <input
          v-model="filters.end_date"
          type="date"
          class="date-input"
          @change="applyFilters"
        />
        <button @click="clearFilters" class="btn btn-secondary">Clear Filters</button>
      </div>

      <div class="queue-stats">
        <div class="stat-card">
          <div class="stat-value">{{ pendingRecords.total }}</div>
          <div class="stat-label">Pending Payments</div>
        </div>
      </div>

      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th>Patient Number</th>
              <th>Patient Name</th>
              <th>Registration Time</th>
              <th>Registered By</th>
              <th>Invoices</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="record in pendingRecords.data" :key="record.id">
              <td>
                <span class="patient-number">{{ record.patient.patient_number }}</span>
              </td>
              <td>
                <span class="patient-name">{{ record.patient.full_name }}</span>
              </td>
              <td>{{ formatDateTime(record.created_at) }}</td>
              <td>{{ record.registrar?.name || 'N/A' }}</td>
              <td>
                <span v-if="record.invoices && record.invoices.length > 0" class="badge badge-info">
                  {{ record.invoices.length }} invoice(s)
                </span>
                <span v-else class="badge badge-warning">No invoices</span>
              </td>
              <td>
                <div class="action-buttons">
                  <a
                    :href="`/accounts/patient-records/${record.id}/create-invoice`"
                    class="btn btn-sm btn-primary"
                  >
                    Create Invoice
                  </a>
                  <a
                    v-if="record.invoices && record.invoices.length > 0"
                    :href="`/accounts/invoices/${record.invoices[0].id}`"
                    class="btn btn-sm btn-secondary"
                  >
                    View Latest Invoice
                  </a>
                </div>
              </td>
            </tr>
            <tr v-if="pendingRecords.data.length === 0">
              <td colspan="6" class="empty-state">
                <div class="empty-icon">📋</div>
                <p>No pending payment records found</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="pendingRecords.last_page > 1" class="pagination">
        <button
          @click="goToPage(pendingRecords.current_page - 1)"
          :disabled="pendingRecords.current_page === 1"
          class="btn btn-secondary"
        >
          Previous
        </button>
        <span class="page-info">
          Page {{ pendingRecords.current_page }} of {{ pendingRecords.last_page }}
        </span>
        <button
          @click="goToPage(pendingRecords.current_page + 1)"
          :disabled="pendingRecords.current_page === pendingRecords.last_page"
          class="btn btn-secondary"
        >
          Next
        </button>
      </div>
    </div>
  </HospitalAdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import HospitalAdminLayout from '../../Layouts/HospitalAdminLayout.vue';

const props = defineProps({
  pendingRecords: Object,
  filters: Object,
});

const filters = ref({
  search: props.filters?.search || '',
  start_date: props.filters?.start_date || '',
  end_date: props.filters?.end_date || '',
});

const formatDateTime = (dateString) => {
  const date = new Date(dateString);
  return date.toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const applyFilters = () => {
  router.get('/accounts/queue', filters.value, {
    preserveState: true,
    preserveScroll: true,
  });
};

const clearFilters = () => {
  filters.value = {
    search: '',
    start_date: '',
    end_date: '',
  };
  applyFilters();
};

const goToPage = (page) => {
  router.get(`/accounts/queue?page=${page}`, filters.value, {
    preserveState: true,
    preserveScroll: true,
  });
};
</script>

<style scoped>
.accounts-queue {
  padding: 2rem;
  max-width: 1400px;
  margin: 0 auto;
}

.header {
  margin-bottom: 2rem;
}

h1 {
  font-size: 2rem;
  color: #2c3e50;
  margin: 0 0 0.5rem 0;
}

.subtitle {
  color: #6c757d;
  margin: 0;
}

.filters {
  display: flex;
  gap: 1rem;
  margin-bottom: 2rem;
  flex-wrap: wrap;
}

.search-input,
.date-input {
  padding: 0.5rem 1rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 0.9375rem;
}

.search-input {
  flex: 1;
  min-width: 250px;
}

.date-input {
  width: 150px;
}

.queue-stats {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: white;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  text-align: center;
}

.stat-value {
  font-size: 2.5rem;
  font-weight: 700;
  color: #007bff;
  margin-bottom: 0.5rem;
}

.stat-label {
  color: #6c757d;
  font-size: 0.875rem;
  font-weight: 500;
}

.table-container {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  margin-bottom: 2rem;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table thead {
  background: #f8f9fa;
}

.data-table th {
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  color: #495057;
  font-size: 0.875rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.data-table td {
  padding: 1rem;
  border-top: 1px solid #e9ecef;
  color: #495057;
}

.patient-number {
  font-family: monospace;
  font-weight: 600;
  color: #007bff;
}

.patient-name {
  font-weight: 500;
}

.badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
}

.badge-info {
  background: #d1ecf1;
  color: #0c5460;
}

.badge-warning {
  background: #fff3cd;
  color: #856404;
}

.action-buttons {
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

.btn-primary {
  background: #007bff;
  color: white;
}

.btn-primary:hover {
  background: #0056b3;
}

.btn-secondary {
  background: #6c757d;
  color: white;
}

.btn-secondary:hover {
  background: #545b62;
}

.btn-sm {
  padding: 0.375rem 0.75rem;
  font-size: 0.8125rem;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.empty-state {
  text-align: center;
  padding: 3rem 1rem;
  color: #6c757d;
}

.empty-icon {
  font-size: 3rem;
  margin-bottom: 1rem;
}

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 1rem;
}

.page-info {
  color: #495057;
  font-weight: 500;
}
</style>
