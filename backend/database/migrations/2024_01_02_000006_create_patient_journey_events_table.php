<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_journey_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('patient_record_id');
            $table->enum('event_type', ['registered', 'routed', 'admitted', 'discharged', 'completed', 'note_added']);
            $table->uuid('from_department_id')->nullable();
            $table->uuid('to_department_id')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('performed_by');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('patient_record_id')->references('id')->on('patient_records')->onDelete('cascade');
            $table->foreign('from_department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('to_department_id')->references('id')->on('departments')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_journey_events');
    }
};
