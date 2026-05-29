<template>
  <HospitalAdminLayout>
    <div class="financial-reports-page">
      <div class="header">
        <h1>Financial Reports</h1>
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
            <button @click="loadReport" class="btn btn-primary">Load Report</button>
          </div>
        </div>
      </div>

      <div v-if="reportData" class="report-content">
        <div class="summary-cards">
          <div class="summary-card revenue">
            <div class="card-icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="1" x2="12" y2="23"></line>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
              </svg>
            </div>
            <div class="card-content">
              <h3>Total Revenue</h3>
              <p class="amount">₦{{ formatNumber(reportData.total_revenue) }}</p>
            </div>
          </div>

          <div class="summary-card outstanding">
            <div class="card-icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
              </svg>
            </div>
            <div class="card-content">
              <h3>Outstanding Invoices</h3>
              <p class="amount">₦{{ formatNumber(reportData.outstanding_invoices) }}</p>
            </div>
          </div>
        </div>

        <div class="payment-breakdown-section">
          <h2>Payment Breakdown</h2>
          <div class="breakdown-grid">
            <div class="breakdown-card">
              <h4>Cash</h4>
              <p class="breakdown-amount">₦{{ formatNumber(reportData.payment_breakdown.cash) }}</p>
              <div class="breakdown-bar">
                <div class="bar-fill cash" :style="{ width: getPercentage(reportData.payment_breakdown.cash) + '%' }"></div>
              </div>
            </div>
            <div class="breakdown-card">
              <h4>POS/Card</h4>
              <p class="breakdown-amount">₦{{ formatNumber(reportData.payment_breakdown.pos_card) }}</p>
              <div class="breakdown-bar">
                <div class="bar-fill pos" :style="{ width: getPercentage(reportData.payment_breakdown.pos_card) + '%' }"></div>
              </div>
            </div>
            <div class="breakdown-card">
              <h4>HMO/Insurance</h4>
              <p class="breakdown-amount">₦{{ formatNumber(reportData.payment_breakdown.hmo_insurance) }}</p>
              <div class="breakdown-bar">
                <div class="bar-fill hmo" :style="{ width: getPercentage(reportData.payment_breakdown.hmo_insurance) + '%' }"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="pie-chart-section">
          <h2>Payment Distribution</h2>
          <div class="pie-chart">
            <svg viewBox="0 0 200 200" class="pie-svg">
              <circle cx="100" cy="100" r="80" fill="none" stroke="#667eea" stroke-width="60"
                      :stroke-dasharray="`${getCashArc()} ${360 - getCashArc()}`"
                      transform="rotate(-90 100 100)" />
              <circle cx="100" cy="100" r="80" fill="none" stroke="#f093fb" stroke-width="60"
                      :stroke-dasharray="`${getPosArc()} ${360 - getPosArc()}`"
                      :transform="`rotate(${getCashArc() - 90} 100 100)`" />
              <circle cx="100" cy="100" r="80" fill="none" stroke="#43e97b" stroke-width="60"
                      :stroke-dasharray="`${getHmoArc()} ${360 - getHmoArc()}`"
                      :transform="`rotate(${getCashArc() + getPosArc() - 90} 100 100)`" />
            </svg>
            <div class="pie-legend">
              <div class="legend-item">
                <span class="legend-color cash"></span>
                <span>Cash ({{ getCashPercentage() }}%)</span>
              </div>
              <div class="legend-item">
                <span class="legend-color pos"></span>
                <span>POS/Card ({{ getPosPercentage() }}%)</span>
              </div>
              <div class="legend-item">
                <span class="legend-color hmo"></span>
                <span>HMO/Insurance ({{ getHmoPercentage() }}%)</span>
              </div>
            </div>
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
  filters: Object,
});

const filters = reactive({
  start_date: props.filters.start_date || '',
  end_date: props.filters.end_date || '',
});

const reportData = ref(null);

const loadReport = async () => {
  try {
    const response = await axios.get('/hospital-admin/api/reports/financial', { params: filters });
    reportData.value = response.data.data;
  } catch (error) {
    console.error('Failed to load report:', error);
    alert('Failed to load report');
  }
};

const formatNumber = (num) => {
  return new Intl.NumberFormat('en-NG').format(num);
};

const getTotalPayments = () => {
  if (!reportData.value) return 0;
  const { cash, pos_card, hmo_insurance } = reportData.value.payment_breakdown;
  return cash + pos_card + hmo_insurance;
};

const getPercentage = (amount) => {
  const total = getTotalPayments();
  return total > 0 ? Math.round((amount / total) * 100) : 0;
};

const getCashPercentage = () => getPercentage(reportData.value?.payment_breakdown.cash || 0);
const getPosPercentage = () => getPercentage(reportData.value?.payment_breakdown.pos_card || 0);
const getHmoPercentage = () => getPercentage(reportData.value?.payment_breakdown.hmo_insurance || 0);

const getCashArc = () => {
  const percentage = getCashPercentage();
  return (percentage / 100) * 360;
};

const getPosArc = () => {
  const percentage = getPosPercentage();
  return (percentage / 100) * 360;
};

const getHmoArc = () => {
  const percentage = getHmoPercentage();
  return (percentage / 100) * 360;
};

// Load report on mount if filters are set
if (filters.start_date || filters.end_date) {
  loadReport();
}
</script>

<style scoped>
.financial-reports-page {
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

.form-group input {
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

.summary-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
}

.summary-card {
  background: white;
  border-radius: 12px;
  padding: 2rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  display: flex;
  gap: 1.5rem;
}

.card-icon {
  width: 64px;
  height: 64px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.summary-card.revenue .card-icon {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.summary-card.outstanding .card-icon {
  background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
  color: white;
}

.card-content h3 {
  margin: 0 0 0.5rem 0;
  font-size: 1rem;
  color: #6c757d;
  font-weight: 500;
}

.amount {
  margin: 0;
  font-size: 1.75rem;
  font-weight: 700;
  color: #2c3e50;
}

.payment-breakdown-section,
.pie-chart-section {
  background: white;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.payment-breakdown-section h2,
.pie-chart-section h2 {
  margin: 0 0 1.5rem 0;
  font-size: 1.25rem;
  color: #2c3e50;
}

.breakdown-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
}

.breakdown-card h4 {
  margin: 0 0 0.5rem 0;
  font-size: 1rem;
  color: #6c757d;
}

.breakdown-amount {
  margin: 0 0 1rem 0;
  font-size: 1.5rem;
  font-weight: 700;
  color: #2c3e50;
}

.breakdown-bar {
  height: 8px;
  background: #e9ecef;
  border-radius: 4px;
  overflow: hidden;
}

.bar-fill {
  height: 100%;
  transition: width 0.3s;
}

.bar-fill.cash {
  background: #667eea;
}

.bar-fill.pos {
  background: #f093fb;
}

.bar-fill.hmo {
  background: #43e97b;
}

.pie-chart {
  display: flex;
  gap: 2rem;
  align-items: center;
  justify-content: center;
}

.pie-svg {
  width: 200px;
  height: 200px;
}

.pie-legend {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 0.9375rem;
  color: #495057;
}

.legend-color {
  width: 20px;
  height: 20px;
  border-radius: 4px;
}

.legend-color.cash {
  background: #667eea;
}

.legend-color.pos {
  background: #f093fb;
}

.legend-color.hmo {
  background: #43e97b;
}
</style>
