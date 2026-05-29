<template>
  <SuperAdminLayout>
    <div class="system-settings">
      <div class="header">
        <h1>System Settings</h1>
        <a href="/admin/dashboard" class="btn btn-secondary">Back to Dashboard</a>
      </div>

      <div class="settings-container">

        <!-- Mobile App Section -->
        <div class="settings-section">
          <h2>Mobile App</h2>
          <p class="section-description">Control mobile app version requirements and update enforcement.</p>
          <div class="form-group">
            <label for="mobile_app_version">Current App Version</label>
            <input id="mobile_app_version" v-model="localSettings.mobile_app_version" type="text" placeholder="e.g., 2.1.0" />
            <small>The latest published version of the mobile app</small>
          </div>
          <div class="form-group">
            <label for="mobile_app_min_version">Minimum Required Version</label>
            <input id="mobile_app_min_version" v-model="localSettings.mobile_app_min_version" type="text" placeholder="e.g., 2.0.0" />
            <small>Users on older versions will be prompted to update</small>
          </div>
          <div class="form-group toggle-group">
            <div class="toggle-info">
              <label class="toggle-label">Force Update</label>
              <small>Block app access until users update to the minimum version</small>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" v-model="localSettings.mobile_app_force_update" />
              <span class="toggle-slider"></span>
            </label>
          </div>
        </div>

        <!-- Security Section -->
        <div class="settings-section">
          <h2>Security</h2>
          <p class="section-description">Configure authentication and session security parameters.</p>
          <div class="form-group">
            <label for="session_timeout_minutes">Session Timeout (minutes)</label>
            <input id="session_timeout_minutes" v-model.number="localSettings.session_timeout_minutes" type="number" min="1" max="1440" />
            <small>JWT access token TTL — users are logged out after this period of inactivity</small>
          </div>
          <div class="form-group">
            <label for="max_failed_login_attempts">Max Failed Login Attempts</label>
            <input id="max_failed_login_attempts" v-model.number="localSettings.max_failed_login_attempts" type="number" min="1" max="100" />
            <small>Account lockout threshold (default: 5)</small>
          </div>
          <div class="form-group">
            <label for="lockout_duration_minutes">Lockout Duration (minutes)</label>
            <input id="lockout_duration_minutes" v-model.number="localSettings.lockout_duration_minutes" type="number" min="1" max="1440" />
            <small>How long accounts remain locked after exceeding failed attempts (default: 10)</small>
          </div>
        </div>

        <!-- System Section -->
        <div class="settings-section">
          <h2>System</h2>
          <p class="section-description">General system configuration and maintenance controls.</p>

          <div class="form-group">
            <label for="system_name">System Name</label>
            <input id="system_name" v-model="localSettings.system_name" type="text" placeholder="e.g., HHMS" />
          </div>

          <div class="form-group">
            <label for="max_file_upload_size_mb">Max File Upload Size (MB)</label>
            <input id="max_file_upload_size_mb" v-model.number="localSettings.max_file_upload_size_mb" type="number" min="1" max="500" />
            <small>Maximum allowed file upload size across the platform (default: 10 MB)</small>
          </div>

          <div class="form-group">
            <label for="api_rate_limit">API Rate Limit (requests/minute)</label>
            <input id="api_rate_limit" v-model.number="localSettings.api_rate_limit" type="number" min="1" max="10000" />
            <small>Maximum API requests per minute per tenant</small>
          </div>

          <div class="form-group toggle-group">
            <div class="toggle-info">
              <label class="toggle-label">Maintenance Mode</label>
              <small>Puts the entire system in maintenance mode — all API routes return HTTP 503 (Super Admin routes are exempt)</small>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" v-model="localSettings.maintenance_mode" />
              <span class="toggle-slider toggle-danger"></span>
            </label>
          </div>

          <div v-if="localSettings.maintenance_mode" class="form-group">
            <label for="maintenance_message">Maintenance Message</label>
            <textarea
              id="maintenance_message"
              v-model="localSettings.maintenance_message"
              rows="3"
              placeholder="The system is currently undergoing scheduled maintenance. Please try again later."
            ></textarea>
          </div>
        </div>

        <!-- Support Contact Section -->
        <div class="settings-section">
          <h2>Support Contact</h2>
          <p class="section-description">Contact information displayed to users across the platform.</p>
          <div class="form-group">
            <label for="support_email">Support Email</label>
            <input id="support_email" v-model="localSettings.support_email" type="email" placeholder="support@example.com" />
          </div>
          <div class="form-group">
            <label for="support_phone">Support Phone</label>
            <input id="support_phone" v-model="localSettings.support_phone" type="text" placeholder="+1 800 000 0000" />
          </div>
        </div>

        <!-- Save Button -->
        <div class="settings-section save-section">
          <div class="form-actions">
            <button @click="updateSettings" class="btn btn-primary" :disabled="savingSettings">
              {{ savingSettings ? 'Saving...' : 'Save All Settings' }}
            </button>
          </div>
          <div v-if="successMessage" class="alert alert-success">{{ successMessage }}</div>
          <div v-if="errorMessage" class="alert alert-error">{{ errorMessage }}</div>
        </div>

        <!-- Feature Flags Section -->
        <div class="settings-section">
          <h2>Feature Flags</h2>
          <p class="section-description">Enable or disable features across all hospitals in the system.</p>
          <div class="feature-flags">
            <div v-for="(enabled, key) in localFeatureFlags" :key="key" class="feature-flag-item">
              <div class="flag-info">
                <label :for="key" class="flag-label">{{ formatFlagName(key) }}</label>
                <small class="flag-description">{{ getFlagDescription(key) }}</small>
              </div>
              <label class="toggle-switch">
                <input :id="key" type="checkbox" v-model="localFeatureFlags[key]" @change="updateFeatureFlag(key)" />
                <span class="toggle-slider"></span>
              </label>
            </div>
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
  settings: Object,
  featureFlags: Object,
});

const localSettings = reactive({ ...props.settings });
const localFeatureFlags = reactive({ ...props.featureFlags });

const savingSettings = ref(false);
const successMessage = ref('');
const errorMessage = ref('');

const formatFlagName = (key) => {
  return key.replace('feature_', '').split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
};

const getFlagDescription = (key) => {
  const descriptions = {
    feature_sms_notifications: 'Enable SMS notifications for appointments and alerts',
    feature_lab_module:        'Enable laboratory module for all hospitals',
    feature_pharmacy_module:   'Enable pharmacy module for all hospitals',
    feature_appointments:      'Enable appointment booking functionality',
    feature_mobile_app:        'Enable mobile app access for clinical staff',
  };
  return descriptions[key] || '';
};

const updateFeatureFlag = async (key) => {
  try {
    await axios.put(`/admin/api/feature-flags/${key}`, { enabled: localFeatureFlags[key] });
    showSuccess('Feature flag updated successfully');
  } catch (error) {
    localFeatureFlags[key] = !localFeatureFlags[key];
    showError('Failed to update feature flag');
  }
};

const updateSettings = async () => {
  savingSettings.value = true;
  clearMessages();

  // Convert booleans to the format the API expects
  const payload = {
    ...localSettings,
    mobile_app_force_update: localSettings.mobile_app_force_update ? 1 : 0,
    maintenance_mode: localSettings.maintenance_mode ? 1 : 0,
  };

  try {
    await axios.put('/admin/api/settings', payload);
    showSuccess('System settings updated successfully');
  } catch (error) {
    showError(error.response?.data?.error?.message || 'Failed to update system settings');
  } finally {
    savingSettings.value = false;
  }
};

const showSuccess = (msg) => {
  successMessage.value = msg;
  errorMessage.value = '';
  setTimeout(() => { successMessage.value = ''; }, 5000);
};

const showError = (msg) => {
  errorMessage.value = msg;
  successMessage.value = '';
  setTimeout(() => { errorMessage.value = ''; }, 5000);
};

const clearMessages = () => {
  successMessage.value = '';
  errorMessage.value = '';
};
</script>

<style scoped>
.system-settings {
  padding: 2rem;
  max-width: 900px;
  margin: 0 auto;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

h1 { font-size: 2rem; color: #2c3e50; margin: 0; }

.settings-container { display: flex; flex-direction: column; gap: 1.5rem; }

.settings-section {
  background: white;
  padding: 2rem;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.settings-section h2 { margin-top: 0; margin-bottom: 0.5rem; color: #2c3e50; font-size: 1.4rem; }
.section-description { color: #6c757d; margin-bottom: 1.5rem; }

.form-group { margin-bottom: 1.5rem; }
.form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #495057; }
.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #ced4da;
  border-radius: 4px;
  font-size: 1rem;
  box-sizing: border-box;
  transition: border-color 0.2s;
}
.form-group input:focus,
.form-group textarea:focus { outline: none; border-color: #007bff; }
.form-group small { display: block; margin-top: 0.25rem; color: #6c757d; font-size: 0.875rem; }

/* Toggle group */
.toggle-group {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  border: 1px solid #e9ecef;
  border-radius: 6px;
}
.toggle-info { flex: 1; }
.toggle-label { display: block; font-weight: 500; color: #2c3e50; margin-bottom: 0.25rem; }

/* Toggle switch */
.toggle-switch {
  position: relative;
  display: inline-block;
  width: 50px;
  height: 26px;
  flex-shrink: 0;
}
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
  position: absolute;
  cursor: pointer;
  top: 0; left: 0; right: 0; bottom: 0;
  background-color: #ccc;
  transition: 0.3s;
  border-radius: 26px;
}
.toggle-slider:before {
  position: absolute;
  content: "";
  height: 20px; width: 20px;
  left: 3px; bottom: 3px;
  background-color: white;
  transition: 0.3s;
  border-radius: 50%;
}
input:checked + .toggle-slider { background-color: #28a745; }
input:checked + .toggle-danger  { background-color: #dc3545; }
input:checked + .toggle-slider:before { transform: translateX(24px); }

/* Feature flags */
.feature-flags { display: flex; flex-direction: column; gap: 1rem; }
.feature-flag-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  border: 1px solid #e9ecef;
  border-radius: 6px;
  transition: background 0.2s;
}
.feature-flag-item:hover { background: #f8f9fa; }
.flag-info { flex: 1; }
.flag-label { display: block; font-weight: 500; color: #2c3e50; margin-bottom: 0.25rem; cursor: pointer; }
.flag-description { display: block; color: #6c757d; font-size: 0.875rem; }

/* Save section */
.save-section { background: #f8f9fa; }
.form-actions { display: flex; justify-content: flex-end; margin-bottom: 1rem; }

/* Buttons */
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
.btn-primary { background: #007bff; color: white; }
.btn-primary:hover:not(:disabled) { background: #0056b3; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-secondary { background: #6c757d; color: white; }
.btn-secondary:hover { background: #5a6268; }

/* Alerts */
.alert { padding: 1rem; border-radius: 4px; margin-top: 0.5rem; }
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
</style>
