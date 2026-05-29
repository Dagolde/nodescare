<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_records', function (Blueprint $table) {
            // Where to route the patient after Accounts payment (for lab/pharmacy referrals)
            $table->uuid('return_to_department_id')->nullable()->after('current_step_order');
            // Context: 'lab_referral' | 'pharmacy_referral' | null
            $table->string('pending_payment_context', 50)->nullable()->after('return_to_department_id');
        });
    }

    public function down(): void
    {
        Schema::table('patient_records', function (Blueprint $table) {
            $table->dropColumn(['return_to_department_id', 'pending_payment_context']);
        });
    }
};
