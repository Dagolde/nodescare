<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Patient Documents
        Schema::create('patient_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('patient_id');
            $table->string('document_type', 100);
            $table->string('object_key', 500);
            $table->string('original_filename', 255)->nullable();
            $table->uuid('uploader_id');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
        });

        // Dispensing Records (Pharmacy)
        Schema::create('dispensing_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('patient_record_id');
            $table->json('medications'); // [{name, dosage, quantity, instructions}]
            $table->text('notes')->nullable();
            $table->uuid('pharmacist_id');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('patient_record_id')->references('id')->on('patient_records')->onDelete('cascade');
        });

        // Lab Results
        Schema::create('lab_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('patient_record_id');
            $table->string('test_name');
            $table->text('result');
            $table->string('unit', 50)->nullable();
            $table->string('reference_range', 100)->nullable();
            $table->enum('status', ['pending', 'completed', 'abnormal'])->default('pending');
            $table->text('notes')->nullable();
            $table->uuid('technician_id');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('patient_record_id')->references('id')->on('patient_records')->onDelete('cascade');
        });

        // Appointments
        Schema::create('appointments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('patient_id');
            $table->uuid('doctor_id');
            $table->uuid('department_id')->nullable();
            $table->timestamp('scheduled_at');
            $table->enum('status', ['scheduled', 'completed', 'cancelled', 'no_show'])->default('scheduled');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('doctor_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('lab_results');
        Schema::dropIfExists('dispensing_records');
        Schema::dropIfExists('patient_documents');
    }
};
