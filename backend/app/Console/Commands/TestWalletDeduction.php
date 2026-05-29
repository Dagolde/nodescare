<?php

namespace App\Console\Commands;

use App\Services\WalletService;
use App\Models\Tenant;
use Illuminate\Console\Command;

class TestWalletDeduction extends Command
{
    protected $signature = 'wallet:test-deduction {tenant_id} {--amount=100}';
    protected $description = 'Test wallet deduction for a specific tenant';

    public function handle()
    {
        $tenantId = $this->argument('tenant_id');
        $amount = (float) $this->option('amount');
        
        $this->info("Testing wallet deduction for tenant: {$tenantId}");
        $this->info("Deduction amount: ₦{$amount}");
        
        // Check if tenant exists
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            $this->error("Tenant {$tenantId} not found!");
            return 1;
        }
        
        $this->info("Tenant found: {$tenant->name}");
        
        try {
            $walletService = app(WalletService::class);
            
            // Get wallet info before deduction
            $wallet = $walletService->getOrCreateWallet($tenantId);
            $balanceBefore = $wallet->balance;
            $this->info("Wallet balance before: ₦{$balanceBefore}");
            
            // Perform test deduction
            $transaction = $walletService->deduct(
                $tenantId,
                $amount,
                'adjustment', // Use valid enum value
                'Manual test deduction via command',
                'test-patient-id'
            );
            
            if ($transaction) {
                $this->info("✅ Deduction successful!");
                $this->info("Transaction ID: {$transaction->id}");
                $this->info("Balance before: ₦{$transaction->balance_before}");
                $this->info("Balance after: ₦{$transaction->balance_after}");
            } else {
                $this->error("❌ Deduction failed - returned false");
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Deduction failed with error: {$e->getMessage()}");
            return 1;
        }
        
        return 0;
    }
}