<template>
  <DepartmentLayout :department="department" :currentUser="currentUser">
    <div class="prescription-page">
      <div class="page-header">
        <div>
          <h1>Prescriptions</h1>
          <p class="subtitle">View and export patient prescriptions for your department</p>
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
        <input
          v-model="filters.doctor_search"
          type="text"
          placeholder="Search by doctor..."
          class="search-input"
          @input="onSearchInput"
        />
        <input v-model="filters.date_from" type="date" class="filter-select" @change="loadPrescriptions" />
        <input v-model="filters.date_to" type="date" class="filter-select" @change="loadPrescriptions" />
        <button @click="clearFilters" class="btn btn-secondary btn-sm">Clear</button>
      </div>

      <!-- Prescriptions Table -->
      <div class="card">
        <div v-if="isLoading" class="loading-state">
          <div class="spinner"></div>
          <p>Loading prescriptions...</p>
        </div>

        <div v-else-if="prescriptions.length === 0" class="empty-state">
          <p>No prescriptions found for your department.</p>
        </div>

        <div v-else>
          <div class="table-container">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Patient</th>
                  <th>Patient No.</th>
                  <th>Prescribing Doctor</th>
                  <th>Prescription Notes</th>
                  <th>Date</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="rx in prescriptions" :key="rx.id">
                  <td>
                    <div class="patient-name">{{ rx.patient_name }}</div>
                  </td>
                  <td>
                    <span class="patient-number">{{ rx.patient_number }}</span>
                  </td>
                  <td>{{ rx.doctor_name || '—' }}</td>
                  <td>
                    <div class="notes-preview" :title="rx.prescription_notes">
                      {{ truncate(rx.prescription_notes, 60) }}
                    </div>
                  </td>
                  <td>{{ formatDate(rx.created_at) }}</td>
                  <td>
                    <div class="action-buttons">
                      <button
                        @click="viewDetails(rx)"
                        class="btn btn-sm btn-outline"
                      >View</button>
                      <button
                        @click="exportPdf(rx.id)"
                        class="btn btn-sm btn-secondary"
                        :disabled="exportingId === rx.id"
                      >
                        {{ exportingId === rx.id ? '...' : '📄 PDF' }}
                      </button>
                    </div>
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

      <!-- Detail Modal -->
      <div v-if="selectedRx" class="modal-overlay" @click.self="selectedRx = null">
        <div class="modal">
          <div class="modal-header">
            <h3>Prescription Details</h3>
            <button @click="selectedRx = null" class="modal-close">✕</button>
          </div>
          <div class="modal-body">
            <div class="detail-row">
              <span class="detail-label">Patient</span>
              <span class="detail-value">{{ selectedRx.patient_name }}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Patient Number</span>
              <span class="detail-value patient-number">{{ selectedRx.patient_number }}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Prescribing Doctor</span>
              <span class="detail-value">{{ selectedRx.doctor_name || '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Date</span>
              <span class="detail-value">{{ formatDate(selectedRx.created_at) }}</span>
            </div>
            <div class="detail-section">
              <span class="detail-label">Prescription Notes</span>
              <div class="notes-full">{{ selectedRx.prescription_notes || 'No notes available.' }}</div>
            </div>
          </div>
          <div class="modal-actions">
            <button @click="selectedRx = null" class="btn btn-secondary">Close</button>
            <button @click="exportPdf(selectedRx.id)" class="btn btn-primary" :disabled="exportingId === selectedRx.id">
              {{ exportingId === selectedRx.id ? 'Exporting...' : '📄 Export PDF' }}
            </button>
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

const prescriptions = ref([]);
const pagination = ref({});
const isLoading = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const exportingId = ref(null);
const searchTimeout = ref(null);
const selectedRx = ref(null);

const filters = reactive({
  patient_search: '',
  doctor_search: '',
  date_from: '',
  date_to: '',
});

const loadPrescriptions = async (page = 1) => {
  isLoading.value = true;
  errorMessage.value = '';
  try {
    const params = { page, per_page: 20 };
    if (filters.patient_search) params.patient_search = filters.patient_search;
    if (filters.doctor_search) params.doctor_search = filters.doctor_search;
    if (filters.date_from) params.date_from = filters.date_from;
    if (filters.date_to) params.date_to = filters.date_to;

    const response = await axios.get('/department/api/prescriptions', { params });
    if (response.data.success) {
      prescriptions.value = response.data.data;
      pagination.value = response.data.meta?.pagination || {};
    }
  } catch (error) {
    errorMessage.value = error.response?.data?.error?.message || 'Failed to load prescriptions.';
  } finally {
    isLoading.value = false;
  }
};

const onSearchInput = () => {
  clearTimeout(searchTimeout.value);
  searchTimeout.value = setTimeout(() => loadPrescriptions(), 300);
};

const goToPage = (page) => {
  if (page >= 1 && page <= pagination.value.last_page) loadPrescriptions(page);
};

const clearFilters = () => {
  Object.assign(filters, { patient_search: '', doctor_search: '', date_from: '', date_to: '' });
  loadPrescriptions();
};

const viewDetails = (rx) => {
  selectedRx.value = rx;
};

const exportPdf = async (id) => {
  exportingId.value = id;
  try {
    const response = await axios.get(`/department/api/prescriptions/${id}/export-pdf`, { responseType: 'blob' });
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `prescription-${id}.pdf`);
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

const truncate = (text, length) => {
  if (!text) return '—';
  return text.length > length ? text.substring(0, length) + '...' : text;
};

const formatDate = (dateStr) => {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

onMounted(() => loadPrescriptions());
</script>

<style scoped>
.prescription-page { padding: 2rem; max-width: 1200px; margin: 0 auto; }
.page-header { margin-bottom: 2rem; }
h1 { font-size: 2rem; color: #2c3e50; margin: 0 0 0.25rem 0; }
.subtitle { color: #6c757d; margin: 0; }

.filters-bar { display: flex; gap: 0.75rem; margin-bottom: 1.5rem; flex-wrap: wrap; align-items: center; }
.search-input { flex: 1; min-width: 180px; padding: 0.625rem 0.875rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.9375rem; }
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
.notes-preview { max-width: 250px; color: #6c757d; font-size: 0.875rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.action-buttons { display: flex; gap: 0.5rem; }

.pagination { display: flex; align-items: center; justify-content: center; gap: 1rem; padding: 1rem; border-top: 1px solid #e9ecef; }
.page-info { color: #6c757d; font-size: 0.875rem; }

.btn { padding: 0.625rem 1.25rem; border: none; border-radius: 6px; font-size: 0.9375rem; font-weight: 500; cursor: pointer; transition: all 0.2s; }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-primary { background: #007bff; color: white; }
.btn-primary:hover:not(:disabled) { background: #0056b3; }
.btn-secondary { background: #6c757d; color: white; }
.btn-secondary:hover:not(:disabled) { background: #545b62; }
.btn-outline { background: white; color: #007bff; border: 1px solid #007bff; }
.btn-outline:hover { background: #e7f3ff; }
.btn-sm { padding: 0.375rem 0.75rem; font-size: 0.8125rem; }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: white; border-radius: 8px; width: 90%; max-width: 520px; max-height: 90vh; overflow-y: auto; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1.5rem 1.5rem 0; }
.modal-header h3 { margin: 0; font-size: 1.25rem; color: #2c3e50; }
.modal-close { background: none; border: none; font-size: 1.25rem; cursor: pointer; color: #6c757d; }
.modal-body { padding: 1.5rem; }
.modal-actions { display: flex; gap: 0.75rem; justify-content: flex-end; padding: 1rem 1.5rem 1.5rem; }

.detail-row { display: flex; gap: 1rem; margin-bottom: 1rem; align-items: baseline; }
.detail-label { font-weight: 600; color: #495057; min-width: 140px; font-size: 0.875rem; }
.detail-value { color: #2c3e50; }
.detail-section { margin-top: 1rem; }
.detail-section .detail-label { display: block; margin-bottom: 0.5rem; }
.notes-full { background: #f8f9fa; padding: 1rem; border-radius: 6px; color: #495057; line-height: 1.6; white-space: pre-wrap; }

.error-message { padding: 0.75rem 1rem; background: #f8d7da; color: #721c24; border-radius: 6px; margin-top: 1rem; }
.toast { position: fixed; bottom: 2rem; right: 2rem; padding: 0.875rem 1.5rem; border-radius: 6px; font-weight: 500; z-index: 2000; }
.toast-success { background: #28a745; color: white; }

@media (max-width: 768px) {
  .prescription-page { padding: 1rem; }
  .filters-bar { flex-direction: column; }
  .search-input, .filter-select { width: 100%; }
}
</style>
