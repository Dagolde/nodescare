<template>
  <HospitalAdminLayout>
    <div class="patient-detail">
      <div class="page-header">
        <button class="btn btn-secondary btn-back" @click="goBack">← Back to Queue</button>
        <div class="header-info">
          <h1>{{ patientRecord.patient?.full_name }}</h1>
          <span class="patient-number">{{ patientRecord.patient?.patient_number }}</span>
          <span class="badge" :class="statusClass(patientRecord.status)">
            {{ formatStatus(patientRecord.status) }}
          </span>
        </div>
      </div>

      <div class="content-grid">
        <!-- Left column: Patient info + Journey history -->
        <div class="left-column">
          <!-- Patient Info Card -->
          <div class="card">
            <div class="card-header">Patient Information</div>
            <div class="card-body">
              <!-- Patient photograph -->
              <div v-if="patientRecord.patient?.photo_object_key" class="photo-section">
                <img
                  v-if="photoUrl"
                  :src="photoUrl"
                  alt="Patient photograph"
                  class="patient-photo"
                />
                <div v-else-if="photoLoading" class="photo-loading">
                  <span>Loading photo…</span>
                </div>
                <div v-else class="photo-placeholder">
                  <span>📷 Photo unavailable</span>
                </div>
              </div>
              <div class="info-grid">
                <div class="info-item">
                  <span class="info-label">Full Name</span>
                  <span class="info-value">{{ patientRecord.patient?.full_name }}</span>
                </div>
                <div class="info-item">
                  <span class="info-label">Patient Number</span>
                  <span class="info-value mono">{{ patientRecord.patient?.patient_number }}</span>
                </div>
                <div class="info-item">
                  <span class="info-label">Date of Birth</span>
                  <span class="info-value">{{ patientRecord.patient?.date_of_birth || 'N/A' }}</span>
                </div>
                <div class="info-item">
                  <span class="info-label">Gender</span>
                  <span class="info-value">{{ capitalize(patientRecord.patient?.gender) }}</span>
                </div>
                <div class="info-item">
                  <span class="info-label">Phone</span>
                  <span class="info-value">{{ patientRecord.patient?.phone || 'N/A' }}</span>
                </div>
                <div class="info-item">
                  <span class="info-label">Address</span>
                  <span class="info-value">{{ patientRecord.patient?.address || 'N/A' }}</span>
                </div>
                <div class="info-item full-width">
                  <span class="info-label">Chief Complaint</span>
                  <span class="info-value complaint">{{ patientRecord.patient?.chief_complaint }}</span>
                </div>
                <div v-if="patientRecord.patient?.hmo_provider_name" class="info-item">
                  <span class="info-label">HMO Provider</span>
                  <span class="info-value">{{ patientRecord.patient.hmo_provider_name }}</span>
                </div>
                <div v-if="patientRecord.patient?.hmo_policy_number" class="info-item">
                  <span class="info-label">HMO Policy #</span>
                  <span class="info-value mono">{{ patientRecord.patient.hmo_policy_number }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Journey History Card -->
          <div class="card">
            <div class="card-header">Journey History</div>
            <div class="card-body">
              <div v-if="patientRecord.journey_events?.length === 0" class="empty-history">
                No journey events yet.
              </div>
              <div class="timeline">
                <div
                  v-for="event in patientRecord.journey_events"
                  :key="event.id"
                  class="timeline-item"
                >
                  <div class="timeline-dot" :class="eventDotClass(event.event_type)"></div>
                  <div class="timeline-content">
                    <div class="timeline-header">
                      <span class="event-type">{{ formatEventType(event.event_type) }}</span>
                      <span class="event-time">{{ formatDateTime(event.created_at) }}</span>
                    </div>
                    <div v-if="event.from_department || event.to_department" class="event-route">
                      <span v-if="event.from_department">{{ event.from_department.name }}</span>
                      <span v-if="event.from_department && event.to_department"> → </span>
                      <span v-if="event.to_department">{{ event.to_department.name }}</span>
                    </div>
                    <div v-if="event.notes" class="event-notes">{{ event.notes }}</div>
                    <div v-if="event.metadata?.prescription_notes" class="event-notes prescription">
                      <strong>Prescription:</strong> {{ event.metadata.prescription_notes }}
                    </div>
                    <div v-if="event.metadata?.investigation_notes" class="event-notes investigation">
                      <strong>Investigation:</strong> {{ event.metadata.investigation_notes }}
                    </div>
                    <div class="event-performer">
                      by {{ event.performer?.name || 'System' }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right column: Actions -->
        <div class="right-column">
          <div class="card actions-card">
            <div class="card-header">Actions</div>
            <div class="card-body">
              <p v-if="patientRecord.status === 'in_transit'" class="in-transit-notice">
                ⏳ This record is currently in transit and cannot be edited.
              </p>

              <div v-else class="action-list">
                <!-- Add Note -->
                <button class="btn btn-info btn-block" @click="openNoteModal">
                  📝 Add Note
                </button>

                <!-- Complete Step -->
                <button
                  class="btn btn-success btn-block"
                  @click="openCompleteModal"
                >
                  ✅ Complete Step
                </button>

                <!-- Doctor-only actions -->
                <template v-if="currentUser.role === 'doctor'">
                  <hr class="divider" />
                  <p class="section-label">Doctor Actions</p>

                  <button class="btn btn-primary btn-block" @click="openRouteModal('pharmacy')">
                    💊 Route to Pharmacy
                  </button>
                  <button class="btn btn-primary btn-block" @click="openRouteModal('laboratory')">
                    🔬 Route to Lab
                  </button>
                  <button class="btn btn-danger btn-block" @click="openAdmitModal">
                    🏥 Mark Admitted
                  </button>
                </template>
              </div>
            </div>
          </div>

          <!-- Current Department -->
          <div class="card">
            <div class="card-header">Current Department</div>
            <div class="card-body">
              <p class="dept-name">{{ patientRecord.current_department?.name || 'N/A' }}</p>
              <p class="dept-type">{{ patientRecord.current_department?.type || '' }}</p>
            </div>
          </div>
        </div>
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
          <textarea
            v-model="noteText"
            placeholder="Enter your note..."
            class="note-textarea"
            rows="6"
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
          <p>Confirm completing this step for <strong>{{ patientRecord.patient?.full_name }}</strong>. The patient will be routed to the next department.</p>
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
            {{ isSubmitting ? 'Processing...' : 'Confirm' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Route to Department Modal (Doctor) -->
    <div v-if="showRouteModal" class="modal-overlay" @click.self="closeRouteModal">
      <div class="modal">
        <div class="modal-header">
          <h3>Route to {{ routeTarget === 'pharmacy' ? 'Pharmacy' : 'Laboratory' }}</h3>
          <button class="modal-close" @click="closeRouteModal">×</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Select Department</label>
            <select v-model="selectedDepartmentId" class="form-select">
              <option value="">-- Select --</option>
              <option
                v-for="dept in filteredDepartments"
                :key="dept.id"
                :value="dept.id"
              >
                {{ dept.name }}
              </option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">
              {{ routeTarget === 'pharmacy' ? 'Prescription Notes' : 'Investigation Notes' }}
            </label>
            <textarea
              v-model="routeNotes"
              :placeholder="routeTarget === 'pharmacy' ? 'Enter prescription details...' : 'Enter investigation request...'"
              class="note-textarea"
              rows="5"
            ></textarea>
          </div>
          <p v-if="routeError" class="error-text">{{ routeError }}</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="closeRouteModal">Cancel</button>
          <button class="btn btn-primary" @click="submitRoute" :disabled="isSubmitting || !selectedDepartmentId">
            {{ isSubmitting ? 'Routing...' : 'Confirm Route' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Admit Modal (Doctor) -->
    <div v-if="showAdmitModal" class="modal-overlay" @click.self="closeAdmitModal">
      <div class="modal">
        <div class="modal-header">
          <h3>Mark Patient as Admitted</h3>
          <button class="modal-close" @click="closeAdmitModal">×</button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to mark <strong>{{ patientRecord.patient?.full_name }}</strong> as admitted?</p>
          <textarea
            v-model="admitNotes"
            placeholder="Admission notes (optional)..."
            class="note-textarea"
            rows="3"
          ></textarea>
          <p v-if="admitError" class="error-text">{{ admitError }}</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="closeAdmitModal">Cancel</button>
          <button class="btn btn-danger" @click="submitAdmit" :disabled="isSubmitting">
            {{ isSubmitting ? 'Processing...' : 'Confirm Admit' }}
          </button>
        </div>
      </div>
    </div>
  </HospitalAdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import HospitalAdminLayout from '../../Layouts/HospitalAdminLayout.vue';

const props = defineProps({
  patientRecord: Object,
  department: Object,
  currentUser: Object,
  availableDepartments: Array,
});

// ── Patient photo ─────────────────────────────────────────────────────────
const photoUrl = ref(null);
const photoLoading = ref(false);

onMounted(async () => {
  if (!props.patientRecord?.patient?.photo_object_key) return;
  photoLoading.value = true;
  try {
    // Fetch via axios so the JWT Authorization header is included,
    // then create a blob URL for the <img> tag
    const res = await axios.get(
      `/api/v1/patients/${props.patientRecord.patient.id}/photo`,
      { responseType: 'blob' }
    );
    photoUrl.value = URL.createObjectURL(res.data);
  } catch {
    // 404 = no photo on file, silently ignore
  } finally {
    photoLoading.value = false;
  }
});

// Modal state
const showNoteModal = ref(false);
const showCompleteModal = ref(false);
const showRouteModal = ref(false);
const showAdmitModal = ref(false);

const noteText = ref('');
const completeNotes = ref('');
const routeNotes = ref('');
const admitNotes = ref('');
const routeTarget = ref('pharmacy'); // 'pharmacy' | 'laboratory'
const selectedDepartmentId = ref('');

const noteError = ref('');
const completeError = ref('');
const routeError = ref('');
const admitError = ref('');
const isSubmitting = ref(false);

// Filtered departments for routing
const filteredDepartments = computed(() => {
  if (!props.availableDepartments) return [];
  const typeMap = { pharmacy: 'pharmacy', laboratory: 'laboratory' };
  const targetType = typeMap[routeTarget.value];
  return props.availableDepartments.filter(d => d.type === targetType);
});

// Helpers
const goBack = () => router.visit('/department/queue');

const capitalize = (str) => str ? str.charAt(0).toUpperCase() + str.slice(1) : '';

const formatStatus = (status) => {
  const map = {
    active: 'Active', in_transit: 'In Transit', admitted: 'Admitted',
    discharged: 'Discharged', completed: 'Completed', pending_payment: 'Pending Payment',
  };
  return map[status] || status;
};

const statusClass = (status) => {
  const map = {
    active: 'badge-success', in_transit: 'badge-warning', admitted: 'badge-info',
    discharged: 'badge-secondary', completed: 'badge-secondary',
  };
  return map[status] || 'badge-secondary';
};

const formatEventType = (type) => {
  const map = {
    registered: 'Registered', routed: 'Routed', admitted: 'Admitted',
    discharged: 'Discharged', note_added: 'Note Added',
    document_attached: 'Document Attached', status_changed: 'Status Changed',
  };
  return map[type] || type;
};

const eventDotClass = (type) => {
  const map = {
    registered: 'dot-blue', routed: 'dot-green', admitted: 'dot-orange',
    discharged: 'dot-gray', note_added: 'dot-purple', document_attached: 'dot-teal',
  };
  return map[type] || 'dot-gray';
};

const formatDateTime = (dateString) => {
  const date = new Date(dateString);
  return date.toLocaleString('en-US', {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
};

// Note modal
const openNoteModal = () => {
  noteText.value = '';
  noteError.value = '';
  showNoteModal.value = true;
};
const closeNoteModal = () => { showNoteModal.value = false; };
const submitNote = async () => {
  if (!noteText.value.trim()) { noteError.value = 'Note cannot be empty.'; return; }
  isSubmitting.value = true;
  noteError.value = '';
  try {
    await axios.post(`/api/v1/patient-records/${props.patientRecord.id}/notes`, {
      notes: noteText.value,
    });
    closeNoteModal();
    router.reload({ only: ['patientRecord'] });
  } catch (err) {
    noteError.value = err.response?.data?.error?.message || 'Failed to save note.';
  } finally {
    isSubmitting.value = false;
  }
};

// Complete modal
const openCompleteModal = () => {
  completeNotes.value = '';
  completeError.value = '';
  showCompleteModal.value = true;
};
const closeCompleteModal = () => { showCompleteModal.value = false; };
const submitComplete = async () => {
  isSubmitting.value = true;
  completeError.value = '';
  try {
    await axios.post(`/api/v1/patient-records/${props.patientRecord.id}/complete`, {
      notes: completeNotes.value || undefined,
    });
    closeCompleteModal();
    router.visit('/department/queue');
  } catch (err) {
    completeError.value = err.response?.data?.error?.message || 'Failed to complete step.';
  } finally {
    isSubmitting.value = false;
  }
};

// Route modal (doctor)
const openRouteModal = (target) => {
  routeTarget.value = target;
  routeNotes.value = '';
  routeError.value = '';
  selectedDepartmentId.value = '';
  showRouteModal.value = true;
};
const closeRouteModal = () => { showRouteModal.value = false; };
const submitRoute = async () => {
  if (!selectedDepartmentId.value) { routeError.value = 'Please select a department.'; return; }
  isSubmitting.value = true;
  routeError.value = '';
  try {
    const payload = { department_id: selectedDepartmentId.value };
    if (routeTarget.value === 'pharmacy') {
      payload.prescription_notes = routeNotes.value || undefined;
    } else {
      payload.investigation_notes = routeNotes.value || undefined;
    }
    await axios.post(`/api/v1/patient-records/${props.patientRecord.id}/route`, payload);
    closeRouteModal();
    router.visit('/department/queue');
  } catch (err) {
    routeError.value = err.response?.data?.error?.message || 'Failed to route patient.';
  } finally {
    isSubmitting.value = false;
  }
};

// Admit modal (doctor)
const openAdmitModal = () => {
  admitNotes.value = '';
  admitError.value = '';
  showAdmitModal.value = true;
};
const closeAdmitModal = () => { showAdmitModal.value = false; };
const submitAdmit = async () => {
  isSubmitting.value = true;
  admitError.value = '';
  try {
    await axios.post(`/api/v1/patient-records/${props.patientRecord.id}/admit`, {
      notes: admitNotes.value || undefined,
    });
    closeAdmitModal();
    router.visit('/department/queue');
  } catch (err) {
    admitError.value = err.response?.data?.error?.message || 'Failed to admit patient.';
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<style scoped>
.patient-detail {
  padding: 2rem;
  max-width: 1400px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.btn-back {
  white-space: nowrap;
}

.header-info {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
}

.header-info h1 {
  font-size: 1.75rem;
  color: #2c3e50;
  margin: 0;
}

.patient-number {
  font-family: monospace;
  font-weight: 600;
  color: #007bff;
  font-size: 1rem;
}

.content-grid {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 1.5rem;
  align-items: start;
}

@media (max-width: 900px) {
  .content-grid {
    grid-template-columns: 1fr;
  }
}

.left-column, .right-column {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.card {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.card-header {
  padding: 1rem 1.5rem;
  background: #f8f9fa;
  font-weight: 600;
  color: #495057;
  font-size: 0.875rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 1px solid #e9ecef;
}

.card-body {
  padding: 1.5rem;
}

.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.info-item.full-width {
  grid-column: 1 / -1;
}

.info-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: #6c757d;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.info-value {
  color: #2c3e50;
  font-size: 0.9375rem;
}

.info-value.mono {
  font-family: monospace;
  color: #007bff;
}

.info-value.complaint {
  font-style: italic;
}

/* Patient photo */
.photo-section {
  display: flex;
  justify-content: center;
  margin-bottom: 1.25rem;
}

.patient-photo {
  width: 140px;
  height: 140px;
  object-fit: cover;
  border-radius: 8px;
  border: 2px solid #e9ecef;
}

.photo-loading,
.photo-placeholder {
  width: 140px;
  height: 140px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f8f9fa;
  border-radius: 8px;
  border: 2px dashed #dee2e6;
  color: #6c757d;
  font-size: 0.875rem;
  text-align: center;
}

/* Timeline */
.timeline {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.timeline-item {
  display: flex;
  gap: 1rem;
  position: relative;
  padding-bottom: 1.5rem;
}

.timeline-item:last-child {
  padding-bottom: 0;
}

.timeline-item:not(:last-child)::before {
  content: '';
  position: absolute;
  left: 7px;
  top: 16px;
  bottom: 0;
  width: 2px;
  background: #e9ecef;
}

.timeline-dot {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  flex-shrink: 0;
  margin-top: 2px;
}

.dot-blue { background: #007bff; }
.dot-green { background: #28a745; }
.dot-orange { background: #fd7e14; }
.dot-gray { background: #6c757d; }
.dot-purple { background: #6f42c1; }
.dot-teal { background: #20c997; }

.timeline-content {
  flex: 1;
}

.timeline-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.25rem;
}

.event-type {
  font-weight: 600;
  color: #2c3e50;
  font-size: 0.9375rem;
}

.event-time {
  font-size: 0.75rem;
  color: #6c757d;
}

.event-route {
  font-size: 0.875rem;
  color: #495057;
  margin-bottom: 0.25rem;
}

.event-notes {
  font-size: 0.875rem;
  color: #495057;
  background: #f8f9fa;
  padding: 0.5rem 0.75rem;
  border-radius: 4px;
  margin: 0.25rem 0;
  border-left: 3px solid #dee2e6;
}

.event-notes.prescription {
  border-left-color: #007bff;
}

.event-notes.investigation {
  border-left-color: #28a745;
}

.event-performer {
  font-size: 0.75rem;
  color: #6c757d;
  margin-top: 0.25rem;
}

.empty-history {
  color: #6c757d;
  text-align: center;
  padding: 1rem 0;
}

/* Actions card */
.action-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.btn-block {
  width: 100%;
  text-align: left;
}

.divider {
  border: none;
  border-top: 1px solid #e9ecef;
  margin: 0.25rem 0;
}

.section-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: #6c757d;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin: 0;
}

.in-transit-notice {
  color: #856404;
  background: #fff3cd;
  padding: 0.75rem 1rem;
  border-radius: 4px;
  font-size: 0.875rem;
  margin: 0;
}

.dept-name {
  font-weight: 600;
  color: #2c3e50;
  margin: 0 0 0.25rem 0;
}

.dept-type {
  font-size: 0.875rem;
  color: #6c757d;
  margin: 0;
  text-transform: capitalize;
}

/* Badges */
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

/* Buttons */
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
.btn-danger { background: #dc3545; color: white; }
.btn-danger:hover { background: #c82333; }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }

/* Form elements */
.form-group {
  margin-bottom: 1rem;
}

.form-label {
  display: block;
  font-size: 0.875rem;
  font-weight: 500;
  color: #495057;
  margin-bottom: 0.5rem;
}

.form-select {
  width: 100%;
  padding: 0.5rem 0.75rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 0.9375rem;
  background: white;
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
  max-width: 520px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
  max-height: 90vh;
  overflow-y: auto;
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
