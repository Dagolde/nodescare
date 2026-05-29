<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hospital wallets (one per tenant)
        Schema::create('hospital_wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->unique();
            $table->decimal('balance', 14, 2)->default(0);
            $table->decimal('low_balance_threshold_percent', 5, 2)->default(40); // notify at 40%
            $table->decimal('initial_funded_amount', 14, 2)->default(0); // for % calculation
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // Wallet transactions (top-ups, deductions)
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('wallet_id');
            $table->enum('type', ['credit', 'debit']);
            $table->enum('reason', [
                'paystack_topup',       // funded via Paystack
                'manual_topup',         // Super Admin manual top-up
                'new_patient_card',     // new patient registration fee
                'consultation',         // consultation fee
                'pharmacy_drugs',       // pharmacy drug payment
                'adjustment',           // Super Admin adjustment
            ]);
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_before', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->string('reference', 100)->nullable(); // Paystack reference
            $table->text('description')->nullable();
            $table->uuid('patient_id')->nullable();
            $table->uuid('invoice_id')->nullable();
            $table->uuid('performed_by')->nullable(); // Super Admin ID for manual ops
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('wallet_id')->references('id')->on('hospital_wallets')->onDelete('cascade');
            $table->index(['tenant_id', 'created_at']);
        });

        // Wallet deduction config (Super Admin configures per-plan or global)
        Schema::create('wallet_deduction_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('plan_id')->nullable(); // null = global default
            $table->decimal('new_patient_card_fee', 10, 2)->default(0);
            $table->decimal('consultation_fee', 10, 2)->default(0);
            $table->decimal('pharmacy_per_transaction_fee', 10, 2)->default(0);
            $table->boolean('deduct_on_new_patient')->default(true);
            $table->boolean('deduct_on_consultation')->default(true);
            $table->boolean('deduct_on_pharmacy')->default(true);
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_deduction_configs');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('hospital_wallets');
    }
};
