# Task 2.2 Implementation: Authentication Endpoints

## Overview

This document describes the implementation of the four authentication endpoints for the Hospital Health Management System (HHMS):

1. `POST /api/v1/auth/login` - User authentication
2. `POST /api/v1/auth/refresh` - Token refresh
3. `POST /api/v1/auth/logout` - User logout
4. `POST /api/v1/auth/password/reset` - Password reset initiation

## Implementation Details

### JWT Token Structure

All JWT tokens include the following claims:

- `sub` - User ID (subject)
- `tenant_id` - Tenant/Hospital ID
- `role` - User role (hospital_admin, doctor, front_desk_officer, etc.)
- `token_version` - Token version for invalidation
- `iat` - Issued at timestamp
- `exp` - Expiration timestamp

### Token TTLs

- **Access Token**: 60 minutes (configurable via `JWT_TTL` env variable)
- **Refresh Token**: 7 days (10,080 minutes, configurable via `JWT_REFRESH_TTL` env variable)

### Token Storage

- **Access tokens**: Stateless JWT, not stored server-side
- **Refresh tokens**: Stored in Redis with the following key pattern:
  ```
  refresh_token:{user_id}:{token_value}
  ```
  
  Value stored as JSON:
  ```json
  {
    "user_id": "uuid",
    "token_version": 0,
    "created_at": "2024-01-01T00:00:00Z"
  }
  ```

### Token Invalidation Strategy

The system uses a **token version** approach instead of a blocklist:

1. Each user has a `token_version` field (integer, default 0)
2. When tokens need to be invalidated (logout all devices, account suspension, etc.):
   - Increment the user's `token_version`
   - All previously issued tokens become invalid
3. JWT validation checks if the token's `token_version` claim matches the user's current version
4. No need to maintain a blocklist of invalidated tokens

## API Endpoints

### 1. Login Endpoint

**Endpoint**: `POST /api/v1/auth/login`

**Request Body**:
```json
{
  "email": "user@example.com",
  "password": "SecurePassword123!"
}
```

**Success Response** (200):
```json
{
  "success": true,
  "data": {
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refresh_token": "a1b2c3d4e5f6...",
    "token_type": "bearer",
    "expires_in": 3600,
    "user": {
      "id": "uuid",
      "name": "John Doe",
      "email": "user@example.com",
      "role": "doctor",
      "department_id": "uuid"
    }
  },
  "message": "Login successful"
}
```

**Error Responses**:

- **401 Unauthorized** - Invalid credentials
  ```json
  {
    "success": false,
    "error": {
      "code": "INVALID_CREDENTIALS",
      "message": "Invalid email or password."
    }
  }
  ```

- **403 Forbidden** - Account locked or inactive
  ```json
  {
    "success": false,
    "error": {
      "code": "ACCOUNT_LOCKED",
      "message": "Account is locked due to multiple failed login attempts."
    }
  }
  ```

**Security Features**:
- Failed login attempts are tracked
- Account locks after 5 consecutive failed attempts within 10 minutes
- Locked accounts remain locked for 10 minutes
- All authentication events are logged to audit log
- Password is verified using bcrypt

### 2. Refresh Token Endpoint

**Endpoint**: `POST /api/v1/auth/refresh`

**Request Body**:
```json
{
  "refresh_token": "a1b2c3d4e5f6..."
}
```

**Success Response** (200):
```json
{
  "success": true,
  "data": {
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "token_type": "bearer",
    "expires_in": 3600
  },
  "message": "Token refreshed successfully"
}
```

**Error Responses**:

- **401 Unauthorized** - Invalid or expired refresh token
  ```json
  {
    "success": false,
    "error": {
      "code": "INVALID_REFRESH_TOKEN",
      "message": "Invalid or expired refresh token."
    }
  }
  ```

- **401 Unauthorized** - Token invalidated (version mismatch)
  ```json
  {
    "success": false,
    "error": {
      "code": "TOKEN_INVALIDATED",
      "message": "Token has been invalidated."
    }
  }
  ```

**Security Features**:
- Validates token version to detect invalidated tokens
- Checks if user account is still active
- Automatically removes invalidated tokens from Redis

### 3. Logout Endpoint

**Endpoint**: `POST /api/v1/auth/logout`

**Request Body**:
```json
{
  "refresh_token": "a1b2c3d4e5f6..."
}
```

**Success Response** (200):
```json
{
  "success": true,
  "message": "Logout successful"
}
```

**Security Features**:
- Removes refresh token from Redis
- Logs logout event to audit log
- Gracefully handles expired or invalid tokens

### 4. Password Reset Endpoint

**Endpoint**: `POST /api/v1/auth/password/reset`

**Request Body**:
```json
{
  "email": "user@example.com"
}
```

**Success Response** (200):
```json
{
  "success": true,
  "message": "If the email exists in our system, a password reset link has been sent."
}
```

**Security Features**:
- Always returns success to prevent email enumeration
- Logs password reset requests to audit log
- TODO: Dispatch password reset email job (to be implemented)

## Audit Logging

All authentication events are logged to the `audit_logs` table with the following information:

- `actor_id` - User ID (null for failed attempts on non-existent users)
- `actor_role` - User role
- `action` - Event type (e.g., `user.login.success`, `user.login.failed`, `user.login.locked`, `user.logout`, `user.password_reset.requested`)
- `resource_type` - Always "user"
- `resource_id` - User ID
- `description` - Human-readable description
- `ip_address` - Client IP address
- `created_at` - Timestamp

## Account Lockout Logic

The system implements automatic account lockout to prevent brute-force attacks:

1. Each failed login attempt increments `failed_login_attempts` counter
2. After 5 failed attempts:
   - `locked_until` is set to 10 minutes from now
   - Audit log entry created with action `user.login.locked`
   - TODO: Notification dispatched to Hospital Admin
3. Successful login resets the counter to 0
4. Locked accounts cannot login even with correct credentials until lock expires

## Files Created

### Controllers
- `backend/app/Http/Controllers/Controller.php` - Base controller
- `backend/app/Http/Controllers/Api/V1/AuthController.php` - Authentication controller

### Form Requests
- `backend/app/Http/Requests/Auth/LoginRequest.php` - Login validation
- `backend/app/Http/Requests/Auth/RefreshTokenRequest.php` - Refresh token validation
- `backend/app/Http/Requests/Auth/PasswordResetRequest.php` - Password reset validation

### Routes
- Updated `backend/routes/api.php` to use AuthController

### Tests
- `backend/tests/TestCase.php` - Base test case
- `backend/tests/CreatesApplication.php` - Application creation trait
- `backend/tests/Feature/Auth/AuthenticationTest.php` - Authentication feature tests
- `backend/database/factories/UserFactory.php` - User factory for testing
- `backend/phpunit.xml` - PHPUnit configuration

## Testing

The implementation includes comprehensive feature tests covering:

1. ✅ Successful login with valid credentials
2. ✅ Failed login with invalid credentials
3. ✅ Failed login with inactive account
4. ✅ Token refresh with valid refresh token
5. ✅ Token refresh with invalid refresh token
6. ✅ Successful logout
7. ✅ Password reset request
8. ✅ Password reset with non-existent email (returns success)
9. ✅ Login validation errors
10. ✅ Account lockout after 5 failed attempts

### Running Tests

```bash
cd backend
php artisan test --filter=AuthenticationTest
```

Or run all tests:
```bash
php artisan test
```

## Configuration

### Environment Variables

Add the following to your `.env` file:

```env
# JWT Configuration
JWT_SECRET=your-secret-key-here
JWT_TTL=60                    # Access token TTL in minutes (60 min = 1 hour)
JWT_REFRESH_TTL=10080         # Refresh token TTL in minutes (10080 min = 7 days)
JWT_ALGO=HS256
JWT_BLACKLIST_ENABLED=false   # We use token_version instead

# Redis Configuration (for refresh tokens)
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Generate JWT Secret

```bash
php artisan jwt:secret
```

## Dependencies

The implementation uses the following packages:

- `php-open-source-saver/jwt-auth` (^2.2) - JWT authentication
- `predis/predis` or `phpredis` extension - Redis client for refresh token storage
- Laravel 11 framework

## Security Considerations

1. **Password Hashing**: Uses bcrypt with configurable rounds
2. **Token Invalidation**: Token version approach prevents need for blocklist
3. **Account Lockout**: Automatic lockout after 5 failed attempts
4. **Audit Logging**: All authentication events are logged
5. **Email Enumeration Prevention**: Password reset always returns success
6. **Secure Token Storage**: Refresh tokens stored in Redis with TTL
7. **IP Address Logging**: Client IP logged for all auth events

## Future Enhancements

1. Implement password reset email dispatch job
2. Implement Hospital Admin notification on account lockout
3. Add rate limiting on authentication endpoints
4. Add two-factor authentication (2FA) support
5. Add device tracking and management
6. Add "remember me" functionality with longer-lived refresh tokens

## Requirements Validated

This implementation validates the following requirements:

- **Requirement 10.1**: JWT authentication for all users
- **Requirement 10.2**: Token expiry and re-authentication
- **Requirement 10.7**: Account lockout after 5 failed attempts

## Notes

- The middleware stack (TenantResolutionMiddleware, JWTAuthMiddleware, RBACMiddleware, TenantScopeMiddleware) is already implemented in Task 2.1
- The User model already includes the `getJWTCustomClaims()` method that adds `role`, `token_version`, and `tenant_id` to the JWT payload
- The AuditLog model is assumed to exist from the database schema implementation
