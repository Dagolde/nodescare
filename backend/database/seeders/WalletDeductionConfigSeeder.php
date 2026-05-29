<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletDeductionConfigSeeder extends Seeder
{
    /**
     * Seed the global default wallet deduction config.
     * These are the platform fees HHMS charges the hospital per patient.
     * Super Admin can update these via the dashboard.
     */
    public function run(): void
    {
        // Only seed if no global default exists
        $exists = DB::table('wallet_deduction_configs')
            ->whereNull('plan_id')
            ->exists();

        if (!$exists) {
            DB::table('wallet_deduction_configs')->insert([
                'id'                           => Str::uuid(),
                'plan_id'                      => null, // global default
                'new_patient_card_fee'         => 50.00,  // ₦50 per new patient card
                'consultation_fee'             => 100.00, // ₦100 per consultation
                'pharmacy_per_transaction_fee' => 20.00,  // ₦20 per pharmacy transaction
                'deduct_on_new_patient'        => true,
                'deduct_on_consultation'       => true,
                'deduct_on_pharmacy'           => true,
                'created_at'                   => now(),
                'updated_at'                   => now(),
            ]);

            $this->command->info('Default wallet deduction config seeded (₦50 card, ₦100 consultation, ₦20 pharmacy).');
        } else {
            $this->command->info('Wallet deduction config already exists — skipped.');
        }
    }
}
