# Design Document: Security Hardening

## Overview

This document describes the technical design for hardening the Hospital Health Management System (HHMS) across three layers: the Laravel backend API, the Nginx reverse proxy, and the Flutter mobile application. The work addresses eight requirements spanning critical vulnerabilities (cross-tenant data leakage, JWT/header spoofing), important gaps (CORS policy, hardcoded secrets, RBAC granularity), and defence-in-depth improvements (HSTS, CSP, certificate pinning).

The design is intentionally incremental — each requirement maps to a self-contained change that can be reviewed, tested, and deployed independently without breaking existing functionality.

---

## Architecture

### Current Security Layers

```
Mobile App ──► Nginx (TLS termination) ──► PHP-FPM (Laravel)
                                               │
                    ┌──────────────────────────┤
                    │  Middleware Stack (api.php)
                    │  1. MaintenanceModeMiddleware
                    │  2. TenantResolutionMiddleware  ← resolves tenant from header
                    │  3. JWTAuthMiddleware           ← validates JWT, binds user
                    │  4. RBACMiddleware              ← role→URL pattern matching
                    │  5. TenantScopeMiddleware       ← applies Eloquent global scope
                    └──────────────────────────┘
```

### Post-Hardening Security Layers

```
Mobile App ──► Nginx (TLS 1.2/1.3, HSTS, CSP, X-Frame-Options) ──► PHP-FPM
  │                                                                      │
  │ Certificate Pinning (Dio)                  Middleware Stack (api.php)
  │ Runtime PUSHER_APP_KEY                     1. MaintenanceModeMiddleware
  │ Runtime PINNED_CERT_SHA256_*               2. TenantResolutionMiddleware
                                               3. JWTAuthMiddleware
                                               4. TenantResolutionMiddleware (JWT cross-check phase)
                                               5. RBACMiddleware (permission-based)
                                               6. TenantScopeMiddleware
                                               │
                                        PatientController
                                        (per-resource tenant ownership guard)
```

The key architectural insight is that `TenantResolutionMiddleware` runs **before** `JWTAuthMiddleware` to resolve the tenant (needed for the user lookup), but the JWT cross-check must happen **after** authentication. This is handled by splitting the middleware's logic into two phases gated on whether a user is bound to the request.

---

## Components and Interfaces

### 1. PatientController — Tenant Ownership Guard

**File:** `backend/app/Http/Controllers/Api/V1/PatientController.php`

A private helper method `findPatientForTenant(string $id): ?Patient` is introduced. It fetches the patient and immediately checks `tenant_id` against `app('current_tenant_id')`. If the patient does not exist **or** belongs to a different tenant, it returns `null` — the caller returns a 404 response in both cases, preventing existence disclosure.

```php
private function findPatientForTenant(string $id): ?Patient
{
    $patient = Patient::find($id);
    if (!$patient || $patient->tenant_id !== app('current_tenant_id')) {
        return null;
    }
    return $patient;
}
```

All seven affected methods (`show`, `journey`, `attachDocument`, `getDocumentUrl`, `getPhotoUrl`, `anonymise`, `newVisit`) replace their current `Patient::find($id)` call with `$this->findPatientForTenant($id)` and return a uniform 404 on `null`.

### 2. TenantResolutionMiddleware — JWT Cross-Check

**File:** `backend/app/Http/Middleware/TenantResolutionMiddleware.php`

After the tenant is resolved from the header/body/session (existing logic), the middleware checks whether a user has already been bound to the request (i.e., `JWTAuthMiddleware` has run). If a user is present, it compares `app('current_tenant_id')` against the JWT `tenant_id` claim extracted from the token payload.

```
handle():
  1. resolveTenant() — existing logic, unchanged
  2. bind tenant into app container — existing logic, unchanged
  3. IF $request->user() is not null (JWT already validated):
       a. Extract tenant_id claim from JWT payload
       b. IF claim is missing → 401 MISSING_TENANT_CLAIM
       c. IF claim != app('current_tenant_id') → 403 TENANT_MISMATCH
  4. $next($request)
```

Because `TenantResolutionMiddleware` runs before `JWTAuthMiddleware` in the stack, `$request->user()` will be `null` on the first pass through the middleware. The cross-check therefore only fires on routes where the middleware is applied **after** JWT auth — which is achieved by registering a second alias `tenant.verify` that wraps only the cross-check logic, applied after `jwt.auth` in the protected route group.

**Design decision:** Rather than reordering the middleware stack (which would break tenant resolution for the JWT lookup), a dedicated `TenantVerificationMiddleware` is extracted to hold only the cross-check. This keeps `TenantResolutionMiddleware` unchanged for unauthenticated routes.

```
New middleware: TenantVerificationMiddleware
  - Reads app('current_tenant_id') (already set by TenantResolutionMiddleware)
  - Reads JWT tenant_id claim from the authenticated user's token
  - Returns 401 if claim missing, 403 if mismatch
```

Updated middleware stack for protected routes:
```
maintenance.mode → tenant.resolve → jwt.auth → tenant.verify → rbac → tenant.scope
```

### 3. Nginx — HTTPS, HSTS, CSP

**File:** `docker/nginx/default.conf`

The existing config already has HTTP→HTTPS redirect, HSTS, TLS 1.2/1.3, and `X-Content-Type-Options`/`X-Frame-Options`. The required additions are:

- Strengthen the cipher suite exclusion list to explicitly block `NULL`, `EXPORT`, `DES`, `RC4`, `MD5` ciphers.
- Add a `Content-Security-Policy` header for web panel routes.
- Add a `report-uri` directive to the CSP header.

The CSP is scoped to the web panel (non-API routes) because the mobile API does not serve HTML. A separate `location` block or `map` directive distinguishes API paths from web panel paths.

**CSP directive design:**
```
default-src 'self';
script-src 'self' 'unsafe-inline';
style-src 'self' 'unsafe-inline';
font-src 'self' data:;
img-src 'self' data: blob:;
connect-src 'self' wss:;
frame-ancestors 'none';
report-uri /api/v1/csp-report;
```

`'unsafe-inline'` is required for the Vue/Inertia SSR setup (inline scripts injected by Vite). A nonce-based approach is the ideal long-term solution but is out of scope for this hardening pass.

### 4. CORS — Strict Origin Whitelist

**File:** `backend/config/cors.php` (new/updated), `backend/.env.example`

Laravel's built-in `HandleCors` middleware (via `fruitcake/laravel-cors` or the built-in `Illuminate\Http\Middleware\HandleCors`) reads from `config/cors.php`. The config is updated to:

- Read `CORS_ALLOWED_ORIGINS` from the environment and split on commas.
- Default to an empty array (deny-all) when the variable is absent.
- Set `supports_credentials` to `false` for API routes (JWT auth, no cookies).
- Restrict `allowed_methods` to the methods actually used.

```php
// config/cors.php
'allowed_origins' => array_filter(
    explode(',', env('CORS_ALLOWED_ORIGINS', ''))
),
'supports_credentials' => false,
'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
'allowed_headers' => ['Content-Type', 'Authorization', 'X-Tenant-ID', 'X-Requested-With'],
```

`HandleCors` is already part of Laravel's global middleware stack; no registration change is needed.

### 5. WebSocketService — Remove Hardcoded Key

**File:** `mobile/lib/services/websocket_service.dart`

The hardcoded `apiKey: 'app-key'` is replaced with a compile-time constant read via `String.fromEnvironment`:

```dart
static const _pusherAppKey = String.fromEnvironment(
  'PUSHER_APP_KEY',
  defaultValue: '',
);
```

In `connect()`, if `_pusherAppKey.isEmpty`, the method logs a warning and returns early without initialising Pusher. This satisfies the graceful-degradation requirement.

Build invocation:
```
flutter build apk --dart-define=PUSHER_APP_KEY=hhms-app-key
```

### 6. RBAC — Permission-Based System

**Files:**
- `backend/database/migrations/XXXX_create_permissions_table.php` (new)
- `backend/database/migrations/XXXX_create_role_permissions_table.php` (new)
- `backend/database/seeders/PermissionSeeder.php` (new)
- `backend/app/Models/Permission.php` (new)
- `backend/app/Http/Middleware/RBACMiddleware.php` (updated)
- `backend/routes/api.php` (updated — add `permission:` middleware parameter)

The current `RBACMiddleware` matches role strings against URL patterns. The new system:
1. Stores named permissions in a `permissions` table.
2. Stores role→permission assignments in a `role_permissions` table.
3. Caches the permission set per user in Redis (same TTL as the JWT).
4. `RBACMiddleware` accepts a `permission:` parameter (e.g., `rbac:patients.view`) and checks whether the authenticated user's role holds that permission.

Route usage:
```php
Route::get('/{id}', [PatientController::class, 'show'])
    ->middleware('rbac:patients.view');
```

### 7. Certificate Pinning — Flutter/Dio

**File:** `mobile/lib/services/api_client.dart` (or wherever Dio is configured)

A custom `HttpClient` is created with a `badCertificateCallback` that computes the SHA-256 fingerprint of the presented certificate's public key and compares it against the pinned values supplied via `--dart-define`.

```dart
static const _pinnedSha256_1 = String.fromEnvironment('PINNED_CERT_SHA256_1', defaultValue: '');
static const _pinnedSha256_2 = String.fromEnvironment('PINNED_CERT_SHA256_2', defaultValue: '');
static const _pinningEnabled = String.fromEnvironment(
  'CERTIFICATE_PINNING_ENABLED', defaultValue: 'true') != 'false';
```

When `_pinningEnabled` is `false`, the callback always returns `true` (accept all). When enabled, the callback rejects any certificate whose computed fingerprint is not in `{_pinnedSha256_1, _pinnedSha256_2}`.

---

## Data Models

### permissions table

| Column       | Type         | Notes                                      |
|--------------|--------------|--------------------------------------------|
| id           | BIGINT PK AI |                                            |
| name         | VARCHAR(100) | Unique. e.g. `patients.view`               |
| display_name | VARCHAR(150) | Human-readable label                       |
| group        | VARCHAR(50)  | Grouping for UI (e.g. `patients`, `invoices`) |
| created_at   | TIMESTAMP    |                                            |
| updated_at   | TIMESTAMP    |                                            |

### role_permissions table

| Column     | Type         | Notes                                      |
|------------|--------------|--------------------------------------------|
| id         | BIGINT PK AI |                                            |
| role       | VARCHAR(50)  | Matches `users.role` enum values           |
| permission | VARCHAR(100) | FK → permissions.name                      |
| created_at | TIMESTAMP    |                                            |

Index: `UNIQUE(role, permission)`

### Seeded Permissions

| Permission Name         | Group      | Roles with default access                                                                 |
|-------------------------|------------|-------------------------------------------------------------------------------------------|
| patients.view           | patients   | hospital_admin, doctor, front_desk_officer, accountant, pharmacist, lab_technician, nurse, triage, ward_staff, ward_nurse, department_staff |
| patients.create         | patients   | hospital_admin, front_desk_officer                                                        |
| patients.new_visit      | patients   | hospital_admin, front_desk_officer                                                        |
| patients.anonymise      | patients   | hospital_admin                                                                            |
| documents.upload        | documents  | hospital_admin, front_desk_officer, doctor, nurse                                         |
| documents.view          | documents  | hospital_admin, doctor, front_desk_officer, nurse, accountant                             |
| invoices.create         | invoices   | hospital_admin, accountant                                                                |
| invoices.settle         | invoices   | hospital_admin, accountant                                                                |
| reports.view            | reports    | hospital_admin, accountant                                                                |
| staff.manage            | staff      | hospital_admin                                                                            |
| departments.manage      | departments| hospital_admin                                                                            |
| workflows.manage        | workflows  | hospital_admin                                                                            |
| audit_logs.view         | audit      | hospital_admin                                                                            |
| department.queue.view   | department | doctor, front_desk_officer, pharmacist, lab_technician, nurse, triage, ward_staff, ward_nurse, department_staff |
| patient_records.manage  | records    | doctor, pharmacist, lab_technician, nurse, triage, ward_staff, ward_nurse, department_staff |
| ward.manage             | ward       | ward_staff, ward_nurse, nurse, department_staff                                           |
| accounts.queue.view     | accounts   | accountant                                                                                |
| appointments.manage     | appointments| doctor, nurse, triage, front_desk_officer                                                |
| my_department.manage    | department | (HOD flag — checked separately)                                                           |

### Permission Model

```php
class Permission extends Model
{
    protected $fillable = ['name', 'display_name', 'group'];
    public $incrementing = true;
}
```

User permission resolution (cached):
```php
// In RBACMiddleware or a PermissionService
$permissions = Cache::remember(
    "tenant:{$tenantId}:role_perms:{$role}",
    3600,
    fn() => RolePermission::where('role', $role)->pluck('permission')->toArray()
);
```

---

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Cross-tenant patient access returns 404

*For any* patient record belonging to tenant A, when a request is authenticated as tenant B (where A ≠ B), every PatientController endpoint that accepts a patient ID SHALL return HTTP 404 with error code `NOT_FOUND`, regardless of which endpoint is called or what the patient ID is.

**Validates: Requirements 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8**

### Property 2: JWT tenant claim mismatch is rejected

*For any* request carrying a valid JWT whose `tenant_id` claim differs from the `X-Tenant-ID` header value, the `TenantVerificationMiddleware` SHALL return HTTP 403 with error code `TENANT_MISMATCH`.

**Validates: Requirements 2.1, 2.2**

### Property 3: CORS rejects unlisted origins

*For any* HTTP request whose `Origin` header value is not present in the `CORS_ALLOWED_ORIGINS` list, the API SHALL NOT include `Access-Control-Allow-Origin` in the response, and a preflight OPTIONS request SHALL receive a non-2xx response.

**Validates: Requirements 4.1, 4.5**

### Property 4: Permission gate is necessary and sufficient

*For any* authenticated user with role R and any route requiring permission P, the `RBACMiddleware` SHALL grant access if and only if the `role_permissions` table contains a row `(R, P)`. No other condition (URL pattern, role name) affects the outcome.

**Validates: Requirements 7.2, 7.5**

### Property 5: Certificate pinning rejects unrecognised fingerprints

*For any* TLS certificate whose SHA-256 public key fingerprint is not in the set `{PINNED_CERT_SHA256_1, PINNED_CERT_SHA256_2}`, the Dio HTTP client SHALL reject the connection when `CERTIFICATE_PINNING_ENABLED` is `true`.

**Validates: Requirements 8.1, 8.2, 8.3**

---

## Error Handling

### PatientController — Tenant Mismatch
- Returns `404 NOT_FOUND` (not 403) to avoid disclosing that a cross-tenant record exists.
- The `findPatientForTenant` helper treats "not found" and "wrong tenant" identically.

### TenantVerificationMiddleware — JWT Claim Issues
- Missing `tenant_id` claim → `401 UNAUTHORIZED` with code `MISSING_TENANT_CLAIM`.
- Claim present but mismatched → `403 FORBIDDEN` with code `TENANT_MISMATCH`.
- Unauthenticated routes (no user bound) → middleware is not applied; no error.

### RBACMiddleware — Permission Denied
- Returns `403 FORBIDDEN` with code `FORBIDDEN`.
- Writes an `AuditLog` entry with `action = 'access.denied'`, `resource_type = 'permission'`, and the required permission name in `metadata`.
- Cache miss (Redis down) falls back to a direct DB query rather than failing open.

### WebSocketService — Missing Config
- Logs `[WebSocketService] PUSHER_APP_KEY not configured — WebSocket disabled.` at warning level.
- Returns from `connect()` without throwing; the rest of the app continues normally.
- FCM push notifications serve as the fallback real-time channel.

### Certificate Pinning — Connection Rejected
- Dio throws a `DioException` with type `DioExceptionType.badCertificate`.
- The calling layer catches this and surfaces a user-facing error: "Secure connection failed. Please update the app or contact support."
- The error is also logged to the local crash reporter.

---

## Testing Strategy

### Unit Tests (PHPUnit / Flutter test)

**Backend:**
- `PatientControllerTest`: verify `findPatientForTenant` returns null for cross-tenant IDs; verify all seven endpoints return 404 for cross-tenant patients.
- `TenantVerificationMiddlewareTest`: verify 403 on mismatch, 401 on missing claim, pass-through on match.
- `RBACMiddlewareTest`: verify grant when permission present, deny when absent, audit log written on deny.
- `PermissionSeederTest`: verify all expected permissions exist after seeding; verify each role has its default permission set.

**Flutter:**
- `WebSocketServiceTest`: verify `connect()` returns early when `PUSHER_APP_KEY` is empty; verify no exception thrown.
- `CertificatePinningTest`: verify `badCertificateCallback` returns false for non-matching fingerprint; returns true for matching fingerprint; returns true for any fingerprint when pinning disabled.

### Property-Based Tests

Property-based testing is applied using **PHPUnit + a PHP PBT library (e.g., `eris/eris`)** for backend properties and **`dart_test` with `glados`** for Flutter properties. Each property test runs a minimum of **100 iterations**.

**Property 1 — Cross-tenant patient access returns 404**
- Generator: random UUID patient IDs, random tenant UUID pairs where tenant A ≠ tenant B.
- For each generated pair: create a patient in tenant A's scope, authenticate as tenant B, call each of the seven endpoints, assert HTTP 404 with `NOT_FOUND`.
- Tag: `Feature: security-hardening, Property 1: cross-tenant patient access returns 404`

**Property 2 — JWT tenant claim mismatch is rejected**
- Generator: random tenant UUID pairs (jwt_tenant_id, header_tenant_id) where they differ.
- For each pair: forge a JWT with `tenant_id = jwt_tenant_id`, send with `X-Tenant-ID: header_tenant_id`, assert 403 `TENANT_MISMATCH`.
- Tag: `Feature: security-hardening, Property 2: JWT tenant claim mismatch is rejected`

**Property 3 — CORS rejects unlisted origins**
- Generator: random origin strings (URLs with random hostnames) that are not in the allowed list.
- For each: send OPTIONS preflight, assert no `Access-Control-Allow-Origin` header in response.
- Tag: `Feature: security-hardening, Property 3: CORS rejects unlisted origins`

**Property 4 — Permission gate is necessary and sufficient**
- Generator: random role strings from the known role set, random permission names from the known permission set, random boolean indicating whether the (role, permission) row exists.
- For each: configure the role_permissions table accordingly, make a request to a route requiring that permission, assert grant iff row exists.
- Tag: `Feature: security-hardening, Property 4: permission gate is necessary and sufficient`

**Property 5 — Certificate pinning rejects unrecognised fingerprints**
- Generator: random SHA-256 hex strings that are not equal to the two pinned values.
- For each: invoke the `badCertificateCallback` with a mock certificate whose fingerprint is the generated value, assert the callback returns false.
- Tag: `Feature: security-hardening, Property 5: certificate pinning rejects unrecognised fingerprints`

### Integration Tests

- Nginx HTTP→HTTPS redirect: `curl -I http://localhost` → verify `301` and `Location: https://`.
- Nginx HSTS header: `curl -I https://localhost` → verify `Strict-Transport-Security: max-age=31536000; includeSubDomains`.
- Nginx security headers: verify `X-Content-Type-Options`, `X-Frame-Options`, `Content-Security-Policy` present on HTTPS responses.
- CORS preflight from allowed origin: verify `204` with correct `Access-Control-*` headers.
- CORS preflight from disallowed origin: verify no `Access-Control-Allow-Origin` header.

### Smoke Tests

- Verify `ssl_protocols TLSv1.2 TLSv1.3` in `docker/nginx/default.conf`.
- Verify `ssl_ciphers` excludes `NULL`, `EXPORT`, `DES`, `RC4`, `MD5`.
- Verify `permissions` and `role_permissions` tables exist in migration files.
- Verify `HandleCors` middleware is registered in the global middleware stack.
- Verify no literal `'app-key'` string in `mobile/lib/services/websocket_service.dart`.
- Verify `--dart-define=PUSHER_APP_KEY` and `--dart-define=PINNED_CERT_SHA256_1` are present in CI build scripts.
