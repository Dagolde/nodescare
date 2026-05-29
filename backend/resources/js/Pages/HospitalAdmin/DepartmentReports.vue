<template>
  <HospitalAdminLayout>
    <div class="department-reports-page">
      <div class="header">
        <h1>Department Activity Reports</h1>
      </div>

      <div class="filters-card">
        <div class="filters">
          <div class="form-group">
            <label>Start Date</label>
            <input v-model="filters.start_date" type="date" />
          </div>
          <div class="form-group">
            <label>End Date</label>
            <input v-model="filters.end_date" type="date" />
          </div>
          <div class="form-group">
            <label>Department</label>
            <select v-model="filters.department_id">
              <option value="">All Departments</option>
              <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
            </select>
          </div>
          <div class="form-group">
            <button @click="loadReport" class="btn btn-primary">Load Report</button>
          </div>
        </div>
      </div>

      <div v-if="reportData" class="report-content">
        <div class="stats-grid">
          <div v-for="stat in reportData" :key="stat.department_id" class="stat-card">
            <h3>{{ stat.department_name }}</h3>
            <div class="stat-row">
              <span class="stat-label">Throughput:</span>
              <span class="stat-value">{{ stat.throughput }} patients</span>
            </div>
            <div class="stat-row">
              <span class="stat-label">Pending:</span>
              <span class="stat-value">{{ stat.pending_count }} patients</span>
            </div>
            <div class="stat-row">
              <span class="stat-label">Avg Wait Time:</span>
              <span class="stat-value">{{ stat.avg_wait_time_minutes }} min</span>
            </div>
          </div>
        </div>

        <div class="chart-section">
          <h2>Throughput Chart</h2>
          <div class="chart-placeholder">
            <svg viewBox="0 0 400 200" class="simple-chart">
              <line x1="40" y1="10" x2="40" y2="180" stroke="#ccc" stroke-width="2"/>
              <line x1="40" y1="180" x2="390" y2="180" stroke="#ccc" stroke-width="2"/>
              <text x="10" y="15" font-size="12" fill="#666">High</text>
              <text x="10" y="95" font-size="12" fill="#666">Med</text>
              <text x="10" y="180" font-size="12" fill="#666">Low</text>
              <rect v-for="(stat, index) in reportData" :key="index"
                    :x="60 + index * 60" 
                    :y="180 - (stat.throughput * 2)" 
                    :width="40" 
                    :height="stat.throughput * 2"
                    :fill="getBarColor(index)" />
            </svg>
          </div>
        </div>

        <div class="chart-section">
          <h2>Wait Times Chart</h2>
          <div class="chart-placeholder">
            <svg viewBox="0 0 400 200" class="simple-chart">
              <line x1="40" y1="10" x2="40" y2="180" stroke="#ccc" stroke-width="2"/>
              <line x1="40" y1="180" x2="390" y2="180" stroke="#ccc" stroke-width="2"/>
              <text x="5" y="15" font-size="12" fill="#666">120m</text>
              <text x="10" y="95" font-size="12" fill="#666">60m</text>
              <text x="15" y="180" font-size="12" fill="#666">0m</text>
              <rect v-for="(stat, index) in reportData" :key="index"
                    :x="60 + index * 60" 
                    :y="180 - (stat.avg_wait_time_minutes * 1.5)" 
                    :width="40" 
                    :height="stat.avg_wait_time_minutes * 1.5"
                    :fill="getBarColor(index)" />
            </svg>
          </div>
        </div>
      </div>
    </div>
  </HospitalAdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import axios from 'axios';
import HospitalAdminLayout from '../../Layouts/HospitalAdminLayout.vue';

const props = defineProps({
  departments: Array,
  filters: Object,
});

const filters = reactive({
  start_date: props.filters.start_date || '',
  end_date: props.filters.end_date || '',
  department_id: props.filters.department_id || '',
});

const reportData = ref(null);

const loadReport = async () => {
  try {
    const response = await axios.get('/hospital-admin/api/reports/department-activity', { params: filters });
    reportData.value = response.data.data;
  } catch (error) {
    console.error('Failed to load report:', error);
    alert('Failed to load report');
  }
};

const getBarColor = (index) => {
  const colors = ['#667eea', '#f093fb', '#4facfe', '#43e97b', '#fa709a', '#feca57'];
  return colors[index % colors.length];
};

// Load report on mount if filters are set
if (filters.start_date || filters.end_date || filters.department_id) {
  loadReport();
}
</script>

<style scoped>
.department-reports-page {
  padding: 2rem;
  max-width: 1400px;
  margin: 0 auto;
}

.header {
  margin-bottom: 2rem;
}

h1 {
  font-size: 2rem;
  color: #2c3e50;
  margin: 0;
}

.filters-card {
  background: white;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  margin-bottom: 2rem;
}

.filters {
  display: flex;
  gap: 1rem;
  align-items: flex-end;
}

.form-group {
  flex: 1;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: #495057;
  font-size: 0.9375rem;
}

.form-group input,
.form-group select {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #ced4da;
  border-radius: 4px;
  font-size: 1rem;
  box-sizing: border-box;
}

.btn {
  padding: 0.5rem 1.5rem;
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

.report-content {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 1.5rem;
}

.stat-card {
  background: white;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.stat-card h3 {
  margin: 0 0 1rem 0;
  font-size: 1.125rem;
  color: #2c3e50;
}

.stat-row {
  display: flex;
  justify-content: space-between;
  padding: 0.5rem 0;
  border-bottom: 1px solid #e9ecef;
}

.stat-row:last-child {
  border-bottom: none;
}

.stat-label {
  color: #6c757d;
  font-size: 0.9375rem;
}

.stat-value {
  font-weight: 600;
  color: #2c3e50;
  font-size: 0.9375rem;
}

.chart-section {
  background: white;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.chart-section h2 {
  margin: 0 0 1.5rem 0;
  font-size: 1.25rem;
  color: #2c3e50;
}

.chart-placeholder {
  width: 100%;
  height: 250px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.simple-chart {
  width: 100%;
  height: 100%;
}
</style>
