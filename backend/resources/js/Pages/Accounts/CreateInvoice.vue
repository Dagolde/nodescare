<template>
  <HospitalAdminLayout>
    <div class="create-invoice">
      <div class="header">
        <h1>Create Invoice</h1>
        <p class="subtitle">Patient: {{ patientRecord.patient.full_name }} ({{ patientRecord.patient.patient_number }})</p>
      </div>

      <div class="patient-info-card">
        <h3>Patient Information</h3>
        <div class="info-grid">
          <div class="info-item">
            <span class="label">Patient Number:</span>
            <span class="value">{{ patientRecord.patient.patient_number }}</span>
          </div>
          <div class="info-item">
            <span class="label">Full Name:</span>
            <span class="value">{{ patientRecord.patient.full_name }}</span>
          </div>
          <div class="info-item">
            <span class="label">Chief Complaint:</span>
            <span class="value">{{ patientRecord.patient.chief_complaint }}</span>
          </div>
          <div class="info-item">
            <span class="label">Registration Time:</span>
            <span class="value">{{ formatDateTime(patientRecord.created_at) }}</span>
          </div>
        </div>
      </div>

      <form @submit.prevent="submitInvoice" class="invoice-form">
        <div class="form-group">
          <label class="checkbox-label">
            <input type="checkbox" v-model="form.is_new_patient" />
            <span>New Patient (requires registration + consultation fees)</span>
          </label>
        </div>

        <div class="invoice-items-section">
          <div class="section-header">
            <h3>Invoice Items</h3>
            <button type="button" @click="addItem" class="btn btn-sm btn-success">
              + Add Item
            </button>
          </div>

          <div v-for="(item, index) in form.items" :key="index" class="invoice-item">
            <div class="item-row">
              <div class="form-group flex-1">
                <label>Service Name</label>
                <select v-model="item.service_name" class="form-control" required>
                  <option value="">Select a service...</option>
                  <option v-for="service in commonServices" :key="service.name" :value="service.name">
                    {{ service.name }}
                  </option>
                  <option value="custom">Custom Service</option>
                </select>
                <input
                  v-if="item.service_name === 'custom'"
                  v-model="item.custom_service_name"
                  type="text"
                  placeholder="Enter custom service name"
                  class="form-control mt-2"
                  required
                />
              </div>

              <div class="form-group" style="width: 150px;">
                <label>Unit Price</label>
                <input
                  v-model.number="item.unit_price"
                  type="number"
                  step="0.01"
                  min="0"
                  class="form-control"
                  required
                  @input="calculateSubtotal(item)"
                />
              </div>

              <div class="form-group" style="width: 100px;">
                <label>Quantity</label>
                <input
                  v-model.number="item.quantity"
                  type="number"
                  min="1"
                  class="form-control"
                  required
                  @input="calculateSubtotal(item)"
                />
              </div>

              <div class="form-group" style="width: 150px;">
                <label>Subtotal</label>
                <input
                  :value="item.subtotal.toFixed(2)"
                  type="text"
                  class="form-control"
                  readonly
                />
              </div>

              <div class="form-group" style="width: 50px;">
                <label>&nbsp;</label>
                <button
                  type="button"
                  @click="removeItem(index)"
                  class="btn btn-sm btn-danger"
                  :disabled="form.items.length === 1"
                >
                  ×
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="invoice-summary">
          <div class="summary-row">
            <span class="summary-label">Total Amount:</span>
            <span class="summary-value">₦{{ totalAmount.toFixed(2) }}</span>
          </div>
        </div>

        <div v-if="error" class="alert alert-danger">
          {{ error }}
        </div>

        <div class="form-actions">
          <button type="button" @click="goBack" class="btn btn-secondary">
            Cancel
          </button>
          <button type="submit" class="btn btn-primary" :disabled="submitting">
            {{ submitting ? 'Creating...' : 'Create Invoice' }}
          </button>
        </div>
      </form>
    </div>
  </HospitalAdminLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import HospitalAdminLayout from '../../Layouts/HospitalAdminLayout.vue';

const props = defineProps({
  patientRecord: Object,
  commonServices: Array,
});

const form = ref({
  is_new_patient: true,
  items: [
    {
      service_name: '',
      custom_service_name: '',
      unit_price: 0,
      quantity: 1,
      subtotal: 0,
    },
  ],
});

const submitting = ref(false);
const error = ref('');

const totalAmount = computed(() => {
  return form.value.items.reduce((sum, item) => sum + item.subtotal, 0);
});

const calculateSubtotal = (item) => {
  item.subtotal = item.unit_price * item.quantity;
};

const addItem = () => {
  form.value.items.push({
    service_name: '',
    custom_service_name: '',
    unit_price: 0,
    quantity: 1,
    subtotal: 0,
  });
};

const removeItem = (index) => {
  if (form.value.items.length > 1) {
    form.value.items.splice(index, 1);
  }
};

// Auto-populate unit price when service is selected
watch(() => form.value.items, (items) => {
  items.forEach(item => {
    if (item.service_name && item.service_name !== 'custom') {
      const service = props.commonServices.find(s => s.name === item.service_name);
      if (service) {
        item.unit_price = service.unit_price;
        calculateSubtotal(item);
      }
    }
  });
}, { deep: true });

const submitInvoice = async () => {
  error.value = '';
  submitting.value = true;

  try {
    // Prepare items for submission
    const items = form.value.items.map(item => ({
      service_name: item.service_name === 'custom' ? item.custom_service_name : item.service_name,
      unit_price: item.unit_price,
      quantity: item.quantity,
    }));

    const response = await axios.post(
      `/api/v1/patient-records/${props.patientRecord.id}/invoices`,
      {
        is_new_patient: form.value.is_new_patient,
        items: items,
      }
    );

    if (response.data.success) {
      // Redirect to invoice details page
      router.visit(`/accounts/invoices/${response.data.data.id}`);
    }
  } catch (err) {
    if (err.response?.data?.error) {
      error.value = err.response.data.error.message;
    } else {
      error.value = 'Failed to create invoice. Please try again.';
    }
  } finally {
    submitting.value = false;
  }
};

const goBack = () => {
  router.visit('/accounts/queue');
};

const formatDateTime = (dateString) => {
  const date = new Date(dateString);
  return date.toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};
</script>

<style scoped>
.create-invoice {
  padding: 2rem;
  max-width: 1200px;
  margin: 0 auto;
}

.header {
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
  font-size: 1rem;
}

.patient-info-card {
  background: white;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  margin-bottom: 2rem;
}

.patient-info-card h3 {
  margin: 0 0 1rem 0;
  color: #2c3e50;
  font-size: 1.125rem;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1rem;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.info-item .label {
  font-size: 0.75rem;
  color: #6c757d;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 600;
}

.info-item .value {
  font-size: 0.9375rem;
  color: #2c3e50;
  font-weight: 500;
}

.invoice-form {
  background: white;
  border-radius: 8px;
  padding: 2rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.form-group {
  margin-bottom: 1.5rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 600;
  color: #495057;
  font-size: 0.875rem;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
  width: 18px;
  height: 18px;
  cursor: pointer;
}

.form-control {
  width: 100%;
  padding: 0.5rem 0.75rem;
  border: 1px solid #ced4da;
  border-radius: 4px;
  font-size: 0.9375rem;
  transition: border-color 0.2s;
}

.form-control:focus {
  outline: none;
  border-color: #007bff;
}

.form-control:read-only {
  background: #e9ecef;
}

.invoice-items-section {
  margin-bottom: 2rem;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.section-header h3 {
  margin: 0;
  color: #2c3e50;
  font-size: 1.125rem;
}

.invoice-item {
  border: 1px solid #e9ecef;
  border-radius: 4px;
  padding: 1rem;
  margin-bottom: 1rem;
}

.item-row {
  display: flex;
  gap: 1rem;
  align-items: flex-end;
}

.flex-1 {
  flex: 1;
}

.mt-2 {
  margin-top: 0.5rem;
}

.invoice-summary {
  background: #f8f9fa;
  border-radius: 4px;
  padding: 1.5rem;
  margin-bottom: 2rem;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.summary-label {
  font-size: 1.125rem;
  font-weight: 600;
  color: #495057;
}

.summary-value {
  font-size: 1.5rem;
  font-weight: 700;
  color: #007bff;
}

.alert {
  padding: 1rem;
  border-radius: 4px;
  margin-bottom: 1.5rem;
}

.alert-danger {
  background: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
}

.form-actions {
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
}

.btn {
  padding: 0.5rem 1.5rem;
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

.btn-success {
  background: #28a745;
  color: white;
}

.btn-success:hover {
  background: #218838;
}

.btn-danger {
  background: #dc3545;
  color: white;
}

.btn-danger:hover:not(:disabled) {
  background: #c82333;
}

.btn-sm {
  padding: 0.375rem 0.75rem;
  font-size: 0.8125rem;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
