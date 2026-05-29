<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('patient_id');
            $table->uuid('workflow_template_id')->nullable();
            $table->enum('status', [
                'pending_payment', 'active', 'in_transit', 'admitted', 'discharged', 'completed'
            ])->default('pending_payment');
            $table->uuid('current_department_id')->nullable();
            $table->integer('current_step_order')->nullable();
            $table->uuid('registered_by');
            $table->timestamp('admitted_at')->nullable();
            $table->timestamp('discharged_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('workflow_template_id')->references('id')->on('workflow_templates')->onDelete('set null');
            $table->foreign('current_department_id')->references('id')->on('departments')->onDelete('set null');

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'current_department_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_records');
    }
};
