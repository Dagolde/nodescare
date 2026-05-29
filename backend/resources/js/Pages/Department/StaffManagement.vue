<template>
  <DepartmentLayout :department="department" :currentUser="currentUser">
    <div class="staff-management">
      <div class="page-header">
        <div>
          <h1>Staff Management</h1>
          <p class="subtitle">Manage your department's staff members</p>
        </div>
        <button @click="openCreateModal" class="btn btn-primary">+ Add Staff</button>
      </div>

      <!-- Filters -->
      <div class="filters-bar">
        <div class="search-box">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search by name or email..."
            class="search-input"
            @input="onSearchInput"
          />
        </div>
        <select v-model="filters.role" class="filter-select" @change="loadStaff">
          <option value="">All Roles</option>
          <option v-for="role in allowedRoles" :key="role.value" :value="role.value">
            {{ role.label }}
          </option>
        </select>
        <select v-model="filters.is_active" class="filter-select" @change="loadStaff">
          <option value="">All Status</option>
          <option value="1">Active</option>
          <option value="0">Inactive</option>
        </select>
      </div>

      <!-- Staff Table -->
      <div class="card">
        <div v-if="isLoading" class="loading-state">
          <div class="spinner"></div>
          <p>Loading staff...</p>
        </div>

        <div v-else-if="staff.length === 0" class="empty-state">
          <p>No staff members found.</p>
          <button @click="openCreateModal" class="btn btn-primary">Add First Staff Member</button>
        </div>

        <div v-else>
          <div class="table-container">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Phone</th>
                  <th>Role</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="member in staff" :key="member.id">
                  <td>
                    <div class="staff-name">{{ member.name }}</div>
                  </td>
                  <td>{{ member.email }}</td>
                  <td>{{ member.phone || '—' }}</td>
                  <td>
                    <span class="role-badge">{{ formatRole(member.role) }}</span>
                  </td>
                  <td>
                    <span class="status-badge" :class="member.is_active ? 'status-active' : 'status-inactive'">
                      {{ member.is_active ? 'Active' : 'Inactive' }}
                    </span>
                  </td>
                  <td>
                    <div class="action-buttons">
                      <button @click="openEditModal(member)" class="btn btn-sm btn-secondary">Edit</button>
                      <button
                        v-if="member.is_active"
                        @click="confirmDeactivate(member)"
                        class="btn btn-sm btn-danger"
                      >Deactivate</button>
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

      <!-- Create/Edit Modal -->
      <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
        <div class="modal">
          <div class="modal-header">
            <h3>{{ editingStaff ? 'Edit Staff Member' : 'Add Staff Member' }}</h3>
            <button @click="closeModal" class="modal-close">✕</button>
          </div>

          <form @submit.prevent="saveStaff" class="modal-form">
            <div class="form-group">
              <label>Full Name <span class="required">*</span></label>
              <input
                v-model="form.name"
                type="text"
                class="form-input"
                :class="{ 'input-error': formErrors.name }"
                required
              />
              <span v-if="formErrors.name" class="error-text">{{ formErrors.name }}</span>
            </div>

            <div class="form-group">
              <label>Email Address <span class="required">*</span></label>
              <input
                v-model="form.email"
                type="email"
                class="form-input"
                :class="{ 'input-error': formErrors.email }"
                :disabled="!!editingStaff"
                required
              />
              <span v-if="formErrors.email" class="error-text">{{ formErrors.email }}</span>
            </div>

            <div class="form-group">
              <label>Phone Number <span class="required">*</span></label>
              <input
                v-model="form.phone"
                type="tel"
                class="form-input"
                :class="{ 'input-error': formErrors.phone }"
                required
              />
              <span v-if="formErrors.phone" class="error-text">{{ formErrors.phone }}</span>
            </div>

            <div v-if="!editingStaff" class="form-group">
              <label>Password <span class="required">*</span></label>
              <input
                v-model="form.password"
                type="password"
                class="form-input"
                :class="{ 'input-error': formErrors.password }"
                required
                minlength="10"
              />
              <span class="hint-text">Min 10 characters, must include uppercase, lowercase, number, and symbol.</span>
              <span v-if="formErrors.password" class="error-text">{{ formErrors.password }}</span>
            </div>

            <div class="form-group">
              <label>Role <span class="required">*</span></label>
              <select
                v-model="form.role"
                class="form-input"
                :class="{ 'input-error': formErrors.role }"
                required
              >
                <option value="">Select a role...</option>
                <option v-for="role in allowedRoles" :key="role.value" :value="role.value">
                  {{ role.label }}
                </option>
              </select>
              <span v-if="formErrors.role" class="error-text">{{ formErrors.role }}</span>
            </div>

            <div v-if="editingStaff" class="form-group">
              <label class="toggle-label">
                <span>Active Status</span>
                <div class="toggle-switch" :class="{ active: form.is_active }" @click="form.is_active = !form.is_active">
                  <div class="toggle-knob"></div>
                </div>
                <span class="toggle-text">{{ form.is_active ? 'Active' : 'Inactive' }}</span>
              </label>
            </div>

            <div v-if="apiError" class="api-error">{{ apiError }}</div>

            <div class="modal-actions">
              <button type="button" @click="closeModal" class="btn btn-secondary">Cancel</button>
              <button type="submit" class="btn btn-primary" :disabled="isSaving">
                {{ isSaving ? 'Saving...' : (editingStaff ? 'Update Staff' : 'Create Staff') }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Deactivate Confirmation Modal -->
      <div v-if="showDeactivateConfirm" class="modal-overlay" @click.self="showDeactivateConfirm = false">
        <div class="modal modal-sm">
          <div class="modal-header">
            <h3>Deactivate Staff Member</h3>
          </div>
          <div class="confirm-body">
            <p>Are you sure you want to deactivate <strong>{{ deactivatingStaff?.name }}</strong>?</p>
            <p class="warning-text">All active sessions will be immediately invalidated.</p>
          </div>
          <div class="modal-actions">
            <button @click="showDeactivateConfirm = false" class="btn btn-secondary">Cancel</button>
            <button @click="deactivateStaff" class="btn btn-danger" :disabled="isSaving">
              {{ isSaving ? 'Deactivating...' : 'Yes, Deactivate' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Success Toast -->
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
  initialStaff: { type: Object, default: () => ({ data: [], meta: { pagination: {} } }) },
});

const allowedRoles = [
  { value: 'doctor', label: 'Doctor' },
  { value: 'front_desk_officer', label: 'Front Desk Officer' },
  { value: 'pharmacist', label: 'Pharmacist' },
  { value: 'lab_technician', label: 'Lab Technician' },
  { value: 'department_staff', label: 'Department Staff' },
];

// State
const staff = ref(props.initialStaff?.data || []);
const pagination = ref(props.initialStaff?.meta?.pagination || {});
const isLoading = ref(false);
const searchQuery = ref('');
const searchTimeout = ref(null);
const filters = reactive({ role: '', is_active: '' });

const showModal = ref(false);
const editingStaff = ref(null);
const isSaving = ref(false);
const apiError = ref('');
const formErrors = reactive({});
const successMessage = ref('');

const showDeactivateConfirm = ref(false);
const deactivatingStaff = ref(null);

const form = reactive({
  name: '',
  email: '',
  phone: '',
  password: '',
  role: '',
  is_active: true,
});

// Methods
const loadStaff = async (page = 1) => {
  isLoading.value = true;
  try {
    const params = { page, per_page: 20 };
    if (searchQuery.value) params.search = searchQuery.value;
    if (filters.role) params.role = filters.role;
    if (filters.is_active !== '') params.is_active = filters.is_active;

    const response = await axios.get('/department/api/staff', { params });
    if (response.data.success) {
      staff.value = response.data.data;
      pagination.value = response.data.meta?.pagination || {};
    }
  } catch (error) {
    console.error('Failed to load staff:', error);
  } finally {
    isLoading.value = false;
  }
};

const onSearchInput = () => {
  clearTimeout(searchTimeout.value);
  searchTimeout.value = setTimeout(() => loadStaff(), 300);
};

const goToPage = (page) => {
  if (page >= 1 && page <= pagination.value.last_page) {
    loadStaff(page);
  }
};

const openCreateModal = () => {
  editingStaff.value = null;
  Object.assign(form, { name: '', email: '', phone: '', password: '', role: '', is_active: true });
  Object.keys(formErrors).forEach(k => delete formErrors[k]);
  apiError.value = '';
  showModal.value = true;
};

const openEditModal = (member) => {
  editingStaff.value = member;
  Object.assign(form, {
    name: member.name,
    email: member.email,
    phone: member.phone || '',
    password: '',
    role: member.role,
    is_active: member.is_active,
  });
  Object.keys(formErrors).forEach(k => delete formErrors[k]);
  apiError.value = '';
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  editingStaff.value = null;
};

const validateForm = () => {
  Object.keys(formErrors).forEach(k => delete formErrors[k]);
  let valid = true;

  if (!form.name.trim()) { formErrors.name = 'Name is required.'; valid = false; }
  if (!editingStaff.value && !form.email.trim()) { formErrors.email = 'Email is required.'; valid = false; }
  if (!form.phone.trim()) { formErrors.phone = 'Phone is required.'; valid = false; }
  if (!editingStaff.value && !form.password) { formErrors.password = 'Password is required.'; valid = false; }
  if (!editingStaff.value && form.password && form.password.length < 10) {
    formErrors.password = 'Password must be at least 10 characters.'; valid = false;
  }
  if (!form.role) { formErrors.role = 'Role is required.'; valid = false; }

  return valid;
};

const saveStaff = async () => {
  if (!validateForm()) return;

  isSaving.value = true;
  apiError.value = '';

  try {
    const payload = { name: form.name, phone: form.phone, role: form.role };

    if (editingStaff.value) {
      payload.is_active = form.is_active;
      await axios.put(`/department/api/staff/${editingStaff.value.id}`, payload);
      showSuccess('Staff member updated successfully.');
    } else {
      payload.email = form.email;
      payload.password = form.password;
      await axios.post('/department/api/staff', payload);
      showSuccess('Staff member created successfully.');
    }

    closeModal();
    await loadStaff();
  } catch (error) {
    const errors = error.response?.data?.errors;
    if (errors) {
      Object.keys(errors).forEach(key => {
        formErrors[key] = errors[key][0];
      });
    } else {
      apiError.value = error.response?.data?.error?.message || 'Failed to save staff member.';
    }
  } finally {
    isSaving.value = false;
  }
};

const confirmDeactivate = (member) => {
  deactivatingStaff.value = member;
  showDeactivateConfirm.value = true;
};

const deactivateStaff = async () => {
  if (!deactivatingStaff.value) return;
  isSaving.value = true;

  try {
    await axios.patch(`/department/api/staff/${deactivatingStaff.value.id}/deactivate`);
    showDeactivateConfirm.value = false;
    deactivatingStaff.value = null;
    showSuccess('Staff member deactivated successfully.');
    await loadStaff();
  } catch (error) {
    apiError.value = error.response?.data?.error?.message || 'Failed to deactivate staff member.';
    showDeactivateConfirm.value = false;
  } finally {
    isSaving.value = false;
  }
};

const showSuccess = (message) => {
  successMessage.value = message;
  setTimeout(() => { successMessage.value = ''; }, 3000);
};

const formatRole = (role) => {
  return role.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

onMounted(() => {
  if (!props.initialStaff?.data?.length) {
    loadStaff();
  }
});
</script>

<style scoped>
.staff-management {
  padding: 2rem;
  max-width: 1200px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 2rem;
}

h1 { font-size: 2rem; color: #2c3e50; margin: 0 0 0.25rem 0; }
.subtitle { color: #6c757d; margin: 0; }

.filters-bar {
  display: flex;
  gap: 1rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}

.search-box { flex: 1; min-width: 200px; }

.search-input, .filter-select {
  width: 100%;
  padding: 0.625rem 0.875rem;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 0.9375rem;
  background: white;
}

.filter-select { min-width: 150px; width: auto; }

.card {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  overflow: hidden;
}

.loading-state, .empty-state {
  padding: 3rem;
  text-align: center;
  color: #6c757d;
}

.spinner {
  width: 40px; height: 40px;
  border: 3px solid #e9ecef;
  border-top-color: #007bff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin: 0 auto 1rem;
}

@keyframes spin { to { transform: rotate(360deg); } }

.table-container { overflow-x: auto; }

.data-table { width: 100%; border-collapse: collapse; }
.data-table thead { background: #f8f9fa; }
.data-table th {
  padding: 0.875rem 1rem;
  text-align: left;
  font-weight: 600;
  color: #495057;
  font-size: 0.875rem;
  border-bottom: 2px solid #e9ecef;
}
.data-table td {
  padding: 0.875rem 1rem;
  border-bottom: 1px solid #f0f0f0;
  color: #495057;
}
.data-table tbody tr:hover { background: #f8f9fa; }

.staff-name { font-weight: 500; color: #2c3e50; }

.role-badge {
  display: inline-block;
  padding: 0.25rem 0.625rem;
  border-radius: 12px;
  font-size: 0.8125rem;
  font-weight: 500;
  background: #e7f3ff;
  color: #0066cc;
}

.status-badge {
  display: inline-block;
  padding: 0.25rem 0.625rem;
  border-radius: 12px;
  font-size: 0.8125rem;
  font-weight: 500;
}
.status-active { background: #d4edda; color: #155724; }
.status-inactive { background: #f8d7da; color: #721c24; }

.action-buttons { display: flex; gap: 0.5rem; }

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  padding: 1rem;
  border-top: 1px solid #e9ecef;
}
.page-info { color: #6c757d; font-size: 0.875rem; }

.btn {
  padding: 0.625rem 1.25rem;
  border: none;
  border-radius: 6px;
  font-size: 0.9375rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-primary { background: #007bff; color: white; }
.btn-primary:hover:not(:disabled) { background: #0056b3; }
.btn-secondary { background: #6c757d; color: white; }
.btn-secondary:hover:not(:disabled) { background: #545b62; }
.btn-danger { background: #dc3545; color: white; }
.btn-danger:hover:not(:disabled) { background: #c82333; }
.btn-sm { padding: 0.375rem 0.75rem; font-size: 0.8125rem; }

.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.5);
  display: flex; align-items: center; justify-content: center;
  z-index: 1000;
}
.modal {
  background: white;
  border-radius: 8px;
  width: 90%;
  max-width: 480px;
  max-height: 90vh;
  overflow-y: auto;
}
.modal-sm { max-width: 400px; }
.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem 1.5rem 0;
}
.modal-header h3 { margin: 0; font-size: 1.25rem; color: #2c3e50; }
.modal-close {
  background: none; border: none;
  font-size: 1.25rem; cursor: pointer; color: #6c757d;
}
.modal-form { padding: 1.5rem; }

.form-group { margin-bottom: 1.25rem; }
.form-group label {
  display: block;
  margin-bottom: 0.375rem;
  font-weight: 500;
  color: #495057;
  font-size: 0.9375rem;
}
.required { color: #dc3545; }
.form-input {
  width: 100%;
  padding: 0.625rem 0.875rem;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 0.9375rem;
  box-sizing: border-box;
}
.form-input:focus { outline: none; border-color: #007bff; box-shadow: 0 0 0 2px rgba(0,123,255,0.15); }
.input-error { border-color: #dc3545; }
.error-text { color: #dc3545; font-size: 0.8125rem; margin-top: 0.25rem; display: block; }
.hint-text { color: #6c757d; font-size: 0.8125rem; margin-top: 0.25rem; display: block; }

.toggle-label {
  display: flex; align-items: center; gap: 0.75rem; cursor: pointer;
}
.toggle-switch {
  width: 44px; height: 24px;
  background: #ccc; border-radius: 12px;
  position: relative; cursor: pointer; transition: background 0.2s;
}
.toggle-switch.active { background: #28a745; }
.toggle-knob {
  width: 20px; height: 20px;
  background: white; border-radius: 50%;
  position: absolute; top: 2px; left: 2px;
  transition: left 0.2s;
}
.toggle-switch.active .toggle-knob { left: 22px; }
.toggle-text { font-size: 0.875rem; color: #495057; }

.api-error {
  padding: 0.75rem 1rem;
  background: #f8d7da; color: #721c24;
  border-radius: 6px; font-size: 0.875rem;
  margin-bottom: 1rem;
}

.confirm-body { padding: 1rem 1.5rem; }
.confirm-body p { margin: 0 0 0.5rem; color: #495057; }
.warning-text { color: #856404; font-size: 0.875rem; }

.modal-actions {
  display: flex; gap: 0.75rem; justify-content: flex-end;
  padding: 1rem 1.5rem 1.5rem;
}

.toast {
  position: fixed; bottom: 2rem; right: 2rem;
  padding: 0.875rem 1.5rem;
  border-radius: 6px; font-weight: 500;
  z-index: 2000; animation: slideIn 0.3s ease;
}
.toast-success { background: #28a745; color: white; }

@keyframes slideIn {
  from { transform: translateY(1rem); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}

@media (max-width: 768px) {
  .staff-management { padding: 1rem; }
  .page-header { flex-direction: column; gap: 1rem; }
  .filters-bar { flex-direction: column; }
  .filter-select { width: 100%; }
}
</style>
