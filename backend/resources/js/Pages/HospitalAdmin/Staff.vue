<template>
  <HospitalAdminLayout>
    <div class="staff-page">
      <div class="header">
        <h1>Staff Management</h1>
        <button @click="openCreateModal" class="btn btn-primary">Create Staff</button>
      </div>

      <div class="filters">
        <select v-model="filters.role" class="filter-select" @change="applyFilters">
          <option value="">All Roles</option>
          <option value="hospital_admin">Hospital Admin</option>
          <option value="doctor">Doctor</option>
          <option value="front_desk_officer">Front Desk Officer</option>
          <option value="accountant">Accountant</option>
          <option value="pharmacist">Pharmacist</option>
          <option value="lab_technician">Lab Technician</option>
          <option value="nurse">Nurse</option>
          <option value="triage">Triage</option>
          <option value="department_staff">Department Staff</option>
        </select>
        <select v-model="filters.department_id" class="filter-select" @change="applyFilters">
          <option value="">All Departments</option>
          <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
        </select>
        <select v-model="filters.is_active" class="filter-select" @change="applyFilters">
          <option value="">All Status</option>
          <option value="1">Active</option>
          <option value="0">Inactive</option>
        </select>
      </div>

      <div class="staff-table">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Department</th>
              <th>Status</th>
              <th>Specialties</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="staff in staffList.data" :key="staff.id">
              <td>{{ staff.name }}</td>
              <td>{{ staff.email }}</td>
              <td>
                <span class="role-badge">{{ formatRole(staff.role) }}</span>
                <span v-if="staff.is_hod" class="hod-badge">HOD</span>
              </td>
              <td>{{ staff.department?.name || 'N/A' }}</td>
              <td>
                <span :class="['status-badge', staff.is_active ? 'status-active' : 'status-inactive']">
                  {{ staff.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td>
                <span v-if="staff.role === 'doctor' && staff.specialties?.length" class="specialties-list">
                  {{ staff.specialties.join(', ') }}
                </span>
                <span v-else class="text-muted">—</span>
              </td>
              <td class="actions">
                <button @click="openEditModal(staff)" class="btn btn-sm btn-secondary">Edit</button>
                <button v-if="staff.is_active" @click="deactivateStaff(staff)" class="btn btn-sm btn-warning">Deactivate</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Create/Edit Modal -->
      <div v-if="showModal" class="modal-overlay" @click="closeModal">
        <div class="modal" @click.stop>
          <h3>{{ editingStaff ? 'Edit Staff' : 'Create Staff' }}</h3>
          <form @submit.prevent="saveStaff">
            <div class="form-group">
              <label>Name *</label>
              <input v-model="form.name" type="text" required />
            </div>
            <div class="form-group">
              <label>Email *</label>
              <input v-model="form.email" type="email" required />
            </div>
            <div class="form-group">
              <label>Phone *</label>
              <input v-model="form.phone" type="tel" required />
            </div>
            <div class="form-group" v-if="!editingStaff">
              <label>Password *</label>
              <input v-model="form.password" type="password" :required="!editingStaff" />
            </div>
            <div class="form-group">
              <label>Role *</label>
              <select v-model="form.role" required>
                <option value="">Select role</option>
                <option value="hospital_admin">Hospital Admin</option>
                <option value="doctor">Doctor</option>
                <option value="front_desk_officer">Front Desk Officer</option>
                <option value="accountant">Accountant</option>
                <option value="pharmacist">Pharmacist</option>
                <option value="lab_technician">Lab Technician</option>
                <option value="nurse">Nurse</option>
                <option value="triage">Triage</option>
                <option value="department_staff">Department Staff</option>
              </select>
            </div>
            <div class="form-group">
              <label>Department</label>
              <select v-model="form.department_id">
                <option value="">None</option>
                <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label class="checkbox-label">
                <input v-model="form.is_active" type="checkbox" />
                Active
              </label>
            </div>
            <div class="form-group">
              <label class="checkbox-label">
                <input v-model="form.is_hod" type="checkbox" />
                Head of Department (HOD)
              </label>
            </div>
            <!-- Specialties — only shown for doctors -->
            <div class="form-group" v-if="form.role === 'doctor'">
              <label>Specialties (Visit Types)</label>
              <div class="specialties-grid">
                <label
                  v-for="vt in visitTypes"
                  :key="vt.slug"
                  class="specialty-checkbox"
                >
                  <input
                    type="checkbox"
                    :value="vt.slug"
                    v-model="form.specialties"
                  />
                  {{ vt.name }}
                </label>
              </div>
              <p class="help-text">Select the visit types this doctor handles. Patients with these visit types will be routed to this doctor.</p>
            </div>
            <div class="modal-actions">
              <button type="button" @click="closeModal" class="btn btn-secondary">Cancel</button>
              <button type="submit" class="btn btn-primary">{{ editingStaff ? 'Update' : 'Create' }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </HospitalAdminLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import HospitalAdminLayout from '../../Layouts/HospitalAdminLayout.vue';

const props = defineProps({
  staff: Object,
  departments: Array,
  filters: Object,
});

const staffList = ref(props.staff);
const visitTypes = ref([]);

const filters = reactive({
  role: props.filters.role || '',
  department_id: props.filters.department_id || '',
  is_active: props.filters.is_active || '',
});

const showModal = ref(false);
const editingStaff = ref(null);

const form = reactive({
  name: '',
  email: '',
  phone: '',
  password: '',
  role: '',
  department_id: '',
  is_active: true,
  is_hod: false,
  specialties: [],
});

onMounted(async () => {
  try {
    const res = await axios.get('/hospital-admin/api/visit-types');
    visitTypes.value = res.data.data || [];
  } catch (e) {
    // non-fatal — specialties section just won't show options
  }
});

const applyFilters = () => {
  router.get('/hospital-admin/staff', filters, {
    preserveState: true,
    preserveScroll: true,
  });
};

const openCreateModal = () => {
  editingStaff.value = null;
  form.name = '';
  form.email = '';
  form.phone = '';
  form.password = '';
  form.role = '';
  form.department_id = '';
  form.is_active = true;
  form.is_hod = false;
  form.specialties = [];
  showModal.value = true;
};

const openEditModal = (staff) => {
  editingStaff.value = staff;
  form.name = staff.name;
  form.email = staff.email;
  form.phone = staff.phone;
  form.password = '';
  form.role = staff.role;
  form.department_id = staff.department_id || '';
  form.is_active = staff.is_active;
  form.is_hod = staff.is_hod || false;
  form.specialties = Array.isArray(staff.specialties) ? [...staff.specialties] : [];
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  editingStaff.value = null;
};

const saveStaff = async () => {
  try {
    const payload = {
      name: form.name,
      email: form.email,
      phone: form.phone,
      role: form.role,
      department_id: form.department_id || null,
      is_active: form.is_active,
      is_hod: form.is_hod,
      specialties: form.role === 'doctor' ? [...form.specialties] : [],
    };
    if (form.password) {
      payload.password = form.password;
    }
    
    if (editingStaff.value) {
      await axios.put(`/hospital-admin/api/staff/${editingStaff.value.id}`, payload);
    } else {
      payload.password = form.password; // required for create
      await axios.post('/hospital-admin/api/staff', payload);
    }
    closeModal();
    router.reload({ only: ['staff'] });
  } catch (error) {
    console.error('Failed to save staff:', error);
    alert('Failed to save staff: ' + (error.response?.data?.error?.message || error.message));
  }
};

const deactivateStaff = async (staff) => {
  if (!confirm(`Are you sure you want to deactivate ${staff.name}? All active sessions will be invalidated.`)) {
    return;
  }
  
  try {
    await axios.patch(`/hospital-admin/api/staff/${staff.id}/deactivate`);
    router.reload({ only: ['staff'] });
  } catch (error) {
    console.error('Failed to deactivate staff:', error);
    alert('Failed to deactivate staff');
  }
};

const formatRole = (role) => {
  return role.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};
</script>

<style scoped>
.staff-page {
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

.staff-table {
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

.role-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.875rem;
  background: #e7f3ff;
  color: #0066cc;
}

.hod-badge {
  display: inline-block;
  padding: 0.25rem 0.5rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
  background: #fff3cd;
  color: #856404;
  margin-left: 0.375rem;
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

.btn-warning {
  background: #ffc107;
  color: #212529;
}

.btn-warning:hover {
  background: #e0a800;
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
  max-height: 90vh;
  overflow-y: auto;
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
.form-group input[type="email"],
.form-group input[type="tel"],
.form-group input[type="password"],
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

.specialties-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.5rem;
  margin-top: 0.5rem;
  padding: 0.75rem;
  border: 1px solid #ced4da;
  border-radius: 4px;
  background: #f8f9fa;
}

.specialty-checkbox {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  font-size: 0.9rem;
  padding: 0.25rem;
}

.specialty-checkbox input[type="checkbox"] {
  width: auto;
  cursor: pointer;
}

.help-text {
  font-size: 0.8rem;
  color: #6c757d;
  margin-top: 0.5rem;
  margin-bottom: 0;
}

.specialties-list {
  font-size: 0.8rem;
  color: #0066cc;
  background: #e7f3ff;
  padding: 0.2rem 0.5rem;
  border-radius: 4px;
}

.text-muted {
  color: #adb5bd;
}
</style>
