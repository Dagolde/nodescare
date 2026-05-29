<template>
  <div class="login-page">
    <div class="login-card">
      <div class="logo">🏥</div>
      <h1>Department Login</h1>
      <p class="subtitle">Head of Department access only</p>
      <p class="mobile-note">Regular staff? Please use the <strong>mobile app</strong> to log in.</p>

      <form @submit.prevent="handleLogin">
        <div class="form-group">
          <label for="hospital">Hospital ID</label>
          <input
            id="hospital"
            v-model="form.hospital_id"
            type="text"
            required
            placeholder="e.g., nodescarecenter"
            :class="{ error: errors.hospital_id }"
          />
          <span v-if="errors.hospital_id" class="error-message">{{ errors.hospital_id }}</span>
        </div>

        <div class="form-group">
          <label for="email">Email</label>
          <input
            id="email"
            v-model="form.email"
            type="email"
            required
            placeholder="hod@hospital.com"
            :class="{ error: errors.email }"
          />
          <span v-if="errors.email" class="error-message">{{ errors.email }}</span>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input
            id="password"
            v-model="form.password"
            type="password"
            required
            placeholder="Enter your password"
            :class="{ error: errors.password }"
          />
          <span v-if="errors.password" class="error-message">{{ errors.password }}</span>
        </div>

        <button type="submit" class="btn btn-primary" :disabled="loading">
          {{ loading ? 'Signing in...' : 'Sign In' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';

defineProps({
  errors: {
    type: Object,
    default: () => ({}),
  },
});

const form = reactive({
  hospital_id: '',
  email: '',
  password: '',
});

const loading = ref(false);

const handleLogin = () => {
  loading.value = true;
  router.post('/department/login', form, {
    onFinish: () => {
      loading.value = false;
    },
  });
};
</script>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}

.login-card {
  background: white;
  padding: 3rem;
  border-radius: 12px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
  width: 100%;
  max-width: 420px;
}

.logo {
  text-align: center;
  font-size: 3rem;
  margin-bottom: 0.5rem;
}

h1 {
  margin: 0 0 0.5rem 0;
  font-size: 1.75rem;
  color: #2c3e50;
  text-align: center;
}

.subtitle {
  margin: 0 0 0.5rem 0;
  color: #6c757d;
  text-align: center;
  font-size: 0.9375rem;
}

.mobile-note {
  margin: 0 0 2rem 0;
  padding: 0.75rem 1rem;
  background: #fff3cd;
  border: 1px solid #ffc107;
  border-radius: 6px;
  color: #856404;
  font-size: 0.875rem;
  text-align: center;
}

.form-group {
  margin-bottom: 1.5rem;
}

label {
  display: block;
  margin-bottom: 0.5rem;
  color: #495057;
  font-weight: 500;
  font-size: 0.9375rem;
}

input {
  width: 100%;
  padding: 0.75rem 1rem;
  border: 1px solid #ced4da;
  border-radius: 6px;
  font-size: 1rem;
  transition: all 0.2s;
  box-sizing: border-box;
}

input:focus {
  outline: none;
  border-color: #11998e;
  box-shadow: 0 0 0 3px rgba(17, 153, 142, 0.1);
}

input.error {
  border-color: #dc3545;
}

.error-message {
  display: block;
  margin-top: 0.5rem;
  color: #dc3545;
  font-size: 0.875rem;
}

.btn {
  width: 100%;
  padding: 0.875rem;
  border: none;
  border-radius: 6px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-primary {
  background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(17, 153, 142, 0.4);
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
