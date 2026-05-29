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

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM(
            'hospital_admin',
            'doctor', 
            'front_desk_officer',
            'accountant',
            'pharmacist',
            'lab_technician',
            'nurse',
            'triage',
            'department_staff'
        )");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('users')
            ->whereIn('role', ['nurse', 'triage'])
            ->update(['role' => 'department_staff']);

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM(
            'hospital_admin',
            'doctor',
            'front_desk_officer', 
            'accountant',
            'pharmacist',
            'lab_technician',
            'department_staff'
        )");
    }
};