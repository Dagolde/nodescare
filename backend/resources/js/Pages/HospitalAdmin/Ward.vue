<template>
  <HospitalAdminLayout>
    <div class="ward-page">
      <div class="page-header">
        <h1>🏥 Ward Management</h1>
        <p class="subtitle">Overview of all currently admitted patients</p>
      </div>

      <!-- Stats Banner -->
      <div class="stats-banner">
        <div class="stat-card teal">
          <div class="stat-icon">🛏️</div>
          <div class="stat-info">
            <div class="stat-number">{{ totalAdmitted }}</div>
            <div class="stat-label">Patients Admitted</div>
          </div>
        </div>
      </div>

      <!-- Patient Table -->
      <div class="card">
        <div class="card-header">
          <h2>Admitted Patients</h2>
          <button @click="refreshPage" class="btn btn-secondary btn-sm">
            🔄 Refresh
          </button>
        </div>

        <div v-if="admittedPatients.length === 0" class="empty-state">
          <div class="empty-icon">🛏️</div>
          <p>No patients currently admitted</p>
        </div>

        <table v-else class="data-table">
          <thead>
            <tr>
              <th>Patient</th>
              <th>Patient No.</th>
              <th>Gender</th>
              <th>Chief Complaint</th>
              <th>Ward / Department</th>
              <th>Admitted At</th>
              <th>Duration</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="record in admittedPatients" :key="record.id">
              <td>
                <div class="patient-name">{{ record.patient?.full_name ?? 'Unknown' }}</div>
                <div class="patient-phone">{{ record.patient?.phone ?? '' }}</div>
              </td>
              <td>
                <span class="badge badge-blue">{{ record.patient?.patient_number ?? 'N/A' }}</span>
              </td>
              <td>{{ record.patient?.gender ?? '—' }}</td>
              <td class="complaint">{{ record.patient?.chief_complaint ?? '—' }}</td>
              <td>{{ record.current_department ?? '—' }}</td>
              <td>{{ formatDate(record.admitted_at) }}</td>
              <td>
                <span :class="durationClass(record.admitted_at)">
                  {{ formatDuration(record.admitted_at) }}
                </span>
              </td>
              <td>
                <button
                  @click="dischargePatient(record)"
                  class="btn btn-danger btn-sm"
                  :disabled="discharging === record.id"
                >
                  {{ discharging === record.id ? 'Discharging...' : '🚪 Discharge' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Discharge Modal -->
      <div v-if="showDischargeModal" class="modal-overlay" @click.self="closeModal">
        <div class="modal">
          <h3>Discharge Patient</h3>
          <p>Discharge <strong>{{ selectedPatient?.patient?.full_name }}</strong> from the ward?</p>
          <div class="form-group">
            <label>Discharge Notes (optional)</label>
            <textarea
              v-model="dischargeNotes"
              rows="3"
              placeholder="e.g. Patient stable, discharged on doctor's orders"
              class="form-control"
            ></textarea>
          </div>
          <div class="modal-actions">
            <button @click="closeModal" class="btn btn-secondary">Cancel</button>
            <button @click="confirmDischarge" class="btn btn-danger" :disabled="discharging">
              {{ discharging ? 'Discharging...' : 'Confirm Discharge' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </HospitalAdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import HospitalAdminLayout from '@/Layouts/HospitalAdminLayout.vue'
import axios from 'axios'

const props = defineProps({
  admittedPatients: Array,
  totalAdmitted: Number,
})

const patients = ref([...props.admittedPatients])
const discharging = ref(null)
const showDischargeModal = ref(false)
const selectedPatient = ref(null)
const dischargeNotes = ref('')

function refreshPage() {
  window.location.reload()
}

function formatDate(dateStr) {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleString('en-GB', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

function formatDuration(dateStr) {
  if (!dateStr) return '—'
  const diff = Date.now() - new Date(dateStr).getTime()
  const days = Math.floor(diff / 86400000)
  const hours = Math.floor((diff % 86400000) / 3600000)
  if (days > 0) return `${days}d ${hours}h`
  const mins = Math.floor((diff % 3600000) / 60000)
  return hours > 0 ? `${hours}h ${mins}m` : `${mins}m`
}

function durationClass(dateStr) {
  if (!dateStr) return 'badge badge-grey'
  const days = (Date.now() - new Date(dateStr).getTime()) / 86400000
  if (days >= 3) return 'badge badge-red'
  if (days >= 1) return 'badge badge-orange'
  return 'badge badge-green'
}

function dischargePatient(record) {
  selectedPatient.value = record
  dischargeNotes.value = ''
  showDischargeModal.value = true
}

function closeModal() {
  showDischargeModal.value = false
  selectedPatient.value = null
  dischargeNotes.value = ''
}

async function confirmDischarge() {
  if (!selectedPatient.value) return
  discharging.value = selectedPatient.value.id

  try {
    await axios.post(`/hospital-admin/api/ward/discharge/${selectedPatient.value.id}`, {
      discharge_notes: dischargeNotes.value || undefined,
    })
    // Remove from list
    patients.value = patients.value.filter(p => p.id !== selectedPatient.value.id)
    closeModal()
    window.location.reload()
  } catch (err) {
    alert(err.response?.data?.error?.message ?? 'Discharge failed. Please try again.')
  } finally {
    discharging.value = null
  }
}
</script>

<style scoped>
.ward-page { padding: 24px; }
.page-header { margin-bottom: 24px; }
.page-header h1 { font-size: 24px; font-weight: 700; margin: 0; }
.subtitle { color: #6b7280; margin: 4px 0 0; }

.stats-banner { display: flex; gap: 16px; margin-bottom: 24px; }
.stat-card {
  display: flex; align-items: center; gap: 16px;
  padding: 20px 28px; border-radius: 12px; min-width: 200px;
}
.stat-card.teal { background: linear-gradient(135deg, #0d9488, #14b8a6); color: white; }
.stat-icon { font-size: 36px; }
.stat-number { font-size: 36px; font-weight: 700; line-height: 1; }
.stat-label { font-size: 14px; opacity: 0.85; margin-top: 4px; }

.card { background: white; border-radius: 12px; box-shadow: 0 1px 4px rgba(0,0,0,.08); overflow: hidden; }
.card-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 16px 20px; border-bottom: 1px solid #f3f4f6;
}
.card-header h2 { font-size: 16px; font-weight: 600; margin: 0; }

.empty-state { text-align: center; padding: 60px 20px; color: #9ca3af; }
.empty-icon { font-size: 48px; margin-bottom: 12px; }

.data-table { width: 100%; border-collapse: collapse; }
.data-table th {
  background: #f9fafb; padding: 12px 16px;
  text-align: left; font-size: 12px; font-weight: 600;
  color: #6b7280; text-transform: uppercase; letter-spacing: .05em;
}
.data-table td { padding: 14px 16px; border-top: 1px solid #f3f4f6; font-size: 14px; }
.data-table tr:hover td { background: #f9fafb; }

.patient-name { font-weight: 600; }
.patient-phone { font-size: 12px; color: #9ca3af; }
.complaint { max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.badge {
  display: inline-block; padding: 2px 10px; border-radius: 12px;
  font-size: 12px; font-weight: 600;
}
.badge-blue { background: #dbeafe; color: #1d4ed8; }
.badge-green { background: #dcfce7; color: #15803d; }
.badge-orange { background: #ffedd5; color: #c2410c; }
.badge-red { background: #fee2e2; color: #b91c1c; }
.badge-grey { background: #f3f4f6; color: #6b7280; }

.btn { padding: 8px 16px; border-radius: 8px; border: none; cursor: pointer; font-size: 14px; font-weight: 500; }
.btn-sm { padding: 6px 12px; font-size: 13px; }
.btn-secondary { background: #f3f4f6; color: #374151; }
.btn-secondary:hover { background: #e5e7eb; }
.btn-danger { background: #ef4444; color: white; }
.btn-danger:hover { background: #dc2626; }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }

.modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,.5);
  display: flex; align-items: center; justify-content: center; z-index: 50;
}
.modal {
  background: white; border-radius: 12px; padding: 28px;
  width: 100%; max-width: 480px; box-shadow: 0 20px 60px rgba(0,0,0,.2);
}
.modal h3 { font-size: 18px; font-weight: 700; margin: 0 0 8px; }
.modal p { color: #6b7280; margin: 0 0 16px; }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 6px; }
.form-control {
  width: 100%; padding: 10px 12px; border: 1px solid #d1d5db;
  border-radius: 8px; font-size: 14px; resize: vertical;
}
.modal-actions { display: flex; gap: 12px; justify-content: flex-end; }
</style>
