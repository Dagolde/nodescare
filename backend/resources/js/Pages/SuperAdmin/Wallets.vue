<template>
  <SuperAdminLayout>
    <div class="wallets-page">
      <div class="header">
        <h1>Hospital Wallets</h1>
        <button @click="showConfigModal = true" class="btn btn-secondary">Configure Deduction Rates</button>
      </div>

      <!-- Wallets Table -->
      <div class="wallets-table card">
        <div v-if="loading" class="loading">Loading wallets...</div>
        <table v-else>
          <thead>
            <tr>
              <th>Hospital</th>
              <th>Plan</th>
              <th>Balance</th>
              <th>Balance %</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="wallet in wallets" :key="wallet.id" :class="{ 'row-low': wallet.balance_percent <= wallet.low_balance_threshold_percent }">
              <td>{{ wallet.tenant?.name }}</td>
              <td>{{ wallet.tenant?.plan?.name || 'N/A' }}</td>
              <td class="balance">₦{{ formatAmount(wallet.balance) }}</td>
              <td>
                <div class="progress-bar">
                  <div
                    class="progress-fill"
                    :class="getProgressClass(wallet.balance_percent)"
                    :style="{ width: Math.min(wallet.balance_percent, 100) + '%' }"
                  ></div>
                </div>
                <span class="percent-label">{{ wallet.balance_percent }}%</span>
              </td>
              <td>
                <span :class="['badge', wallet.is_active ? 'badge-active' : 'badge-inactive']">
                  {{ wallet.is_active ? 'Active' : 'Inactive' }}
                </span>
                <span v-if="wallet.balance_percent <= wallet.low_balance_threshold_percent" class="badge badge-warning">Low</span>
              </td>
              <td class="actions">
                <button @click="openTopUp(wallet)" class="btn btn-sm btn-primary">Top Up</button>
                <button @click="openTransactions(wallet)" class="btn btn-sm btn-info">Transactions</button>
                <button @click="openSettings(wallet)" class="btn btn-sm btn-secondary">Settings</button>
              </td>
            </tr>
            <tr v-if="wallets.length === 0">
              <td colspan="6" class="empty">No wallets found. Create a hospital first.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Top Up Modal -->
      <div v-if="showTopUpModal" class="modal-overlay" @click="showTopUpModal = false">
        <div class="modal" @click.stop>
          <h3>Top Up — {{ selectedWallet?.tenant?.name }}</h3>
          <p class="current-balance">Current balance: <strong>₦{{ formatAmount(selectedWallet?.balance) }}</strong></p>
          <div class="form-group">
            <label>Amount (₦)</label>
            <input v-model.number="topUpForm.amount" type="number" min="1" placeholder="e.g., 50000" />
          </div>
          <div class="form-group">
            <label>Description</label>
            <input v-model="topUpForm.description" type="text" placeholder="e.g., Monthly top-up" />
          </div>
          <div v-if="topUpError" class="alert alert-error">{{ topUpError }}</div>
          <div class="modal-actions">
            <button @click="showTopUpModal = false" class="btn btn-secondary">Cancel</button>
            <button @click="submitTopUp" class="btn btn-primary" :disabled="topUpLoading">
              {{ topUpLoading ? 'Processing...' : 'Top Up' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Transactions Modal -->
      <div v-if="showTransactionsModal" class="modal-overlay" @click="showTransactionsModal = false">
        <div class="modal modal-large" @click.stop>
          <h3>Transactions — {{ selectedWallet?.tenant?.name }}</h3>
          <div v-if="txLoading" class="loading">Loading...</div>
          <table v-else class="tx-table">
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
                <td><span :class="['badge', tx.type === 'credit' ? 'badge-active' : 'badge-debit']">{{ tx.type }}</span></td>
                <td>{{ formatReason(tx.reason) }}</td>
                <td :class="tx.type === 'credit' ? 'text-green' : 'text-red'">
                  {{ tx.type === 'credit' ? '+' : '-' }}₦{{ formatAmount(tx.amount) }}
                </td>
                <td>₦{{ formatAmount(tx.balance_after) }}</td>
                <td>{{ tx.description }}</td>
              </tr>
              <tr v-if="transactions.length === 0">
                <td colspan="6" class="empty">No transactions yet.</td>
              </tr>
            </tbody>
          </table>
          <div class="modal-actions">
            <button @click="showTransactionsModal = false" class="btn btn-secondary">Close</button>
          </div>
        </div>
      </div>

      <!-- Settings Modal -->
      <div v-if="showSettingsModal" class="modal-overlay" @click="showSettingsModal = false">
        <div class="modal" @click.stop>
          <h3>Wallet Settings — {{ selectedWallet?.tenant?.name }}</h3>
          <div class="form-group">
            <label>Low Balance Alert Threshold (%)</label>
            <input v-model.number="settingsForm.low_balance_threshold_percent" type="number" min="1" max="100" />
            <small>Send notification when balance drops to this % of initial funded amount</small>
          </div>
          <div class="form-group">
            <label class="checkbox-label">
              <input v-model="settingsForm.is_active" type="checkbox" />
              Wallet Active (deductions enabled)
            </label>
          </div>
          <div class="modal-actions">
            <button @click="showSettingsModal = false" class="btn btn-secondary">Cancel</button>
            <button @click="submitSettings" class="btn btn-primary" :disabled="settingsLoading">
              {{ settingsLoading ? 'Saving...' : 'Save' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Deduction Config Modal -->
      <div v-if="showConfigModal" class="modal-overlay" @click="showConfigModal = false">
        <div class="modal modal-large" @click.stop>
          <h3>Deduction Rate Configuration</h3>
          <p class="section-desc">Configure how much is deducted from hospital wallets per patient visit. Set per-plan or global default (no plan selected).</p>

          <div class="form-group">
            <label>Apply to Plan</label>
            <select v-model="configForm.plan_id">
              <option value="">Global Default (all plans)</option>
              <option v-for="plan in plans" :key="plan.id" :value="plan.id">{{ plan.name }}</option>
            </select>
          </div>

          <div class="config-grid">
            <div class="config-item">
              <label class="checkbox-label">
                <input v-model="configForm.deduct_on_new_patient" type="checkbox" />
                Deduct for New Patient
              </label>
              <div class="form-group" v-if="configForm.deduct_on_new_patient">
                <label>Card/Registration Fee (₦)</label>
                <input v-model.number="configForm.new_patient_card_fee" type="number" min="0" step="0.01" />
              </div>
            </div>

            <div class="config-item">
              <label class="checkbox-label">
                <input v-model="configForm.deduct_on_consultation" type="checkbox" />
                Deduct for Consultation
              </label>
              <div class="form-group" v-if="configForm.deduct_on_consultation">
                <label>Consultation Fee (₦)</label>
                <input v-model.number="configForm.consultation_fee" type="number" min="0" step="0.01" />
              </div>
            </div>

            <div class="config-item">
              <label class="checkbox-label">
                <input v-model="configForm.deduct_on_pharmacy" type="checkbox" />
                Deduct for Pharmacy
              </label>
              <div class="form-group" v-if="configForm.deduct_on_pharmacy">
                <label>Per-Transaction Fee (₦)</label>
                <input v-model.number="configForm.pharmacy_per_transaction_fee" type="number" min="0" step="0.01" />
              </div>
            </div>
          </div>

          <div v-if="configError" class="alert alert-error">{{ configError }}</div>
          <div v-if="configSuccess" class="alert alert-success">{{ configSuccess }}</div>

          <div class="modal-actions">
            <button @click="showConfigModal = false" class="btn btn-secondary">Close</button>
            <button @click="submitConfig" class="btn btn-primary" :disabled="configLoading">
              {{ configLoading ? 'Saving...' : 'Save Config' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </SuperAdminLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';
import SuperAdminLayout from '../../Layouts/SuperAdminLayout.vue';

const props = defineProps({
  plans: { type: Array, default: () => [] },
});

const wallets      = ref([]);
const loading      = ref(true);
const transactions = ref([]);
const txLoading    = ref(false);

const selectedWallet = ref(null);
const showTopUpModal        = ref(false);
const showTransactionsModal = ref(false);
const showSettingsModal     = ref(false);
const showConfigModal       = ref(false);

const topUpLoading   = ref(false);
const topUpError     = ref('');
const settingsLoading = ref(false);
const configLoading  = ref(false);
const configError    = ref('');
const configSuccess  = ref('');

const topUpForm = reactive({ amount: '', description: '' });
const settingsForm = reactive({ low_balance_threshold_percent: 40, is_active: true });
const configForm = reactive({
  plan_id: '',
  new_patient_card_fee: 0,
  consultation_fee: 0,
  pharmacy_per_transaction_fee: 0,
  deduct_on_new_patient: true,
  deduct_on_consultation: true,
  deduct_on_pharmacy: true,
});

const fetchWallets = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/admin/api/wallets');
    // Enrich with balance_percent
    wallets.value = res.data.data.map(w => ({
      ...w,
      balance_percent: w.initial_funded_amount > 0
        ? Math.round((w.balance / w.initial_funded_amount) * 100 * 100) / 100
        : 100,
    }));
  } catch (e) {
    console.error('Failed to load wallets', e);
  } finally {
    loading.value = false;
  }
};

const openTopUp = (wallet) => {
  selectedWallet.value = wallet;
  topUpForm.amount = '';
  topUpForm.description = '';
  topUpError.value = '';
  showTopUpModal.value = true;
};

const submitTopUp = async () => {
  topUpLoading.value = true;
  topUpError.value = '';
  try {
    await axios.post(`/admin/api/wallets/${selectedWallet.value.tenant_id}/topup`, topUpForm);
    showTopUpModal.value = false;
    await fetchWallets();
  } catch (e) {
    topUpError.value = e.response?.data?.error?.message || 'Failed to top up.';
  } finally {
    topUpLoading.value = false;
  }
};

const openTransactions = async (wallet) => {
  selectedWallet.value = wallet;
  showTransactionsModal.value = true;
  txLoading.value = true;
  try {
    const res = await axios.get(`/admin/api/wallets/${wallet.tenant_id}/transactions`);
    transactions.value = res.data.data;
  } catch (e) {
    console.error('Failed to load transactions', e);
  } finally {
    txLoading.value = false;
  }
};

const openSettings = (wallet) => {
  selectedWallet.value = wallet;
  settingsForm.low_balance_threshold_percent = wallet.low_balance_threshold_percent;
  settingsForm.is_active = wallet.is_active;
  showSettingsModal.value = true;
};

const submitSettings = async () => {
  settingsLoading.value = true;
  try {
    await axios.put(`/admin/api/wallets/${selectedWallet.value.tenant_id}/settings`, settingsForm);
    showSettingsModal.value = false;
    await fetchWallets();
  } catch (e) {
    alert(e.response?.data?.error?.message || 'Failed to save settings.');
  } finally {
    settingsLoading.value = false;
  }
};

const submitConfig = async () => {
  configLoading.value = true;
  configError.value = '';
  configSuccess.value = '';
  try {
    // Send null for global default (empty string causes mismatch in DB)
    const payload = {
      ...configForm,
      plan_id: configForm.plan_id || null,
    };
    await axios.post('/admin/api/wallet-configs', payload);
    configSuccess.value = 'Deduction config saved successfully.';
    setTimeout(() => { configSuccess.value = ''; }, 3000);
  } catch (e) {
    configError.value = e.response?.data?.error?.message || 'Failed to save config.';
  } finally {
    configLoading.value = false;
  }
};

const formatAmount = (v) => Number(v || 0).toLocaleString('en-NG', { minimumFractionDigits: 2 });
const formatDate   = (d) => new Date(d).toLocaleString('en-NG', { dateStyle: 'medium', timeStyle: 'short' });
const formatReason = (r) => r?.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) || r;
const getProgressClass = (pct) => {
  if (pct <= 20) return 'fill-danger';
  if (pct <= 40) return 'fill-warning';
  return 'fill-success';
};

onMounted(fetchWallets);
</script>

<style scoped>
.wallets-page { padding: 2rem; max-width: 1400px; margin: 0 auto; }
.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
h1 { font-size: 2rem; color: #2c3e50; margin: 0; }

.card { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
.loading, .empty { padding: 2rem; text-align: center; color: #6c757d; }

table { width: 100%; border-collapse: collapse; }
thead { background: #f8f9fa; }
th, td { padding: 0.875rem 1rem; text-align: left; border-bottom: 1px solid #e9ecef; font-size: 0.875rem; }
th { font-weight: 600; color: #495057; }

.row-low { background: #fff8e1; }
.balance { font-weight: 600; font-size: 1rem; }

.progress-bar { height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden; width: 100px; display: inline-block; vertical-align: middle; margin-right: 0.5rem; }
.progress-fill { height: 100%; border-radius: 4px; transition: width 0.3s; }
.fill-success { background: #28a745; }
.fill-warning { background: #ffc107; }
.fill-danger  { background: #dc3545; }
.percent-label { font-size: 0.8rem; color: #6c757d; }

.badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 10px; font-size: 0.75rem; font-weight: 600; margin-right: 0.25rem; }
.badge-active  { background: #d4edda; color: #155724; }
.badge-inactive { background: #f8d7da; color: #721c24; }
.badge-warning { background: #fff3cd; color: #856404; }
.badge-debit   { background: #f8d7da; color: #721c24; }

.actions { display: flex; gap: 0.5rem; }

.text-green { color: #28a745; font-weight: 600; }
.text-red   { color: #dc3545; font-weight: 600; }

.modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: white; padding: 2rem; border-radius: 8px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto; }
.modal-large { max-width: 800px; }
.modal h3 { margin-top: 0; margin-bottom: 1rem; color: #2c3e50; }

.current-balance { color: #6c757d; margin-bottom: 1.5rem; }
.section-desc { color: #6c757d; margin-bottom: 1.5rem; font-size: 0.9rem; }

.form-group { margin-bottom: 1.25rem; }
.form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #495057; }
.form-group input, .form-group select { width: 100%; padding: 0.625rem; border: 1px solid #ced4da; border-radius: 4px; font-size: 1rem; box-sizing: border-box; }
.form-group small { display: block; margin-top: 0.25rem; color: #6c757d; font-size: 0.8rem; }

.checkbox-label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 500; }
.checkbox-label input { width: auto; }

.config-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem; }
.config-item { padding: 1rem; border: 1px solid #e9ecef; border-radius: 6px; }

.tx-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.tx-table th, .tx-table td { padding: 0.625rem; border-bottom: 1px solid #e9ecef; }
.tx-table thead { background: #f8f9fa; }

.modal-actions { display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem; }

.alert { padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1rem; }
.alert-error   { background: #f8d7da; color: #721c24; }
.alert-success { background: #d4edda; color: #155724; }

.btn { padding: 0.5rem 1rem; border: none; border-radius: 4px; font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: all 0.2s; }
.btn-primary   { background: #007bff; color: white; }
.btn-primary:hover:not(:disabled) { background: #0056b3; }
.btn-secondary { background: #6c757d; color: white; }
.btn-secondary:hover { background: #5a6268; }
.btn-info      { background: #17a2b8; color: white; }
.btn-info:hover { background: #138496; }
.btn-sm { padding: 0.375rem 0.75rem; font-size: 0.8125rem; }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }
</style>
