<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\AccountLockedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountLockoutNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_hospital_admins_are_notified_when_account_is_locked(): void
    {
        // Create Hospital Admins
        $hospitalAdmin1 = User::factory()->create([
            'role' => 'hospital_admin',
            'is_active' => true,
        ]);

        $hospitalAdmin2 = User::factory()->create([
            'role' => 'hospital_admin',
            'is_active' => true,
        ]);

        // Create a regular user who will be locked
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'role' => 'doctor',
        ]);

        // Make 5 failed login attempts to trigger lockout
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword',
            ]);
        }

        // Assert notification was sent to all Hospital Admins
        Notification::assertSentTo(
            [$hospitalAdmin1, $hospitalAdmin2],
            AccountLockedNotification::class,
            function ($notification, $channels) use ($user) {
                return $notification->lockedUser->id === $user->id;
            }
        );
    }

    public function test_inactive_hospital_admins_do_not_receive_lockout_notification(): void
    {
        // Create active Hospital Admin
        $activeAdmin = User::factory()->create([
            'role' => 'hospital_admin',
            'is_active' => true,
        ]);

        // Create inactive Hospital Admin
        $inactiveAdmin = User::factory()->create([
            'role' => 'hospital_admin',
            'is_active' => false,
        ]);

        // Create a regular user who will be locked
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'role' => 'doctor',
        ]);

        // Make 5 failed login attempts to trigger lockout
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword',
            ]);
        }

        // Assert notification was sent only to active admin
        Notification::assertSentTo($activeAdmin, AccountLockedNotification::class);
        Notification::assertNotSentTo($inactiveAdmin, AccountLockedNotification::class);
    }

    public function test_notification_is_not_sent_before_fifth_failed_attempt(): void
    {
        // Create Hospital Admin
        $hospitalAdmin = User::factory()->create([
            'role' => 'hospital_admin',
            'is_active' => true,
        ]);

        // Create a regular user
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        // Make 4 failed login attempts (not enough to trigger lockout)
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword',
            ]);
        }

        // Assert no notification was sent
        Notification::assertNothingSent();
    }

    public function test_notification_contains_correct_user_details(): void
    {
        // Create Hospital Admin
        $hospitalAdmin = User::factory()->create([
            'role' => 'hospital_admin',
            'is_active' => true,
        ]);

        // Create a regular user who will be locked
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'name' => 'Test User',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'role' => 'doctor',
        ]);

        // Make 5 failed login attempts to trigger lockout
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword',
            ]);
        }

        // Assert notification contains correct details
        Notification::assertSentTo(
            $hospitalAdmin,
            AccountLockedNotification::class,
            function ($notification) use ($user) {
                return $notification->lockedUser->id === $user->id
                    && $notification->lockedUser->name === 'Test User'
                    && $notification->lockedUser->email === 'test@example.com'
                    && $notification->lockedUser->role === 'doctor'
                    && !empty($notification->ipAddress);
            }
        );
    }

    public function test_notification_is_queued(): void
    {
        // Create Hospital Admin
        $hospitalAdmin = User::factory()->create([
            'role' => 'hospital_admin',
            'is_active' => true,
        ]);

        // Create a regular user who will be locked
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

        // Assert notification was queued (ShouldQueue interface)
        Notification::assertSentTo(
            $hospitalAdmin,
            AccountLockedNotification::class
        );
    }
}
