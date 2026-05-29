<template>
  <DepartmentLayout :department="department" :currentUser="currentUser">
    <div class="department-settings">
      <h1>Department Settings</h1>
      <p class="subtitle">Manage department information, working hours, and notification preferences</p>

      <!-- Tabs -->
      <div class="tabs">
        <button
          :class="{ active: activeTab === 'info' }"
          @click="activeTab = 'info'"
          class="tab-button"
        >
          Department Info
        </button>
        <button
          :class="{ active: activeTab === 'hours' }"
          @click="activeTab = 'hours'"
          class="tab-button"
        >
          Working Hours
        </button>
        <button
          :class="{ active: activeTab === 'notifications' }"
          @click="activeTab = 'notifications'"
          class="tab-button"
        >
          Notifications
        </button>
      </div>

      <!-- Department Info Tab -->
      <div v-if="activeTab === 'info'" class="tab-content">
        <div class="card">
          <h3>Department Information</h3>
          <form @submit.prevent="updateDepartmentInfo" class="form">
            <div class="form-group">
              <label>Department Name</label>
              <input
                v-model="infoForm.name"
                type="text"
                class="form-input"
                required
              />
              <span v-if="infoErrors.name" class="error-text">{{ infoErrors.name }}</span>
            </div>
            <div class="form-group">
              <label>Description</label>
              <textarea
                v-model="infoForm.description"
                class="form-input"
                rows="3"
              ></textarea>
            </div>
            <div class="form-group">
              <label>Contact Email</label>
              <input
                v-model="infoForm.contact_email"
                type="email"
                class="form-input"
              />
            </div>
            <div class="form-group">
              <label>Contact Phone</label>
              <input
                v-model="infoForm.contact_phone"
                type="tel"
                class="form-input"
              />
            </div>
            <div class="form-actions">
              <button type="submit" class="btn btn-primary" :disabled="infoSubmitting">
                {{ infoSubmitting ? 'Saving...' : 'Save Changes' }}
              </button>
            </div>
            <div v-if="infoSuccess" class="success-message">{{ infoSuccess }}</div>
            <div v-if="infoErrors.general" class="error-message">{{ infoErrors.general }}</div>
          </form>
        </div>
      </div>

      <!-- Working Hours Tab -->
      <div v-if="activeTab === 'hours'" class="tab-content">
        <div class="card">
          <h3>Working Hours</h3>
          <form @submit.prevent="updateWorkingHours" class="form">
            <div v-for="(day, index) in hoursForm" :key="index" class="hours-row">
              <div class="day-name">{{ getDayName(day.day_of_week) }}</div>
              <div class="hours-controls">
                <label class="checkbox-label">
                  <input
                    v-model="day.is_open"
                    type="checkbox"
                    class="checkbox"
                  />
                  <span>Open</span>
                </label>
                <div v-if="day.is_open" class="time-inputs">
                  <input
                    v-model="day.opening_time"
                    type="time"
                    class="form-input time-input"
                    required
                  />
                  <span>to</span>
                  <input
                    v-model="day.closing_time"
                    type="time"
                    class="form-input time-input"
                    required
                  />
                </div>
              </div>
            </div>
            <span v-if="hoursErrors.general" class="error-text">{{ hoursErrors.general }}</span>
            <div class="form-actions">
              <button type="submit" class="btn btn-primary" :disabled="hoursSubmitting">
                {{ hoursSubmitting ? 'Saving...' : 'Save Working Hours' }}
              </button>
            </div>
            <div v-if="hoursSuccess" class="success-message">{{ hoursSuccess }}</div>
          </form>
        </div>
      </div>

      <!-- Notifications Tab -->
      <div v-if="activeTab === 'notifications'" class="tab-content">
        <div class="card">
          <h3>Notification Preferences</h3>
          <form @submit.prevent="updateNotifications" class="form">
            <div class="section">
              <h4>Event Types</h4>
              <label class="toggle-label">
                <input
                  v-model="notifForm.patient_arrivals_enabled"
                  type="checkbox"
                  class="toggle"
                />
                <span>Patient Arrivals</span>
              </label>
              <label class="toggle-label">
                <input
                  v-model="notifForm.staff_actions_enabled"
                  type="checkbox"
                  class="toggle"
                />
                <span>Staff Actions</span>
              </label>
              <label class="toggle-label">
                <input
                  v-model="notifForm.system_alerts_enabled"
                  type="checkbox"
                  class="toggle"
                />
                <span>System Alerts</span>
              </label>
            </div>
            <div class="section">
              <h4>Delivery Methods</h4>
              <label class="toggle-label">
                <input
                  v-model="notifForm.email_enabled"
                  type="checkbox"
                  class="toggle"
                />
                <span>Email Notifications</span>
              </label>
              <label class="toggle-label">
                <input
                  v-model="notifForm.sms_enabled"
                  type="checkbox"
                  class="toggle"
                />
                <span>SMS Notifications</span>
              </label>
              <label class="toggle-label">
                <input
                  v-model="notifForm.in_app_enabled"
                  type="checkbox"
                  class="toggle"
                />
                <span>In-App Notifications</span>
              </label>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn btn-primary" :disabled="notifSubmitting">
                {{ notifSubmitting ? 'Saving...' : 'Save Preferences' }}
              </button>
            </div>
            <div v-if="notifSuccess" class="success-message">{{ notifSuccess }}</div>
            <div v-if="notifErrors.general" class="error-message">{{ notifErrors.general }}</div>
          </form>
        </div>
      </div>
    </div>
  </DepartmentLayout>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';
import DepartmentLayout from '@/Layouts/DepartmentLayout.vue';

const props = defineProps({
  department: Object,
  workingHours: Array,
  notificationPreferences: Object,
  currentUser: Object,
});

const activeTab = ref('info');

// Department Info Form
const infoForm = ref({
  name: props.department?.name || '',
  description: props.department?.description || '',
  contact_email: props.department?.contact_email || '',
  contact_phone: props.department?.contact_phone || '',
});
const infoErrors = ref({});
const infoSuccess = ref('');
const infoSubmitting = ref(false);

// Working Hours Form
const hoursForm = ref(
  props.workingHours?.length
    ? props.workingHours
    : [
        { day_of_week: 0, is_open: false, opening_time: '08:00', closing_time: '17:00' },
        { day_of_week: 1, is_open: true, opening_time: '08:00', closing_time: '17:00' },
        { day_of_week: 2, is_open: true, opening_time: '08:00', closing_time: '17:00' },
        { day_of_week: 3, is_open: true, opening_time: '08:00', closing_time: '17:00' },
        { day_of_week: 4, is_open: true, opening_time: '08:00', closing_time: '17:00' },
        { day_of_week: 5, is_open: true, opening_time: '08:00', closing_time: '17:00' },
        { day_of_week: 6, is_open: false, opening_time: '08:00', closing_time: '17:00' },
      ]
);
const hoursErrors = ref({});
const hoursSuccess = ref('');
const hoursSubmitting = ref(false);

// Notifications Form
const notifForm = ref({
  patient_arrivals_enabled: props.notificationPreferences?.patient_arrivals_enabled ?? true,
  staff_actions_enabled: props.notificationPreferences?.staff_actions_enabled ?? true,
  system_alerts_enabled: props.notificationPreferences?.system_alerts_enabled ?? true,
  email_enabled: props.notificationPreferences?.email_enabled ?? true,
  sms_enabled: props.notificationPreferences?.sms_enabled ?? false,
  in_app_enabled: props.notificationPreferences?.in_app_enabled ?? true,
});
const notifErrors = ref({});
const notifSuccess = ref('');
const notifSubmitting = ref(false);

// Helpers
const getDayName = (dayOfWeek) => {
  const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  return days[dayOfWeek];
};

// Submit handlers
const updateDepartmentInfo = async () => {
  infoSubmitting.value = true;
  infoErrors.value = {};
  infoSuccess.value = '';

  try {
    const response = await axios.put('/department/api/settings/info', infoForm.value);
    if (response.data.success) {
      infoSuccess.value = 'Department information updated successfully!';
      setTimeout(() => (infoSuccess.value = ''), 3000);
    }
  } catch (error) {
    if (error.response?.data?.errors) {
      infoErrors.value = error.response.data.errors;
    } else {
      infoErrors.value.general = error.response?.data?.error?.message || 'Failed to update department information.';
    }
  } finally {
    infoSubmitting.value = false;
  }
};

const updateWorkingHours = async () => {
  hoursSubmitting.value = true;
  hoursErrors.value = {};
  hoursSuccess.value = '';

  try {
    const response = await axios.put('/department/api/settings/working-hours', {
      working_hours: hoursForm.value,
    });
    if (response.data.success) {
      hoursSuccess.value = 'Working hours updated successfully!';
      setTimeout(() => (hoursSuccess.value = ''), 3000);
    }
  } catch (error) {
    hoursErrors.value.general = error.response?.data?.error?.message || 'Failed to update working hours.';
  } finally {
    hoursSubmitting.value = false;
  }
};

const updateNotifications = async () => {
  notifSubmitting.value = true;
  notifErrors.value = {};
  notifSuccess.value = '';

  try {
    const response = await axios.put('/department/api/settings/notifications', notifForm.value);
    if (response.data.success) {
      notifSuccess.value = 'Notification preferences updated successfully!';
      setTimeout(() => (notifSuccess.value = ''), 3000);
    }
  } catch (error) {
    notifErrors.value.general = error.response?.data?.error?.message || 'Failed to update notification preferences.';
  } finally {
    notifSubmitting.value = false;
  }
};
</script>

<style scoped>
.department-settings {
  padding: 2rem;
  max-width: 1000px;
  margin: 0 auto;
}

h1 {
  font-size: 2rem;
  color: #2c3e50;
  margin: 0 0 0.5rem 0;
}

.subtitle {
  color: #6c757d;
  margin: 0 0 2rem 0;
}

.tabs {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 2rem;
  border-bottom: 2px solid #e9ecef;
}

.tab-button {
  padding: 0.75rem 1.5rem;
  border: none;
  background: none;
  color: #6c757d;
  font-weight: 500;
  cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  transition: all 0.2s;
}

.tab-button:hover {
  color: #007bff;
}

.tab-button.active {
  color: #007bff;
  border-bottom-color: #007bff;
}

.tab-content {
  animation: fadeIn 0.3s;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.card {
  background: white;
  border-radius: 8px;
  padding: 2rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.card h3 {
  margin: 0 0 1.5rem 0;
  font-size: 1.25rem;
  color: #2c3e50;
}

.form {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-group label {
  font-weight: 500;
  color: #495057;
  font-size: 0.9375rem;
}

.form-input {
  padding: 0.75rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 0.9375rem;
}

.form-input:focus {
  outline: none;
  border-color: #007bff;
  box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.15);
}

.hours-row {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  border: 1px solid #e9ecef;
  border-radius: 4px;
}

.day-name {
  font-weight: 500;
  color: #495057;
  min-width: 100px;
}

.hours-controls {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex: 1;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
}

.checkbox {
  width: 18px;
  height: 18px;
  cursor: pointer;
}

.time-inputs {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.time-input {
  width: 120px;
}

.section {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 1rem;
  background: #f8f9fa;
  border-radius: 4px;
}

.section h4 {
  margin: 0 0 0.5rem 0;
  font-size: 1rem;
  color: #2c3e50;
}

.toggle-label {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  cursor: pointer;
  padding: 0.5rem;
  border-radius: 4px;
  transition: background 0.2s;
}

.toggle-label:hover {
  background: white;
}

.toggle {
  width: 20px;
  height: 20px;
  cursor: pointer;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  margin-top: 1rem;
}

.btn {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 4px;
  font-size: 0.9375rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-primary {
  background: #007bff;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: #0056b3;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.error-text {
  color: #dc3545;
  font-size: 0.875rem;
}

.success-message {
  padding: 0.75rem 1rem;
  background: #d4edda;
  color: #155724;
  border-radius: 4px;
  font-size: 0.9375rem;
}

.error-message {
  padding: 0.75rem 1rem;
  background: #f8d7da;
  color: #721c24;
  border-radius: 4px;
  font-size: 0.9375rem;
}

/* Responsive */
@media (max-width: 768px) {
  .department-settings {
    padding: 1rem;
  }

  .hours-row {
    flex-direction: column;
    align-items: flex-start;
  }

  .hours-controls {
    flex-direction: column;
    align-items: flex-start;
    width: 100%;
  }

  .time-inputs {
    width: 100%;
  }

  .time-input {
    flex: 1;
  }
}
</style>
