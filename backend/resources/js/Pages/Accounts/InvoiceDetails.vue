<template>
  <HospitalAdminLayout>
    <div class="invoice-details">
      <div class="header">
        <div>
          <h1>Invoice {{ invoice.invoice_number }}</h1>
          <p class="subtitle">
            Patient: {{ invoice.patient.full_name }} ({{ invoice.patient.patient_number }})
          </p>
        </div>
        <div class="header-actions">
          <span :class="['status-badge', `status-${invoice.status}`]">
            {{ formatStatus(invoice.status) }}
          </span>
          <button
            v-if="invoice.status === 'settled'"
            @click="downloadReceipt"
            class="btn btn-success"
          >
            📄 Download Receipt
          </button>
        </div>
      </div>

      <div class="content-grid">
        <!-- Invoice Information -->
        <div class="card">
          <h3>Invoice Information</h3>
          <div class="info-list">
            <div class="info-row">
              <span class="label">Invoice Number:</span>
              <span class="value">{{ invoice.invoice_number }}</span>
            </div>
            <div class="info-row">
              <span class="label">Patient Type:</span>
              <span class="value">{{ invoice.is_new_patient ? 'New Patient' : 'Returning Patient' }}</span>
            </div>
            <div class="info-row">
              <span class="label">Created By:</span>
              <span class="value">{{ invoice.creator?.name || 'N/A' }}</span>
            </div>
            <div class="info-row">
              <span class="label">Created At:</span>
              <span class="value">{{ formatDateTime(invoice.created_at) }}</span>
            </div>
            <div v-if="invoice.settled_at" class="info-row">
              <span class="label">Settled At:</span>
              <span class="value">{{ formatDateTime(invoice.settled_at) }}</span>
            </div>
          </div>
        </div>

        <!-- Invoice Items -->
        <div class="card full-width">
          <h3>Invoice Items</h3>
          <table class="items-table">
            <thead>
              <tr>
                <th>Service Name</th>
                <th>Unit Price</th>
                <th>Quantity</th>
                <th>Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in invoice.items" :key="item.id">
                <td>{{ item.service_name }}</td>
                <td>₦{{ parseFloat(item.unit_price).toFixed(2) }}</td>
                <td>{{ item.quantity }}</td>
                <td>₦{{ parseFloat(item.subtotal).toFixed(2) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="3" class="text-right"><strong>Total Amount:</strong></td>
                <td><strong>₦{{ parseFloat(invoice.total_amount).toFixed(2) }}</strong></td>
              </tr>
              <tr>
                <td colspan="3" class="text-right">Amount Paid:</td>
                <td class="text-success">₦{{ parseFloat(invoice.amount_paid).toFixed(2) }}</td>
              </tr>
              <tr v-if="invoice.outstanding_balance > 0">
                <td colspan="3" class="text-right">Outstanding Balance:</td>
                <td class="text-danger">₦{{ parseFloat(invoice.outstanding_balance).toFixed(2) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Payment History -->
        <div class="card full-width">
          <h3>Payment History</h3>
          <div v-if="invoice.payments && invoice.payments.length > 0">
            <table class="payments-table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Payment Type</th>
                  <th>Amount</th>
                  <th>Receipt Reference</th>
                  <th>Recorded By</th>
                  <th>HMO Details</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="payment in invoice.payments" :key="payment.id">
                  <td>{{ formatDateTime(payment.created_at) }}</td>
                  <td>
                    <span :class="['payment-type-badge', `type-${payment.payment_type}`]">
                      {{ formatPaymentType(payment.payment_type) }}
                    </span>
                  </td>
                  <td>₦{{ parseFloat(payment.amount).toFixed(2) }}</td>
                  <td>{{ payment.receipt_reference || 'N/A' }}</td>
                  <td>{{ payment.accountant?.name || 'N/A' }}</td>
                  <td>
                    <div v-if="payment.payment_type === 'hmo_insurance'">
                      <div><strong>Provider:</strong> {{ payment.hmo_provider_name }}</div>
                      <div><strong>Policy:</strong> {{ payment.hmo_policy_number }}</div>
                      <div v-if="payment.hmo_claim_reference">
                        <strong>Claim:</strong> {{ payment.hmo_claim_reference }}
                      </div>
                    </div>
                    <span v-else>-</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="empty-state">
            <p>No payments recorded yet</p>
          </div>
        </div>

        <!-- Record Payment Form -->
        <div v-if="invoice.status !== 'settled'" class="card full-width">
          <h3>Record Payment</h3>
          <form @submit.prevent="recordPayment" class="payment-form">
            <div class="form-row">
              <div class="form-group">
                <label>Payment Type *</label>
                <select v-model="paymentForm.payment_type" class="form-control" required>
                  <option value="">Select payment type...</option>
                  <option value="cash">Cash</option>
                  <option value="pos_card">POS/Card</option>
                  <option value="hmo_insurance">HMO/Insurance</option>
                </select>
              </div>

              <div class="form-group">
                <label>Amount *</label>
                <input
                  v-model.number="paymentForm.amount"
                  type="number"
                  step="0.01"
                  min="0.01"
                  class="form-control"
                  required
                />
              </div>

              <div class="form-group">
                <label>Receipt Reference</label>
                <input
                  v-model="paymentForm.receipt_reference"
                  type="text"
                  class="form-control"
                  placeholder="Optional"
                />
              </div>
            </div>

            <div v-if="paymentForm.payment_type === 'hmo_insurance'" class="hmo-fields">
              <div class="form-row">
                <div class="form-group">
                  <label>HMO Provider Name *</label>
                  <input
                    v-model="paymentForm.hmo_provider_name"
                    type="text"
                    class="form-control"
                    required
                  />
                </div>

                <div class="form-group">
                  <label>HMO Policy Number *</label>
                  <input
                    v-model="paymentForm.hmo_policy_number"
                    type="text"
                    class="form-control"
                    required
                  />
                </div>

                <div class="form-group">
                  <label>HMO Claim Reference</label>
                  <input
                    v-model="paymentForm.hmo_claim_reference"
                    type="text"
                    class="form-control"
                    placeholder="Optional"
                  />
                </div>
              </div>
            </div>

            <div v-if="paymentError" class="alert alert-danger">
              {{ paymentError }}
            </div>

            <div class="form-actions">
              <button type="submit" class="btn btn-primary" :disabled="submittingPayment">
                {{ submittingPayment ? 'Recording...' : 'Record Payment' }}
              </button>
            </div>
          </form>
        </div>

        <!-- Settle Invoice -->
        <div v-if="invoice.status !== 'settled' && invoice.outstanding_balance <= 0" class="card full-width">
          <h3>Settle Invoice</h3>
          <p>The invoice has been fully paid. Click below to settle and route the patient to the first clinical department.</p>
          <button @click="settleInvoice" class="btn btn-success" :disabled="settling">
            {{ settling ? 'Settling...' : 'Settle Invoice & Route Patient' }}
          </button>
          <div v-if="settleError" class="alert alert-danger mt-2">
            {{ settleError }}
          </div>
        </div>
      </div>
    </div>
  </HospitalAdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import HospitalAdminLayout from '../../Layouts/HospitalAdminLayout.vue';

const props = defineProps({
  invoice: Object,
});

const paymentForm = ref({
  payment_type: '',
  amount: 0,
  receipt_reference: '',
  hmo_provider_name: '',
  hmo_policy_number: '',
  hmo_claim_reference: '',
});

const submittingPayment = ref(false);
const paymentError = ref('');
const settling = ref(false);
const settleError = ref('');

const formatStatus = (status) => {
  const statusMap = {
    draft: 'Draft',
    issued: 'Issued',
    partially_paid: 'Partially Paid',
    settled: 'Settled',
    reversed: 'Reversed',
  };
  return statusMap[status] || status;
};

const formatPaymentType = (type) => {
  const typeMap = {
    cash: 'Cash',
    pos_card: 'POS/Card',
    hmo_insurance: 'HMO/Insurance',
  };
  return typeMap[type] || type;
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

const recordPayment = async () => {
  paymentError.value = '';
  submittingPayment.value = true;

  try {
    const response = await axios.post(
      `/api/v1/invoices/${props.invoice.id}/payments`,
      paymentForm.value
    );

    if (response.data.success) {
      // Reload the page to show updated invoice
      router.reload();
    }
  } catch (err) {
    if (err.response?.data?.error) {
      paymentError.value = err.response.data.error.message;
    } else {
      paymentError.value = 'Failed to record payment. Please try again.';
    }
  } finally {
    submittingPayment.value = false;
  }
};

const settleInvoice = async () => {
  if (!confirm('Are you sure you want to settle this invoice? The patient will be routed to the first clinical department.')) {
    return;
  }

  settleError.value = '';
  settling.value = true;

  try {
    const response = await axios.post(`/api/v1/invoices/${props.invoice.id}/settle`);

    if (response.data.success) {
      // Reload the page to show updated status
      router.reload();
    }
  } catch (err) {
    if (err.response?.data?.error) {
      settleError.value = err.response.data.error.message;
    } else {
      settleError.value = 'Failed to settle invoice. Please try again.';
    }
  } finally {
    settling.value = false;
  }
};

const downloadReceipt = () => {
  window.location.href = `/api/v1/invoices/${props.invoice.id}/receipt`;
};
</script>

<style scoped>
.invoice-details {
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

.header-actions {
  display: flex;
  gap: 1rem;
  align-items: center;
}

.status-badge {
  padding: 0.5rem 1rem;
  border-radius: 20px;
  font-size: 0.875rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.status-issued {
  background: #d1ecf1;
  color: #0c5460;
}

.status-partially_paid {
  background: #fff3cd;
  color: #856404;
}

.status-settled {
  background: #d4edda;
  color: #155724;
}

.status-reversed {
  background: #f8d7da;
  color: #721c24;
}

.content-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 2rem;
}

.card {
  background: white;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.card.full-width {
  grid-column: 1 / -1;
}

.card h3 {
  margin: 0 0 1.5rem 0;
  color: #2c3e50;
  font-size: 1.125rem;
}

.info-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.info-row {
  display: flex;
  justify-content: space-between;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid #e9ecef;
}

.info-row:last-child {
  border-bottom: none;
}

.info-row .label {
  font-weight: 600;
  color: #6c757d;
  font-size: 0.875rem;
}

.info-row .value {
  color: #2c3e50;
  font-size: 0.9375rem;
}

.items-table,
.payments-table {
  width: 100%;
  border-collapse: collapse;
}

.items-table th,
.payments-table th {
  text-align: left;
  padding: 0.75rem;
  background: #f8f9fa;
  font-weight: 600;
  color: #495057;
  font-size: 0.875rem;
  border-bottom: 2px solid #dee2e6;
}

.items-table td,
.payments-table td {
  padding: 0.75rem;
  border-bottom: 1px solid #e9ecef;
  color: #495057;
}

.items-table tfoot td {
  padding: 0.75rem;
  font-size: 0.9375rem;
}

.text-right {
  text-align: right;
}

.text-success {
  color: #28a745;
  font-weight: 600;
}

.text-danger {
  color: #dc3545;
  font-weight: 600;
}

.payment-type-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
}

.type-cash {
  background: #d4edda;
  color: #155724;
}

.type-pos_card {
  background: #d1ecf1;
  color: #0c5460;
}

.type-hmo_insurance {
  background: #e2e3e5;
  color: #383d41;
}

.empty-state {
  text-align: center;
  padding: 2rem;
  color: #6c757d;
}

.payment-form {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.form-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group label {
  margin-bottom: 0.5rem;
  font-weight: 600;
  color: #495057;
  font-size: 0.875rem;
}

.form-control {
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

.hmo-fields {
  padding: 1rem;
  background: #f8f9fa;
  border-radius: 4px;
}

.alert {
  padding: 1rem;
  border-radius: 4px;
}

.alert-danger {
  background: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
}

.mt-2 {
  margin-top: 0.5rem;
}

.form-actions {
  display: flex;
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

.btn-success {
  background: #28a745;
  color: white;
}

.btn-success:hover:not(:disabled) {
  background: #218838;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
