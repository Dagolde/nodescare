<?php

namespace App\Console\Commands;

use App\Models\HospitalWallet;
use App\Models\WalletTransaction;
use App\Models\Tenant;
use Illuminate\Console\Command;

class CheckWalletStatus extends Command
{
    protected $signature = 'wallet:status {tenant_id}';
    protected $description = 'Check wallet status and recent transactions for a tenant';

    public function handle()
    {
        $tenantId = $this->argument('tenant_id');
        
        // Check if tenant exists
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            $this->error("Tenant {$tenantId} not found!");
            return 1;
        }
        
        $this->info("=== Tenant Information ===");
        $this->info("ID: {$tenant->id}");
        $this->info("Name: {$tenant->name}");
        $this->info("Status: {$tenant->status}");
        $this->info("Plan ID: {$tenant->plan_id}");
        
        // Check wallet
        $wallet = HospitalWallet::where('tenant_id', $tenantId)->first();
        
        if (!$wallet) {
            $this->warn("No wallet found for this tenant.");
            return 0;
        }
        
        $this->info("\n=== Wallet Information ===");
        $this->info("Wallet ID: {$wallet->id}");
        $this->info("Balance: ₦{$wallet->balance}");
        $this->info("Initial Funded Amount: ₦{$wallet->initial_funded_amount}");
        $this->info("Low Balance Threshold: {$wallet->low_balance_threshold_percent}%");
        $this->info("Is Active: " . ($wallet->is_active ? 'Yes' : 'No'));
        $this->info("Created: {$wallet->created_at}");
        $this->info("Updated: {$wallet->updated_at}");
        
        // Check recent transactions
        $transactions = WalletTransaction::where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
            
        if ($transactions->count() > 0) {
            $this->info("\n=== Recent Transactions (Last 10) ===");
            foreach ($transactions as $tx) {
                $type = $tx->type === 'credit' ? '💰' : '💸';
                $this->info("{$type} {$tx->created_at} | {$tx->type} | ₦{$tx->amount} | {$tx->reason} | Balance: ₦{$tx->balance_after}");
            }
        } else {
            $this->warn("\nNo transactions found for this wallet.");
        }
        
        return 0;
    }
}