# Requirements Document

## Introduction

The Hospital Health Management System (HHMS) requires a security hardening initiative to address identified vulnerabilities across the backend API, Nginx layer, and mobile application. The issues span three priority tiers: critical vulnerabilities that expose patient data or allow tenant impersonation (fix immediately), important gaps that weaken the security posture (fix soon), and improvements that add defence-in-depth (fix later). This document captures the full set of requirements for all three tiers.

## Glossary

- **HHMS**: Hospital Health Management System — the multi-tenant SaaS platform under scope.
- **API**: The Laravel-based RESTful backend exposed under `/api/v1/`.
- **PatientController**: The Laravel controller at `backend/app/Http/Controllers/Api/V1/PatientController.php` that handles patient CRUD, document, photo, journey, and anonymisation endpoints.
- **TenantResolutionMiddleware**: The Laravel middleware at `backend/app/Http/Middleware/TenantResolutionMiddleware.php` responsible for resolving the active tenant from the incoming request.
- **RBACMiddleware**: The Laravel middleware at `backend/app/Http/Middleware/RBACMiddleware.php` that enforces coarse role-based route access.
- **Tenant**: A hospital organisation registered in the central database, identified by a UUID tenant ID.
- **Authenticated_User**: The user whose identity has been verified by `JWTAuthMiddleware` and whose record is bound to the current request.
- **JWT**: JSON Web Token issued by the backend on login, containing `sub` (user ID), `tenant_id`, `role`, and `token_version` claims.
- **Nginx**: The reverse-proxy web server configured at `docker/nginx/default.conf` that terminates TLS and forwards requests to PHP-FPM.
- **Mobile_App**: The Flutter application that communicates with the API and stores session credentials locally.
- **WebSocketService**: The Flutter singleton at `mobile/lib/services/websocket_service.dart` that manages the Pusher/Soketi real-time connection.
- **AuthStorage**: The Flutter class at `mobile/lib/data/local/auth_storage.dart` that persists authentication tokens on the device.
- **CORS**: Cross-Origin Resource Sharing — the HTTP mechanism that controls which origins may call the API from a browser.
- **HSTS**: HTTP Strict Transport Security — the response header that instructs browsers to use HTTPS exclusively.
- **CSP**: Content Security Policy — the response header that restricts the sources from which the browser may load resources.
- **RBAC**: Role-Based Access Control — the permission model that grants or denies access based on a user's assigned role.
- **Permission**: A fine-grained capability (e.g., `patients.view`, `patients.anonymise`) that can be assigned to a role independently of other capabilities.

---

## Requirements

### Requirement 1: Per-Resource Tenant Ownership Checks in PatientController

**User Story:** As a hospital administrator, I want every patient data endpoint to verify that the requested patient belongs to the authenticated user's tenant, so that staff from one hospital cannot access or modify patient records belonging to another hospital.

#### Acceptance Criteria

1. WHEN an Authenticated_User calls `GET /api/v1/patients/{id}`, THE PatientController SHALL verify that the patient's `tenant_id` matches `app('current_tenant_id')` before returning any data.
2. WHEN an Authenticated_User calls `GET /api/v1/patients/{id}/journey`, THE PatientController SHALL verify that the patient's `tenant_id` matches `app('current_tenant_id')` before returning journey events.
3. WHEN an Authenticated_User calls `POST /api/v1/patients/{id}/documents`, THE PatientController SHALL verify that the patient's `tenant_id` matches `app('current_tenant_id')` before attaching the document.
4. WHEN an Authenticated_User calls `GET /api/v1/patients/{id}/documents/{docId}/url`, THE PatientController SHALL verify that the patient's `tenant_id` matches `app('current_tenant_id')` before generating the signed URL.
5. WHEN an Authenticated_User calls `GET /api/v1/patients/{id}/photo/url`, THE PatientController SHALL verify that the patient's `tenant_id` matches `app('current_tenant_id')` before generating the signed photo URL.
6. WHEN an Authenticated_User calls `POST /api/v1/patients/{id}/anonymise`, THE PatientController SHALL verify that the patient's `tenant_id` matches `app('current_tenant_id')` before anonymising the record.
7. IF the patient's `tenant_id` does not match `app('current_tenant_id')`, THEN THE PatientController SHALL return HTTP 404 with error code `NOT_FOUND` so that the existence of cross-tenant records is not disclosed.
8. THE PatientController SHALL apply the same tenant ownership check to `POST /api/v1/patients/{id}/new-visit` before creating a new visit record.

---

### Requirement 2: Tenant Identity Verification in TenantResolutionMiddleware

**User Story:** As a security engineer, I want the tenant resolution middleware to cross-check the supplied tenant identifier against the authenticated user's JWT claims after authentication, so that a malicious actor cannot impersonate a different tenant by forging the `X-Tenant-ID` header.

#### Acceptance Criteria

1. WHEN a request carries a valid JWT and an `X-Tenant-ID` header, THE TenantResolutionMiddleware SHALL compare the resolved tenant ID against the `tenant_id` claim embedded in the JWT.
2. IF the resolved tenant ID does not match the JWT `tenant_id` claim, THEN THE TenantResolutionMiddleware SHALL reject the request with HTTP 403 and error code `TENANT_MISMATCH`.
3. WHEN a request is to an unauthenticated route (login, refresh, password reset), THE TenantResolutionMiddleware SHALL resolve the tenant from the header or body without performing a JWT cross-check, because no JWT is present at that stage.
4. THE TenantResolutionMiddleware SHALL perform the JWT cross-check only after `JWTAuthMiddleware` has successfully authenticated the user and bound the user to the request.
5. IF the JWT does not contain a `tenant_id` claim, THEN THE TenantResolutionMiddleware SHALL reject the request with HTTP 401 and error code `MISSING_TENANT_CLAIM`.

---

### Requirement 3: HTTPS Enforcement and HSTS in Nginx

**User Story:** As a hospital administrator, I want all HTTP traffic to be automatically redirected to HTTPS and for browsers to remember this preference, so that patient data is never transmitted over an unencrypted connection.

#### Acceptance Criteria

1. WHEN Nginx receives an HTTP request on port 80, THE Nginx SHALL respond with HTTP 301 redirecting the client to the equivalent HTTPS URL.
2. WHILE serving HTTPS responses, THE Nginx SHALL include the `Strict-Transport-Security` header with a `max-age` of at least 31536000 seconds (one year) and the `includeSubDomains` directive.
3. THE Nginx SHALL terminate TLS using TLS 1.2 or TLS 1.3 only; older protocol versions SHALL be disabled.
4. THE Nginx SHALL configure the `ssl_prefer_server_ciphers` directive to `on` and exclude weak cipher suites (NULL, EXPORT, DES, RC4, MD5).
5. WHILE serving HTTPS responses, THE Nginx SHALL include the `X-Content-Type-Options: nosniff` header.
6. WHILE serving HTTPS responses, THE Nginx SHALL include the `X-Frame-Options: SAMEORIGIN` header.

---

### Requirement 4: CORS with Strict Origin Whitelist

**User Story:** As a security engineer, I want the API to enforce a strict CORS policy that only allows requests from known, approved origins, so that malicious websites cannot make authenticated cross-origin requests on behalf of logged-in users.

#### Acceptance Criteria

1. THE API SHALL reject cross-origin requests from origins not present in the configured `CORS_ALLOWED_ORIGINS` list by returning HTTP 403.
2. WHEN a browser sends a CORS preflight `OPTIONS` request from an allowed origin, THE API SHALL respond with HTTP 204 and the appropriate `Access-Control-Allow-Origin`, `Access-Control-Allow-Methods`, and `Access-Control-Allow-Headers` headers.
3. THE API SHALL expose only the HTTP methods required by each route group; methods not in use SHALL NOT be listed in `Access-Control-Allow-Methods`.
4. THE API SHALL set `Access-Control-Allow-Credentials: true` only for routes that require cookie-based session authentication (web panel routes), and SHALL NOT set it for JWT-authenticated mobile API routes.
5. WHERE the `CORS_ALLOWED_ORIGINS` environment variable is not set, THE API SHALL default to denying all cross-origin requests.
6. THE API SHALL configure CORS via Laravel's built-in `config/cors.php` and the `HandleCors` middleware so that the policy is applied consistently to all routes.

---

### Requirement 5: Remove Hardcoded Pusher API Key from Mobile App

**User Story:** As a security engineer, I want the Pusher/Soketi API key to be supplied at runtime rather than compiled into the mobile binary, so that the key cannot be extracted from a distributed APK or IPA and used to connect to the WebSocket server without authorisation.

#### Acceptance Criteria

1. THE WebSocketService SHALL NOT contain any hardcoded Pusher or Soketi API key, app ID, or secret in source code.
2. WHEN the Mobile_App initialises the WebSocketService, THE WebSocketService SHALL read the Pusher app key from a runtime configuration source (such as environment-injected build config or a remote configuration endpoint).
3. IF the Pusher app key is absent from the runtime configuration, THEN THE WebSocketService SHALL log a configuration error and skip the WebSocket connection attempt without crashing.
4. THE Mobile_App build pipeline SHALL supply the Pusher app key via `--dart-define` or a `.env`-equivalent mechanism so that different keys can be used for development, staging, and production environments.

---

### Requirement 6: Content Security Policy Header in Nginx

**User Story:** As a security engineer, I want the web panel responses to include a Content Security Policy header, so that the browser blocks execution of injected scripts and loading of resources from untrusted origins.

#### Acceptance Criteria

1. WHILE serving HTTPS responses for web panel routes, THE Nginx SHALL include a `Content-Security-Policy` header.
2. THE Content-Security-Policy header SHALL set `default-src 'self'` as the base directive.
3. THE Content-Security-Policy header SHALL explicitly list trusted script, style, font, and image sources required by the Vue/Inertia frontend.
4. THE Content-Security-Policy header SHALL include `frame-ancestors 'none'` to prevent the web panel from being embedded in iframes on external sites.
5. IF a CSP violation occurs in a browser, THE Nginx SHALL include a `report-uri` or `report-to` directive pointing to a configurable reporting endpoint so that violations can be monitored.

---

### Requirement 7: Fine-Grained RBAC Permissions

**User Story:** As a hospital administrator, I want each API action to be gated by a specific named permission rather than a broad role-to-route wildcard, so that I can grant a staff member access to one capability without inadvertently granting access to all capabilities of that role.

#### Acceptance Criteria

1. THE HHMS SHALL define a set of named permissions (e.g., `patients.view`, `patients.create`, `patients.anonymise`, `documents.upload`, `documents.view`, `invoices.create`, `invoices.settle`, `reports.view`) that map to individual API actions.
2. THE RBACMiddleware SHALL evaluate the Authenticated_User's permissions against the named permission required by the requested route, rather than matching the user's role string against a URL pattern.
3. WHEN a role is created or updated, THE HHMS SHALL allow a hospital administrator to assign any combination of named permissions to that role.
4. THE HHMS SHALL seed default permission sets for each existing role (hospital_admin, doctor, front_desk_officer, accountant, pharmacist, lab_technician, nurse, triage, ward_staff, ward_nurse, department_staff) that replicate the current coarse-grained access without regression.
5. IF an Authenticated_User attempts an action for which their role does not hold the required permission, THEN THE RBACMiddleware SHALL return HTTP 403 with error code `FORBIDDEN` and log the access denial to the audit log.
6. THE HHMS SHALL store role-permission assignments in the database so that permissions can be updated without a code deployment.

---

### Requirement 8: Certificate Pinning in Flutter

**User Story:** As a security engineer, I want the mobile app to pin the server's TLS certificate or public key, so that a compromised or rogue certificate authority cannot perform a man-in-the-middle attack against the app's API traffic.

#### Acceptance Criteria

1. THE Mobile_App SHALL configure the HTTP client (Dio) to validate the server's certificate against a pinned SHA-256 public key fingerprint.
2. IF the server presents a certificate whose public key fingerprint does not match the pinned value, THEN THE Mobile_App SHALL abort the connection and surface an error to the user.
3. THE Mobile_App SHALL support pinning at least two certificate fingerprints simultaneously to allow for certificate rotation without a forced app update.
4. WHERE the `CERTIFICATE_PINNING_ENABLED` build flag is set to `false`, THE Mobile_App SHALL skip certificate pinning to allow local development and testing without a valid TLS certificate.
5. THE Mobile_App build pipeline SHALL supply pinned fingerprints via `--dart-define` so that fingerprints can be rotated by releasing a new build without changing application logic.
