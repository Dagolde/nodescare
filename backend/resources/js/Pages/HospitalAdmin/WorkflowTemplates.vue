<template>
  <HospitalAdminLayout>
    <div class="workflow-templates-page">
      <div class="header">
        <h1>Workflow Templates</h1>
        <button @click="openCreateModal" class="btn btn-primary">Create Template</button>
      </div>

      <div class="templates-list">
        <div v-for="template in templates.data" :key="template.id" class="template-card">
          <div class="template-header">
            <div>
              <h3>{{ template.name }}</h3>
              <p v-if="template.description">{{ template.description }}</p>
              <span v-if="template.is_default" class="default-badge">Default</span>
            </div>
            <div class="template-actions">
              <button @click="openEditModal(template)" class="btn btn-sm btn-secondary">Edit</button>
              <button @click="cloneTemplate(template)" class="btn btn-sm btn-info">Clone</button>
              <button v-if="!template.is_default" @click="setAsDefault(template)" class="btn btn-sm btn-success">Set as Default</button>
            </div>
          </div>
          <div class="template-steps">
            <div v-for="(step, index) in template.steps" :key="step.id" class="step-item">
              <span class="step-number">{{ index + 1 }}</span>
              <span class="step-name">{{ step.department.name }}</span>
              <span v-if="step.is_terminal" class="terminal-badge">Terminal</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Create/Edit Modal -->
      <div v-if="showModal" class="modal-overlay" @click="closeModal">
        <div class="modal large" @click.stop>
          <h3>{{ editingTemplate ? 'Edit Template' : 'Create Template' }}</h3>
          <form @submit.prevent="saveTemplate">
            <div class="form-group">
              <label>Name *</label>
              <input v-model="form.name" type="text" required />
            </div>
            <div class="form-group">
              <label>Description</label>
              <textarea v-model="form.description" rows="3"></textarea>
            </div>
            <div class="form-group">
              <label class="checkbox-label">
                <input v-model="form.is_default" type="checkbox" />
                Set as Default
              </label>
            </div>
            
            <div class="form-group">
              <label>Workflow Steps *</label>
              <div class="steps-builder">
                <div v-for="(step, index) in form.steps" :key="index" class="step-builder-item">
                  <button type="button" @click="moveStepUp(index)" :disabled="index === 0" class="btn-icon">↑</button>
                  <button type="button" @click="moveStepDown(index)" :disabled="index === form.steps.length - 1" class="btn-icon">↓</button>
                  <span class="step-order">{{ index + 1 }}</span>
                  <select v-model="step.department_id" required>
                    <option value="">Select department</option>
                    <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                  </select>
                  <label class="checkbox-label small">
                    <input v-model="step.is_terminal" type="checkbox" />
                    Terminal
                  </label>
                  <button type="button" @click="removeStep(index)" class="btn-icon btn-danger">×</button>
                </div>
                <button type="button" @click="addStep" class="btn btn-sm btn-secondary">Add Step</button>
              </div>
            </div>

            <div class="modal-actions">
              <button type="button" @click="closeModal" class="btn btn-secondary">Cancel</button>
              <button type="submit" class="btn btn-primary">{{ editingTemplate ? 'Update' : 'Create' }}</button>
            </div>
          </form>
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
  templates: Object,
  departments: Array,
  filters: Object,
});

const showModal = ref(false);
const editingTemplate = ref(null);

const form = reactive({
  name: '',
  description: '',
  is_default: false,
  steps: [],
});

const openCreateModal = () => {
  editingTemplate.value = null;
  form.name = '';
  form.description = '';
  form.is_default = false;
  form.steps = [];
  showModal.value = true;
};

const openEditModal = (template) => {
  editingTemplate.value = template;
  form.name = template.name;
  form.description = template.description || '';
  form.is_default = template.is_default;
  form.steps = template.steps.map(step => ({
    department_id: step.department_id,
    step_order: step.step_order,
    is_terminal: step.is_terminal,
  }));
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  editingTemplate.value = null;
};

const addStep = () => {
  form.steps.push({
    department_id: '',
    step_order: form.steps.length + 1,
    is_terminal: false,
  });
};

const removeStep = (index) => {
  form.steps.splice(index, 1);
  // Reorder
  form.steps.forEach((step, i) => {
    step.step_order = i + 1;
  });
};

const moveStepUp = (index) => {
  if (index === 0) return;
  const temp = form.steps[index];
  form.steps[index] = form.steps[index - 1];
  form.steps[index - 1] = temp;
  // Reorder
  form.steps.forEach((step, i) => {
    step.step_order = i + 1;
  });
};

const moveStepDown = (index) => {
  if (index === form.steps.length - 1) return;
  const temp = form.steps[index];
  form.steps[index] = form.steps[index + 1];
  form.steps[index + 1] = temp;
  // Reorder
  form.steps.forEach((step, i) => {
    step.step_order = i + 1;
  });
};

const saveTemplate = async () => {
  try {
    if (editingTemplate.value) {
      await axios.put(`/hospital-admin/api/workflows/templates/${editingTemplate.value.id}`, form);
    } else {
      await axios.post('/hospital-admin/api/workflows/templates', form);
    }
    closeModal();
    router.reload({ only: ['templates'] });
  } catch (error) {
    console.error('Failed to save template:', error);
    alert(error.response?.data?.error?.message || 'Failed to save template');
  }
};

const cloneTemplate = async (template) => {
  const clonedForm = {
    name: `${template.name} (Copy)`,
    description: template.description,
    is_default: false,
    steps: template.steps.map(step => ({
      department_id: step.department_id,
      step_order: step.step_order,
      is_terminal: step.is_terminal,
    })),
  };
  
  try {
    await axios.post('/hospital-admin/api/workflows/templates', clonedForm);
    router.reload({ only: ['templates'] });
  } catch (error) {
    console.error('Failed to clone template:', error);
    alert('Failed to clone template');
  }
};

const setAsDefault = async (template) => {
  try {
    await axios.put(`/hospital-admin/api/workflows/templates/${template.id}`, {
      ...template,
      is_default: true,
      steps: template.steps.map(step => ({
        department_id: step.department_id,
        step_order: step.step_order,
        is_terminal: step.is_terminal,
      })),
    });
    router.reload({ only: ['templates'] });
  } catch (error) {
    console.error('Failed to set as default:', error);
    alert('Failed to set as default');
  }
};
</script>

<style scoped>
.workflow-templates-page {
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

.templates-list {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.template-card {
  background: white;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.template-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.template-header h3 {
  margin: 0 0 0.5rem 0;
  font-size: 1.25rem;
  color: #2c3e50;
}

.template-header p {
  margin: 0 0 0.5rem 0;
  color: #6c757d;
  font-size: 0.9375rem;
}

.default-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.875rem;
  background: #d4edda;
  color: #155724;
  font-weight: 500;
}

.template-actions {
  display: flex;
  gap: 0.5rem;
}

.template-steps {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.step-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  background: #f8f9fa;
  border-radius: 6px;
  border: 2px solid #e9ecef;
}

.step-number {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  background: #007bff;
  color: white;
  border-radius: 50%;
  font-size: 0.875rem;
  font-weight: 600;
}

.step-name {
  font-weight: 500;
  color: #495057;
}

.terminal-badge {
  padding: 0.125rem 0.5rem;
  border-radius: 8px;
  font-size: 0.75rem;
  background: #fff3cd;
  color: #856404;
  font-weight: 500;
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

.btn-info {
  background: #17a2b8;
  color: white;
}

.btn-info:hover {
  background: #138496;
}

.btn-success {
  background: #28a745;
  color: white;
}

.btn-success:hover {
  background: #218838;
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

.modal.large {
  max-width: 700px;
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
.form-group textarea,
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

.checkbox-label.small {
  font-size: 0.875rem;
}

.checkbox-label input[type="checkbox"] {
  width: auto;
}

.steps-builder {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.step-builder-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem;
  background: #f8f9fa;
  border-radius: 6px;
}

.step-order {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  background: #007bff;
  color: white;
  border-radius: 50%;
  font-size: 0.875rem;
  font-weight: 600;
}

.step-builder-item select {
  flex: 1;
  padding: 0.5rem;
  border: 1px solid #ced4da;
  border-radius: 4px;
}

.btn-icon {
  width: 32px;
  height: 32px;
  padding: 0;
  border: 1px solid #ced4da;
  background: white;
  border-radius: 4px;
  cursor: pointer;
  font-size: 1rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-icon:hover:not(:disabled) {
  background: #e9ecef;
}

.btn-icon:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-icon.btn-danger {
  color: #dc3545;
  border-color: #dc3545;
}

.btn-icon.btn-danger:hover {
  background: #dc3545;
  color: white;
}

.modal-actions {
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
  margin-top: 2rem;
}
</style>
