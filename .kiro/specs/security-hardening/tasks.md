# Implementation Plan: Security Hardening

## Overview

Incremental hardening of the HHMS backend API, Nginx layer, and Flutter mobile app across eight requirements. Tasks are ordered by priority: critical tenant-isolation fixes first, then important gaps, then defence-in-depth improvements. Each task is self-contained and can be reviewed and deployed independently.

## Tasks

- [x] 1. Add `findPatientForTenant` helper and apply tenant ownership guard to PatientController
  - Add private `findPatientForTenant(string $id): ?Patient` method to `backend/app/Http/Controllers/Api/V1/PatientController.php` that calls `Patient::find($id)` and returns `null` if the patient is missing or if `$patient->tenant_id !== app('current_tenant_id')`
  - Replace the `Patient::find($id)` call in `show()` with `$this->findPatientForTenant($id)` and return a uniform `404 NOT_FOUND` response on `null`
  - Apply the same replacement in `journey()`, `attachDocument()`, `getDocumentUrl()`, `getPhotoUrl()`, `anonymise()`, and `newVisit()`
  - Ensure the 404 response body uses `['code' => 'NOT_FOUND', 'message' => 'Patient not found']` in all seven methods so cross-tenant existence is not disclosed
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8_

  - [ ]* 1.1 Write property test for cross-tenant patient access (Property 1)
    - **Property 1: Cross-tenant patient access returns 404**
    - Generate random UUID patient IDs and random tenant UUID pairs where tenant A ≠ tenant B; create a patient scoped to tenant A, authenticate as tenant B, call each of the seven endpoints, assert HTTP 404 with `NOT_FOUND`
    - Use PHPUnit with `eris/eris` (or equivalent PBT library); run minimum 100 iterations
    - **Validates: Requirements 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8**

  - [ ]* 1.2 Write unit tests for PatientController tenant guard
    - Test `findPatientForTenant` returns `null` for a patient belonging to a different tenant
    - Test `findPatientForTenant` returns `null` for a non-existent patient ID
    - Test `findPatientForTenant` returns the patient when `tenant_id` matches `app('current_tenant_id')`
    - Test each of the seven endpoints returns 404 when `findPatientForTenant` returns `null`
    - _Requirements: 1.1–1.8_

- [x] 2. Create `TenantVerificationMiddleware` for JWT tenant claim cross-check
  - Create `backend/app/Http/Middleware/TenantVerificationMiddleware.php`
  - In `handle()`: read `app('current_tenant_id')` (already set by `TenantResolutionMiddleware`); extract the `tenant_id` claim from the authenticated user's JWT payload via `JWTAuth::parseToken()->getPayload()->get('tenant_id')`
  - If the claim is absent, return `401 UNAUTHORIZED` with code `MISSING_TENANT_CLAIM`
  - If the claim is present but does not match `app('current_tenant_id')`, return `403 FORBIDDEN` with code `TENANT_MISMATCH`
  - If the claim matches, call `$next($request)`
  - Register the alias `tenant.verify` in `backend/bootstrap/app.php` pointing to `TenantVerificationMiddleware`
  - Insert `tenant.verify` into the protected route middleware stack in `backend/routes/api.php` immediately after `jwt.auth`: `['maintenance.mode', 'tenant.resolve', 'jwt.auth', 'tenant.verify', 'rbac', 'tenant.scope']`
  - _Requirements: 2.1, 2.2, 2.4, 2.5_

  - [ ]* 2.1 Write property test for JWT tenant claim mismatch (Property 2)
    - **Property 2: JWT tenant claim mismatch is rejected**
    - Generate random tenant UUID pairs `(jwt_tenant_id, header_tenant_id)` where they differ; forge a JWT with `tenant_id = jwt_tenant_id`, send with `X-Tenant-ID: header_tenant_id`, assert 403 `TENANT_MISMATCH`
    - Run minimum 100 iterations
    - **Validates: Requirements 2.1, 2.2**

  - [ ]* 2.2 Write unit tests for TenantVerificationMiddleware
    - Test 403 `TENANT_MISMATCH` when JWT `tenant_id` claim differs from `app('current_tenant_id')`
    - Test 401 `MISSING_TENANT_CLAIM` when JWT has no `tenant_id` claim
    - Test pass-through when claim matches `app('current_tenant_id')`
    - _Requirements: 2.1, 2.2, 2.4, 2.5_

- [x] 3. Checkpoint — Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 4. Harden Nginx cipher suite and add CSP header
  - In `docker/nginx/default.conf`, update `ssl_ciphers` to explicitly exclude weak suites: `HIGH:!aNULL:!eNULL:!EXPORT:!DES:!RC4:!MD5:!PSK:!SRP:!CAMELLIA`
  - Add a `Content-Security-Policy` header scoped to web panel routes (non-API `location /` block):
    ```
    default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; font-src 'self' data:; img-src 'self' data: blob:; connect-src 'self' wss:; frame-ancestors 'none'; report-uri /api/v1/csp-report;
    ```
  - Confirm `ssl_protocols TLSv1.2 TLSv1.3`, `ssl_prefer_server_ciphers on`, `Strict-Transport-Security`, `X-Content-Type-Options`, and `X-Frame-Options` headers are all present (they already exist; verify no regression)
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 6.1, 6.2, 6.3, 6.4, 6.5_

- [x] 5. Create `config/cors.php` and wire `HandleCors` middleware
  - Create `backend/config/cors.php` reading `CORS_ALLOWED_ORIGINS` from the environment, splitting on commas, and defaulting to an empty array (deny-all)
  - Set `supports_credentials` to `false`, `allowed_methods` to `['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']`, and `allowed_headers` to `['Content-Type', 'Authorization', 'X-Tenant-ID', 'X-Requested-With']`
  - Verify `Illuminate\Http\Middleware\HandleCors` is present in the global middleware stack in `backend/bootstrap/app.php`; add it if absent
  - Add `CORS_ALLOWED_ORIGINS=` to `backend/.env.example` with a comment explaining the comma-separated format
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6_

  - [ ]* 5.1 Write property test for CORS origin rejection (Property 3)
    - **Property 3: CORS rejects unlisted origins**
    - Generate random origin strings (URLs with random hostnames) not present in the allowed list; send OPTIONS preflight, assert no `Access-Control-Allow-Origin` header in response
    - Run minimum 100 iterations
    - **Validates: Requirements 4.1, 4.5**

  - [ ]* 5.2 Write unit tests for CORS config
    - Test that a preflight from an allowed origin returns 204 with correct `Access-Control-Allow-Origin`
    - Test that a preflight from a disallowed origin returns no `Access-Control-Allow-Origin` header
    - Test that `CORS_ALLOWED_ORIGINS` unset defaults to deny-all
    - _Requirements: 4.1, 4.2, 4.5, 4.6_

- [x] 6. Remove hardcoded Pusher key from WebSocketService
  - In `mobile/lib/services/websocket_service.dart`, add a compile-time constant at the top of the class: `static const _pusherAppKey = String.fromEnvironment('PUSHER_APP_KEY', defaultValue: '');`
  - In `connect()`, add an early-return guard: if `_pusherAppKey.isEmpty`, log a warning (`[WebSocketService] PUSHER_APP_KEY not configured — WebSocket disabled.`) and return without initialising Pusher
  - Replace the hardcoded `apiKey: 'app-key'` in the `_pusher!.init(...)` call with `apiKey: _pusherAppKey`
  - _Requirements: 5.1, 5.2, 5.3, 5.4_

  - [ ]* 6.1 Write unit tests for WebSocketService key guard
    - Test that `connect()` returns early without throwing when `PUSHER_APP_KEY` is empty (simulate via test double or compile-time override)
    - Test that no `PusherChannelsFlutter` instance is created when the key is absent
    - _Requirements: 5.1, 5.3_

- [x] 7. Checkpoint — Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 8. Create permissions and role_permissions migrations and Permission model
  - Create migration `backend/database/migrations/YYYY_MM_DD_000001_create_permissions_table.php` with columns: `id` (bigint PK AI), `name` (varchar 100, unique), `display_name` (varchar 150), `group` (varchar 50), `created_at`, `updated_at`
  - Create migration `backend/database/migrations/YYYY_MM_DD_000002_create_role_permissions_table.php` with columns: `id` (bigint PK AI), `role` (varchar 50), `permission` (varchar 100), `created_at`; add unique index on `(role, permission)`
  - Create `backend/app/Models/Permission.php` with `$fillable = ['name', 'display_name', 'group']`
  - Create `backend/app/Models/RolePermission.php` with `$fillable = ['role', 'permission']` and `$timestamps = false` (only `created_at`)
  - _Requirements: 7.1, 7.3, 7.6_

- [x] 9. Create PermissionSeeder with default role-permission assignments
  - Create `backend/database/seeders/PermissionSeeder.php` that inserts all named permissions from the design's seeded permissions table (`patients.view`, `patients.create`, `patients.new_visit`, `patients.anonymise`, `documents.upload`, `documents.view`, `invoices.create`, `invoices.settle`, `reports.view`, `staff.manage`, `departments.manage`, `workflows.manage`, `audit_logs.view`, `department.queue.view`, `patient_records.manage`, `ward.manage`, `accounts.queue.view`, `appointments.manage`, `my_department.manage`)
  - For each permission, insert the corresponding `role_permissions` rows matching the default role assignments defined in the design document
  - Register `PermissionSeeder` in `backend/database/seeders/DatabaseSeeder.php`
  - _Requirements: 7.1, 7.4, 7.6_

  - [ ]* 9.1 Write unit tests for PermissionSeeder
    - Test that all 19 named permissions exist in the `permissions` table after seeding
    - Test that each role has its expected default permission set (spot-check `hospital_admin` has `patients.anonymise`, `doctor` does not)
    - _Requirements: 7.1, 7.4_

- [x] 10. Update RBACMiddleware to use permission-based lookup
  - Update `backend/app/Http/Middleware/RBACMiddleware.php` to accept a `permission:` parameter (e.g., `rbac:patients.view`) in the `handle()` signature: `public function handle(Request $request, Closure $next, ?string $requiredPermission = null)`
  - When `$requiredPermission` is provided, look up the user's role permissions from the `role_permissions` table (cached in Redis under `tenant:{tenantId}:role_perms:{role}` with a 3600-second TTL); grant access if the permission is present, return 403 `FORBIDDEN` otherwise
  - When `$requiredPermission` is `null`, fall back to the existing URL-pattern matching logic so no existing routes break
  - On cache miss (Redis unavailable), fall back to a direct DB query rather than failing open
  - Update the `logUnauthorizedAccess` audit log entry to include `resource_type = 'permission'` and the required permission name in `metadata` when a named permission is checked
  - _Requirements: 7.2, 7.5, 7.6_

  - [ ]* 10.1 Write property test for permission gate (Property 4)
    - **Property 4: Permission gate is necessary and sufficient**
    - Generate random role strings from the known role set and random permission names from the known permission set, with a random boolean indicating whether the `(role, permission)` row exists; configure `role_permissions` accordingly, make a request to a route requiring that permission, assert grant iff row exists
    - Run minimum 100 iterations
    - **Validates: Requirements 7.2, 7.5**

  - [ ]* 10.2 Write unit tests for updated RBACMiddleware
    - Test grant when `role_permissions` contains `(role, permission)`
    - Test 403 when `role_permissions` does not contain `(role, permission)`
    - Test audit log is written with correct `resource_type` and permission name on deny
    - Test cache miss falls back to DB query (mock Redis to throw)
    - _Requirements: 7.2, 7.5_

- [x] 11. Add `permission:` middleware to patient routes in api.php
  - Update the patient route group in `backend/routes/api.php` to apply named permission middleware to each route, e.g.:
    - `GET /{id}` → `->middleware('rbac:patients.view')`
    - `POST /` → `->middleware('rbac:patients.create')`
    - `POST /{id}/new-visit` → `->middleware('rbac:patients.new_visit')`
    - `POST /{id}/anonymise` → `->middleware('rbac:patients.anonymise')`
    - `POST /{id}/documents` → `->middleware('rbac:documents.upload')`
    - `GET /{id}/documents/{docId}/url` → `->middleware('rbac:documents.view')`
    - `GET /{id}/photo/url` → `->middleware('rbac:documents.view')`
  - Apply equivalent permission middleware to other route groups (invoices, reports, staff, departments, ward, accounts) following the seeded permission names
  - _Requirements: 7.2, 7.3, 7.4_

- [-] 12. Checkpoint — Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 13. Add certificate pinning to the Dio client in the mobile app
  - In `mobile/lib/presentation/providers/accounts_provider.dart` (the `dioProvider`), add compile-time constants: `static const _pinnedSha256_1 = String.fromEnvironment('PINNED_CERT_SHA256_1', defaultValue: '');`, `static const _pinnedSha256_2 = String.fromEnvironment('PINNED_CERT_SHA256_2', defaultValue: '');`, `static const _pinningEnabled = String.fromEnvironment('CERTIFICATE_PINNING_ENABLED', defaultValue: 'true') != 'false';`
  - Create a custom `HttpClient` with a `badCertificateCallback` that, when `_pinningEnabled` is `true`, computes the SHA-256 fingerprint of the presented certificate's DER-encoded public key bytes and returns `false` (reject) if the fingerprint is not in `{_pinnedSha256_1, _pinnedSha256_2}`; when `_pinningEnabled` is `false`, the callback returns `true` (accept all)
  - Pass the custom `HttpClient` to Dio via `(dio.httpClientAdapter as IOHttpClientAdapter).createHttpClient = () => customClient`
  - Catch `DioException` with type `DioExceptionType.badCertificate` in the calling layer and surface a user-facing error: "Secure connection failed. Please update the app or contact support."
  - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_

  - [ ]* 13.1 Write property test for certificate pinning (Property 5)
    - **Property 5: Certificate pinning rejects unrecognised fingerprints**
    - Generate random SHA-256 hex strings that are not equal to the two pinned values; invoke the `badCertificateCallback` with a mock certificate whose fingerprint is the generated value, assert the callback returns `false`
    - Run minimum 100 iterations using `glados` or equivalent Dart PBT library
    - **Validates: Requirements 8.1, 8.2, 8.3**

  - [ ]* 13.2 Write unit tests for certificate pinning
    - Test `badCertificateCallback` returns `false` for a fingerprint not in the pinned set
    - Test `badCertificateCallback` returns `true` for a fingerprint matching `PINNED_CERT_SHA256_1`
    - Test `badCertificateCallback` returns `true` for a fingerprint matching `PINNED_CERT_SHA256_2`
    - Test `badCertificateCallback` returns `true` for any fingerprint when `CERTIFICATE_PINNING_ENABLED=false`
    - _Requirements: 8.1, 8.2, 8.3, 8.4_

- [ ] 14. Final checkpoint — Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for a faster MVP
- Tasks 1–3 address critical vulnerabilities and should be deployed first
- Tasks 4–7 address important security gaps
- Tasks 8–14 implement fine-grained RBAC and certificate pinning (defence-in-depth)
- Property tests use PHPUnit + `eris/eris` for backend and `glados` for Flutter; each runs a minimum of 100 iterations
- The `tenant.verify` middleware (Task 2) must be inserted after `jwt.auth` in the route stack — do not reorder `tenant.resolve` as it is needed before JWT auth for tenant-scoped user lookup
- The RBAC update (Tasks 10–11) is backward-compatible: routes without a `permission:` parameter continue to use URL-pattern matching until explicitly migrated
