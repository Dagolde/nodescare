<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MODIFY COLUMN / ENUM alteration is MySQL-only — skip on SQLite (test env)
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE wallet_transactions MODIFY COLUMN reason ENUM(
            'paystack_topup',
            'manual_topup',
            'new_patient_card',
            'consultation',
            'pharmacy_drugs',
            'adjustment',
            'test_deduction'
        )");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE wallet_transactions MODIFY COLUMN reason ENUM(
            'paystack_topup',
            'manual_topup',
            'new_patient_card',
            'consultation',
            'pharmacy_drugs',
            'adjustment'
        )");
    }
};