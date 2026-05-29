<template>
  <SuperAdminLayout>
    <div class="hospital-detail">
      <div class="header">
        <div>
          <a href="/admin/dashboard" class="back-link">← Back to Dashboard</a>
          <h1>{{ hospital.name }}</h1>
        </div>
        <div class="header-actions">
          <button
            v-if="hospital.status === 'active'"
            @click="confirmAction('suspend')"
            class="btn btn-warning"
          >
            Suspend Hospital
          </button>
          <button
            v-if="hospital.status === 'suspended'"
            @click="confirmAction('activate')"
            class="btn btn-success"
          >
            Activate Hospital
          </button>
        </div>
      </div>

      <div class="content-grid">
        <!-- Hospital Info Card -->
        <div class="card info-card">
          <h2>Hospital Information</h2>
          <div class="info-row">
            <span class="info-label">Name</span>
            <span class="info-value">{{ hospital.name }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">Subdomain</span>
            <span class="info-value">{{ hospital.subdomain }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">Plan</span>
            <span class="info-value">{{ hospital.plan?.name || 'N/A' }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">Status</span>
            <span :class="['status-badge', `status-${hospital.status}`]">{{ hospital.status }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">Created</span>
            <span class="info-value">{{ formatDate(hospital.created_at) }}</span>
          </div>
        </div>

        <!-- Stats Card -->
        <div class="card stats-card">
          <h2>Statistics</h2>
          <div v-if="loadingStats" class="loading-text">Loading stats...</div>
          <div v-else class="stats-grid">
            <div class="stat-item">
              <div class="stat-value">{{ stats.staff_count }}</div>
              <div class="stat-label">Staff</div>
            </div>
            <div class="stat-item">
              <div class="stat-value">{{ stats.department_count }}</div>
              <div class="stat-label">Departments</div>
            </div>
            <div class="stat-item">
              <div class="stat-value">{{ stats.patient_count }}</div>
              <div class="stat-label">Total Patients</div>
            </div>
            <div class="stat-item">
              <div class="stat-value">{{ stats.active_patients_today }}</div>
              <div class="stat-label">Patients Today</div>
            </div>
          </div>
        </div>

        <!-- Edit Form -->
        <div class="card edit-card">
          <h2>Edit Hospital</h2>
          <form @submit.prevent="saveChanges">
            <div class="form-group">
              <label>Name</label>
              <input v-model="editForm.name" type="text" />
            </div>
            <div class="form-group">
              <label>Subdomain</label>
              <input v-model="editForm.subdomain" type="text" />
            </div>
            <div class="form-group">
              <label>Plan</label>
              <select v-model="editForm.plan_id">
                <option value="">— Select Plan —</option>
                <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                  {{ plan.name }}
                </option>
              </select>
            </div>
            <div v-if="saveError" class="alert alert-error">{{ saveError }}</div>
            <div v-if="saveSuccess" class="alert alert-success">{{ saveSuccess }}</div>
            <div class="form-actions">
              <button type="submit" class="btn btn-primary" :disabled="saving">
                {{ saving ? 'Saving...' : 'Save Changes' }}
              </button>
            </div>
          </form>
        </div>

        <!-- Recent Audit Logs -->
        <div class="card audit-card">
          <h2>Recent Audit Logs</h2>
          <div v-if="loadingStats" class="loading-text">Loading logs...</div>
          <table v-else>
            <thead>
              <tr>
                <th>Action</th>
                <th>Actor</th>
                <th>Resource</th>
                <th>Timestamp</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="log in recentLogs" :key="log.id">
                <td>{{ log.action }}</td>
                <td>{{ log.actor_role || 'System' }}</td>
                <td>{{ log.resource_type }}</td>
                <td>{{ formatDate(log.created_at) }}</td>
              </tr>
              <tr v-if="recentLogs.length === 0">
                <td colspan="4" class="empty-state">No audit logs found.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Confirmation Modal -->
      <div v-if="showModal" class="modal-overlay" @click="closeModal">
        <div class="modal" @click.stop>
          <h3>Confirm {{ pendingAction }}</h3>
          <p>Are you sure you want to {{ pendingAction }} <strong>{{ hospital.name }}</strong>?</p>
          <div class="modal-actions">
            <button @click="closeModal" class="btn btn-secondary">Cancel</button>
            <button @click="executeAction" class="btn btn-primary" :disabled="actionLoading">
              {{ actionLoading ? 'Processing...' : 'Confirm' }}
            </button>
          </div>
        </div>
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
  hospital: Object,
  plans: Array,
});

const hospital = ref({ ...props.hospital });
const stats = ref({
  staff_count: 0,
  department_count: 0,
  patient_count: 0,
  active_patients_today: 0,
});
const recentLogs = ref([]);
const loadingStats = ref(true);

const editForm = reactive({
  name: props.hospital.name,
  subdomain: props.hospital.subdomain,
  plan_id: props.hospital.plan_id || '',
});

const saving = ref(false);
const saveError = ref('');
const saveSuccess = ref('');

const showModal = ref(false);
const pendingAction = ref('');
const actionLoading = ref(false);

const fetchDetails = async () => {
  try {
    const response = await axios.get(`/admin/api/hospitals/${props.hospital.id}`);
    const data = response.data.data;
    stats.value = data.stats || stats.value;
    recentLogs.value = data.recent_audit_logs || [];
  } catch (error) {
    console.error('Failed to fetch hospital details:', error);
  } finally {
    loadingStats.value = false;
  }
};

onMounted(fetchDetails);

const saveChanges = async () => {
  saving.value = true;
  saveError.value = '';
  saveSuccess.value = '';

  try {
    const response = await axios.put(`/admin/api/hospitals/${hospital.value.id}`, editForm);
    hospital.value = { ...hospital.value, ...response.data.data };
    saveSuccess.value = 'Hospital updated successfully.';
    setTimeout(() => { saveSuccess.value = ''; }, 4000);
  } catch (error) {
    saveError.value = error.response?.data?.error?.message || 'Failed to save changes.';
  } finally {
    saving.value = false;
  }
};

const confirmAction = (action) => {
  pendingAction.value = action;
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  pendingAction.value = '';
};

const executeAction = async () => {
  actionLoading.value = true;
  try {
    await axios.patch(`/admin/api/hospitals/${hospital.value.id}/${pendingAction.value}`);
    hospital.value.status = pendingAction.value === 'suspend' ? 'suspended' : 'active';
    closeModal();
  } catch (error) {
    alert(error.response?.data?.error?.message || `Failed to ${pendingAction.value} hospital.`);
  } finally {
    actionLoading.value = false;
  }
};

const formatDate = (dateString) => {
  if (!dateString) return '—';
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric', month: 'short', day: 'numeric',
  });
};
</script>

<style scoped>
.hospital-detail {
  padding: 2rem;
  max-width: 1400px;
  margin: 0 auto;
}

.back-link {
  color: #007bff;
  text-decoration: none;
  font-size: 0.9rem;
  display: block;
  margin-bottom: 0.5rem;
}

.back-link:hover { text-decoration: underline; }

.header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 2rem;
}

h1 { font-size: 2rem; color: #2c3e50; margin: 0; }

.header-actions { display: flex; gap: 0.75rem; }

.content-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
}

.card {
  background: white;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 2px 4px rgba(0,0,0,0.08);
}

.card h2 { margin-top: 0; margin-bottom: 1.25rem; color: #2c3e50; font-size: 1.25rem; }

.audit-card { grid-column: 1 / -1; }

/* Info rows */
.info-row {
  display: flex;
  justify-content: space-between;
  padding: 0.625rem 0;
  border-bottom: 1px solid #f0f0f0;
}
.info-row:last-child { border-bottom: none; }
.info-label { color: #6c757d; font-size: 0.9rem; }
.info-value { font-weight: 500; color: #2c3e50; }

/* Stats */
.stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
.stat-item { text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 6px; }
.stat-value { font-size: 2rem; font-weight: 700; color: #007bff; }
.stat-label { font-size: 0.8rem; color: #6c757d; margin-top: 0.25rem; }

.loading-text { color: #6c757d; font-style: italic; }

/* Form */
.form-group { margin-bottom: 1.25rem; }
.form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #495057; }
.form-group input,
.form-group select {
  width: 100%;
  padding: 0.625rem;
  border: 1px solid #ced4da;
  border-radius: 4px;
  font-size: 1rem;
  box-sizing: border-box;
}
.form-actions { display: flex; justify-content: flex-end; margin-top: 1rem; }

/* Table */
table { width: 100%; border-collapse: collapse; }
thead { background: #f8f9fa; }
th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #e9ecef; font-size: 0.875rem; }
th { font-weight: 600; color: #495057; }
.empty-state { text-align: center; color: #6c757d; padding: 1.5rem; }

/* Status badge */
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

/* Buttons */
.btn {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 4px;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-primary   { background: #007bff; color: white; }
.btn-primary:hover:not(:disabled) { background: #0056b3; }
.btn-warning   { background: #ffc107; color: #212529; }
.btn-warning:hover { background: #e0a800; }
.btn-success   { background: #28a745; color: white; }
.btn-success:hover { background: #218838; }
.btn-secondary { background: #6c757d; color: white; }
.btn-secondary:hover { background: #5a6268; }
.btn:disabled  { opacity: 0.6; cursor: not-allowed; }

/* Alerts */
.alert { padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1rem; }
.alert-error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }

/* Modal */
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
  max-width: 480px;
  width: 90%;
}
.modal h3 { margin-top: 0; text-transform: capitalize; }
.modal p  { color: #495057; margin-bottom: 1.5rem; }
.modal-actions { display: flex; gap: 1rem; justify-content: flex-end; }
</style>
