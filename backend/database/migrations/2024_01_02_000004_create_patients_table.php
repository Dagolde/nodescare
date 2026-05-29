<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('patient_number', 20);
            $table->string('full_name');
            $table->date('date_of_birth');
            $table->enum('gender', ['male', 'female', 'other']);
            $table->string('phone', 20);
            $table->text('address')->nullable();
            $table->string('next_of_kin_name')->nullable();
            $table->string('next_of_kin_phone', 20)->nullable();
            $table->text('chief_complaint');
            $table->string('hmo_provider_name')->nullable();
            $table->string('hmo_policy_number')->nullable();
            $table->string('photo_object_key', 500)->nullable();
            $table->boolean('is_anonymised')->default(false);
            $table->uuid('created_by');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'patient_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
