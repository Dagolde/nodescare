<template>
  <SuperAdminLayout>
    <div class="plans-page">
      <div class="header">
        <h1>Subscription Plans</h1>
        <button @click="openCreateModal" class="btn btn-primary">Create Plan</button>
      </div>

      <div class="plans-table">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>API Rate Limit</th>
              <th>Feature Flags</th>
              <th>Hospitals</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="plan in localPlans" :key="plan.id">
              <td>{{ plan.name }}</td>
              <td>{{ plan.api_rate_limit }} req/min</td>
              <td>
                <div class="feature-tags">
                  <span
                    v-for="(enabled, flag) in (plan.feature_flags || {})"
                    :key="flag"
                    :class="['feature-tag', enabled ? 'tag-enabled' : 'tag-disabled']"
                  >
                    {{ formatFlagName(flag) }}
                  </span>
                  <span v-if="!plan.feature_flags || Object.keys(plan.feature_flags).length === 0" class="text-muted">
                    None
                  </span>
                </div>
              </td>
              <td>{{ plan.tenants_count ?? 0 }}</td>
              <td class="actions">
                <button @click="openEditModal(plan)" class="btn btn-secondary btn-sm">Edit</button>
                <button @click="confirmDelete(plan)" class="btn btn-danger btn-sm">Delete</button>
              </td>
            </tr>
            <tr v-if="localPlans.length === 0">
              <td colspan="5" class="empty-state">No plans found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Create / Edit Modal -->
      <div v-if="showModal" class="modal-overlay" @click="closeModal">
        <div class="modal" @click.stop>
          <h3>{{ editingPlan ? 'Edit Plan' : 'Create Plan' }}</h3>

          <div class="form-group">
            <label>Name</label>
            <input v-model="form.name" type="text" placeholder="e.g. Basic, Pro, Enterprise" />
          </div>

          <div class="form-group">
            <label>API Rate Limit (req/min)</label>
            <input v-model.number="form.api_rate_limit" type="number" min="1" max="10000" />
          </div>

          <div class="form-group">
            <label>Feature Flags</label>
            <div class="flag-checkboxes">
              <label v-for="flag in availableFlags" :key="flag" class="checkbox-label">
                <input
                  type="checkbox"
                  :value="flag"
                  v-model="selectedFlags"
                />
                {{ formatFlagName(flag) }}
              </label>
            </div>
          </div>

          <div v-if="formError" class="alert alert-error">{{ formError }}</div>

          <div class="modal-actions">
            <button @click="closeModal" class="btn btn-secondary">Cancel</button>
            <button @click="submitForm" class="btn btn-primary" :disabled="saving">
              {{ saving ? 'Saving...' : (editingPlan ? 'Update' : 'Create') }}
            </button>
          </div>
        </div>
      </div>

      <!-- Delete Confirmation Modal -->
      <div v-if="showDeleteModal" class="modal-overlay" @click="closeDeleteModal">
        <div class="modal" @click.stop>
          <h3>Delete Plan</h3>
          <p v-if="deletingPlan?.tenants_count > 0" class="alert alert-error">
            Cannot delete <strong>{{ deletingPlan?.name }}</strong> — {{ deletingPlan?.tenants_count }} hospital(s) are currently on this plan.
          </p>
          <p v-else>
            Are you sure you want to delete <strong>{{ deletingPlan?.name }}</strong>? This action cannot be undone.
          </p>
          <div class="modal-actions">
            <button @click="closeDeleteModal" class="btn btn-secondary">Cancel</button>
            <button
              v-if="!deletingPlan?.tenants_count"
              @click="executeDelete"
              class="btn btn-danger"
              :disabled="saving"
            >
              {{ saving ? 'Deleting...' : 'Delete' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </SuperAdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import axios from 'axios';
import SuperAdminLayout from '../../Layouts/SuperAdminLayout.vue';

const props = defineProps({
  plans: Array,
});

const localPlans = ref([...props.plans]);

const availableFlags = [
  'sms_notifications',
  'lab_module',
  'pharmacy_module',
  'appointments',
  'mobile_app',
];

// Modal state
const showModal = ref(false);
const showDeleteModal = ref(false);
const editingPlan = ref(null);
const deletingPlan = ref(null);
const saving = ref(false);
const formError = ref('');
const selectedFlags = ref([]);

const form = reactive({
  name: '',
  api_rate_limit: 60,
});

const formatFlagName = (key) => {
  return key.replace('feature_', '').split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
};

const openCreateModal = () => {
  editingPlan.value = null;
  form.name = '';
  form.api_rate_limit = 60;
  selectedFlags.value = [];
  formError.value = '';
  showModal.value = true;
};

const openEditModal = (plan) => {
  editingPlan.value = plan;
  form.name = plan.name;
  form.api_rate_limit = plan.api_rate_limit;
  selectedFlags.value = Object.entries(plan.feature_flags || {})
    .filter(([, v]) => v)
    .map(([k]) => k.replace('feature_', ''));
  formError.value = '';
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  editingPlan.value = null;
};

const confirmDelete = (plan) => {
  deletingPlan.value = plan;
  showDeleteModal.value = true;
};

const closeDeleteModal = () => {
  showDeleteModal.value = false;
  deletingPlan.value = null;
};

const buildFeatureFlags = () => {
  const flags = {};
  availableFlags.forEach(f => {
    flags[`feature_${f}`] = selectedFlags.value.includes(f);
  });
  return flags;
};

const submitForm = async () => {
  if (!form.name.trim()) {
    formError.value = 'Plan name is required.';
    return;
  }

  saving.value = true;
  formError.value = '';

  try {
    const payload = {
      name: form.name,
      api_rate_limit: form.api_rate_limit,
      feature_flags: buildFeatureFlags(),
    };

    if (editingPlan.value) {
      const response = await axios.put(`/admin/api/plans/${editingPlan.value.id}`, payload);
      const idx = localPlans.value.findIndex(p => p.id === editingPlan.value.id);
      if (idx !== -1) localPlans.value[idx] = { ...localPlans.value[idx], ...response.data.data };
    } else {
      const response = await axios.post('/admin/api/plans', payload);
      localPlans.value.push({ ...response.data.data, tenants_count: 0 });
    }

    closeModal();
  } catch (error) {
    formError.value = error.response?.data?.error?.message
      || error.response?.data?.message
      || JSON.stringify(error.response?.data)
      || 'Failed to save plan.';
  } finally {
    saving.value = false;
  }
};

const executeDelete = async () => {
  saving.value = true;
  try {
    await axios.delete(`/admin/api/plans/${deletingPlan.value.id}`);
    localPlans.value = localPlans.value.filter(p => p.id !== deletingPlan.value.id);
    closeDeleteModal();
  } catch (error) {
    alert(error.response?.data?.error?.message || 'Failed to delete plan.');
  } finally {
    saving.value = false;
  }
};
</script>

<style scoped>
.plans-page {
  padding: 2rem;
  max-width: 1200px;
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

.plans-table {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  overflow: hidden;
}

table { width: 100%; border-collapse: collapse; }
thead { background: #f8f9fa; }
th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #e9ecef; }
th { font-weight: 600; color: #495057; }

.feature-tags { display: flex; flex-wrap: wrap; gap: 0.25rem; }
.feature-tag {
  padding: 0.2rem 0.5rem;
  border-radius: 10px;
  font-size: 0.75rem;
  font-weight: 500;
}
.tag-enabled  { background: #d4edda; color: #155724; }
.tag-disabled { background: #f8d7da; color: #721c24; }
.text-muted   { color: #6c757d; font-size: 0.875rem; }

.empty-state { text-align: center; color: #6c757d; padding: 2rem; }

.actions { display: flex; gap: 0.5rem; }

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
.btn-secondary { background: #6c757d; color: white; }
.btn-secondary:hover { background: #5a6268; }
.btn-danger    { background: #dc3545; color: white; }
.btn-danger:hover:not(:disabled) { background: #c82333; }
.btn-sm { padding: 0.375rem 0.75rem; font-size: 0.8125rem; }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }

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
  max-width: 520px;
  width: 90%;
}

.modal h3 { margin-top: 0; margin-bottom: 1.5rem; color: #2c3e50; }

.form-group { margin-bottom: 1.25rem; }
.form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #495057; }
.form-group input[type="text"],
.form-group input[type="number"] {
  width: 100%;
  padding: 0.625rem;
  border: 1px solid #ced4da;
  border-radius: 4px;
  font-size: 1rem;
  box-sizing: border-box;
}

.flag-checkboxes { display: flex; flex-direction: column; gap: 0.5rem; }
.checkbox-label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.9rem; }

.modal-actions { display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem; }

.alert { padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1rem; }
.alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
</style>
