<template>
  <DepartmentLayout :department="department" :currentUser="currentUser">
    <div class="department-reports">
      <h1>Department Reports</h1>
      <p class="subtitle">Generate and export comprehensive department reports</p>

      <!-- Report Configuration -->
      <div class="card report-config">
        <h3>Generate Report</h3>
        <form @submit.prevent="generateReport" class="form">
          <div class="form-group">
            <label>Report Type</label>
            <select v-model="reportForm.type" class="form-input" required>
              <option value="">Select report type...</option>
              <option value="daily_summary">Daily Summary</option>
              <option value="staff_performance">Staff Performance</option>
              <option value="efficiency_metrics">Department Efficiency Metrics</option>
            </select>
          </div>

          <div v-if="reportForm.type === 'daily_summary'" class="form-group">
            <label>Date</label>
            <input
              v-model="reportForm.date"
              type="date"
              class="form-input"
              required
            />
          </div>

          <div v-if="reportForm.type !== 'daily_summary' && reportForm.type" class="form-grid">
            <div class="form-group">
              <label>Start Date</label>
              <input
                v-model="reportForm.start_date"
                type="date"
                class="form-input"
                required
              />
            </div>
            <div class="form-group">
              <label>End Date</label>
              <input
                v-model="reportForm.end_date"
                type="date"
                class="form-input"
                required
              />
            </div>
          </div>

          <div v-if="dateRangeError" class="error-text">{{ dateRangeError }}</div>

          <div class="form-actions">
            <button type="submit" class="btn btn-primary" :disabled="isGenerating">
              {{ isGenerating ? 'Generating...' : 'Generate Report' }}
            </button>
          </div>
        </form>
      </div>

      <!-- Report Preview -->
      <div v-if="reportData" class="card report-preview">
        <div class="report-header">
          <h3>{{ getReportTitle() }}</h3>
          <div class="export-buttons">
            <button @click="exportPDF" class="btn btn-secondary" :disabled="isExporting">
              {{ isExporting ? 'Exporting...' : '📄 Export PDF' }}
            </button>
            <button @click="exportExcel" class="btn btn-secondary" :disabled="isExporting">
              {{ isExporting ? 'Exporting...' : '📊 Export Excel' }}
            </button>
          </div>
        </div>

        <!-- Daily Summary Report -->
        <div v-if="reportForm.type === 'daily_summary'" class="report-content">
          <div class="summary-metrics">
            <div class="metric-card">
              <div class="metric-value">{{ reportData.total_patients || 0 }}</div>
              <div class="metric-label">Total Patients</div>
            </div>
            <div class="metric-card">
              <div class="metric-value">{{ formatWaitTime(reportData.average_wait_time) }}</div>
              <div class="metric-label">Avg Wait Time</div>
            </div>
            <div class="metric-card">
              <div class="metric-value">{{ formatDuration(reportData.average_service_time) }}</div>
              <div class="metric-label">Avg Service Time</div>
            </div>
            <div class="metric-card">
              <div class="metric-value">{{ reportData.date }}</div>
              <div class="metric-label">Report Date</div>
            </div>
          </div>

          <h4>Patient List</h4>
          <div class="table-container">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Patient Number</th>
                  <th>Patient Name</th>
                  <th>Arrival</th>
                  <th>Departure</th>
                  <th>Wait Time</th>
                  <th>Outcome</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="patient in (reportData.patient_list || [])" :key="patient.patient_number">
                  <td>{{ patient.patient_number }}</td>
                  <td>{{ patient.patient_name }}</td>
                  <td>{{ patient.time_of_arrival || '—' }}</td>
                  <td>{{ patient.time_of_departure || '—' }}</td>
                  <td>{{ patient.wait_time ? formatWaitTime(patient.wait_time) : '—' }}</td>
                  <td><span class="badge" :class="patient.outcome === 'Completed' ? 'badge-completed' : 'badge-in_progress'">{{ patient.outcome }}</span></td>
                </tr>
                <tr v-if="!reportData.patient_list?.length">
                  <td colspan="6" style="text-align:center;color:#6c757d;">No patients found for this date.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Staff Performance Report -->
        <div v-if="reportForm.type === 'staff_performance'" class="report-content">
          <div class="table-container">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Staff Name</th>
                  <th>Role</th>
                  <th>Patients Handled</th>
                  <th>Avg Service Time</th>
                  <th>Notes Added</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="staff in (reportData.staff_metrics || [])" :key="staff.staff_name">
                  <td>{{ staff.staff_name }}</td>
                  <td>{{ staff.role }}</td>
                  <td>{{ staff.patients_handled }}</td>
                  <td>{{ formatDuration(staff.average_service_time) }}</td>
                  <td>{{ staff.notes_added }}</td>
                </tr>
                <tr v-if="!reportData.staff_metrics?.length">
                  <td colspan="5" style="text-align:center;color:#6c757d;">No staff data found for this period.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Efficiency Metrics Report -->
        <div v-if="reportForm.type === 'efficiency_metrics'" class="report-content">
          <div class="metrics-grid">
            <div class="metric-box">
              <div class="metric-title">Average Wait Time</div>
              <div class="metric-value-large">{{ formatWaitTime(reportData.metrics?.average_wait_time) }}</div>
              <div class="metric-trend" :class="getTrendClass(reportData.trend_indicators?.average_service_time?.percentage_change)">
                {{ getTrendText(reportData.trend_indicators?.average_service_time) }}
              </div>
            </div>
            <div class="metric-box">
              <div class="metric-title">Average Service Time</div>
              <div class="metric-value-large">{{ formatDuration(reportData.metrics?.average_service_time) }}</div>
              <div class="metric-trend" :class="getTrendClass(reportData.trend_indicators?.average_service_time?.percentage_change)">
                {{ getTrendText(reportData.trend_indicators?.average_service_time) }}
              </div>
            </div>
            <div class="metric-box">
              <div class="metric-title">Throughput</div>
              <div class="metric-value-large">{{ reportData.metrics?.patient_throughput || 0 }} patients/day</div>
              <div class="metric-trend" :class="getTrendClass(reportData.trend_indicators?.throughput?.percentage_change, true)">
                {{ getTrendText(reportData.trend_indicators?.throughput, true) }}
              </div>
            </div>
            <div class="metric-box">
              <div class="metric-title">Staff Utilization</div>
              <div class="metric-value-large">{{ reportData.metrics?.staff_utilization || 0 }}%</div>
              <div class="metric-trend" :class="getTrendClass(reportData.trend_indicators?.staff_utilization?.percentage_change, true)">
                {{ getTrendText(reportData.trend_indicators?.staff_utilization, true) }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="errorMessage" class="error-message">{{ errorMessage }}</div>
    </div>
  </DepartmentLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import DepartmentLayout from '@/Layouts/DepartmentLayout.vue';

const props = defineProps({
  department: Object,
  currentUser: Object,
});

// State
const reportForm = ref({
  type: '',
  date: new Date().toISOString().split('T')[0],
  start_date: '',
  end_date: '',
});

const reportData = ref(null);
const dateRangeError = ref('');
const errorMessage = ref('');
const isGenerating = ref(false);
const isExporting = ref(false);

// Methods
const validateDateRange = () => {
  dateRangeError.value = '';
  
  if (reportForm.value.type === 'daily_summary') return true;
  
  if (!reportForm.value.start_date || !reportForm.value.end_date) {
    dateRangeError.value = 'Both start and end dates are required.';
    return false;
  }
  
  const start = new Date(reportForm.value.start_date);
  const end = new Date(reportForm.value.end_date);
  
  if (start > end) {
    dateRangeError.value = 'Start date must be before end date.';
    return false;
  }
  
  const daysDiff = (end - start) / (1000 * 60 * 60 * 24);
  if (daysDiff > 365) {
    dateRangeError.value = 'Date range cannot exceed 365 days.';
    return false;
  }
  
  return true;
};

const generateReport = async () => {
  if (!validateDateRange()) return;
  
  isGenerating.value = true;
  errorMessage.value = '';
  reportData.value = null;
  
  try {
    let endpoint = '';
    let params = {};
    
    if (reportForm.value.type === 'daily_summary') {
      endpoint = '/department/api/reports/daily-summary';
      params = { date: reportForm.value.date };
    } else if (reportForm.value.type === 'staff_performance') {
      endpoint = '/department/api/reports/staff-performance';
      params = {
        start_date: reportForm.value.start_date,
        end_date: reportForm.value.end_date,
      };
    } else if (reportForm.value.type === 'efficiency_metrics') {
      endpoint = '/department/api/reports/efficiency-metrics';
      params = {
        start_date: reportForm.value.start_date,
        end_date: reportForm.value.end_date,
      };
    }
    
    const response = await axios.get(endpoint, { params });
    
    if (response.data.success) {
      reportData.value = response.data.data;
    }
  } catch (error) {
    errorMessage.value = error.response?.data?.error?.message || 'Failed to generate report.';
  } finally {
    isGenerating.value = false;
  }
};

const exportPDF = async () => {
  if (!reportData.value) return;
  
  isExporting.value = true;
  
  try {
    const response = await axios.post(
      '/department/api/reports/export-pdf',
      {
        report_type: reportForm.value.type,
        report_data: reportData.value,
      },
      { responseType: 'blob' }
    );
    
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `${reportForm.value.type}_${Date.now()}.pdf`);
    document.body.appendChild(link);
    link.click();
    link.remove();
  } catch (error) {
    errorMessage.value = 'Failed to export PDF.';
  } finally {
    isExporting.value = false;
  }
};

const exportExcel = async () => {
  if (!reportData.value) return;
  
  isExporting.value = true;
  
  try {
    const response = await axios.post(
      '/department/api/reports/export-excel',
      {
        report_type: reportForm.value.type,
        report_data: reportData.value,
      },
      { responseType: 'blob' }
    );
    
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `${reportForm.value.type}_${Date.now()}.xlsx`);
    document.body.appendChild(link);
    link.click();
    link.remove();
  } catch (error) {
    errorMessage.value = 'Failed to export Excel.';
  } finally {
    isExporting.value = false;
  }
};

const getReportTitle = () => {
  const titles = {
    daily_summary: 'Daily Summary Report',
    staff_performance: 'Staff Performance Report',
    efficiency_metrics: 'Department Efficiency Metrics',
  };
  return titles[reportForm.value.type] || 'Report';
};

const formatWaitTime = (minutes) => {
  if (!minutes) return '0m';
  if (minutes < 60) return `${Math.round(minutes)}m`;
  const hours = Math.floor(minutes / 60);
  const mins = Math.round(minutes % 60);
  return `${hours}h ${mins}m`;
};

const formatDuration = (minutes) => {
  return formatWaitTime(minutes);
};

const getTrendClass = (trend, higherIsBetter = false) => {
  if (!trend) return '';
  const change = typeof trend === 'object' ? trend.percentage_change : trend;
  if (!change) return '';
  if (higherIsBetter) {
    return change > 0 ? 'trend-up' : 'trend-down';
  }
  return change > 0 ? 'trend-down' : 'trend-up';
};

const getTrendText = (trend, higherIsBetter = false) => {
  if (!trend) return 'No change';
  const change = typeof trend === 'object' ? trend.percentage_change : trend;
  const direction = typeof trend === 'object' ? trend.direction : null;
  if (!change) return 'No change';
  const arrow = change > 0 ? '↑' : '↓';
  const label = direction || ((higherIsBetter && change > 0) || (!higherIsBetter && change < 0) ? 'Improving' : 'Declining');
  return `${arrow} ${Math.abs(change)}% ${label}`;
};
</script>

<style scoped>
.department-reports {
  padding: 2rem;
  max-width: 1200px;
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

.card {
  background: white;
  border-radius: 8px;
  padding: 2rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  margin-bottom: 2rem;
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

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

.form-actions {
  display: flex;
  justify-content: flex-end;
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

.btn-secondary {
  background: #6c757d;
  color: white;
}

.btn-secondary:hover:not(:disabled) {
  background: #545b62;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.report-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid #e9ecef;
}

.export-buttons {
  display: flex;
  gap: 1rem;
}

.summary-metrics {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.metric-card {
  background: #f8f9fa;
  padding: 1.5rem;
  border-radius: 6px;
  text-align: center;
}

.metric-value {
  font-size: 2rem;
  font-weight: 700;
  color: #007bff;
  margin-bottom: 0.5rem;
}

.metric-label {
  color: #6c757d;
  font-size: 0.875rem;
  font-weight: 500;
}

.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
}

.metric-box {
  background: #f8f9fa;
  padding: 2rem;
  border-radius: 8px;
  text-align: center;
}

.metric-title {
  color: #6c757d;
  font-size: 0.875rem;
  font-weight: 500;
  margin-bottom: 1rem;
}

.metric-value-large {
  font-size: 2.5rem;
  font-weight: 700;
  color: #2c3e50;
  margin-bottom: 0.5rem;
}

.metric-trend {
  font-size: 0.875rem;
  font-weight: 500;
}

.trend-up {
  color: #28a745;
}

.trend-down {
  color: #dc3545;
}

.table-container {
  overflow-x: auto;
  margin-top: 1rem;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table thead {
  background: #f8f9fa;
}

.data-table th {
  padding: 0.75rem;
  text-align: left;
  font-weight: 600;
  color: #495057;
  font-size: 0.875rem;
  border-bottom: 2px solid #e9ecef;
}

.data-table td {
  padding: 0.75rem;
  border-bottom: 1px solid #e9ecef;
  color: #495057;
}

.badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
}

.badge-active { background: #d4edda; color: #155724; }
.badge-completed { background: #d1ecf1; color: #0c5460; }
.badge-in_progress { background: #fff3cd; color: #856404; }

.error-text {
  color: #dc3545;
  font-size: 0.875rem;
}

.error-message {
  padding: 0.75rem 1rem;
  background: #f8d7da;
  color: #721c24;
  border-radius: 4px;
  font-size: 0.9375rem;
}

h4 {
  margin: 2rem 0 1rem 0;
  font-size: 1.125rem;
  color: #2c3e50;
}

/* Responsive */
@media (max-width: 768px) {
  .department-reports {
    padding: 1rem;
  }

  .report-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
  }

  .export-buttons {
    width: 100%;
    flex-direction: column;
  }

  .export-buttons .btn {
    width: 100%;
  }
}
</style>
