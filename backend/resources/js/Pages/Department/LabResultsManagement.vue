<template>
  <DepartmentLayout :department="department" :currentUser="currentUser">
    <div class="lab-results-page">
      <div class="page-header">
        <div>
          <h1>Lab Results</h1>
          <p class="subtitle">View and export patient lab results for your department</p>
        </div>
      </div>

      <!-- Filters -->
      <div class="filters-bar">
        <input
          v-model="filters.patient_search"
          type="text"
          placeholder="Search patient name or number..."
          class="search-input"
          @input="onSearchInput"
        />
        <select v-model="filters.test_type" class="filter-select" @change="loadResults">
          <option value="">All Test Types</option>
          <option value="blood_test">Blood Test</option>
          <option value="urine_test">Urine Test</option>
          <option value="imaging">Imaging</option>
          <option value="microbiology">Microbiology</option>
          <option value="pathology">Pathology</option>
          <option value="other">Other</option>
        </select>
        <input v-model="filters.date_from" type="date" class="filter-select" @change="loadResults" />
        <input v-model="filters.date_to" type="date" class="filter-select" @change="loadResults" />
        <button @click="clearFilters" class="btn btn-secondary btn-sm">Clear</button>
      </div>

      <!-- Results Table -->
      <div class="card">
        <div v-if="isLoading" class="loading-state">
          <div class="spinner"></div>
          <p>Loading lab results...</p>
        </div>

        <div v-else-if="results.length === 0" class="empty-state">
          <p>No lab results found for your department.</p>
        </div>

        <div v-else>
          <div class="table-container">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Patient</th>
                  <th>Patient No.</th>
                  <th>Test Type</th>
                  <th>Test Name</th>
                  <th>Status</th>
                  <th>Date</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="result in results" :key="result.id">
                  <td>
                    <div class="patient-name">{{ result.patient_name }}</div>
                  </td>
                  <td>
                    <span class="patient-number">{{ result.patient_number }}</span>
                  </td>
                  <td>{{ formatTestType(result.test_type) }}</td>
                  <td>{{ result.test_name || '—' }}</td>
                  <td>
                    <span class="status-badge" :class="`status-${result.result_status}`">
                      {{ formatStatus(result.result_status) }}
                    </span>
                  </td>
                  <td>{{ formatDate(result.result_date || result.created_at) }}</td>
                  <td>
                    <button
                      @click="exportPdf(result.id)"
                      class="btn btn-sm btn-secondary"
                      :disabled="exportingId === result.id"
                    >
                      {{ exportingId === result.id ? '...' : '📄 PDF' }}
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div v-if="pagination.last_page > 1" class="pagination">
            <button
              @click="goToPage(pagination.current_page - 1)"
              :disabled="pagination.current_page === 1"
              class="btn btn-sm btn-secondary"
            >← Prev</button>
            <span class="page-info">
              Page {{ pagination.current_page }} of {{ pagination.last_page }}
              ({{ pagination.total }} total)
            </span>
            <button
              @click="goToPage(pagination.current_page + 1)"
              :disabled="pagination.current_page === pagination.last_page"
              class="btn btn-sm btn-secondary"
            >Next →</button>
          </div>
        </div>
      </div>

      <div v-if="errorMessage" class="error-message">{{ errorMessage }}</div>
      <div v-if="successMessage" class="toast toast-success">{{ successMessage }}</div>
    </div>
  </DepartmentLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';
import DepartmentLayout from '@/Layouts/DepartmentLayout.vue';

const props = defineProps({
  department: Object,
  currentUser: Object,
});

const results = ref([]);
const pagination = ref({});
const isLoading = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const exportingId = ref(null);
const searchTimeout = ref(null);

const filters = reactive({
  patient_search: '',
  test_type: '',
  date_from: '',
  date_to: '',
});

const loadResults = async (page = 1) => {
  isLoading.value = true;
  errorMessage.value = '';
  try {
    const params = { page, per_page: 20 };
    if (filters.patient_search) params.patient_search = filters.patient_search;
    if (filters.test_type) params.test_type = filters.test_type;
    if (filters.date_from) params.date_from = filters.date_from;
    if (filters.date_to) params.date_to = filters.date_to;

    const response = await axios.get('/department/api/lab-results', { params });
    if (response.data.success) {
      results.value = response.data.data;
      pagination.value = response.data.meta?.pagination || {};
    }
  } catch (error) {
    errorMessage.value = error.response?.data?.error?.message || 'Failed to load lab results.';
  } finally {
    isLoading.value = false;
  }
};

const onSearchInput = () => {
  clearTimeout(searchTimeout.value);
  searchTimeout.value = setTimeout(() => loadResults(), 300);
};

const goToPage = (page) => {
  if (page >= 1 && page <= pagination.value.last_page) loadResults(page);
};

const clearFilters = () => {
  Object.assign(filters, { patient_search: '', test_type: '', date_from: '', date_to: '' });
  loadResults();
};

const exportPdf = async (id) => {
  exportingId.value = id;
  try {
    const response = await axios.get(`/department/api/lab-results/${id}/export-pdf`, { responseType: 'blob' });
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `lab-result-${id}.pdf`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    successMessage.value = 'PDF exported successfully.';
    setTimeout(() => { successMessage.value = ''; }, 3000);
  } catch (error) {
    errorMessage.value = 'Failed to export PDF.';
  } finally {
    exportingId.value = null;
  }
};

const formatTestType = (type) => {
  const map = {
    blood_test: 'Blood Test', urine_test: 'Urine Test',
    imaging: 'Imaging', microbiology: 'Microbiology',
    pathology: 'Pathology', other: 'Other',
  };
  return map[type] || type?.replace(/_/g, ' ') || '—';
};

const formatStatus = (status) => {
  const map = { pending: 'Pending', completed: 'Completed', abnormal: 'Abnormal', normal: 'Normal' };
  return map[status] || status || '—';
};

const formatDate = (dateStr) => {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

onMounted(() => loadResults());
</script>

<style scoped>
.lab-results-page { padding: 2rem; max-width: 1200px; margin: 0 auto; }
.page-header { margin-bottom: 2rem; }
h1 { font-size: 2rem; color: #2c3e50; margin: 0 0 0.25rem 0; }
.subtitle { color: #6c757d; margin: 0; }

.filters-bar {
  display: flex; gap: 0.75rem; margin-bottom: 1.5rem; flex-wrap: wrap; align-items: center;
}
.search-input { flex: 1; min-width: 200px; padding: 0.625rem 0.875rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.9375rem; }
.filter-select { padding: 0.625rem 0.875rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.9375rem; background: white; }

.card { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
.loading-state, .empty-state { padding: 3rem; text-align: center; color: #6c757d; }
.spinner { width: 40px; height: 40px; border: 3px solid #e9ecef; border-top-color: #007bff; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 1rem; }
@keyframes spin { to { transform: rotate(360deg); } }

.table-container { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table thead { background: #f8f9fa; }
.data-table th { padding: 0.875rem 1rem; text-align: left; font-weight: 600; color: #495057; font-size: 0.875rem; border-bottom: 2px solid #e9ecef; }
.data-table td { padding: 0.875rem 1rem; border-bottom: 1px solid #f0f0f0; color: #495057; }
.data-table tbody tr:hover { background: #f8f9fa; }

.patient-name { font-weight: 500; color: #2c3e50; }
.patient-number { font-family: monospace; font-size: 0.875rem; background: #f0f0f0; padding: 0.125rem 0.375rem; border-radius: 4px; }

.status-badge { display: inline-block; padding: 0.25rem 0.625rem; border-radius: 12px; font-size: 0.8125rem; font-weight: 500; }
.status-pending { background: #fff3cd; color: #856404; }
.status-completed { background: #d4edda; color: #155724; }
.status-normal { background: #d1ecf1; color: #0c5460; }
.status-abnormal { background: #f8d7da; color: #721c24; }

.pagination { display: flex; align-items: center; justify-content: center; gap: 1rem; padding: 1rem; border-top: 1px solid #e9ecef; }
.page-info { color: #6c757d; font-size: 0.875rem; }

.btn { padding: 0.625rem 1.25rem; border: none; border-radius: 6px; font-size: 0.9375rem; font-weight: 500; cursor: pointer; transition: all 0.2s; }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-secondary { background: #6c757d; color: white; }
.btn-secondary:hover:not(:disabled) { background: #545b62; }
.btn-sm { padding: 0.375rem 0.75rem; font-size: 0.8125rem; }

.error-message { padding: 0.75rem 1rem; background: #f8d7da; color: #721c24; border-radius: 6px; margin-top: 1rem; }
.toast { position: fixed; bottom: 2rem; right: 2rem; padding: 0.875rem 1.5rem; border-radius: 6px; font-weight: 500; z-index: 2000; }
.toast-success { background: #28a745; color: white; }

@media (max-width: 768px) {
  .lab-results-page { padding: 1rem; }
  .filters-bar { flex-direction: column; }
  .search-input, .filter-select { width: 100%; }
}
</style>
