<template>
  <HospitalAdminLayout>
    <div class="department-queue">
      <div class="header">
        <div>
          <h1>{{ department?.name || 'Department' }} Queue</h1>
          <p class="subtitle">Active patient records assigned to your department</p>
        </div>
        <div class="realtime-indicator" :class="{ connected: isConnected }">
          <span class="dot"></span>
          {{ isConnected ? 'Live' : 'Connecting...' }}
        </div>
      </div>

      <div class="filters">
        <input
          v-model="filters.search"
          type="text"
          placeholder="Search by patient name or number..."
          class="search-input"
          @input="applyFilters"
        />
        <button @click="clearFilters" class="btn btn-secondary">Clear</button>
      </div>

      <div class="queue-stats">
        <div class="stat-card">
          <div class="stat-value">{{ queueRecords.total }}</div>
          <div class="stat-label">Patients in Queue</div>
        </div>
      </div>

      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th>Patient Number</th>
              <th>Patient Name</th>
              <th>Chief Complaint</th>
              <th>Time in Queue</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="record in localQueue"
              :key="record.id"
              class="clickable-row"
              @click="viewPatient(record.id)"
            >
              <td>
                <span class="patient-number">{{ record.patient?.patient_number }}</span>
              </td>
              <td>
                <span class="patient-name">{{ record.patient?.full_name }}</span>
              </td>
              <td>
                <span class="complaint">{{ record.patient?.chief_complaint }}</span>
              </td>
              <td>{{ timeInQueue(record.created_at) }}</td>
              <td>
                <span class="badge" :class="statusClass(record.status)">
                  {{ formatStatus(record.status) }}
                </span>
              </td>
              <td @click.stop>
                <div class="action-buttons">
                  <button
                    class="btn btn-sm btn-info"
                    @click="openNoteModal(record)"
                  >
                    Add Note
                  </button>
                  <button
                    class="btn btn-sm btn-success"
                    @click="openCompleteModal(record)"
                    :disabled="record.status === 'in_transit'"
                  >
                    Complete
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="localQueue.length === 0">
              <td colspan="6" class="empty-state">
                <div class="empty-icon">🏥</div>
                <p>No patients in queue</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="queueRecords.last_page > 1" class="pagination">
        <button
          @click="goToPage(queueRecords.current_page - 1)"
          :disabled="queueRecords.current_page === 1"
          class="btn btn-secondary"
        >
          Previous
        </button>
        <span class="page-info">
          Page {{ queueRecords.current_page }} of {{ queueRecords.last_page }}
        </span>
        <button
          @click="goToPage(queueRecords.current_page + 1)"
          :disabled="queueRecords.current_page === queueRecords.last_page"
          class="btn btn-secondary"
        >
          Next
        </button>
      </div>
    </div>

    <!-- Add Note Modal -->
    <div v-if="showNoteModal" class="modal-overlay" @click.self="closeNoteModal">
      <div class="modal">
        <div class="modal-header">
          <h3>Add Note</h3>
          <button class="modal-close" @click="closeNoteModal">×</button>
        </div>
        <div class="modal-body">
          <p class="modal-patient">
            Patient: <strong>{{ selectedRecord?.patient?.full_name }}</strong>
          </p>
          <textarea
            v-model="noteText"
            placeholder="Enter your note..."
            class="note-textarea"
            rows="5"
          ></textarea>
          <p v-if="noteError" class="error-text">{{ noteError }}</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="closeNoteModal">Cancel</button>
          <button class="btn btn-primary" @click="submitNote" :disabled="isSubmitting">
            {{ isSubmitting ? 'Saving...' : 'Save Note' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Complete Step Modal -->
    <div v-if="showCompleteModal" class="modal-overlay" @click.self="closeCompleteModal">
      <div class="modal">
        <div class="modal-header">
          <h3>Complete Step</h3>
          <button class="modal-close" @click="closeCompleteModal">×</button>
        </div>
        <div class="modal-body">
          <p>
            Are you sure you want to mark
            <strong>{{ selectedRecord?.patient?.full_name }}</strong>'s
            record as complete? This will route the patient to the next department.
          </p>
          <textarea
            v-model="completeNotes"
            placeholder="Optional completion notes..."
            class="note-textarea"
            rows="3"
          ></textarea>
          <p v-if="completeError" class="error-text">{{ completeError }}</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="closeCompleteModal">Cancel</button>
          <button class="btn btn-success" @click="submitComplete" :disabled="isSubmitting">
            {{ isSubmitting ? 'Processing...' : 'Confirm Complete' }}
          </button>
        </div>
      </div>
    </div>
  </HospitalAdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import HospitalAdminLayout from '../../Layouts/HospitalAdminLayout.vue';

const props = defineProps({
  queueRecords: Object,
  department: Object,
  currentUser: Object,
  filters: Object,
});

const filters = ref({
  search: props.filters?.search || '',
});

// Local reactive copy of queue for real-time updates
const localQueue = ref([...(props.queueRecords?.data || [])]);

// WebSocket state
const isConnected = ref(false);
let echoChannel = null;

// Modal state
const showNoteModal = ref(false);
const showCompleteModal = ref(false);
const selectedRecord = ref(null);
const noteText = ref('');
const completeNotes = ref('');
const noteError = ref('');
const completeError = ref('');
const isSubmitting = ref(false);

// Computed
const timeInQueue = (createdAt) => {
  const now = new Date();
  const created = new Date(createdAt);
  const diffMs = now - created;
  const diffMins = Math.floor(diffMs / 60000);
  if (diffMins < 60) return `${diffMins}m`;
  const hours = Math.floor(diffMins / 60);
  const mins = diffMins % 60;
  return `${hours}h ${mins}m`;
};

const formatStatus = (status) => {
  const map = {
    active: 'Active',
    in_transit: 'In Transit',
    admitted: 'Admitted',
    discharged: 'Discharged',
    completed: 'Completed',
  };
  return map[status] || status;
};

const statusClass = (status) => {
  const map = {
    active: 'badge-success',
    in_transit: 'badge-warning',
    admitted: 'badge-info',
    discharged: 'badge-secondary',
    completed: 'badge-secondary',
  };
  return map[status] || 'badge-secondary';
};

// Navigation
const viewPatient = (recordId) => {
  router.visit(`/department/patient-records/${recordId}`);
};

// Filters
const applyFilters = () => {
  router.get('/department/queue', filters.value, {
    preserveState: true,
    preserveScroll: true,
  });
};

const clearFilters = () => {
  filters.value = { search: '' };
  applyFilters();
};

const goToPage = (page) => {
  router.get(`/department/queue?page=${page}`, filters.value, {
    preserveState: true,
    preserveScroll: true,
  });
};

// Note modal
const openNoteModal = (record) => {
  selectedRecord.value = record;
  noteText.value = '';
  noteError.value = '';
  showNoteModal.value = true;
};

const closeNoteModal = () => {
  showNoteModal.value = false;
  selectedRecord.value = null;
};

const submitNote = async () => {
  if (!noteText.value.trim()) {
    noteError.value = 'Note cannot be empty.';
    return;
  }
  isSubmitting.value = true;
  noteError.value = '';
  try {
    await axios.post(`/api/v1/patient-records/${selectedRecord.value.id}/notes`, {
      notes: noteText.value,
    });
    closeNoteModal();
  } catch (err) {
    noteError.value = err.response?.data?.error?.message || 'Failed to save note.';
  } finally {
    isSubmitting.value = false;
  }
};

// Complete modal
const openCompleteModal = (record) => {
  selectedRecord.value = record;
  completeNotes.value = '';
  completeError.value = '';
  showCompleteModal.value = true;
};

const closeCompleteModal = () => {
  showCompleteModal.value = false;
  selectedRecord.value = null;
};

const submitComplete = async () => {
  isSubmitting.value = true;
  completeError.value = '';
  try {
    await axios.post(`/api/v1/patient-records/${selectedRecord.value.id}/complete`, {
      notes: completeNotes.value || undefined,
    });
    // Remove from local queue
    localQueue.value = localQueue.value.filter(r => r.id !== selectedRecord.value.id);
    closeCompleteModal();
  } catch (err) {
    completeError.value = err.response?.data?.error?.message || 'Failed to complete step.';
  } finally {
    isSubmitting.value = false;
  }
};

// Real-time WebSocket subscription
const setupEcho = () => {
  if (!window.Echo || !props.department?.id) return;

  echoChannel = window.Echo.private(`department.${props.department.id}`)
    .listen('.patient.routed', (event) => {
      // New patient arrived in this department
      if (event.patient_record) {
        const exists = localQueue.value.find(r => r.id === event.patient_record.id);
        if (!exists) {
          localQueue.value.push(event.patient_record);
        }
      }
    })
    .listen('.patient.completed', (event) => {
      // Patient left this department
      if (event.patient_record_id) {
        localQueue.value = localQueue.value.filter(r => r.id !== event.patient_record_id);
      }
    })
    .listen('.patient.updated', (event) => {
      // Patient record updated
      if (event.patient_record) {
        const idx = localQueue.value.findIndex(r => r.id === event.patient_record.id);
        if (idx !== -1) {
          localQueue.value[idx] = { ...localQueue.value[idx], ...event.patient_record };
        }
      }
    });

  // Track connection state
  window.Echo.connector.pusher.connection.bind('connected', () => {
    isConnected.value = true;
  });
  window.Echo.connector.pusher.connection.bind('disconnected', () => {
    isConnected.value = false;
  });
  window.Echo.connector.pusher.connection.bind('unavailable', () => {
    isConnected.value = false;
  });

  // Check initial state
  isConnected.value = window.Echo.connector.pusher.connection.state === 'connected';
};

onMounted(() => {
  setupEcho();
});

onUnmounted(() => {
  if (echoChannel && props.department?.id) {
    window.Echo.leave(`department.${props.department.id}`);
  }
});
</script>

<style scoped>
.department-queue {
  padding: 2rem;
  max-width: 1400px;
  margin: 0 auto;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
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

.realtime-indicator {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: #6c757d;
  padding: 0.5rem 1rem;
  border-radius: 20px;
  background: #f8f9fa;
}

.realtime-indicator.connected {
  color: #28a745;
  background: #d4edda;
}

.dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #6c757d;
}

.connected .dot {
  background: #28a745;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}

.filters {
  display: flex;
  gap: 1rem;
  margin-bottom: 2rem;
}

.search-input {
  flex: 1;
  padding: 0.5rem 1rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 0.9375rem;
  max-width: 400px;
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

.clickable-row {
  cursor: pointer;
  transition: background 0.15s;
}

.clickable-row:hover {
  background: #f0f4ff;
}

.patient-number {
  font-family: monospace;
  font-weight: 600;
  color: #007bff;
}

.patient-name {
  font-weight: 500;
}

.complaint {
  max-width: 200px;
  display: block;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
}

.badge-success { background: #d4edda; color: #155724; }
.badge-warning { background: #fff3cd; color: #856404; }
.badge-info { background: #d1ecf1; color: #0c5460; }
.badge-secondary { background: #e2e3e5; color: #383d41; }

.action-buttons {
  display: flex;
  gap: 0.5rem;
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

.btn-primary { background: #007bff; color: white; }
.btn-primary:hover { background: #0056b3; }
.btn-secondary { background: #6c757d; color: white; }
.btn-secondary:hover { background: #545b62; }
.btn-success { background: #28a745; color: white; }
.btn-success:hover { background: #1e7e34; }
.btn-info { background: #17a2b8; color: white; }
.btn-info:hover { background: #117a8b; }
.btn-sm { padding: 0.375rem 0.75rem; font-size: 0.8125rem; }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }

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

/* Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal {
  background: white;
  border-radius: 8px;
  width: 100%;
  max-width: 500px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid #e9ecef;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.25rem;
  color: #2c3e50;
}

.modal-close {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  color: #6c757d;
  line-height: 1;
  padding: 0;
}

.modal-body {
  padding: 1.5rem;
}

.modal-patient {
  margin: 0 0 1rem 0;
  color: #495057;
}

.note-textarea {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 0.9375rem;
  resize: vertical;
  box-sizing: border-box;
}

.note-textarea:focus {
  outline: none;
  border-color: #007bff;
  box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.15);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding: 1rem 1.5rem;
  border-top: 1px solid #e9ecef;
}

.error-text {
  color: #dc3545;
  font-size: 0.875rem;
  margin: 0.5rem 0 0 0;
}
</style>
