<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('recipient_id')->nullable();
            $table->string('type', 100);
            $table->string('channel', 50)->default('system');
            $table->json('payload');
            $table->enum('status', ['pending', 'delivered', 'failed'])->default('pending');
            $table->integer('attempts')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('actor_id')->nullable();
            $table->string('actor_role', 50)->nullable();
            $table->string('action', 100);
            $table->string('resource_type', 100)->nullable();
            $table->uuid('resource_id')->nullable();
            $table->text('description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['tenant_id', 'action']);
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('user_device_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id');
            $table->string('token', 500);
            $table->string('platform', 20)->default('android');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['tenant_id', 'user_id', 'token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_device_tokens');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notifications');
    }
};
