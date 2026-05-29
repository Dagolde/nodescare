<template>
  <SuperAdminLayout>
    <div class="create-hospital">
      <div class="header">
        <h1>Create New Hospital</h1>
        <a :href="route('admin.dashboard')" class="btn btn-secondary">Back to Dashboard</a>
      </div>

    <div class="form-container">
      <form @submit.prevent="submitForm">
        <div class="form-group">
          <label for="name">Hospital Name *</label>
          <input
            id="name"
            v-model="form.name"
            type="text"
            required
            placeholder="e.g., City General Hospital"
            :class="{ 'error': errors.name }"
          />
          <span v-if="errors.name" class="error-message">{{ errors.name }}</span>
        </div>

        <div class="form-group">
          <label for="subdomain">Subdomain *</label>
          <div class="subdomain-input">
            <input
              id="subdomain"
              v-model="form.subdomain"
              type="text"
              required
              placeholder="e.g., citygeneral"
              pattern="[a-z0-9-]+"
              :class="{ 'error': errors.subdomain }"
            />
            <span class="subdomain-suffix">.hhms.app</span>
          </div>
          <span v-if="errors.subdomain" class="error-message">{{ errors.subdomain }}</span>
          <small>Only lowercase letters, numbers, and hyphens allowed</small>
        </div>

        <div class="form-group">
          <label for="plan_id">Plan *</label>
          <select
            id="plan_id"
            v-model="form.plan_id"
            required
            :class="{ 'error': errors.plan_id }"
          >
            <option value="">Select a plan</option>
            <option v-for="plan in plans" :key="plan.id" :value="plan.id">
              {{ plan.name }}
            </option>
          </select>
          <span v-if="errors.plan_id" class="error-message">{{ errors.plan_id }}</span>
        </div>

        <div class="form-group">
          <label for="admin_name">Admin Name *</label>
          <input
            id="admin_name"
            v-model="form.admin_name"
            type="text"
            required
            placeholder="e.g., John Doe"
            :class="{ 'error': errors.admin_name }"
          />
          <span v-if="errors.admin_name" class="error-message">{{ errors.admin_name }}</span>
        </div>

        <div class="form-group">
          <label for="admin_email">Admin Email *</label>
          <input
            id="admin_email"
            v-model="form.admin_email"
            type="email"
            required
            placeholder="e.g., admin@citygeneral.com"
            :class="{ 'error': errors.admin_email }"
          />
          <span v-if="errors.admin_email" class="error-message">{{ errors.admin_email }}</span>
        </div>

        <div class="form-group">
          <label for="admin_password">Admin Password *</label>
          <input
            id="admin_password"
            v-model="form.admin_password"
            type="password"
            required
            placeholder="Minimum 8 characters"
            :class="{ 'error': errors.admin_password }"
          />
          <span v-if="errors.admin_password" class="error-message">{{ errors.admin_password }}</span>
          <small>This will be the Hospital Admin's login password</small>
        </div>

        <div class="form-actions">
          <button type="button" @click="goBack" class="btn btn-secondary">Cancel</button>
          <button type="submit" class="btn btn-primary" :disabled="submitting">
            {{ submitting ? 'Creating...' : 'Create Hospital' }}
          </button>
        </div>
      </form>

      <div v-if="successMessage" class="success-alert">
        {{ successMessage }}
      </div>
    </div>
    </div>
  </SuperAdminLayout>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import SuperAdminLayout from '../../Layouts/SuperAdminLayout.vue';

const props = defineProps({
  plans: Array,
});

const form = reactive({
  name: '',
  subdomain: '',
  plan_id: '',
  admin_name: '',
  admin_email: '',
  admin_password: '',
});

const errors = reactive({});
const submitting = ref(false);
const successMessage = ref('');

const submitForm = async () => {
  submitting.value = true;
  successMessage.value = '';
  Object.keys(errors).forEach(key => delete errors[key]);

  try {
    const response = await axios.post('/admin/api/hospitals', form);
    successMessage.value = response.data.message || 'Hospital created successfully!';
    
    setTimeout(() => {
      router.visit(route('admin.dashboard'));
    }, 2000);
  } catch (error) {
    if (error.response?.data?.error?.details) {
      Object.assign(errors, error.response.data.error.details);
    } else {
      alert('Failed to create hospital. Please try again.');
    }
  } finally {
    submitting.value = false;
  }
};

const goBack = () => {
  router.visit(route('admin.dashboard'));
};

const route = (name) => {
  const routes = {
    'admin.dashboard': '/admin/dashboard',
  };
  return routes[name] || '/';
};
</script>

<style scoped>
.create-hospital {
  padding: 2rem;
  max-width: 800px;
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

.form-container {
  background: white;
  padding: 2rem;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.form-group {
  margin-bottom: 1.5rem;
}

label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: #495057;
}

input,
select {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #ced4da;
  border-radius: 4px;
  font-size: 1rem;
  transition: border-color 0.2s;
}

input:focus,
select:focus {
  outline: none;
  border-color: #007bff;
}

input.error,
select.error {
  border-color: #dc3545;
}

.subdomain-input {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.subdomain-input input {
  flex: 1;
}

.subdomain-suffix {
  color: #6c757d;
  font-weight: 500;
}

small {
  display: block;
  margin-top: 0.25rem;
  color: #6c757d;
  font-size: 0.875rem;
}

.error-message {
  display: block;
  margin-top: 0.25rem;
  color: #dc3545;
  font-size: 0.875rem;
}

.form-actions {
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
  margin-top: 2rem;
}

.btn {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 4px;
  font-size: 1rem;
  font-weight: 500;
  cursor: pointer;
  text-decoration: none;
  display: inline-block;
  transition: all 0.2s;
}

.btn-primary {
  background: #007bff;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: #0056b3;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-secondary {
  background: #6c757d;
  color: white;
}

.btn-secondary:hover {
  background: #5a6268;
}

.success-alert {
  margin-top: 1.5rem;
  padding: 1rem;
  background: #d4edda;
  color: #155724;
  border: 1px solid #c3e6cb;
  border-radius: 4px;
}
</style>
