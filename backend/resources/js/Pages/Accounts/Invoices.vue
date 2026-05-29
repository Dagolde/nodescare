<template>
  <HospitalAdminLayout>
    <div class="invoices-list">
      <div class="header">
        <h1>All Invoices</h1>
        <p class="subtitle">View and search all invoices</p>
      </div>

      <div class="filters">
        <input
          v-model="filters.search"
          type="text"
          placeholder="Search by invoice number or patient..."
          class="search-input"
          @input="applyFilters"
        />
        <select v-model="filters.status" class="filter-select" @change="applyFilters">
          <option value="">All Statuses</option>
          <option value="issued">Issued</option>
          <option value="partially_paid">Partially Paid</option>
          <option value="settled">Settled</option>
          <option value="reversed">Reversed</option>
        </select>
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

      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th>Invoice Number</th>
              <th>Patient</th>
              <th>Total Amount</th>
              <th>Amount Paid</th>
              <th>Outstanding</th>
              <th>Status</th>
              <th>Created At</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="invoice in invoices.data" :key="invoice.id">
              <td>
                <span class="invoice-number">{{ invoice.invoice_number }}</span>
              </td>
              <td>
                <div class="patient-info">
                  <div class="patient-name">{{ invoice.patient.full_name }}</div>
                  <div class="patient-number">{{ invoice.patient.patient_number }}</div>
                </div>
              </td>
              <td>₦{{ parseFloat(invoice.total_amount).toFixed(2) }}</td>
              <td class="text-success">₦{{ parseFloat(invoice.amount_paid).toFixed(2) }}</td>
              <td :class="invoice.outstanding_balance > 0 ? 'text-danger' : 'text-muted'">
                ₦{{ parseFloat(invoice.outstanding_balance).toFixed(2) }}
              </td>
              <td>
                <span :class="['status-badge', `status-${invoice.status}`]">
                  {{ formatStatus(invoice.status) }}
                </span>
              </td>
              <td>{{ formatDateTime(invoice.created_at) }}</td>
              <td>
                <a
                  :href="`/accounts/invoices/${invoice.id}`"
                  class="btn btn-sm btn-primary"
                >
                  View Details
                </a>
              </td>
            </tr>
            <tr v-if="invoices.data.length === 0">
              <td colspan="8" class="empty-state">
                <div class="empty-icon">📄</div>
                <p>No invoices found</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="invoices.last_page > 1" class="pagination">
        <button
          @click="goToPage(invoices.current_page - 1)"
          :disabled="invoices.current_page === 1"
          class="btn btn-secondary"
        >
          Previous
        </button>
        <span class="page-info">
          Page {{ invoices.current_page }} of {{ invoices.last_page }}
        </span>
        <button
          @click="goToPage(invoices.current_page + 1)"
          :disabled="invoices.current_page === invoices.last_page"
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
  invoices: Object,
  filters: Object,
});

const filters = ref({
  search: props.filters?.search || '',
  status: props.filters?.status || '',
  start_date: props.filters?.start_date || '',
  end_date: props.filters?.end_date || '',
});

const formatStatus = (status) => {
  const statusMap = {
    draft: 'Draft',
    issued: 'Issued',
    partially_paid: 'Partially Paid',
    settled: 'Settled',
    reversed: 'Reversed',
  };
  return statusMap[status] || status;
};

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
  router.get('/accounts/invoices', filters.value, {
    preserveState: true,
    preserveScroll: true,
  });
};

const clearFilters = () => {
  filters.value = {
    search: '',
    status: '',
    start_date: '',
    end_date: '',
  };
  applyFilters();
};

const goToPage = (page) => {
  router.get(`/accounts/invoices?page=${page}`, filters.value, {
    preserveState: true,
    preserveScroll: true,
  });
};
</script>

<style scoped>
.invoices-list {
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

.search-input {
  flex: 1;
  min-width: 250px;
  padding: 0.5rem 1rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 0.9375rem;
}

.filter-select,
.date-input {
  padding: 0.5rem 1rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 0.9375rem;
}

.filter-select {
  width: 180px;
}

.date-input {
  width: 150px;
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

.invoice-number {
  font-family: monospace;
  font-weight: 600;
  color: #007bff;
}

.patient-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.patient-name {
  font-weight: 500;
}

.patient-number {
  font-size: 0.8125rem;
  color: #6c757d;
  font-family: monospace;
}

.text-success {
  color: #28a745;
  font-weight: 600;
}

.text-danger {
  color: #dc3545;
  font-weight: 600;
}

.text-muted {
  color: #6c757d;
}

.status-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.status-issued {
  background: #d1ecf1;
  color: #0c5460;
}

.status-partially_paid {
  background: #fff3cd;
  color: #856404;
}

.status-settled {
  background: #d4edda;
  color: #155724;
}

.status-reversed {
  background: #f8d7da;
  color: #721c24;
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
