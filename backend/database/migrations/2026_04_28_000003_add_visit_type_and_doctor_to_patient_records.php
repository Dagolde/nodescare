<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_records', function (Blueprint $table) {
            $table->string('visit_type')->nullable()->after('registered_by');
            $table->uuid('assigned_doctor_id')->nullable()->after('visit_type');
            $table->foreign('assigned_doctor_id')->references('id')->on('users')->nullOnDelete();
            $table->index('assigned_doctor_id');
        });
    }

    public function down(): void
    {
        Schema::table('patient_records', function (Blueprint $table) {
            $table->dropForeign(['assigned_doctor_id']);
            $table->dropIndex(['assigned_doctor_id']);
            $table->dropColumn(['visit_type', 'assigned_doctor_id']);
        });
    }
};
