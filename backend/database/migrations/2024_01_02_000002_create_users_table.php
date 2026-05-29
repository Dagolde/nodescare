<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('email');
            $table->string('phone', 20)->nullable();
            $table->string('password');
            $table->enum('role', [
                'hospital_admin',
                'doctor',
                'front_desk_officer',
                'accountant',
                'pharmacist',
                'lab_technician',
                'department_staff'
            ]);
            $table->uuid('department_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_hod')->default(false);
            $table->uuid('created_by')->nullable();
            $table->integer('token_version')->default(0);
            $table->integer('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->unique(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
