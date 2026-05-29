# Middleware Stack Documentation

## Overview

The Hospital Health Management System (HHMS) implements a comprehensive middleware stack to ensure secure, multi-tenant operation with proper authentication, authorization, and data isolation.

## Middleware Execution Order

The middleware layers are applied in the following order for all protected API routes:

1. **TenantResolutionMiddleware** (`tenant.resolve`)
2. **JWTAuthMiddleware** (`jwt.auth`)
3. **RBACMiddleware** (`rbac`)
4. **TenantScopeMiddleware** (`tenant.scope`)

## Middleware Details

### 1. TenantResolutionMiddleware

**Purpose:** Resolves the tenant context from the incoming request.

**Resolution Strategy:**
- First checks for `X-Tenant-ID` header (for mobile clients)
- Falls back to subdomain extraction (e.g., `citygeneral.hhms.app`)

**Validation:**
- Verifies tenant exists in the central database
- Ensures tenant status is `ACTIVE`
- Rejects with HTTP 404 if tenant not found or not active

**Actions:**
- Initializes tenancy using `stancl/tenancy`
- Switches database connection to tenant-specific database

**Location:** `app/Http/Middleware/TenantResolutionMiddleware.php`

### 2. JWTAuthMiddleware

**Purpose:** Validates JWT tokens and authenticates users.

**Validation Steps:**
1. Parses JWT token from Authorization header
2. Validates JWT signature using configured secret
3. Checks token expiration
4. Authenticates user from token payload
5. Validates `token_version` claim against user record
6. Checks if user account is active
7. Checks if user account is locked

**Token Invalidation:**
- Incrementing `token_version` on user record invalidates all previously issued tokens
- No token blacklist required

**Error Responses:**
- HTTP 401 for expired, invalid, or missing tokens
- HTTP 401 for inactive or locked accounts
- HTTP 401 for token version mismatch

**Location:** `app/Http/Middleware/JWTAuthMiddleware.php`

### 3. RBACMiddleware

**Purpose:** Enforces role-based access control (RBAC) for routes.

**Supported Roles:**
- `super_admin` - Full system access
- `hospital_admin` - Hospital management
- `doctor` - Clinical operations
- `front_desk_officer` - Patient registration
- `accountant` - Financial operations
- `pharmacist` - Pharmacy operations
- `lab_technician` - Laboratory operations
- `department_staff` - General department access

**Permission Checking:**
- Maps user roles to allowed route patterns
- Supports wildcard patterns (e.g., `api/v1/patients*`)
- Super admin has access to all routes

**Audit Logging:**
- Logs all unauthorized access attempts to `audit_logs` table
- Includes user ID, role, IP address, and request details

**Error Response:**
- HTTP 403 for insufficient permissions

**Location:** `app/Http/Middleware/RBACMiddleware.php`

### 4. TenantScopeMiddleware

**Purpose:** Applies Eloquent global tenant scope as a secondary safeguard.

**Functionality:**
- Verifies tenant context is initialized
- Ensures all database queries are scoped to the current tenant
- Acts as a safety net if application code forgets to scope queries

**Note:** The primary tenant scoping is handled by `stancl/tenancy`'s `DatabaseTenancyBootstrapper`. This middleware provides an additional verification layer.

**Error Response:**
- HTTP 500 if tenant context not initialized

**Location:** `app/Http/Middleware/TenantScopeMiddleware.php`

## Usage in Routes

### Authentication Routes (Tenant Resolution Only)

```php
Route::middleware(['tenant.resolve'])->prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
```

### Protected Routes (Full Middleware Stack)

```php
Route::middleware(['tenant.resolve', 'jwt.auth', 'rbac', 'tenant.scope'])->group(function () {
    Route::get('/user', [UserController::class, 'show']);
    Route::get('/department/queue', [DepartmentController::class, 'queue']);
    Route::apiResource('patients', PatientController::class);
});
```

### Role-Specific Routes

```php
Route::middleware(['tenant.resolve', 'jwt.auth', 'rbac:doctor', 'tenant.scope'])->group(function () {
    Route::post('/appointments', [AppointmentController::class, 'store']);
});
```

## Middleware Registration

Middleware aliases are registered in `bootstrap/app.php`:

```php
$middleware->alias([
    'tenant.resolve' => \App\Http\Middleware\TenantResolutionMiddleware::class,
    'jwt.auth' => \App\Http\Middleware\JWTAuthMiddleware::class,
    'rbac' => \App\Http\Middleware\RBACMiddleware::class,
    'tenant.scope' => \App\Http\Middleware\TenantScopeMiddleware::class,
]);
```

## Security Considerations

1. **Tenant Isolation:** All middleware layers work together to ensure complete tenant data isolation
2. **Token Security:** JWT tokens include tenant_id, role, and token_version claims
3. **Audit Trail:** All unauthorized access attempts are logged for security monitoring
4. **Defense in Depth:** Multiple layers of validation ensure security even if one layer fails

## Testing

To test the middleware stack:

1. **Tenant Resolution:**
   - Send request with `X-Tenant-ID` header
   - Send request to subdomain (e.g., `citygeneral.hhms.app`)
   - Verify HTTP 404 for invalid or inactive tenants

2. **JWT Authentication:**
   - Send request without token → HTTP 401
   - Send request with expired token → HTTP 401
   - Send request with invalid signature → HTTP 401
   - Increment user's token_version → HTTP 401 for old tokens

3. **RBAC:**
   - Access route without permission → HTTP 403
   - Verify audit log entry created
   - Super admin can access all routes

4. **Tenant Scope:**
   - Verify queries only return data for current tenant
   - Attempt cross-tenant access → No data returned

## Requirements Validated

This middleware stack validates the following requirements:

- **1.2:** Tenant context resolution before processing requests
- **1.3:** Data queries restricted to authenticated user's tenant
- **1.4:** Cross-tenant access rejected with HTTP 403 and audit log
- **10.1:** Token-based authentication (JWT)
- **10.2:** Session token expiration enforcement
- **10.3:** Role-based access control
- **10.4:** API responses restricted by role and tenant
- **10.5:** Unauthorized access returns HTTP 403 with audit log
