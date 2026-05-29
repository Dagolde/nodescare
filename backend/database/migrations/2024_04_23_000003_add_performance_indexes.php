<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Index on patient_records(current_department_id, status)
        Schema::table('patient_records', function (Blueprint $table) {
            $table->index(['current_department_id', 'status'], 'idx_patient_records_dept_status');
        });

        // Index on patient_journey_events(to_department_id, created_at)
        Schema::table('patient_journey_events', function (Blueprint $table) {
            $table->index(['to_department_id', 'created_at'], 'idx_journey_events_dept_date');
        });

        // Index on patient_vitals(patient_record_id, created_at) - already exists in create_patient_vitals_table migration
        // No action needed

        // Index on lab_results(patient_record_id, created_at)
        Schema::table('lab_results', function (Blueprint $table) {
            $table->index(['patient_record_id', 'created_at'], 'idx_lab_results_patient_date');
        });

        // Index on users(department_id, is_active)
        Schema::table('users', function (Blueprint $table) {
            $table->index(['department_id', 'is_active'], 'idx_users_dept_active');
        });
    }

    public function down(): void
    {
        Schema::table('patient_records', function (Blueprint $table) {
            $table->dropIndex('idx_patient_records_dept_status');
        });

        Schema::table('patient_journey_events', function (Blueprint $table) {
            $table->dropIndex('idx_journey_events_dept_date');
        });

        Schema::table('lab_results', function (Blueprint $table) {
            $table->dropIndex('idx_lab_results_patient_date');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_dept_active');
        });
    }
};
