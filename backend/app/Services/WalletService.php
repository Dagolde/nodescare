<?php

namespace App\Services;

use App\Models\HospitalWallet;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WalletDeductionConfig;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WalletService
{
    /**
     * Get or create a wallet for a tenant.
     */
    public function getOrCreateWallet(string $tenantId): HospitalWallet
    {
        Log::info('getOrCreateWallet called', [
            'tenant_id' => $tenantId,
        ]);

        // Check if tenant exists first
        $tenant = \App\Models\Tenant::find($tenantId);
        if (!$tenant) {
            Log::error('Tenant not found when creating wallet', [
                'tenant_id' => $tenantId,
            ]);
            throw new \Exception("Tenant with ID {$tenantId} not found. Cannot create wallet.");
        }

        Log::info('Tenant found', [
            'tenant_id' => $tenantId,
            'tenant_name' => $tenant->name ?? 'N/A',
            'tenant_status' => $tenant->status ?? 'N/A',
        ]);

        return HospitalWallet::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'id'                            => Str::uuid(),
                'balance'                       => 0,
                'low_balance_threshold_percent' => 40,
                'initial_funded_amount'         => 0,
                'is_active'                     => true,
            ]
        );
    }

    /**
     * Credit wallet (top-up via Paystack or manual).
     */
    public function credit(
        string $tenantId,
        float $amount,
        string $reason,
        string $description,
        ?string $reference = null,
        ?string $performedBy = null
    ): WalletTransaction {
        return DB::transaction(function () use ($tenantId, $amount, $reason, $description, $reference, $performedBy) {
            $wallet = $this->getOrCreateWallet($tenantId);

            $balanceBefore = (float) $wallet->balance;
            $balanceAfter  = $balanceBefore + $amount;

            $wallet->balance = $balanceAfter;

            // Update initial_funded_amount for percentage tracking
            if (in_array($reason, ['paystack_topup', 'manual_topup'])) {
                $wallet->initial_funded_amount = (float) $wallet->initial_funded_amount + $amount;
            }

            $wallet->save();

            $tx = WalletTransaction::create([
                'id'             => Str::uuid(),
                'tenant_id'      => $tenantId,
                'wallet_id'      => $wallet->id,
                'type'           => 'credit',
                'reason'         => $reason,
                'amount'         => $amount,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'reference'      => $reference,
                'description'    => $description,
                'performed_by'   => $performedBy,
            ]);

            Log::info('Wallet credited', [
                'tenant_id' => $tenantId,
                'amount'    => $amount,
                'reason'    => $reason,
                'balance'   => $balanceAfter,
            ]);

            return $tx;
        });
    }

    /**
     * Deduct from wallet. Always records the transaction even if balance goes negative.
     * Returns the transaction record.
     */
    public function deduct(
        string $tenantId,
        float $amount,
        string $reason,
        string $description,
        ?string $patientId = null,
        ?string $invoiceId = null
    ): WalletTransaction|false {
        return DB::transaction(function () use ($tenantId, $amount, $reason, $description, $patientId, $invoiceId) {
            Log::info('deduct method called', [
                'tenant_id' => $tenantId,
                'amount' => $amount,
                'reason' => $reason,
                'description' => $description,
                'patient_id' => $patientId,
                'invoice_id' => $invoiceId,
            ]);

            $wallet = $this->getOrCreateWallet($tenantId);

            Log::info('Wallet retrieved/created', [
                'wallet_id' => $wallet->id,
                'balance' => $wallet->balance,
                'is_active' => $wallet->is_active,
            ]);

            if (!$wallet->is_active) {
                Log::warning('Wallet deduction skipped — wallet inactive', ['tenant_id' => $tenantId]);
                return false;
            }

            $balanceBefore = (float) $wallet->balance;
            $balanceAfter  = $balanceBefore - $amount;

            Log::info('Processing deduction', [
                'balance_before' => $balanceBefore,
                'deduction_amount' => $amount,
                'balance_after' => $balanceAfter,
            ]);

            // Allow negative balance — deduction is always recorded for billing purposes
            $wallet->balance = $balanceAfter;
            $wallet->save();

            $tx = WalletTransaction::create([
                'id'             => Str::uuid(),
                'tenant_id'      => $tenantId,
                'wallet_id'      => $wallet->id,
                'type'           => 'debit',
                'reason'         => $reason,
                'amount'         => $amount,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'description'    => $description,
                'patient_id'     => $patientId,
                'invoice_id'     => $invoiceId,
            ]);

            Log::info('Wallet deducted successfully', [
                'tenant_id' => $tenantId,
                'amount' => $amount,
                'reason' => $reason,
                'balance' => $balanceAfter,
                'transaction_id' => $tx->id,
            ]);

            // Check low balance threshold and notify
            $wallet->refresh();
            if ($wallet->isLowBalance()) {
                $this->sendLowBalanceNotification($tenantId, $wallet);
            }

            return $tx;
        });
    }

    /**
     * Deduct for a new patient registration (card fee + consultation).
     */
    public function deductForNewPatient(string $tenantId, string $patientId, ?string $planId): void
    {
        Log::info('deductForNewPatient called', [
            'tenant_id' => $tenantId,
            'patient_id' => $patientId,
            'plan_id' => $planId,
        ]);

        $config = WalletDeductionConfig::forPlan($planId);

        Log::info('Wallet deduction config loaded', [
            'config_id' => $config->id ?? 'unsaved',
            'plan_id' => $config->plan_id,
            'new_patient_card_fee' => $config->new_patient_card_fee,
            'consultation_fee' => $config->consultation_fee,
            'deduct_on_new_patient' => $config->deduct_on_new_patient,
            'deduct_on_consultation' => $config->deduct_on_consultation,
        ]);

        if ($config->deduct_on_new_patient && $config->new_patient_card_fee > 0) {
            Log::info('Deducting new patient card fee', [
                'amount' => $config->new_patient_card_fee,
            ]);
            $this->deduct(
                $tenantId,
                (float) $config->new_patient_card_fee,
                'new_patient_card',
                'New patient registration card fee',
                $patientId
            );
        }

        if ($config->deduct_on_consultation && $config->consultation_fee > 0) {
            Log::info('Deducting consultation fee', [
                'amount' => $config->consultation_fee,
            ]);
            $this->deduct(
                $tenantId,
                (float) $config->consultation_fee,
                'consultation',
                'Consultation fee',
                $patientId
            );
        }
    }

    /**
     * Deduct for a returning patient (consultation only).
     */
    public function deductForReturningPatient(string $tenantId, string $patientId, ?string $planId): void
    {
        Log::info('deductForReturningPatient called', [
            'tenant_id' => $tenantId,
            'patient_id' => $patientId,
            'plan_id' => $planId,
        ]);

        $config = WalletDeductionConfig::forPlan($planId);

        Log::info('Wallet deduction config loaded for returning patient', [
            'config_id' => $config->id ?? 'unsaved',
            'plan_id' => $config->plan_id,
            'consultation_fee' => $config->consultation_fee,
            'deduct_on_consultation' => $config->deduct_on_consultation,
        ]);

        if ($config->deduct_on_consultation && $config->consultation_fee > 0) {
            Log::info('Deducting consultation fee for returning patient', [
                'amount' => $config->consultation_fee,
            ]);
            $this->deduct(
                $tenantId,
                (float) $config->consultation_fee,
                'consultation',
                'Consultation fee (returning patient)',
                $patientId
            );
        }
    }

    /**
     * Deduct for pharmacy drug payment.
     */
    public function deductForPharmacy(string $tenantId, string $patientId, ?string $invoiceId, ?string $planId): void
    {
        $config = WalletDeductionConfig::forPlan($planId);

        if ($config->deduct_on_pharmacy && $config->pharmacy_per_transaction_fee > 0) {
            $this->deduct(
                $tenantId,
                (float) $config->pharmacy_per_transaction_fee,
                'pharmacy_drugs',
                'Pharmacy drug transaction fee',
                $patientId,
                $invoiceId
            );
        }
    }

    /**
     * Send low balance notification to all hospital staff.
     */
    private function sendLowBalanceNotification(string $tenantId, HospitalWallet $wallet): void
    {
        try {
            $percent = $wallet->balancePercent();

            // Notify all active users in this tenant
            $users = User::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->get();

            foreach ($users as $user) {
                Notification::withoutGlobalScopes()->create([
                    'id'           => Str::uuid(),
                    'tenant_id'    => $tenantId,
                    'recipient_id' => $user->id,
                    'type'         => 'wallet_low_balance',
                    'channel'      => 'in_app',
                    'payload'      => [
                        'title'   => 'Low Wallet Balance',
                        'message' => "Hospital wallet balance is at {$percent}% (₦" . number_format($wallet->balance, 2) . " remaining). Please top up soon.",
                        'balance' => $wallet->balance,
                        'percent' => $percent,
                    ],
                    'status'   => 'delivered',
                    'attempts' => 1,
                ]);
            }

            Log::info('Low balance notification sent', [
                'tenant_id' => $tenantId,
                'balance'   => $wallet->balance,
                'percent'   => $percent,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send low balance notification', [
                'tenant_id' => $tenantId,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Verify a Paystack payment and credit the wallet.
     */
    public function verifyAndCreditPaystack(string $tenantId, string $reference): array
    {
        // Call Paystack verify endpoint
        $paystackSecretKey = config('services.paystack.secret_key');

        $ch = curl_init("https://api.paystack.co/transaction/verify/{$reference}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$paystackSecretKey}",
            "Content-Type: application/json",
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return ['success' => false, 'message' => 'Failed to verify payment with Paystack.'];
        }

        $data = json_decode($response, true);

        if (!$data['status'] || $data['data']['status'] !== 'success') {
            return ['success' => false, 'message' => 'Payment not successful.'];
        }

        // Amount is in kobo (smallest unit), convert to naira
        $amountNaira = $data['data']['amount'] / 100;

        // Check if this reference was already processed
        $existing = WalletTransaction::where('reference', $reference)->first();
        if ($existing) {
            return ['success' => false, 'message' => 'Payment already processed.'];
        }

        $tx = $this->credit(
            $tenantId,
            $amountNaira,
            'paystack_topup',
            "Paystack top-up — ref: {$reference}",
            $reference
        );

        return [
            'success'     => true,
            'amount'      => $amountNaira,
            'transaction' => $tx,
            'message'     => "Wallet credited with ₦" . number_format($amountNaira, 2),
        ];
    }
}
