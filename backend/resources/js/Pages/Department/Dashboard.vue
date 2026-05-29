<template>
  <DepartmentLayout :department="department" :currentUser="currentUser">
    <div class="hod-dashboard">
      <div class="header">
        <div>
          <h1>Dashboard</h1>
          <p class="subtitle">Real-time overview of department operations</p>
        </div>
        <div class="realtime-indicator" :class="{ connected: isConnected }">
          <span class="dot"></span>
          {{ isConnected ? 'Live' : 'Connecting...' }}
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon">👥</div>
          <div class="stat-content">
            <div class="stat-value">{{ localStats.total_in_queue || 0 }}</div>
            <div class="stat-label">Patients in Queue</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">✅</div>
          <div class="stat-content">
            <div class="stat-value">{{ localStats.patients_seen_today || 0 }}</div>
            <div class="stat-label">Patients Seen Today</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">⏱️</div>
          <div class="stat-content">
            <div class="stat-value">{{ formatWaitTime(localStats.average_wait_time) }}</div>
            <div class="stat-label">Avg Wait Time</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">👨‍⚕️</div>
          <div class="stat-content">
            <div class="stat-value">{{ localStats.staff_on_duty || 0 }}</div>
            <div class="stat-label">Staff on Duty</div>
          </div>
        </div>
      </div>

      <!-- Charts Section -->
      <div class="charts-grid">
        <div class="chart-card">
          <h3>Patient Flow (Last 7 Days)</h3>
          <canvas ref="patientFlowChart"></canvas>
        </div>
        <div class="chart-card">
          <h3>Staff Workload (Today)</h3>
          <canvas ref="staffWorkloadChart"></canvas>
        </div>
      </div>

      <!-- Recent Activity Feed -->
      <div class="activity-section">
        <h3>Recent Activity</h3>
        <div class="activity-feed">
          <div
            v-for="activity in localActivity"
            :key="activity.id"
            class="activity-item"
          >
            <div class="activity-icon" :class="`activity-${activity.event_type}`">
              {{ getActivityIcon(activity.event_type) }}
            </div>
            <div class="activity-content">
              <div class="activity-text">
                <strong>{{ activity.patient_name }}</strong>
                {{ getActivityText(activity.event_type) }}
                <span v-if="activity.staff_name">by {{ activity.staff_name }}</span>
              </div>
              <div class="activity-time">{{ formatTime(activity.timestamp) }}</div>
            </div>
          </div>
          <div v-if="localActivity.length === 0" class="empty-state">
            <p>No recent activity</p>
          </div>
        </div>
      </div>
    </div>
  </DepartmentLayout>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import { Chart, registerables } from 'chart.js';
import axios from 'axios';
import DepartmentLayout from '@/Layouts/DepartmentLayout.vue';

Chart.register(...registerables);

const props = defineProps({
  statistics: Object,
  patientFlowData: Object,
  staffWorkloadData: Object,
  recentActivity: Array,
  department: Object,
  currentUser: Object,
});

// Local reactive state for real-time updates
const localStats = ref({ ...props.statistics });
const localActivity = ref([...props.recentActivity]);

// WebSocket state
const isConnected = ref(false);
let echoChannel = null;

// Chart refs
const patientFlowChart = ref(null);
const staffWorkloadChart = ref(null);
let flowChartInstance = null;
let workloadChartInstance = null;

// Format helpers
const formatWaitTime = (minutes) => {
  if (!minutes) return '0m';
  if (minutes < 60) return `${Math.round(minutes)}m`;
  const hours = Math.floor(minutes / 60);
  const mins = Math.round(minutes % 60);
  return `${hours}h ${mins}m`;
};

const formatTime = (timestamp) => {
  const date = new Date(timestamp);
  const now = new Date();
  const diffMs = now - date;
  const diffMins = Math.floor(diffMs / 60000);
  
  if (diffMins < 1) return 'Just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  const hours = Math.floor(diffMins / 60);
  if (hours < 24) return `${hours}h ago`;
  return date.toLocaleDateString();
};

const getActivityIcon = (eventType) => {
  const icons = {
    patient_arrived: '🚪',
    vitals_recorded: '❤️',
    lab_result_added: '🔬',
    prescription_added: '💊',
    patient_routed: '➡️',
    patient_completed: '✅',
  };
  return icons[eventType] || '📋';
};

const getActivityText = (eventType) => {
  const texts = {
    patient_arrived: 'arrived in department',
    vitals_recorded: 'vitals recorded',
    lab_result_added: 'lab result added',
    prescription_added: 'prescription added',
    patient_routed: 'routed to next department',
    patient_completed: 'completed treatment',
  };
  return texts[eventType] || 'activity recorded';
};

// Initialize charts
const initializeCharts = () => {
  nextTick(() => {
    // Patient Flow Chart
    if (patientFlowChart.value) {
      const ctx = patientFlowChart.value.getContext('2d');
      flowChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
          labels: props.patientFlowData.labels || [],
          datasets: [{
            label: 'Patients',
            data: props.patientFlowData.data || [],
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            tension: 0.4,
            fill: true,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: false,
            },
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                stepSize: 1,
              },
            },
          },
        },
      });
    }

    // Staff Workload Chart
    if (staffWorkloadChart.value) {
      const ctx = staffWorkloadChart.value.getContext('2d');
      workloadChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: props.staffWorkloadData.labels || [],
          datasets: [{
            label: 'Patients Handled',
            data: props.staffWorkloadData.data || [],
            backgroundColor: '#28a745',
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: false,
            },
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                stepSize: 1,
              },
            },
          },
        },
      });
    }
  });
};

// Refresh statistics
const refreshStatistics = async () => {
  try {
    const response = await axios.get('/department/api/dashboard/statistics');
    if (response.data.success) {
      localStats.value = response.data.data;
    }
  } catch (error) {
    console.error('Failed to refresh statistics:', error);
  }
};

// Setup WebSocket
const setupEcho = () => {
  if (!window.Echo || !props.department?.id) return;

  echoChannel = window.Echo.private(`department.${props.department.id}`)
    .listen('.PatientRouted', (event) => {
      refreshStatistics();
      if (event.activity) {
        localActivity.value.unshift(event.activity);
        if (localActivity.value.length > 10) {
          localActivity.value.pop();
        }
      }
    })
    .listen('.PatientStatusChanged', (event) => {
      refreshStatistics();
    })
    .listen('.VitalsRecorded', (event) => {
      refreshStatistics();
      if (event.activity) {
        localActivity.value.unshift(event.activity);
        if (localActivity.value.length > 10) {
          localActivity.value.pop();
        }
      }
    });

  // Track connection state
  window.Echo.connector.pusher.connection.bind('connected', () => {
    isConnected.value = true;
  });
  window.Echo.connector.pusher.connection.bind('disconnected', () => {
    isConnected.value = false;
  });
  window.Echo.connector.pusher.connection.bind('unavailable', () => {
    isConnected.value = false;
  });

  // Check initial state
  isConnected.value = window.Echo.connector.pusher.connection.state === 'connected';
};

onMounted(() => {
  initializeCharts();
  setupEcho();
});

onUnmounted(() => {
  if (flowChartInstance) flowChartInstance.destroy();
  if (workloadChartInstance) workloadChartInstance.destroy();
  if (echoChannel && props.department?.id) {
    window.Echo.leave(`department.${props.department.id}`);
  }
});
</script>

<style scoped>
.hod-dashboard {
  padding: 2rem;
  max-width: 1400px;
  margin: 0 auto;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 2rem;
}

h1 {
  font-size: 2rem;
  color: #2c3e50;
  margin: 0 0 0.5rem 0;
}

.subtitle {
  color: #6c757d;
  margin: 0;
}

.realtime-indicator {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: #6c757d;
  padding: 0.5rem 1rem;
  border-radius: 20px;
  background: #f8f9fa;
}

.realtime-indicator.connected {
  color: #28a745;
  background: #d4edda;
}

.dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #6c757d;
}

.connected .dot {
  background: #28a745;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: white;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  display: flex;
  align-items: center;
  gap: 1rem;
}

.stat-icon {
  font-size: 2.5rem;
}

.stat-content {
  flex: 1;
}

.stat-value {
  font-size: 2rem;
  font-weight: 700;
  color: #007bff;
  margin-bottom: 0.25rem;
}

.stat-label {
  color: #6c757d;
  font-size: 0.875rem;
  font-weight: 500;
}

.charts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.chart-card {
  background: white;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.chart-card h3 {
  margin: 0 0 1rem 0;
  font-size: 1.125rem;
  color: #2c3e50;
}

.chart-card canvas {
  height: 250px !important;
}

.activity-section {
  background: white;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.activity-section h3 {
  margin: 0 0 1rem 0;
  font-size: 1.125rem;
  color: #2c3e50;
}

.activity-feed {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.activity-item {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  padding: 0.75rem;
  border-radius: 6px;
  transition: background 0.2s;
}

.activity-item:hover {
  background: #f8f9fa;
}

.activity-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  background: #e9ecef;
}

.activity-patient_arrived { background: #d1ecf1; }
.activity-vitals_recorded { background: #f8d7da; }
.activity-lab_result_added { background: #d4edda; }
.activity-prescription_added { background: #fff3cd; }
.activity-patient_routed { background: #d1ecf1; }
.activity-patient_completed { background: #d4edda; }

.activity-content {
  flex: 1;
}

.activity-text {
  color: #495057;
  font-size: 0.9375rem;
  margin-bottom: 0.25rem;
}

.activity-time {
  color: #6c757d;
  font-size: 0.8125rem;
}

.empty-state {
  text-align: center;
  padding: 2rem;
  color: #6c757d;
}

/* Responsive */
@media (max-width: 768px) {
  .hod-dashboard {
    padding: 1rem;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }

  .charts-grid {
    grid-template-columns: 1fr;
  }

  .header {
    flex-direction: column;
    gap: 1rem;
  }
}
</style>
