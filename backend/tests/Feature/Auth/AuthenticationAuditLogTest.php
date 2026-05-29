<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_creates_audit_log(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'user.login.success',
            'resource_type' => 'user',
            'resource_id' => $user->id,
        ]);

        $auditLog = AuditLog::where('action', 'user.login.success')->first();
        $this->assertNotNull($auditLog->ip_address);
        $this->assertEquals($user->role, $auditLog->actor_role);
        $this->assertStringContainsString('Login successful', $auditLog->description);
    }

    public function test_failed_login_creates_audit_log(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'user.login.failed',
            'resource_type' => 'user',
            'resource_id' => $user->id,
        ]);

        $auditLog = AuditLog::where('action', 'user.login.failed')->first();
        $this->assertNotNull($auditLog->ip_address);
        $this->assertStringContainsString('Failed login attempt', $auditLog->description);
    }

    public function test_account_lockout_creates_audit_log(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        // Make 5 failed login attempts to trigger lockout
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword',
            ]);
        }

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'user.login.locked',
            'resource_type' => 'user',
            'resource_id' => $user->id,
        ]);

        $auditLog = AuditLog::where('action', 'user.login.locked')->first();
        $this->assertNotNull($auditLog->ip_address);
        $this->assertStringContainsString('Account locked after 5 failed attempts', $auditLog->description);
    }

    public function test_failed_login_with_nonexistent_user_creates_audit_log(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => null,
            'action' => 'user.login.failed',
            'resource_type' => 'user',
        ]);

        $auditLog = AuditLog::where('action', 'user.login.failed')
            ->whereNull('actor_id')
            ->first();
        
        $this->assertNotNull($auditLog);
        $this->assertNotNull($auditLog->ip_address);
        $this->assertStringContainsString('User not found', $auditLog->description);
    }

    public function test_inactive_account_login_attempt_creates_audit_log(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'user.login.failed',
            'resource_type' => 'user',
            'resource_id' => $user->id,
        ]);

        $auditLog = AuditLog::where('action', 'user.login.failed')
            ->where('actor_id', $user->id)
            ->first();
        
        $this->assertStringContainsString('Account inactive', $auditLog->description);
    }

    public function test_locked_account_login_attempt_creates_audit_log(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'locked_until' => now()->addMinutes(10),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'user.login.failed',
            'resource_type' => 'user',
            'resource_id' => $user->id,
        ]);

        $auditLog = AuditLog::where('action', 'user.login.failed')
            ->where('actor_id', $user->id)
            ->first();
        
        $this->assertStringContainsString('Account locked', $auditLog->description);
    }

    public function test_audit_log_includes_ip_address_and_timestamp(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $auditLog = AuditLog::where('action', 'user.login.success')->first();
        
        $this->assertNotNull($auditLog->ip_address);
        $this->assertNotNull($auditLog->created_at);
        $this->assertMatchesRegularExpression('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $auditLog->ip_address);
    }

    public function test_multiple_failed_attempts_create_separate_audit_logs(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        // Make 3 failed login attempts
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword',
            ]);
        }

        $auditLogs = AuditLog::where('action', 'user.login.failed')
            ->where('actor_id', $user->id)
            ->get();

        $this->assertCount(3, $auditLogs);
        
        // Verify each log has the correct attempt number
        $this->assertStringContainsString('attempt #1', $auditLogs[0]->description);
        $this->assertStringContainsString('attempt #2', $auditLogs[1]->description);
        $this->assertStringContainsString('attempt #3', $auditLogs[2]->description);
    }
}
