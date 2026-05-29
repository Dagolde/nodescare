<template>
  <DepartmentLayout :department="department" :currentUser="currentUser">
    <div class="vitals-recording">
      <h1>Record Patient Vitals</h1>
      <p class="subtitle">Record and view vital signs for patients in your department</p>

      <!-- Patient Selector -->
      <div class="card patient-selector">
        <h3>Select Patient</h3>
        <div class="search-box">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search by patient name or number..."
            class="form-input"
            @input="searchPatients"
          />
        </div>
        <div v-if="searchResults.length > 0" class="search-results">
          <div
            v-for="patient in searchResults"
            :key="patient.id"
            class="patient-item"
            @click="selectPatient(patient)"
          >
            <div class="patient-info">
              <strong>{{ patient.patient?.full_name }}</strong>
              <span class="patient-number">{{ patient.patient?.patient_number }}</span>
            </div>
            <div class="patient-complaint">{{ patient.patient?.chief_complaint }}</div>
          </div>
        </div>
      </div>

      <!-- Vitals Form -->
      <div v-if="selectedPatient" class="card vitals-form">
        <div class="patient-header">
          <h3>Recording Vitals for: {{ selectedPatient.patient?.full_name }}</h3>
          <span class="patient-number">{{ selectedPatient.patient?.patient_number }}</span>
        </div>

        <form @submit.prevent="submitVitals" class="form">
          <div class="form-grid">
            <div class="form-group">
              <label>Blood Pressure (mmHg)</label>
              <input
                v-model="vitalsForm.blood_pressure"
                type="text"
                placeholder="120/80"
                class="form-input"
              />
              <span class="hint">Format: systolic/diastolic</span>
            </div>

            <div class="form-group">
              <label>Temperature (°C)</label>
              <input
                v-model.number="vitalsForm.temperature"
                type="number"
                step="0.1"
                min="30"
                max="45"
                placeholder="37.0"
                class="form-input"
              />
            </div>

            <div class="form-group">
              <label>Pulse Rate (bpm)</label>
              <input
                v-model.number="vitalsForm.pulse_rate"
                type="number"
                min="30"
                max="200"
                placeholder="72"
                class="form-input"
              />
            </div>

            <div class="form-group">
              <label>Respiratory Rate (breaths/min)</label>
              <input
                v-model.number="vitalsForm.respiratory_rate"
                type="number"
                min="5"
                max="60"
                placeholder="16"
                class="form-input"
              />
            </div>

            <div class="form-group">
              <label>Oxygen Saturation (%)</label>
              <input
                v-model.number="vitalsForm.oxygen_saturation"
                type="number"
                min="50"
                max="100"
                placeholder="98"
                class="form-input"
              />
            </div>

            <div class="form-group">
              <label>Blood Glucose (mg/dL)</label>
              <input
                v-model.number="vitalsForm.blood_glucose"
                type="number"
                min="20"
                max="600"
                placeholder="90"
                class="form-input"
              />
            </div>

            <div class="form-group">
              <label>Weight (kg)</label>
              <input
                v-model.number="vitalsForm.weight"
                type="number"
                step="0.1"
                min="1"
                max="500"
                placeholder="70.0"
                class="form-input"
                @input="calculateBMI"
              />
            </div>

            <div class="form-group">
              <label>Height (cm)</label>
              <input
                v-model.number="vitalsForm.height"
                type="number"
                step="0.1"
                min="30"
                max="300"
                placeholder="170.0"
                class="form-input"
                @input="calculateBMI"
              />
            </div>
          </div>

          <div v-if="calculatedBMI" class="bmi-display">
            <strong>Calculated BMI:</strong> {{ calculatedBMI }}
            <span :class="getBMIClass(calculatedBMI)">{{ getBMICategory(calculatedBMI) }}</span>
          </div>

          <div class="form-group">
            <label>Notes (Optional)</label>
            <textarea
              v-model="vitalsForm.notes"
              class="form-input"
              rows="3"
              placeholder="Additional observations..."
            ></textarea>
          </div>

          <div v-if="formErrors.general" class="error-message">{{ formErrors.general }}</div>

          <div class="form-actions">
            <button type="button" @click="clearForm" class="btn btn-secondary">Clear</button>
            <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
              {{ isSubmitting ? 'Recording...' : 'Record Vitals' }}
            </button>
          </div>

          <div v-if="successMessage" class="success-message">{{ successMessage }}</div>
        </form>
      </div>

      <!-- Vitals History -->
      <div v-if="selectedPatient && vitalsHistory.length > 0" class="card vitals-history">
        <h3>Vitals History</h3>
        <div class="table-container">
          <table class="data-table">
            <thead>
              <tr>
                <th>Date/Time</th>
                <th>BP</th>
                <th>Temp</th>
                <th>Pulse</th>
                <th>RR</th>
                <th>SpO2</th>
                <th>BMI</th>
                <th>Recorded By</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="vital in vitalsHistory" :key="vital.id">
                <td>{{ formatDateTime(vital.created_at) }}</td>
                <td :class="getVitalClass('bp', vital.blood_pressure)">
                  {{ vital.blood_pressure || '-' }}
                </td>
                <td :class="getVitalClass('temp', vital.temperature)">
                  {{ vital.temperature ? vital.temperature + '°C' : '-' }}
                </td>
                <td :class="getVitalClass('pulse', vital.pulse_rate)">
                  {{ vital.pulse_rate || '-' }}
                </td>
                <td :class="getVitalClass('rr', vital.respiratory_rate)">
                  {{ vital.respiratory_rate || '-' }}
                </td>
                <td :class="getVitalClass('spo2', vital.oxygen_saturation)">
                  {{ vital.oxygen_saturation ? vital.oxygen_saturation + '%' : '-' }}
                </td>
                <td :class="getVitalClass('bmi', vital.bmi)">
                  {{ vital.bmi || '-' }}
                </td>
                <td>{{ vital.recorder?.name || 'Unknown' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </DepartmentLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import DepartmentLayout from '@/Layouts/DepartmentLayout.vue';

const props = defineProps({
  patientRecord: Object,
  vitalsHistory: Array,
  department: Object,
  currentUser: Object,
});

// State
const searchQuery = ref('');
const searchResults = ref([]);
const selectedPatient = ref(props.patientRecord || null);
const vitalsHistory = ref(props.vitalsHistory || []);

const vitalsForm = ref({
  blood_pressure: '',
  temperature: null,
  pulse_rate: null,
  respiratory_rate: null,
  oxygen_saturation: null,
  weight: null,
  height: null,
  blood_glucose: null,
  notes: '',
});

const formErrors = ref({});
const successMessage = ref('');
const isSubmitting = ref(false);

// Computed
const calculatedBMI = computed(() => {
  if (vitalsForm.value.weight && vitalsForm.value.height) {
    const heightInMeters = vitalsForm.value.height / 100;
    const bmi = vitalsForm.value.weight / (heightInMeters * heightInMeters);
    return bmi.toFixed(2);
  }
  return null;
});

// Methods
const searchPatients = async () => {
  if (searchQuery.value.length < 2) {
    searchResults.value = [];
    return;
  }

  try {
    const response = await axios.get('/department/queue', {
      params: { search: searchQuery.value },
    });
    searchResults.value = response.data.queueRecords?.data || [];
  } catch (error) {
    console.error('Search failed:', error);
  }
};

const selectPatient = async (patient) => {
  selectedPatient.value = patient;
  searchResults.value = [];
  searchQuery.value = '';

  // Load vitals history
  try {
    const response = await axios.get(`/department/api/patient-records/${patient.id}/vitals`);
    if (response.data.success) {
      vitalsHistory.value = response.data.data;
    }
  } catch (error) {
    console.error('Failed to load vitals history:', error);
  }
};

const calculateBMI = () => {
  // BMI is calculated automatically via computed property
};

const getBMICategory = (bmi) => {
  const bmiValue = parseFloat(bmi);
  if (bmiValue < 18.5) return 'Underweight';
  if (bmiValue < 25) return 'Normal';
  if (bmiValue < 30) return 'Overweight';
  return 'Obese';
};

const getBMIClass = (bmi) => {
  const bmiValue = parseFloat(bmi);
  if (bmiValue < 18.5) return 'bmi-underweight';
  if (bmiValue < 25) return 'bmi-normal';
  if (bmiValue < 30) return 'bmi-overweight';
  return 'bmi-obese';
};

const getVitalClass = (type, value) => {
  if (!value) return '';
  
  // Simple normal range checks
  if (type === 'temp') {
    const temp = parseFloat(value);
    if (temp < 36.1 || temp > 37.2) return 'vital-abnormal';
  } else if (type === 'pulse') {
    if (value < 60 || value > 100) return 'vital-abnormal';
  } else if (type === 'spo2') {
    if (value < 95) return 'vital-abnormal';
  } else if (type === 'bmi') {
    const bmi = parseFloat(value);
    if (bmi < 18.5 || bmi >= 25) return 'vital-abnormal';
  }
  
  return 'vital-normal';
};

const formatDateTime = (datetime) => {
  const date = new Date(datetime);
  return date.toLocaleString();
};

const submitVitals = async () => {
  if (!selectedPatient.value) return;

  isSubmitting.value = true;
  formErrors.value = {};
  successMessage.value = '';

  try {
    const response = await axios.post(
      `/department/api/patient-records/${selectedPatient.value.id}/vitals`,
      vitalsForm.value
    );

    if (response.data.success) {
      successMessage.value = 'Vitals recorded successfully!';
      
      // Add to history
      vitalsHistory.value.unshift(response.data.data);
      
      // Clear form
      clearForm();
      
      setTimeout(() => (successMessage.value = ''), 3000);
    }
  } catch (error) {
    if (error.response?.data?.errors) {
      formErrors.value = error.response.data.errors;
    } else {
      formErrors.value.general = error.response?.data?.error?.message || 'Failed to record vitals.';
    }
  } finally {
    isSubmitting.value = false;
  }
};

const clearForm = () => {
  vitalsForm.value = {
    blood_pressure: '',
    temperature: null,
    pulse_rate: null,
    respiratory_rate: null,
    oxygen_saturation: null,
    weight: null,
    height: null,
    blood_glucose: null,
    notes: '',
  };
};
</script>

<style scoped>
.vitals-recording {
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

.search-box {
  margin-bottom: 1rem;
}

.form-input {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 0.9375rem;
  box-sizing: border-box;
}

.form-input:focus {
  outline: none;
  border-color: #007bff;
  box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.15);
}

.search-results {
  border: 1px solid #e9ecef;
  border-radius: 4px;
  max-height: 300px;
  overflow-y: auto;
}

.patient-item {
  padding: 1rem;
  border-bottom: 1px solid #e9ecef;
  cursor: pointer;
  transition: background 0.2s;
}

.patient-item:hover {
  background: #f8f9fa;
}

.patient-item:last-child {
  border-bottom: none;
}

.patient-info {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 0.25rem;
}

.patient-number {
  font-family: monospace;
  color: #007bff;
  font-size: 0.875rem;
}

.patient-complaint {
  color: #6c757d;
  font-size: 0.875rem;
}

.patient-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid #e9ecef;
}

.form {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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

.hint {
  font-size: 0.8125rem;
  color: #6c757d;
}

.bmi-display {
  padding: 1rem;
  background: #f8f9fa;
  border-radius: 4px;
  display: flex;
  align-items: center;
  gap: 1rem;
  font-size: 1.125rem;
}

.bmi-normal { color: #28a745; }
.bmi-underweight { color: #ffc107; }
.bmi-overweight { color: #fd7e14; }
.bmi-obese { color: #dc3545; }

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

.btn-secondary {
  background: #6c757d;
  color: white;
}

.btn-secondary:hover {
  background: #545b62;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
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

.table-container {
  overflow-x: auto;
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

.vital-normal {
  color: #28a745;
  font-weight: 500;
}

.vital-abnormal {
  color: #dc3545;
  font-weight: 500;
}

/* Responsive */
@media (max-width: 768px) {
  .vitals-recording {
    padding: 1rem;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }

  .patient-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }
}
</style>
