<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_vitals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('patient_record_id');
            $table->uuid('recorded_by');

            // Vital signs
            $table->string('blood_pressure', 20)->nullable();       // e.g. "120/80"
            $table->decimal('temperature', 5, 2)->nullable();       // °C
            $table->integer('pulse_rate')->nullable();              // bpm
            $table->integer('respiratory_rate')->nullable();        // breaths/min
            $table->integer('oxygen_saturation')->nullable();       // %
            $table->decimal('weight', 6, 2)->nullable();            // kg
            $table->decimal('height', 6, 2)->nullable();            // cm
            $table->decimal('bmi', 5, 2)->nullable();               // calculated
            $table->integer('blood_glucose')->nullable();           // mg/dL
            $table->text('notes')->nullable();                      // additional notes

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('patient_record_id')->references('id')->on('patient_records')->onDelete('cascade');
            $table->index(['patient_record_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_vitals');
    }
};
