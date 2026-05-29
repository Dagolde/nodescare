<template>
  <HospitalAdminLayout>
    <div class="departments-page">
      <div class="header">
        <h1>Department Management</h1>
        <button @click="openCreateModal" class="btn btn-primary">Create Department</button>
      </div>

      <div class="filters">
        <select v-model="filters.type" class="filter-select" @change="applyFilters">
          <option value="">All Types</option>
          <option value="clinical">Clinical</option>
          <option value="pharmacy">Pharmacy</option>
          <option value="laboratory">Laboratory</option>
          <option value="front_desk">Front Desk</option>
          <option value="triage">Triage</option>
          <option value="ward">Ward</option>
          <option value="icu_ae">ICU/A&E</option>
          <option value="accounts">Accounts</option>
          <option value="other">Other</option>
        </select>
        <select v-model="filters.is_active" class="filter-select" @change="applyFilters">
          <option value="">All Status</option>
          <option value="1">Active</option>
          <option value="0">Inactive</option>
        </select>
      </div>

      <div class="departments-table">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Code</th>
              <th>Type</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="dept in departments.data" :key="dept.id">
              <td>{{ dept.name }}</td>
              <td><code>{{ dept.code }}</code></td>
              <td><span class="type-badge">{{ formatType(dept.type) }}</span></td>
              <td>
                <span :class="['status-badge', dept.is_active ? 'status-active' : 'status-inactive']">
                  {{ dept.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="actions">
                <button @click="openEditModal(dept)" class="btn btn-sm btn-secondary">Edit</button>
                <button @click="confirmDelete(dept)" class="btn btn-sm btn-danger">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Create/Edit Modal -->
      <div v-if="showModal" class="modal-overlay" @click="closeModal">
        <div class="modal" @click.stop>
          <h3>{{ editingDept ? 'Edit Department' : 'Create Department' }}</h3>
          <form @submit.prevent="saveDepartment">
            <div class="form-group">
              <label>Name *</label>
              <input v-model="form.name" type="text" required />
            </div>
            <div class="form-group">
              <label>Code *</label>
              <input v-model="form.code" type="text" required placeholder="e.g., CARDIO" />
            </div>
            <div class="form-group">
              <label>Type *</label>
              <select v-model="form.type" required>
                <option value="">Select type</option>
                <option value="clinical">Clinical</option>
                <option value="pharmacy">Pharmacy</option>
                <option value="laboratory">Laboratory</option>
                <option value="front_desk">Front Desk</option>
                <option value="triage">Triage</option>
                <option value="ward">Ward</option>
                <option value="icu_ae">ICU/A&E</option>
                <option value="accounts">Accounts</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="form-group">
              <label class="checkbox-label">
                <input v-model="form.is_active" type="checkbox" />
                Active
              </label>
            </div>
            <div class="modal-actions">
              <button type="button" @click="closeModal" class="btn btn-secondary">Cancel</button>
              <button type="submit" class="btn btn-primary">{{ editingDept ? 'Update' : 'Create' }}</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Delete Confirmation Modal -->
      <div v-if="showDeleteModal" class="modal-overlay" @click="closeDeleteModal">
        <div class="modal" @click.stop>
          <h3>Confirm Delete</h3>
          <p v-if="deleteError" class="error-message">{{ deleteError }}</p>
          <p v-else>Are you sure you want to delete <strong>{{ deletingDept?.name }}</strong>?</p>
          <div class="modal-actions">
            <button @click="closeDeleteModal" class="btn btn-secondary">Cancel</button>
            <button v-if="!deleteError" @click="deleteDepartment" class="btn btn-danger">Delete</button>
          </div>
        </div>
      </div>
    </div>
  </HospitalAdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import HospitalAdminLayout from '../../Layouts/HospitalAdminLayout.vue';

const props = defineProps({
  departments: Object,
  filters: Object,
});

const filters = reactive({
  type: props.filters.type || '',
  is_active: props.filters.is_active || '',
});

const showModal = ref(false);
const showDeleteModal = ref(false);
const editingDept = ref(null);
const deletingDept = ref(null);
const deleteError = ref('');

const form = reactive({
  name: '',
  code: '',
  type: '',
  is_active: true,
});

const applyFilters = () => {
  router.get('/hospital-admin/departments', filters, {
    preserveState: true,
    preserveScroll: true,
  });
};

const openCreateModal = () => {
  editingDept.value = null;
  form.name = '';
  form.code = '';
  form.type = '';
  form.is_active = true;
  showModal.value = true;
};

const openEditModal = (dept) => {
  editingDept.value = dept;
  form.name = dept.name;
  form.code = dept.code;
  form.type = dept.type;
  form.is_active = dept.is_active;
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  editingDept.value = null;
};

const saveDepartment = async () => {
  try {
    if (editingDept.value) {
      await axios.put(`/hospital-admin/api/departments/${editingDept.value.id}`, form);
    } else {
      await axios.post('/hospital-admin/api/departments', form);
    }
    closeModal();
    router.reload({ only: ['departments'] });
  } catch (error) {
    console.error('Failed to save department:', error);
    alert('Failed to save department');
  }
};

const confirmDelete = (dept) => {
  deletingDept.value = dept;
  deleteError.value = '';
  showDeleteModal.value = true;
};

const closeDeleteModal = () => {
  showDeleteModal.value = false;
  deletingDept.value = null;
  deleteError.value = '';
};

const deleteDepartment = async () => {
  try {
    await axios.delete(`/hospital-admin/api/departments/${deletingDept.value.id}`);
    closeDeleteModal();
    router.reload({ only: ['departments'] });
  } catch (error) {
    if (error.response?.data?.error?.message) {
      deleteError.value = error.response.data.error.message;
    } else {
      console.error('Failed to delete department:', error);
      alert('Failed to delete department');
    }
  }
};

const formatType = (type) => {
  return type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};
</script>

<style scoped>
.departments-page {
  padding: 2rem;
  max-width: 1400px;
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

.filters {
  display: flex;
  gap: 1rem;
  margin-bottom: 2rem;
}

.filter-select {
  padding: 0.5rem 1rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 1rem;
  min-width: 150px;
}

.departments-table {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

table {
  width: 100%;
  border-collapse: collapse;
}

thead {
  background: #f8f9fa;
}

th, td {
  padding: 1rem;
  text-align: left;
  border-bottom: 1px solid #e9ecef;
}

th {
  font-weight: 600;
  color: #495057;
}

code {
  background: #f8f9fa;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-family: monospace;
  font-size: 0.875rem;
}

.type-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.875rem;
  background: #e9ecef;
  color: #495057;
}

.status-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.875rem;
  font-weight: 500;
}

.status-active {
  background: #d4edda;
  color: #155724;
}

.status-inactive {
  background: #f8d7da;
  color: #721c24;
}

.actions {
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
  background: #5a6268;
}

.btn-danger {
  background: #dc3545;
  color: white;
}

.btn-danger:hover {
  background: #c82333;
}

.btn-sm {
  padding: 0.375rem 0.75rem;
  font-size: 0.8125rem;
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal {
  background: white;
  padding: 2rem;
  border-radius: 8px;
  max-width: 500px;
  width: 90%;
}

.modal h3 {
  margin-top: 0;
  margin-bottom: 1.5rem;
}

.form-group {
  margin-bottom: 1.5rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: #495057;
}

.form-group input[type="text"],
.form-group select {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #ced4da;
  border-radius: 4px;
  font-size: 1rem;
  box-sizing: border-box;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
  width: auto;
}

.modal-actions {
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
  margin-top: 2rem;
}

.error-message {
  color: #dc3545;
  margin-bottom: 1rem;
}
</style>
