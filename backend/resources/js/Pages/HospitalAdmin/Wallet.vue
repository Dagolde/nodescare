<template>
  <HospitalAdminLayout>
    <div class="wallet-page">
      <div class="header">
        <h1>Hospital Wallet</h1>
        <button @click="showTopUpModal = true" class="btn btn-primary">Top Up via Paystack</button>
      </div>

      <!-- Balance Card -->
      <div v-if="wallet" class="balance-card">
        <div class="balance-main">
          <div class="balance-label">Current Balance</div>
          <div class="balance-amount">₦{{ formatAmount(wallet.balance) }}</div>
          <div class="balance-bar-wrap">
            <div class="balance-bar">
              <div
                class="balance-fill"
                :class="getBarClass(balancePercent)"
                :style="{ width: Math.min(balancePercent, 100) + '%' }"
              ></div>
            </div>
            <span class="balance-pct">{{ balancePercent }}% remaining</span>
          </div>
          <div v-if="isLowBalance" class="low-balance-alert">
            ⚠️ Low balance — please top up soon to avoid service interruption.
          </div>
        </div>
        <div class="balance-meta">
          <div class="meta-item">
            <span class="meta-label">Status</span>
            <span :class="['badge', wallet.is_active ? 'badge-active' : 'badge-inactive']">
              {{ wallet.is_active ? 'Active' : 'Inactive' }}
            </span>
          </div>
          <div class="meta-item">
            <span class="meta-label">Alert Threshold</span>
            <span class="meta-value">{{ wallet.low_balance_threshold_percent }}%</span>
          </div>
        </div>
      </div>

      <div v-else-if="loading" class="loading">Loading wallet...</div>

      <!-- Recent Transactions -->
      <div class="transactions-section">
        <h2>Recent Transactions</h2>
        <div class="transactions-table card">
          <table v-if="transactions.length > 0">
            <thead>
              <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Reason</th>
                <th>Amount</th>
                <th>Balance After</th>
                <th>Description</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="tx in transactions" :key="tx.id">
                <td>{{ formatDate(tx.created_at) }}</td>
                <td>
                  <span :class="['badge', tx.type === 'credit' ? 'badge-active' : 'badge-debit']">
                    {{ tx.type }}
                  </span>
                </td>
                <td>{{ formatReason(tx.reason) }}</td>
                <td :class="tx.type === 'credit' ? 'text-green' : 'text-red'">
                  {{ tx.type === 'credit' ? '+' : '-' }}₦{{ formatAmount(tx.amount) }}
                </td>
                <td>₦{{ formatAmount(tx.balance_after) }}</td>
                <td>{{ tx.description }}</td>
              </tr>
            </tbody>
          </table>
          <div v-else class="empty">No transactions yet.</div>
        </div>
      </div>

      <!-- Paystack Top-Up Modal -->
      <div v-if="showTopUpModal" class="modal-overlay" @click="showTopUpModal = false">
        <div class="modal" @click.stop>
          <h3>Top Up Wallet</h3>
          <p class="modal-desc">Fund your hospital wallet via Paystack. Funds are credited instantly after payment.</p>

          <div class="form-group">
            <label>Amount (₦)</label>
            <input v-model.number="topUpForm.amount" type="number" min="100" placeholder="e.g., 50000" />
            <small>Minimum ₦100</small>
          </div>
          <div class="form-group">
            <label>Email (for receipt)</label>
            <input v-model="topUpForm.email" type="email" placeholder="admin@hospital.com" />
          </div>

          <div v-if="topUpError" class="alert alert-error">{{ topUpError }}</div>

          <div class="modal-actions">
            <button @click="showTopUpModal = false" class="btn btn-secondary">Cancel</button>
            <button @click="initiatePaystack" class="btn btn-primary" :disabled="topUpLoading">
              {{ topUpLoading ? 'Initiating...' : 'Pay with Paystack' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Verify Payment Modal (shown after Paystack redirect) -->
      <div v-if="showVerifyModal" class="modal-overlay">
        <div class="modal" @click.stop>
          <h3>Verify Payment</h3>
          <p>Enter your Paystack payment reference to verify and credit your wallet.</p>
          <div class="form-group">
            <label>Payment Reference</label>
            <input v-model="verifyRef" type="text" placeholder="e.g., WALLET-XXXXXXXXXXXX" />
          </div>
          <div v-if="verifyError" class="alert alert-error">{{ verifyError }}</div>
          <div v-if="verifySuccess" class="alert alert-success">{{ verifySuccess }}</div>
          <div class="modal-actions">
            <button @click="showVerifyModal = false" class="btn btn-secondary">Close</button>
            <button @click="verifyPayment" class="btn btn-primary" :disabled="verifyLoading">
              {{ verifyLoading ? 'Verifying...' : 'Verify Payment' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </HospitalAdminLayout>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import axios from 'axios';
import HospitalAdminLayout from '../../Layouts/HospitalAdminLayout.vue';

const props = defineProps({
  paystackPublicKey: { type: String, default: '' },
});

const wallet       = ref(null);
const transactions = ref([]);
const loading      = ref(true);

const showTopUpModal  = ref(false);
const showVerifyModal = ref(false);
const topUpLoading    = ref(false);
const topUpError      = ref('');
const verifyLoading   = ref(false);
const verifyError     = ref('');
const verifySuccess   = ref('');
const verifyRef       = ref('');

const topUpForm = reactive({ amount: '', email: '' });

const balancePercent = computed(() => {
  if (!wallet.value || wallet.value.initial_funded_amount <= 0) return 100;
  return Math.round((wallet.value.balance / wallet.value.initial_funded_amount) * 100 * 100) / 100;
});

const isLowBalance = computed(() => {
  if (!wallet.value) return false;
  return balancePercent.value <= wallet.value.low_balance_threshold_percent;
});

const fetchWallet = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/hospital-admin/api/wallet');
    wallet.value       = res.data.data.wallet;
    transactions.value = res.data.data.recent_transactions || [];
  } catch (e) {
    console.error('Failed to load wallet', e);
  } finally {
    loading.value = false;
  }
};

const initiatePaystack = async () => {
  if (!topUpForm.amount || topUpForm.amount < 100) {
    topUpError.value = 'Minimum amount is ₦100.';
    return;
  }
  if (!topUpForm.email) {
    topUpError.value = 'Email is required.';
    return;
  }

  topUpLoading.value = true;
  topUpError.value   = '';

  try {
    const res = await axios.post('/hospital-admin/api/wallet/paystack/initiate', topUpForm);
    const { authorization_url, reference } = res.data.data;

    // Open Paystack in a new tab
    window.open(authorization_url, '_blank');

    // Show verify modal so user can confirm after payment
    showTopUpModal.value  = false;
    showVerifyModal.value = true;
    verifyRef.value       = reference;
  } catch (e) {
    topUpError.value = e.response?.data?.error?.message || 'Failed to initiate payment.';
  } finally {
    topUpLoading.value = false;
  }
};

const verifyPayment = async () => {
  if (!verifyRef.value) {
    verifyError.value = 'Please enter the payment reference.';
    return;
  }

  verifyLoading.value = true;
  verifyError.value   = '';
  verifySuccess.value = '';

  try {
    const res = await axios.post('/hospital-admin/api/wallet/paystack/verify', {
      reference: verifyRef.value,
    });
    verifySuccess.value = res.data.message;
    await fetchWallet();
    setTimeout(() => { showVerifyModal.value = false; }, 2000);
  } catch (e) {
    verifyError.value = e.response?.data?.error?.message || 'Verification failed.';
  } finally {
    verifyLoading.value = false;
  }
};

const formatAmount = (v) => Number(v || 0).toLocaleString('en-NG', { minimumFractionDigits: 2 });
const formatDate   = (d) => new Date(d).toLocaleString('en-NG', { dateStyle: 'medium', timeStyle: 'short' });
const formatReason = (r) => r?.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) || r;
const getBarClass  = (pct) => {
  if (pct <= 20) return 'fill-danger';
  if (pct <= 40) return 'fill-warning';
  return 'fill-success';
};

onMounted(fetchWallet);
</script>

<style scoped>
.wallet-page { padding: 2rem; max-width: 1000px; margin: 0 auto; }
.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
h1 { font-size: 2rem; color: #2c3e50; margin: 0; }
h2 { font-size: 1.4rem; color: #2c3e50; margin: 0 0 1rem 0; }

.balance-card {
  background: white;
  border-radius: 12px;
  padding: 2rem;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  margin-bottom: 2rem;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 2rem;
}

.balance-label { font-size: 0.9rem; color: #6c757d; margin-bottom: 0.5rem; }
.balance-amount { font-size: 2.5rem; font-weight: 700; color: #2c3e50; margin-bottom: 1rem; }

.balance-bar-wrap { display: flex; align-items: center; gap: 1rem; }
.balance-bar { height: 10px; background: #e9ecef; border-radius: 5px; overflow: hidden; width: 200px; }
.balance-fill { height: 100%; border-radius: 5px; transition: width 0.5s; }
.fill-success { background: #28a745; }
.fill-warning { background: #ffc107; }
.fill-danger  { background: #dc3545; }
.balance-pct  { font-size: 0.875rem; color: #6c757d; white-space: nowrap; }

.low-balance-alert {
  margin-top: 1rem;
  padding: 0.75rem 1rem;
  background: #fff3cd;
  border: 1px solid #ffc107;
  border-radius: 6px;
  color: #856404;
  font-size: 0.9rem;
}

.balance-meta { display: flex; flex-direction: column; gap: 1rem; min-width: 160px; }
.meta-item { display: flex; flex-direction: column; gap: 0.25rem; }
.meta-label { font-size: 0.8rem; color: #6c757d; }
.meta-value { font-weight: 600; color: #2c3e50; }

.loading { padding: 2rem; text-align: center; color: #6c757d; }

.transactions-section { margin-top: 1rem; }
.card { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
.empty { padding: 2rem; text-align: center; color: #6c757d; }

table { width: 100%; border-collapse: collapse; }
thead { background: #f8f9fa; }
th, td { padding: 0.875rem 1rem; text-align: left; border-bottom: 1px solid #e9ecef; font-size: 0.875rem; }
th { font-weight: 600; color: #495057; }

.badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 10px; font-size: 0.75rem; font-weight: 600; }
.badge-active   { background: #d4edda; color: #155724; }
.badge-inactive { background: #f8d7da; color: #721c24; }
.badge-debit    { background: #f8d7da; color: #721c24; }

.text-green { color: #28a745; font-weight: 600; }
.text-red   { color: #dc3545; font-weight: 600; }

.modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: white; padding: 2rem; border-radius: 8px; max-width: 480px; width: 90%; }
.modal h3 { margin-top: 0; margin-bottom: 0.5rem; color: #2c3e50; }
.modal-desc { color: #6c757d; margin-bottom: 1.5rem; font-size: 0.9rem; }

.form-group { margin-bottom: 1.25rem; }
.form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #495057; }
.form-group input { width: 100%; padding: 0.625rem; border: 1px solid #ced4da; border-radius: 4px; font-size: 1rem; box-sizing: border-box; }
.form-group small { display: block; margin-top: 0.25rem; color: #6c757d; font-size: 0.8rem; }

.modal-actions { display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem; }

.alert { padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1rem; }
.alert-error   { background: #f8d7da; color: #721c24; }
.alert-success { background: #d4edda; color: #155724; }

.btn { padding: 0.5rem 1rem; border: none; border-radius: 4px; font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: all 0.2s; }
.btn-primary { background: #007bff; color: white; }
.btn-primary:hover:not(:disabled) { background: #0056b3; }
.btn-secondary { background: #6c757d; color: white; }
.btn-secondary:hover { background: #5a6268; }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }
</style>
