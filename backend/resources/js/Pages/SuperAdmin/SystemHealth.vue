<template>
  <SuperAdminLayout>
    <div class="system-health">
      <div class="header">
        <h1>System Health</h1>
        <span class="last-updated">Last updated: {{ lastUpdated }}</span>
      </div>

      <div v-if="loading" class="loading">Loading health data...</div>

      <template v-else>
        <!-- Stats Cards -->
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-value">{{ health.total_hospitals ?? 0 }}</div>
            <div class="stat-label">Total Hospitals</div>
          </div>
          <div class="stat-card stat-active">
            <div class="stat-value">{{ health.active_hospitals ?? 0 }}</div>
            <div class="stat-label">Active</div>
          </div>
          <div class="stat-card stat-suspended">
            <div class="stat-value">{{ health.suspended_hospitals ?? 0 }}</div>
            <div class="stat-label">Suspended</div>
          </div>
          <div class="stat-card stat-patients">
            <div class="stat-value">{{ health.total_patients_today ?? 0 }}</div>
            <div class="stat-label">Patients Today</div>
          </div>
        </div>

        <!-- Service Status -->
        <div class="services-section">
          <h2>Service Status</h2>
          <div class="services-grid">
            <div v-for="service in services" :key="service.key" class="service-card">
              <div class="service-info">
                <span class="service-name">{{ service.label }}</span>
                <span :class="['status-dot', health[service.key] === 'ok' ? 'dot-ok' : 'dot-error']"></span>
              </div>
              <div :class="['service-status', health[service.key] === 'ok' ? 'text-ok' : 'text-error']">
                {{ health[service.key] === 'ok' ? 'Operational' : 'Error' }}
              </div>
            </div>
          </div>
        </div>

        <!-- Uptime -->
        <div class="uptime-section">
          <h2>System Uptime</h2>
          <p class="uptime-value">{{ health.uptime ?? 'N/A' }}</p>
        </div>
      </template>
    </div>
  </SuperAdminLayout>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import SuperAdminLayout from '../../Layouts/SuperAdminLayout.vue';

const health = ref({});
const loading = ref(true);
const lastUpdated = ref('—');
let refreshInterval = null;

const services = [
  { key: 'database', label: 'Database' },
  { key: 'redis',    label: 'Redis' },
  { key: 'queue',    label: 'Queue' },
  { key: 'storage',  label: 'Storage' },
];

const fetchHealth = async () => {
  try {
    const response = await axios.get('/admin/api/system-health');
    health.value = response.data.data;
    lastUpdated.value = new Date().toLocaleTimeString();
  } catch (error) {
    console.error('Failed to fetch system health:', error);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchHealth();
  // Auto-refresh every 30 seconds
  refreshInterval = setInterval(fetchHealth, 30000);
});

onUnmounted(() => {
  if (refreshInterval) clearInterval(refreshInterval);
});
</script>

<style scoped>
.system-health {
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

.last-updated {
  font-size: 0.875rem;
  color: #6c757d;
}

.loading {
  text-align: center;
  padding: 3rem;
  color: #6c757d;
  font-size: 1.1rem;
}

/* Stats */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 1rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: white;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 2px 4px rgba(0,0,0,0.08);
  text-align: center;
}

.stat-value {
  font-size: 2.5rem;
  font-weight: 700;
  color: #2c3e50;
}

.stat-label {
  font-size: 0.875rem;
  color: #6c757d;
  margin-top: 0.25rem;
}

.stat-active .stat-value   { color: #28a745; }
.stat-suspended .stat-value { color: #ffc107; }
.stat-patients .stat-value  { color: #007bff; }

/* Services */
.services-section {
  background: white;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 2px 4px rgba(0,0,0,0.08);
  margin-bottom: 2rem;
}

.services-section h2 {
  margin-top: 0;
  margin-bottom: 1.25rem;
  color: #2c3e50;
}

.services-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.service-card {
  border: 1px solid #e9ecef;
  border-radius: 6px;
  padding: 1rem 1.25rem;
}

.service-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.service-name {
  font-weight: 600;
  color: #2c3e50;
}

.status-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  display: inline-block;
}

.dot-ok    { background: #28a745; }
.dot-error { background: #dc3545; }

.service-status {
  font-size: 0.875rem;
  font-weight: 500;
}

.text-ok    { color: #28a745; }
.text-error { color: #dc3545; }

/* Uptime */
.uptime-section {
  background: white;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 2px 4px rgba(0,0,0,0.08);
}

.uptime-section h2 {
  margin-top: 0;
  margin-bottom: 0.75rem;
  color: #2c3e50;
}

.uptime-value {
  font-size: 1.5rem;
  font-weight: 600;
  color: #007bff;
  margin: 0;
}
</style>
