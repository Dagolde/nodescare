<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HospitalWallet;
use App\Models\Tenant;
use App\Models\WalletDeductionConfig;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    public function __construct(private readonly WalletService $walletService) {}

    // -------------------------------------------------------------------------
    // Super Admin — manage all hospital wallets
    // -------------------------------------------------------------------------

    /**
     * List all hospital wallets with balances.
     * GET /admin/api/wallets
     */
    public function listWallets(Request $request): JsonResponse
    {
        $query = HospitalWallet::with('tenant.plan')
            ->orderBy('balance', 'asc'); // lowest balance first

        if ($request->has('tenant_id')) {
            $query->where('tenant_id', $request->input('tenant_id'));
        }

        $wallets = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $wallets->items(),
            'meta'    => [
                'pagination' => [
                    'current_page' => $wallets->currentPage(),
                    'per_page'     => $wallets->perPage(),
                    'total'        => $wallets->total(),
                    'last_page'    => $wallets->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * Get wallet details for a specific hospital.
     * GET /admin/api/wallets/{tenantId}
     */
    public function getWallet(string $tenantId): JsonResponse
    {
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            return response()->json(['success' => false, 'error' => ['message' => 'Hospital not found.']], 404);
        }

        $wallet = $this->walletService->getOrCreateWallet($tenantId);

        $recentTransactions = WalletTransaction::where('wallet_id', $wallet->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'wallet'              => $wallet,
                'tenant'              => $tenant->only(['id', 'name', 'subdomain', 'status']),
                'balance_percent'     => $wallet->balancePercent(),
                'is_low_balance'      => $wallet->isLowBalance(),
                'recent_transactions' => $recentTransactions,
            ],
        ]);
    }

    /**
     * Manual top-up by Super Admin.
     * POST /admin/api/wallets/{tenantId}/topup
     */
    public function topUp(string $tenantId, Request $request): JsonResponse
    {
        $request->validate([
            'amount'      => ['required', 'numeric', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            return response()->json(['success' => false, 'error' => ['message' => 'Hospital not found.']], 404);
        }

        $tx = $this->walletService->credit(
            $tenantId,
            (float) $request->input('amount'),
            'manual_topup',
            $request->input('description', 'Manual top-up by Super Admin'),
            null,
            auth()->id()
        );

        $wallet = $this->walletService->getOrCreateWallet($tenantId);

        return response()->json([
            'success' => true,
            'data'    => [
                'transaction'     => $tx,
                'new_balance'     => $wallet->balance,
                'balance_percent' => $wallet->balancePercent(),
            ],
            'message' => 'Wallet topped up successfully.',
        ]);
    }

    /**
     * Adjust wallet balance (debit or credit) by Super Admin.
     * POST /admin/api/wallets/{tenantId}/adjust
     */
    public function adjust(string $tenantId, Request $request): JsonResponse
    {
        $request->validate([
            'type'        => ['required', 'in:credit,debit'],
            'amount'      => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            return response()->json(['success' => false, 'error' => ['message' => 'Hospital not found.']], 404);
        }

        if ($request->input('type') === 'credit') {
            $tx = $this->walletService->credit(
                $tenantId,
                (float) $request->input('amount'),
                'adjustment',
                $request->input('description'),
                null,
                auth()->id()
            );
        } else {
            $tx = $this->walletService->deduct(
                $tenantId,
                (float) $request->input('amount'),
                'adjustment',
                $request->input('description')
            );

            if ($tx === false) {
                return response()->json([
                    'success' => false,
                    'error'   => ['message' => 'Insufficient wallet balance for this deduction.'],
                ], 422);
            }
        }

        $wallet = $this->walletService->getOrCreateWallet($tenantId);

        return response()->json([
            'success' => true,
            'data'    => [
                'transaction'     => $tx,
                'new_balance'     => $wallet->balance,
                'balance_percent' => $wallet->balancePercent(),
            ],
            'message' => 'Wallet adjusted successfully.',
        ]);
    }

    /**
     * Update wallet settings (threshold, active status).
     * PUT /admin/api/wallets/{tenantId}/settings
     */
    public function updateSettings(string $tenantId, Request $request): JsonResponse
    {
        $request->validate([
            'low_balance_threshold_percent' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'is_active'                     => ['nullable', 'boolean'],
        ]);

        $wallet = $this->walletService->getOrCreateWallet($tenantId);

        $wallet->update(array_filter([
            'low_balance_threshold_percent' => $request->input('low_balance_threshold_percent'),
            'is_active'                     => $request->input('is_active'),
        ], fn ($v) => $v !== null));

        return response()->json([
            'success' => true,
            'data'    => $wallet->fresh(),
            'message' => 'Wallet settings updated.',
        ]);
    }

    /**
     * Get wallet transactions for a hospital.
     * GET /admin/api/wallets/{tenantId}/transactions
     */
    public function getTransactions(string $tenantId, Request $request): JsonResponse
    {
        $wallet = HospitalWallet::where('tenant_id', $tenantId)->first();

        if (!$wallet) {
            return response()->json(['success' => true, 'data' => [], 'meta' => []]);
        }

        $query = WalletTransaction::where('wallet_id', $wallet->id)
            ->orderBy('created_at', 'desc');

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->has('reason')) {
            $query->where('reason', $request->input('reason'));
        }
        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->input('start_date'));
        }
        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->input('end_date'));
        }

        $transactions = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $transactions->items(),
            'meta'    => [
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'per_page'     => $transactions->perPage(),
                    'total'        => $transactions->total(),
                    'last_page'    => $transactions->lastPage(),
                ],
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Deduction Config
    // -------------------------------------------------------------------------

    /**
     * List all deduction configs.
     * GET /admin/api/wallet-configs
     */
    public function listConfigs(): JsonResponse
    {
        $configs = WalletDeductionConfig::with('plan')->get();
        return response()->json(['success' => true, 'data' => $configs]);
    }

    /**
     * Create or update deduction config for a plan (or global default).
     * POST /admin/api/wallet-configs
     */
    public function saveConfig(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id'                        => ['nullable', 'uuid', 'exists:plans,id'],
            'new_patient_card_fee'           => ['required', 'numeric', 'min:0'],
            'consultation_fee'               => ['required', 'numeric', 'min:0'],
            'pharmacy_per_transaction_fee'   => ['required', 'numeric', 'min:0'],
            'deduct_on_new_patient'          => ['required', 'boolean'],
            'deduct_on_consultation'         => ['required', 'boolean'],
            'deduct_on_pharmacy'             => ['required', 'boolean'],
        ]);

        $config = WalletDeductionConfig::updateOrCreate(
            ['plan_id' => $request->input('plan_id') ?: null],
            [
                'new_patient_card_fee'           => $request->input('new_patient_card_fee'),
                'consultation_fee'               => $request->input('consultation_fee'),
                'pharmacy_per_transaction_fee'   => $request->input('pharmacy_per_transaction_fee'),
                'deduct_on_new_patient'          => $request->input('deduct_on_new_patient'),
                'deduct_on_consultation'         => $request->input('deduct_on_consultation'),
                'deduct_on_pharmacy'             => $request->input('deduct_on_pharmacy'),
            ]
        );

        return response()->json([
            'success' => true,
            'data'    => $config->fresh('plan'),
            'message' => 'Deduction config saved.',
        ]);
    }

    /**
     * Delete a deduction config.
     * DELETE /admin/api/wallet-configs/{id}
     */
    public function deleteConfig(string $id): JsonResponse
    {
        $config = WalletDeductionConfig::find($id);
        if (!$config) {
            return response()->json(['success' => false, 'error' => ['message' => 'Config not found.']], 404);
        }

        $config->delete();
        return response()->json(['success' => true, 'message' => 'Config deleted.']);
    }

    // -------------------------------------------------------------------------
    // Hospital Admin — view own wallet + Paystack top-up
    // -------------------------------------------------------------------------

    /**
     * Get own hospital wallet (Hospital Admin).
     * GET /hospital-admin/api/wallet
     */
    public function myWallet(Request $request): JsonResponse
    {
        $tenantId = session('tenant_id') ?? app('current_tenant_id');

        if (!$tenantId) {
            return response()->json(['success' => false, 'error' => ['message' => 'Tenant not found.']], 404);
        }

        $wallet = $this->walletService->getOrCreateWallet($tenantId);

        $recentTransactions = WalletTransaction::where('wallet_id', $wallet->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'wallet'              => $wallet,
                'balance_percent'     => $wallet->balancePercent(),
                'is_low_balance'      => $wallet->isLowBalance(),
                'recent_transactions' => $recentTransactions,
            ],
        ]);
    }

    /**
     * Initiate Paystack payment for wallet top-up.
     * POST /hospital-admin/api/wallet/paystack/initiate
     */
    public function initiatePaystack(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:100'], // minimum ₦100
            'email'  => ['required', 'email'],
        ]);

        $tenantId = session('tenant_id') ?? app('current_tenant_id');
        $paystackSecretKey = config('services.paystack.secret_key');

        if (!$paystackSecretKey) {
            return response()->json([
                'success' => false,
                'error'   => ['message' => 'Paystack is not configured. Contact Super Admin.'],
            ], 503);
        }

        $amountKobo = (int) ($request->input('amount') * 100);
        $reference  = 'WALLET-' . strtoupper(Str::random(12));

        $ch = curl_init('https://api.paystack.co/transaction/initialize');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'email'     => $request->input('email'),
            'amount'    => $amountKobo,
            'reference' => $reference,
            'metadata'  => ['tenant_id' => $tenantId, 'purpose' => 'wallet_topup'],
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$paystackSecretKey}",
            "Content-Type: application/json",
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return response()->json([
                'success' => false,
                'error'   => ['message' => 'Failed to initiate payment.'],
            ], 500);
        }

        $data = json_decode($response, true);

        return response()->json([
            'success' => true,
            'data'    => [
                'authorization_url' => $data['data']['authorization_url'],
                'reference'         => $reference,
                'amount'            => $request->input('amount'),
            ],
        ]);
    }

    /**
     * Verify Paystack payment and credit wallet.
     * POST /hospital-admin/api/wallet/paystack/verify
     */
    public function verifyPaystack(Request $request): JsonResponse
    {
        $request->validate([
            'reference' => ['required', 'string'],
        ]);

        $tenantId = session('tenant_id') ?? app('current_tenant_id');

        $result = $this->walletService->verifyAndCreditPaystack($tenantId, $request->input('reference'));

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error'   => ['message' => $result['message']],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data'    => $result,
            'message' => $result['message'],
        ]);
    }
}
