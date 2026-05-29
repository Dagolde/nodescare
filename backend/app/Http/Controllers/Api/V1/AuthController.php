<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\PasswordResetRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\AccountLockedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redis;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    /**
     * Authenticate user and return JWT tokens.
     *
     * POST /api/v1/auth/login
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');
        $user = User::where('email', $credentials['email'])->first();

        // Check if user exists
        if (!$user) {
            $this->logAuthEvent('user.login.failed', null, $request->ip(), 'User not found');
            
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_CREDENTIALS',
                    'message' => 'Invalid email or password.',
                ],
            ], 401);
        }

        // Check if account is locked
        if ($user->isLocked()) {
            $this->logAuthEvent('user.login.failed', $user->id, $request->ip(), 'Account locked');
            
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ACCOUNT_LOCKED',
                    'message' => 'Account is locked due to multiple failed login attempts.',
                ],
            ], 403);
        }

        // Check if account is active
        if (!$user->is_active) {
            $this->logAuthEvent('user.login.failed', $user->id, $request->ip(), 'Account inactive');
            
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ACCOUNT_INACTIVE',
                    'message' => 'Account is inactive.',
                ],
            ], 403);
        }

        // Verify password
        if (!Hash::check($credentials['password'], $user->password)) {
            $this->handleFailedLogin($user, $request->ip());
            
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_CREDENTIALS',
                    'message' => 'Invalid email or password.',
                ],
            ], 401);
        }

        // Reset failed login attempts on successful login
        $user->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        // Generate JWT tokens
        $token = JWTAuth::fromUser($user);
        $refreshToken = $this->generateRefreshToken($user);

        // Store refresh token in Redis with 7-day TTL
        $refreshTtl = config('jwt.refresh_ttl', 10080); // 7 days in minutes
        Redis::setex(
            "refresh_token:{$user->id}:{$refreshToken}",
            $refreshTtl * 60,
            json_encode([
                'user_id' => $user->id,
                'token_version' => $user->token_version,
                'created_at' => now()->toIso8601String(),
            ])
        );

        $this->logAuthEvent('user.login.success', $user->id, $request->ip(), 'Login successful');

        return response()->json([
            'success' => true,
            'data' => [
                'access_token' => $token,
                'refresh_token' => $refreshToken,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl', 60) * 60, // Convert minutes to seconds
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'department_id' => $user->department_id,
                ],
            ],
            'message' => 'Login successful',
        ]);
    }

    /**
     * Refresh access token using refresh token.
     *
     * POST /api/v1/auth/refresh
     *
     * @param RefreshTokenRequest $request
     * @return JsonResponse
     */
    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $refreshToken = $request->input('refresh_token');

        // Find the refresh token in Redis
        $keys = Redis::keys("refresh_token:*:{$refreshToken}");
        
        if (empty($keys)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_REFRESH_TOKEN',
                    'message' => 'Invalid or expired refresh token.',
                ],
            ], 401);
        }

        $tokenData = json_decode(Redis::get($keys[0]), true);
        $user = User::find($tokenData['user_id']);

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'USER_NOT_FOUND',
                    'message' => 'User not found.',
                ],
            ], 404);
        }

        // Verify token version matches (invalidates tokens if user was suspended/logged out)
        if ($tokenData['token_version'] !== $user->token_version) {
            Redis::del($keys[0]);
            
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TOKEN_INVALIDATED',
                    'message' => 'Token has been invalidated.',
                ],
            ], 401);
        }

        // Check if account is still active
        if (!$user->is_active) {
            Redis::del($keys[0]);
            
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ACCOUNT_INACTIVE',
                    'message' => 'Account is inactive.',
                ],
            ], 403);
        }

        // Generate new access token
        $newAccessToken = JWTAuth::fromUser($user);

        return response()->json([
            'success' => true,
            'data' => [
                'access_token' => $newAccessToken,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl', 60) * 60, // Convert minutes to seconds
            ],
            'message' => 'Token refreshed successfully',
        ]);
    }

    /**
     * Logout user and invalidate refresh token.
     *
     * POST /api/v1/auth/logout
     *
     * @param RefreshTokenRequest $request
     * @return JsonResponse
     */
    public function logout(RefreshTokenRequest $request): JsonResponse
    {
        $refreshToken = $request->input('refresh_token');

        // Find and delete the refresh token from Redis
        $keys = Redis::keys("refresh_token:*:{$refreshToken}");
        
        if (!empty($keys)) {
            Redis::del($keys[0]);
        }

        // Log the logout event if user is authenticated
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if ($user) {
                $this->logAuthEvent('user.logout', $user->id, $request->ip(), 'Logout successful');
            }
        } catch (\Exception $e) {
            // Token might be expired or invalid, but we still want to delete the refresh token
        }

        return response()->json([
            'success' => true,
            'message' => 'Logout successful',
        ]);
    }

    /**
     * Initiate password reset process.
     *
     * POST /api/v1/auth/password/reset
     *
     * @param PasswordResetRequest $request
     * @return JsonResponse
     */
    public function passwordReset(PasswordResetRequest $request): JsonResponse
    {
        $user = User::where('email', $request->input('email'))->first();

        // Always return success to prevent email enumeration
        // In production, this would dispatch a password reset email job
        if ($user) {
            $this->logAuthEvent('user.password_reset.requested', $user->id, $request->ip(), 'Password reset requested');
            
            // TODO: Dispatch password reset email job
            // dispatch(new SendPasswordResetEmail($user));
        }

        return response()->json([
            'success' => true,
            'message' => 'If the email exists in our system, a password reset link has been sent.',
        ]);
    }

    /**
     * Generate a unique refresh token.
     *
     * @param User $user
     * @return string
     */
    private function generateRefreshToken(User $user): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Handle failed login attempt.
     *
     * @param User $user
     * @param string $ipAddress
     * @return void
     */
    private function handleFailedLogin(User $user, string $ipAddress): void
    {
        $attempts = $user->failed_login_attempts + 1;
        
        // Lock account after 5 failed attempts
        if ($attempts >= 5) {
            $user->update([
                'failed_login_attempts' => $attempts,
                'locked_until' => now()->addMinutes(10),
            ]);
            
            $this->logAuthEvent('user.login.locked', $user->id, $ipAddress, 'Account locked after 5 failed attempts');
            
            // Dispatch notification to Hospital Admins
            $this->notifyHospitalAdmins($user, $ipAddress);
        } else {
            $user->update([
                'failed_login_attempts' => $attempts,
            ]);
            
            $this->logAuthEvent('user.login.failed', $user->id, $ipAddress, "Failed login attempt #{$attempts}");
        }
    }

    /**
     * Notify all Hospital Admins about account lockout.
     *
     * @param User $lockedUser
     * @param string $ipAddress
     * @return void
     */
    private function notifyHospitalAdmins(User $lockedUser, string $ipAddress): void
    {
        try {
            // Get all Hospital Admins in the current tenant
            $hospitalAdmins = User::where('role', 'hospital_admin')
                ->where('is_active', true)
                ->get();

            // Send notification to all Hospital Admins
            Notification::send(
                $hospitalAdmins,
                new AccountLockedNotification($lockedUser, $ipAddress)
            );
        } catch (\Exception $e) {
            // Log error if notification fails, but don't block the lockout
            \Log::error('Failed to send account locked notification', [
                'locked_user_id' => $lockedUser->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log authentication event to audit log.
     *
     * @param string $action
     * @param string|null $userId
     * @param string $ipAddress
     * @param string $description
     * @return void
     */
    private function logAuthEvent(string $action, ?string $userId, string $ipAddress, string $description): void
    {
        try {
            AuditLog::create([
                'tenant_id'     => app('current_tenant_id'),
                'actor_id'      => $userId,
                'actor_role'    => $userId ? User::withoutGlobalScopes()->find($userId)?->role : null,
                'action'        => $action,
                'resource_type' => 'user',
                'resource_id'   => $userId,
                'description'   => $description,
                'ip_address'    => $ipAddress,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to create audit log entry', [
                'action' => $action,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
