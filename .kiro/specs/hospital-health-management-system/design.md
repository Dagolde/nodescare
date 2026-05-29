# Design Document

## Hospital Health Management System (HHMS)

## Overview

The Hospital Health Management System (HHMS) is a multi-tenant, cloud-based SaaS platform that enables multiple hospitals to operate independently within a single deployment. Each hospital (tenant) has fully isolated data, configuration, and user accounts. The system serves two distinct client surfaces:

- **Mobile App (Flutter)**: Used by all clinical and department-level staff — Front Desk Officers, Department Staff, Doctors, Pharmacists, and Lab Technicians — for patient registration, department workflows, and real-time notifications.
- **Web Dashboard (Vue.js 3 + Inertia.js)**: Used by Hospital Admins and the Super Admin for hospital configuration, staff management, reporting, and system oversight. Served from the same Laravel application via Inertia.js, providing a seamless SPA experience without a separate frontend deployment.

The backend is a PHP Laravel REST API with `stancl/tenancy` providing database-per-tenant isolation. Redis powers both the queue system (Laravel Queues) and caching layer. Real-time events are delivered via Laravel Broadcasting over WebSockets (Pusher-compatible protocol, self-hosted via Soketi or managed via Pusher). File storage uses **Cloudflare R2** (S3-compatible) with tenant-isolated buckets and signed URL access. Push notifications to backgrounded mobile devices are delivered via **Firebase Cloud Messaging (FCM)** as a fallback channel alongside Soketi WebSockets.

### Key Design Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Multi-tenancy strategy | Database-per-tenant (stancl/tenancy) | Strongest data isolation for healthcare; simplifies compliance; avoids cross-tenant query leakage |
| Auth mechanism | JWT (php-open-source-saver/jwt-auth) | Stateless, suitable for mobile + web clients; tenant context embedded in token claims |
| Real-time transport | Laravel Broadcasting + Soketi (self-hosted) | Pusher-compatible protocol; avoids per-message SaaS costs; deployable on-premise |
| Offline storage (mobile) | SQLCipher (AES-256 encrypted SQLite) | Transparent encryption at rest; industry standard for mobile healthcare data |
| Web Dashboard | Vue.js 3 + Inertia.js (served from Laravel) | SPA experience without a separate frontend deployment; shares Laravel routing and auth |
| File storage | Cloudflare R2 (S3-compatible), tenant-isolated buckets | Cost-effective egress pricing; signed URLs enforce access control; no direct public exposure of patient files |
| Push notifications (mobile) | Firebase Cloud Messaging (FCM) as fallback | Delivers critical routing events to backgrounded/offline mobile devices when WebSocket is unavailable |
| Patient journey engine | Configurable workflow sequences (templates + custom) | Supports both fixed templates and fully custom department sequences per hospital |
| Payment gate | PENDING_PAYMENT status on patient_records | Prevents clinical staff from seeing unpaid patients; enforced at API query layer |


## Architecture

### System Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              HHMS Platform                                  │
│                                                                             │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────────────────────┐  │
│  │ Flutter App  │    │ Web Dashboard│    │     Super Admin Dashboard    │  │
│  │ (All clinical│    │ (Hospital    │    │     (Vue.js 3 + Inertia.js)  │  │
│  │  staff)      │    │  Admin)      │    │     served via Laravel       │  │
│  └──────┬───────┘    └──────┬───────┘    └──────────────┬───────────────┘  │
│         │                  │                            │                  │
│         └──────────────────┴────────────────────────────┘                  │
│                            │ HTTPS / JWT                                    │
│                            ▼                                                │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │                    Laravel API Gateway                              │   │
│  │  ┌─────────────────┐  ┌──────────────────┐  ┌──────────────────┐  │   │
│  │  │ Tenant Resolver │  │  Auth Middleware  │  │  RBAC Middleware │  │   │
│  │  │ (subdomain/     │  │  (JWT Validation) │  │  (Role + Scope)  │  │   │
│  │  │  header)        │  │                  │  │                  │  │   │
│  │  └─────────────────┘  └──────────────────┘  └──────────────────┘  │   │
│  └──────────────────────────────┬──────────────────────────────────────┘   │
│                                 │                                           │
│         ┌───────────────────────┼───────────────────────┐                  │
│         ▼                       ▼                       ▼                  │
│  ┌─────────────┐    ┌───────────────────┐    ┌──────────────────┐         │
│  │  Central DB │    │  Tenant DB Pool   │    │  Redis Cluster   │         │
│  │  (system    │    │  (one DB per      │    │  (Cache + Queue  │         │
│  │   tenants,  │    │   hospital)       │    │   + Sessions)    │         │
│  │   plans)    │    │                   │    │                  │         │
│  └─────────────┘    └───────────────────┘    └──────────────────┘         │
│                                                                             │
│  ┌──────────────────────┐    ┌──────────────────────────────────────────┐  │
│  │  Laravel Queue       │    │  Soketi WebSocket Server                 │  │
│  │  Workers (Redis)     │    │  (Pusher-compatible protocol)            │  │
│  │  - Notifications     │    │  - Patient routing events                │  │
│  │  - SMS dispatch      │    │  - Department alerts                     │  │
│  │  - Tenant provision  │    │  - Real-time dashboard updates           │  │
│  └──────────────────────┘    └──────────────────────────────────────────┘  │
│                                                                             │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │  Cloudflare R2 Object Storage (S3-compatible, tenant-isolated)       │  │
│  │  - Patient photographs  - Lab result documents  - Registration docs  │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Multi-Tenancy Architecture

HHMS uses the **database-per-tenant** strategy via `stancl/tenancy`. Each hospital is provisioned its own MySQL database. The central (landlord) database holds only tenant registry records, system configuration, and Super Admin data.

**Tenant Resolution Flow:**

1. Client sends request with subdomain (e.g., `citygeneral.hhms.app`) or `X-Tenant-ID` header.
2. `InitializeTenancyBySubdomain` middleware (or header-based resolver) looks up the tenant in the central DB.
3. `stancl/tenancy` switches the active database connection to the tenant's database.
4. All subsequent Eloquent queries execute against the tenant database for the duration of the request.
5. If no valid tenant is resolved, the request is rejected with HTTP 404.

**Tenant Lifecycle:**

```
Super Admin creates hospital
        │
        ▼
Tenant record created in central DB
        │
        ▼
Tenant database provisioned (migrations run)
        │
        ▼
Default Hospital Admin credentials generated
        │
        ▼
Onboarding email dispatched via queue
        │
        ▼
Tenant status: ACTIVE
```

Tenant states: `ACTIVE` → `SUSPENDED` → `INACTIVE` (soft-deleted). Suspension immediately invalidates all JWT tokens for that tenant by incrementing a `token_version` field checked during JWT validation.


## Components and Interfaces

### API Gateway Layer

The Laravel API serves as the single entry point for all clients. It is structured around these middleware layers applied in order:

1. **TenantResolutionMiddleware** — resolves tenant from subdomain or `X-Tenant-ID` header; switches DB connection.
2. **JWTAuthMiddleware** — validates JWT signature, expiry, and `token_version` claim against the user record.
3. **RBACMiddleware** — checks the user's role against the required permission for the route.
4. **TenantScopeMiddleware** — ensures all query builders have the tenant scope applied (enforced by Eloquent global scopes as a secondary safeguard).

**API versioning:** All routes are prefixed `/api/v1/`. Breaking changes increment to `/api/v2/`.

**Rate limiting:** Configured per-tenant via Super Admin feature flags, enforced by Laravel's `ThrottleRequests` middleware backed by Redis.

### Authentication Service

- **Package:** `php-open-source-saver/jwt-auth`
- **Token structure:** JWT payload includes `sub` (user ID), `tenant_id`, `role`, `token_version`, `iat`, `exp`.
- **Access token TTL:** 60 minutes.
- **Refresh token TTL:** 7 days (stored in Redis, invalidated on logout or suspension).
- **Token invalidation:** Incrementing `token_version` on the user record invalidates all previously issued tokens without a blocklist.

**Authentication endpoints:**

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/v1/auth/login` | Authenticate user, return JWT + refresh token |
| POST | `/api/v1/auth/refresh` | Exchange refresh token for new access token |
| POST | `/api/v1/auth/logout` | Invalidate refresh token |
| POST | `/api/v1/auth/password/reset` | Initiate password reset |

### Notification Service

The Notification Service is a Laravel component that dispatches notifications through multiple channels:

- **In-app / WebSocket:** Via Laravel Broadcasting → Soketi → Flutter app (using `pusher_channels_flutter` package) and web dashboard (using Laravel Echo + Pusher.js).
- **SMS:** Via a pluggable SMS gateway abstraction (`App\Contracts\SmsGateway`). Concrete implementations (Twilio, Vonage, Africa's Talking, etc.) are bound in the service container via configuration. Swapping providers requires only a config change and a new implementation class.
- **Email:** Via Laravel Mail (SMTP/SES).

**Notification retry logic:** Failed notifications are retried up to 3 times with exponential backoff via Laravel Queue. After 3 failures, the job is moved to the failed jobs table and an Audit Log entry is written.

### File Storage Service

- **Driver:** Cloudflare R2 (S3-compatible object storage). The Laravel filesystem is configured with the `s3` driver pointing to Cloudflare R2's S3-compatible API endpoint.
- **Bucket naming:** `hhms-tenant-{tenant_id}` — one bucket per tenant.
- **Access:** No public bucket policies. All access via time-limited signed URLs (15-minute TTL) generated server-side.
- **Upload flow:** Client requests a pre-signed upload URL from the API → uploads directly to R2 → API records the object key in the database. This avoids routing large files through the API server.
- **Encryption at rest:** Cloudflare R2 encrypts all objects at rest by default (AES-256).

### SMS Gateway Abstraction

```php
// App\Contracts\SmsGateway
interface SmsGateway {
    public function send(string $to, string $message): SmsResult;
}

// Bound in AppServiceProvider based on config('sms.driver')
// Implementations: TwilioSmsGateway, VonageSmsGateway, AfricasTalkingSmsGateway, NullSmsGateway (testing)
```


## Data Models

### Central (Landlord) Database

```sql
-- tenants: registry of all hospitals
tenants
  id              UUID PK
  name            VARCHAR(255)
  subdomain       VARCHAR(100) UNIQUE
  status          ENUM('active','suspended','inactive')
  plan_id         FK -> plans.id
  created_at      TIMESTAMP
  updated_at      TIMESTAMP

-- plans: subscription/feature tiers
plans
  id              UUID PK
  name            VARCHAR(100)
  feature_flags   JSON   -- {"sms_notifications": true, "lab_module": true, ...}
  api_rate_limit  INT    -- requests per minute
  created_at      TIMESTAMP

-- system_settings: global Super Admin configuration
system_settings
  key             VARCHAR(100) PK
  value           TEXT
  updated_by      UUID   -- super admin user ID
  updated_at      TIMESTAMP

-- super_admins: platform-level admin accounts
super_admins
  id              UUID PK
  name            VARCHAR(255)
  email           VARCHAR(255) UNIQUE
  password        VARCHAR(255)  -- bcrypt
  token_version   INT DEFAULT 0
  created_at      TIMESTAMP
  updated_at      TIMESTAMP
```

### Tenant Database (per hospital)

```sql
-- users: all staff accounts within the hospital
users
  id              UUID PK
  name            VARCHAR(255)
  email           VARCHAR(255) UNIQUE
  phone           VARCHAR(20)
  password        VARCHAR(255)  -- bcrypt
  role            ENUM('hospital_admin','doctor','front_desk_officer',
                       'accountant','pharmacist','lab_technician','department_staff')
  department_id   UUID NULL FK -> departments.id
  is_active       BOOLEAN DEFAULT TRUE
  token_version   INT DEFAULT 0
  failed_login_attempts INT DEFAULT 0
  locked_until    TIMESTAMP NULL
  created_at      TIMESTAMP
  updated_at      TIMESTAMP

-- departments: clinical/admin units within the hospital
departments
  id              UUID PK
  name            VARCHAR(255)
  code            VARCHAR(50) UNIQUE  -- e.g. "PHARM", "LAB", "CARDIO", "ACCTS"
  type            ENUM('clinical','pharmacy','laboratory','front_desk','triage','ward','icu_ae','accounts','other')
  is_active       BOOLEAN DEFAULT TRUE
  created_at      TIMESTAMP
  updated_at      TIMESTAMP

-- workflow_templates: reusable patient journey templates
workflow_templates
  id              UUID PK
  name            VARCHAR(255)
  description     TEXT NULL
  is_default      BOOLEAN DEFAULT FALSE
  created_by      UUID FK -> users.id
  created_at      TIMESTAMP
  updated_at      TIMESTAMP

-- workflow_template_steps: ordered steps in a template
workflow_template_steps
  id              UUID PK
  template_id     UUID FK -> workflow_templates.id
  department_id   UUID FK -> departments.id
  step_order      INT
  is_terminal     BOOLEAN DEFAULT FALSE  -- marks discharge/end step
  created_at      TIMESTAMP

-- patients: registered patient demographics
patients
  id              UUID PK
  patient_number  VARCHAR(20) UNIQUE  -- human-readable, e.g. "HOS-2024-00001"
  full_name       VARCHAR(255)        -- AES-256 encrypted at application layer
  date_of_birth   DATE                -- AES-256 encrypted
  gender          ENUM('male','female','other')
  phone           VARCHAR(20)         -- AES-256 encrypted
  address         TEXT NULL           -- AES-256 encrypted
  next_of_kin_name    VARCHAR(255) NULL
  next_of_kin_phone   VARCHAR(20) NULL
  chief_complaint     TEXT NOT NULL   -- captured at registration
  hmo_provider_name   VARCHAR(255) NULL  -- insurance/HMO provider
  hmo_policy_number   VARCHAR(255) NULL  -- insurance/HMO policy or member number
  photo_object_key    VARCHAR(500) NULL  -- R2 object key
  is_anonymised   BOOLEAN DEFAULT FALSE
  created_by      UUID FK -> users.id
  created_at      TIMESTAMP
  updated_at      TIMESTAMP

-- patient_records: a single visit/encounter for a patient
patient_records
  id              UUID PK
  patient_id      UUID FK -> patients.id
  workflow_template_id UUID NULL FK -> workflow_templates.id
  status          ENUM('pending_payment','active','in_transit','admitted','discharged','completed')
  current_department_id UUID NULL FK -> departments.id
  current_step_order    INT NULL
  registered_by   UUID FK -> users.id
  admitted_at     TIMESTAMP NULL
  discharged_at   TIMESTAMP NULL
  created_at      TIMESTAMP
  updated_at      TIMESTAMP

-- patient_journey_events: immutable log of every routing event
patient_journey_events
  id              UUID PK
  patient_record_id UUID FK -> patient_records.id
  from_department_id UUID NULL FK -> departments.id
  to_department_id   UUID NULL FK -> departments.id
  event_type      ENUM('registered','routed','admitted','discharged',
                       'note_added','document_attached','status_changed')
  performed_by    UUID FK -> users.id
  notes           TEXT NULL
  metadata        JSON NULL  -- prescription notes, investigation requests, etc.
  created_at      TIMESTAMP  -- immutable; no updated_at

-- patient_documents: files attached to a patient record
patient_documents
  id              UUID PK
  patient_record_id UUID FK -> patient_records.id
  uploader_id     UUID FK -> users.id
  document_type   ENUM('registration','lab_result','prescription','other')
  object_key      VARCHAR(500)  -- S3 object key
  original_filename VARCHAR(255)
  file_size_bytes INT
  uploaded_at     TIMESTAMP

-- dispensing_records: pharmacy dispensing log
dispensing_records
  id              UUID PK
  patient_record_id UUID FK -> patient_records.id
  pharmacist_id   UUID FK -> users.id
  drug_name       VARCHAR(255)
  dosage          VARCHAR(100)
  quantity        VARCHAR(100)
  dispensed_at    TIMESTAMP
  created_at      TIMESTAMP

-- lab_results: laboratory test results
lab_results
  id              UUID PK
  patient_record_id UUID FK -> patient_records.id
  technician_id   UUID FK -> users.id
  test_name       VARCHAR(255)
  result_value    TEXT
  reference_range VARCHAR(255) NULL
  result_document_key VARCHAR(500) NULL  -- S3 object key
  resulted_at     TIMESTAMP
  created_at      TIMESTAMP

-- appointments: scheduled follow-up consultations
appointments
  id              UUID PK
  patient_id      UUID FK -> patients.id
  patient_record_id UUID NULL FK -> patient_records.id
  doctor_id       UUID FK -> users.id
  scheduled_at    TIMESTAMP
  status          ENUM('scheduled','completed','cancelled','no_show')
  notes           TEXT NULL
  sms_reminder_sent BOOLEAN DEFAULT FALSE
  created_at      TIMESTAMP
  updated_at      TIMESTAMP

-- notifications: notification delivery log
notifications
  id              UUID PK
  recipient_id    UUID FK -> users.id
  type            VARCHAR(100)  -- e.g. 'patient_routed', 'appointment_reminder'
  channel         ENUM('in_app','sms','email')
  payload         JSON
  status          ENUM('pending','delivered','failed')
  attempts        INT DEFAULT 0
  delivered_at    TIMESTAMP NULL
  created_at      TIMESTAMP

-- audit_logs: immutable system audit trail
audit_logs
  id              UUID PK
  actor_id        UUID NULL   -- user ID (null for system actions)
  actor_role      VARCHAR(50) NULL
  action          VARCHAR(100)  -- e.g. 'patient.created', 'user.login.failed'
  resource_type   VARCHAR(100) NULL
  resource_id     UUID NULL
  description     TEXT
  ip_address      VARCHAR(45) NULL
  metadata        JSON NULL
  created_at      TIMESTAMP   -- no updated_at; immutable

-- invoices: itemised bills created by the Accounts department
invoices
  id                    UUID PK
  patient_record_id     UUID FK -> patient_records.id
  patient_id            UUID FK -> patients.id
  created_by            UUID FK -> users.id  -- Accountant who created the invoice
  invoice_number        VARCHAR(30) UNIQUE   -- human-readable, e.g. "INV-2024-00001"
  is_new_patient        BOOLEAN              -- TRUE = registration+consultation fees required
  subtotal              DECIMAL(10,2)
  total_amount          DECIMAL(10,2)
  amount_paid           DECIMAL(10,2) DEFAULT 0
  outstanding_balance   DECIMAL(10,2)        -- computed: total_amount - amount_paid
  status                ENUM('draft','issued','partially_paid','settled','reversed')
  settled_at            TIMESTAMP NULL
  reversed_at           TIMESTAMP NULL
  reversed_by           UUID NULL FK -> users.id
  reversal_reason       TEXT NULL
  created_at            TIMESTAMP
  updated_at            TIMESTAMP

-- invoice_items: line items on an invoice
invoice_items
  id                    UUID PK
  invoice_id            UUID FK -> invoices.id
  service_name          VARCHAR(255)   -- e.g. "Registration Fee", "Consultation", "Blood Test"
  unit_price            DECIMAL(10,2)
  quantity              INT DEFAULT 1
  subtotal              DECIMAL(10,2)  -- unit_price * quantity
  created_at            TIMESTAMP

-- invoice_payments: payment transactions recorded against an invoice
invoice_payments
  id                    UUID PK
  invoice_id            UUID FK -> invoices.id
  accountant_id         UUID FK -> users.id
  payment_type          ENUM('cash','pos_card','hmo_insurance')
  amount                DECIMAL(10,2)
  receipt_reference     VARCHAR(255) NULL
  hmo_provider_name     VARCHAR(255) NULL
  hmo_policy_number     VARCHAR(255) NULL
  hmo_claim_reference   VARCHAR(255) NULL
  created_at            TIMESTAMP  -- immutable; no updated_at
```

### Application-Layer Encryption

Sensitive PII fields (`full_name`, `date_of_birth`, `phone`, `address`) are encrypted at the application layer using Laravel's `Crypt` facade (AES-256-CBC) before being stored in the database. This is in addition to database-at-rest encryption, providing defence in depth. The encryption key is per-tenant, stored in a secrets manager (AWS Secrets Manager or HashiCorp Vault), not in the codebase.


## API Design

### Tenant Resolution

Tenants are resolved by subdomain: `{subdomain}.hhms.app`. For mobile clients that cannot use subdomains, the `X-Tenant-ID` header (containing the tenant UUID) is accepted as a fallback. The resolution middleware validates the tenant exists and is `ACTIVE` before proceeding.

### Key API Endpoints

#### Authentication

| Method | Endpoint | Role | Description |
|---|---|---|---|
| POST | `/api/v1/auth/login` | All | Login, returns JWT + refresh token |
| POST | `/api/v1/auth/refresh` | All | Refresh access token |
| POST | `/api/v1/auth/logout` | All | Invalidate session |

#### Super Admin (Central DB scope)

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/admin/hospitals` | List all tenants |
| POST | `/api/v1/admin/hospitals` | Create + provision new tenant |
| PATCH | `/api/v1/admin/hospitals/{id}/suspend` | Suspend tenant |
| PATCH | `/api/v1/admin/hospitals/{id}/activate` | Reactivate tenant |
| GET | `/api/v1/admin/audit-logs` | System-wide audit logs |
| PUT | `/api/v1/admin/settings` | Update system-wide settings |
| GET | `/api/v1/admin/feature-flags` | List feature flags |
| PUT | `/api/v1/admin/feature-flags/{key}` | Toggle feature flag |

#### Hospital Admin (Tenant scope)

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/departments` | List departments |
| POST | `/api/v1/departments` | Create department |
| PUT | `/api/v1/departments/{id}` | Update department |
| DELETE | `/api/v1/departments/{id}` | Delete department (guarded) |
| GET | `/api/v1/staff` | List staff accounts |
| POST | `/api/v1/staff` | Create staff account |
| PUT | `/api/v1/staff/{id}` | Update staff account |
| PATCH | `/api/v1/staff/{id}/deactivate` | Deactivate staff |
| GET | `/api/v1/workflows/templates` | List workflow templates |
| POST | `/api/v1/workflows/templates` | Create workflow template |
| PUT | `/api/v1/workflows/templates/{id}` | Update template |
| GET | `/api/v1/reports/department-activity` | Department activity report |
| GET | `/api/v1/audit-logs` | Hospital-scoped audit logs |
| GET | `/api/v1/audit-logs/export` | Export audit logs as CSV |

#### Patient Management

| Method | Endpoint | Role | Description |
|---|---|---|---|
| POST | `/api/v1/patients` | Front Desk | Register new patient |
| GET | `/api/v1/patients/{id}` | All clinical | Get patient record |
| GET | `/api/v1/patients/{id}/journey` | All clinical | Get full journey history |
| POST | `/api/v1/patients/{id}/documents` | All clinical | Attach document |
| GET | `/api/v1/patients/{id}/documents/{docId}/url` | All clinical | Get signed download URL |

#### Department Workflow

| Method | Endpoint | Role | Description |
|---|---|---|---|
| GET | `/api/v1/department/queue` | All clinical | Get current department patient queue (excludes `pending_payment` records) |
| POST | `/api/v1/patient-records/{id}/notes` | All clinical | Add note to record |
| POST | `/api/v1/patient-records/{id}/complete` | All clinical | Mark step complete, trigger routing |
| POST | `/api/v1/patient-records/{id}/route` | Doctor | Route to specific department (Pharmacy/Lab/Admit) |
| POST | `/api/v1/patient-records/{id}/admit` | Doctor | Mark patient as admitted |

#### Accounts Department

| Method | Endpoint | Role | Description |
|---|---|---|---|
| GET | `/api/v1/accounts/queue` | Accountant | List all PENDING_PAYMENT records |
| POST | `/api/v1/patient-records/{id}/invoices` | Accountant | Create invoice for a patient record |
| GET | `/api/v1/patient-records/{id}/invoices` | All clinical | List invoices for a patient record |
| POST | `/api/v1/invoices/{id}/payments` | Accountant | Record payment against an invoice |
| POST | `/api/v1/invoices/{id}/settle` | Accountant | Mark invoice as settled (triggers routing to first clinical dept) |
| GET | `/api/v1/invoices/{id}/receipt` | Accountant | Generate/export receipt for a settled invoice |
| GET | `/api/v1/reports/financial` | Hospital_Admin, Accountant | Financial reports (revenue, outstanding invoices, payment breakdown) |

#### Pharmacy & Laboratory

| Method | Endpoint | Role | Description |
|---|---|---|---|
| POST | `/api/v1/patient-records/{id}/dispensing` | Pharmacist | Record dispensing |
| POST | `/api/v1/patient-records/{id}/lab-results` | Lab Tech | Record lab results |

#### Appointments

| Method | Endpoint | Role | Description |
|---|---|---|---|
| POST | `/api/v1/appointments` | Doctor | Book appointment |
| GET | `/api/v1/appointments` | Doctor | List appointments |
| PATCH | `/api/v1/appointments/{id}` | Doctor | Update appointment |

### API Response Format

All responses follow a consistent envelope:

```json
{
  "success": true,
  "data": { ... },
  "message": "Operation completed",
  "meta": {
    "pagination": { "current_page": 1, "per_page": 20, "total": 150 }
  }
}
```

Error responses:

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": { "full_name": ["The full name field is required."] }
  }
}
```


## Mobile App Architecture (Flutter)

### Architecture Pattern

The Flutter app follows a **layered architecture** with clear separation of concerns:

```
┌─────────────────────────────────────────────────────┐
│                  Presentation Layer                  │
│  (Flutter Widgets, Screens, BLoC/Riverpod state)    │
├─────────────────────────────────────────────────────┤
│                  Domain Layer                        │
│  (Use Cases, Entities, Repository Interfaces)        │
├─────────────────────────────────────────────────────┤
│                  Data Layer                          │
│  ┌─────────────────────┐  ┌────────────────────┐   │
│  │  Remote Data Source  │  │  Local Data Source  │   │
│  │  (Dio HTTP client,   │  │  (SQLCipher DB,     │   │
│  │   WebSocket/Pusher)  │  │   flutter_secure_   │   │
│  │                      │  │   storage for keys) │   │
│  └─────────────────────┘  └────────────────────┘   │
└─────────────────────────────────────────────────────┘
```

**State management:** Riverpod (or BLoC) for reactive state. Each department screen subscribes to its patient queue stream.

### Offline Sync Architecture

The app uses an **offline-first** approach with a local encrypted database as the source of truth for the UI, synced to the server when connectivity is available.

**Local storage:** SQLCipher (via `sqflite_sqlcipher` package) — AES-256 encrypted SQLite database. The encryption key is generated on first launch, stored in `flutter_secure_storage` (Android Keystore / iOS Keychain).

**Sync queue:** A `sync_queue` table in the local database stores pending operations:

```sql
sync_queue (local SQLCipher)
  id              INTEGER PK AUTOINCREMENT
  operation_type  TEXT  -- 'patient_registration', 'note_add', 'document_attach'
  payload         TEXT  -- JSON-encoded operation data
  status          TEXT  -- 'pending', 'syncing', 'synced', 'conflict'
  created_at      TEXT
  synced_at       TEXT NULL
  retry_count     INTEGER DEFAULT 0
```

**Connectivity monitoring:** `connectivity_plus` package monitors network state. When connectivity is restored, a background sync service processes the queue in FIFO order, retrying failed items up to 3 times before marking as `conflict`.

**Conflict resolution:** If the server returns a 409 Conflict (the same patient record was modified server-side since the local copy was created), the item is flagged as `conflict` in the local queue and surfaced to the Hospital Admin via the web dashboard for manual resolution.

**Offline indicator:** A persistent banner is shown when the device is offline, clearly indicating queued-but-unsynced data count.

### Real-Time Notifications (Mobile)

The Flutter app connects to Soketi via the `pusher_channels_flutter` package. Each authenticated user subscribes to:

- `private-department.{department_id}` — patient routing events for their department.
- `private-user.{user_id}` — personal alerts (appointment reminders, account notifications).

WebSocket connection is established after JWT login and maintained with automatic reconnection. When the app is backgrounded, Firebase Cloud Messaging (FCM) is used as a fallback push notification channel for critical routing events.

### File Upload Flow (Mobile)

1. User selects photo/document in Flutter app.
2. App calls `GET /api/v1/upload/presigned-url` with file metadata.
3. API generates a pre-signed R2 PUT URL (15-minute TTL) and returns it.
4. Flutter app uploads the file directly to R2 using the signed URL (bypasses API server).
5. App calls `POST /api/v1/patients/{id}/documents` with the returned R2 object key to register the document in the database.

When offline, the file is stored in the device's encrypted local storage and the upload is queued for when connectivity is restored.


## Real-Time Notification Architecture

### WebSocket Infrastructure

```
Flutter App / Web Dashboard
        │
        │  WebSocket (Pusher protocol)
        ▼
   Soketi Server  ◄──── Laravel Broadcasting ◄──── Laravel Event
   (self-hosted,          (pusher driver)            (dispatched from
    Pusher-compatible)                                queue worker)
        │
        │  (app backgrounded / device offline)
        ▼
   Firebase Cloud Messaging (FCM)
   (push notification fallback for mobile)
```

**Soketi** is a self-hosted, open-source WebSocket server implementing the Pusher Channels protocol. It is stateless and horizontally scalable. Multiple Soketi instances can run behind a load balancer with Redis as the shared state backend for channel subscriptions.

**FCM fallback:** When the Flutter app is backgrounded or the device is offline, critical routing events (e.g., `PatientRouted`) are also dispatched via Firebase Cloud Messaging. The Laravel backend sends FCM push notifications through the `kreait/laravel-firebase` package. The Flutter app registers its FCM device token on login and the token is stored server-side per user. Both channels (Soketi WebSocket and FCM) are used in parallel for routing events — the app deduplicates on receipt.

### Channel Design

| Channel | Type | Subscribers | Events |
|---|---|---|---|
| `private-department.{dept_id}` | Private | All active staff in department | `PatientRouted`, `PatientStatusChanged` |
| `private-user.{user_id}` | Private | Individual user | `AppointmentReminder`, `AccountLocked` |
| `private-hospital.admin` | Private | Hospital Admins | `PatientAdmitted`, `StaffLocked`, `AnomalyDetected` |
| `private-superadmin` | Private | Super Admins | `TenantProvisioned`, `TenantSuspended` |

Private channels require authentication via Laravel's channel authorization endpoint (`/broadcasting/auth`), which validates the JWT and confirms the user has access to the requested channel.

### Notification Delivery Pipeline

```
Domain Event occurs (e.g., patient routed)
        │
        ▼
Laravel Event dispatched (synchronous)
        │
        ▼
NotificationJob queued in Redis
        │
        ├──► BroadcastNotification → Soketi → WebSocket clients (< 5 seconds)
        │
        ├──► SmsNotificationJob → SmsGateway::send() (for appointments)
        │
        └──► EmailNotificationJob → Laravel Mail (for admin alerts)
```

**Retry policy:** Each notification job retries up to 3 times with exponential backoff (1s, 4s, 16s). After 3 failures, the job is moved to the `failed_jobs` table and an `AuditLog` entry is written with `action = 'notification.delivery_failed'`.

### Appointment SMS Reminders

A scheduled Laravel command (`appointments:send-reminders`) runs every 15 minutes via the task scheduler. It queries appointments due within the next 24 hours where `sms_reminder_sent = false`, dispatches an `SmsNotificationJob` for each, and marks them as sent. This command is idempotent — re-running it will not send duplicate reminders.


## Patient Journey Workflow Engine

### Design Philosophy

The workflow engine supports two modes, both configurable per hospital by the Hospital Admin:

1. **Template-based:** The Hospital Admin selects a pre-defined workflow template (e.g., "Standard OPD", "Emergency", "Antenatal"). The template defines an ordered sequence of departments.
2. **Custom sequence:** The Hospital Admin defines a fully custom ordered list of departments for a specific patient record or as a new named template.

Both modes produce the same runtime data structure: an ordered list of `workflow_steps` attached to a `patient_record`.

### Workflow Data Model

When a patient is registered, the Front Desk Officer selects a workflow template (or the hospital's default template is applied automatically). The template's steps are copied into the `patient_record` as a concrete sequence — changes to the template after registration do not affect in-progress records.

```
patient_records.workflow_template_id  →  workflow_templates.id
patient_records.current_step_order    →  current position in sequence
```

The engine reads `workflow_template_steps` ordered by `step_order` to determine the next department.

### Routing State Machine

```
REGISTERED
    │  (Front Desk submits registration)
    ▼
PENDING_PAYMENT  (Accounts dept queue — not visible to clinical queues)
    │  (Accountant creates Invoice, records payment, settles Invoice
    │   via POST /api/v1/patient-records/{id}/invoices
    │       POST /api/v1/invoices/{id}/payments
    │       POST /api/v1/invoices/{id}/settle)
    ▼
ACTIVE (at step 1 clinical department — Triage/Nurse)
    │  (Department marks complete)
    ▼
IN_TRANSIT  ──────────────────────────────────────────────────────┐
    │  (Routing job completes, record arrives at next dept)        │
    ▼                                                             │
ACTIVE (at step N department)  ──── (more steps) ────────────────┘
    │  (Doctor routes to Pharmacy/Lab)
    ├──► ACTIVE (at Pharmacy)  [optionally passes through Accounts for lab/pharmacy invoice]
    ├──► ACTIVE (at Laboratory)  ──► (results complete) ──► ACTIVE (back at Doctor)
    │
    │  (Doctor marks admitted)
    ├──► ADMITTED
    │
    │  (Final step completed)
    ▼
DISCHARGED / COMPLETED
```

**Concurrent edit prevention:** When a patient record is `IN_TRANSIT`, the API rejects any edit attempts with HTTP 409. When `ACTIVE`, only the staff in the `current_department_id` can edit the record. Attempts from other departments return HTTP 403.

### Doctor Override Routing

Doctors can override the linear workflow sequence to route directly to Pharmacy, Laboratory, or Admission. This is modelled as a special routing action that:

1. Attaches the doctor's notes (prescription or investigation request) to the `patient_journey_events` metadata.
2. Sets `current_department_id` to the target department.
3. Appends a `routed` event to the journey history.
4. Broadcasts a `PatientRouted` event to the target department's channel.

After the Pharmacy or Laboratory completes their step, the record routes back to the originating doctor's department queue (tracked via `from_department_id` in the routing event).

### Workflow Template Management

Hospital Admins can:
- Create named templates with an ordered list of departments.
- Mark one template as the hospital default (applied automatically at registration).
- Edit templates (only affects future patient records, not in-progress ones).
- Clone an existing template as a starting point for a custom one.


## Role-Based Access Control Design

### Role Hierarchy

```
Super_Admin  (central DB scope — all tenants)
    │
    └── Hospital_Admin  (tenant scope — one hospital)
            │
            ├── Doctor
            ├── Front_Desk_Officer
            ├── Accountant
            ├── Pharmacist
            ├── Lab_Technician
            └── Department_Staff
```

### Permission Matrix

| Resource / Action | Super Admin | Hospital Admin | Doctor | Front Desk | Accountant | Pharmacist | Lab Tech | Dept Staff |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| Manage tenants | ✓ | — | — | — | — | — | — | — |
| View all audit logs | ✓ | — | — | — | — | — | — | — |
| View hospital audit logs | ✓ | ✓ | — | — | — | — | — | — |
| Manage hospital staff | — | ✓ | — | — | — | — | — | — |
| Manage departments | — | ✓ | — | — | — | — | — | — |
| Manage workflow templates | — | ✓ | — | — | — | — | — | — |
| View department reports | — | ✓ | — | — | — | — | — | — |
| Register patients | — | — | — | ✓ | — | — | — | — |
| View own dept queue | — | — | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Add notes to record | — | — | ✓ | — | — | ✓ | ✓ | ✓ |
| Complete dept step | — | — | ✓ | ✓ | — | ✓ | ✓ | ✓ |
| Route to Pharmacy/Lab | — | — | ✓ | — | — | — | — | — |
| Mark patient admitted | — | — | ✓ | — | — | — | — | — |
| Record dispensing | — | — | — | — | — | ✓ | — | — |
| Record lab results | — | — | — | — | — | — | ✓ | — |
| Book appointments | — | — | ✓ | — | — | — | — | — |
| Attach documents | — | — | ✓ | ✓ | — | ✓ | ✓ | ✓ |
| Request data deletion | — | ✓ | — | — | — | — | — | — |
| Create invoice | — | — | — | — | ✓ | — | — | — |
| Record payment against invoice | — | — | — | — | ✓ | — | — | — |
| View accounts queue | — | — | — | — | ✓ | — | — | — |
| View financial reports | — | ✓ | — | — | ✓ | — | — | — |
| Authorise invoice reversal | — | ✓ | — | — | — | — | — | — |

### RBAC Implementation

Roles are stored on the `users.role` column. Laravel Gates and Policies enforce permissions:

- **Gates** handle simple boolean checks (e.g., `can('manage-staff')`).
- **Policies** handle resource-level checks (e.g., `PatientRecordPolicy::update()` verifies the user's department matches `current_department_id`).

The `RBACMiddleware` checks the route's required permission against the authenticated user's role before the controller is invoked. Resource-level checks happen inside the Policy, called from the controller.

**Department scope enforcement:** A `DepartmentScopeMiddleware` applied to department-facing routes ensures that `GET /api/v1/department/queue` only returns records where `current_department_id = auth()->user()->department_id`.


## Scalability and Deployment Considerations

### Horizontal Scaling

| Component | Scaling Strategy |
|---|---|
| Laravel API | Stateless (JWT auth, Redis sessions) — scale horizontally behind a load balancer |
| Queue Workers | Multiple `php artisan queue:work` processes; scale worker count per queue priority |
| Soketi | Stateless with Redis adapter for shared channel state; scale horizontally |
| Redis | Redis Cluster or managed service (ElastiCache) for HA |
| MySQL | Primary + read replicas; tenant DBs can be sharded across multiple MySQL instances |
| S3 | Managed service; inherently scalable |

### Tenant Provisioning (< 60 seconds SLA)

Tenant provisioning is handled by a high-priority queue job (`TenantProvisioningJob`):

1. Create tenant record in central DB.
2. Create tenant database.
3. Run tenant migrations.
4. Seed default data (roles, default workflow template).
5. Create Hospital Admin user account.
6. Dispatch onboarding email.
7. Update tenant status to `ACTIVE`.

The entire sequence is wrapped in a transaction. If any step fails, the tenant is marked `FAILED` and the Super Admin is notified.

### Caching Strategy

- **Department patient queue** (`GET /api/v1/department/queue`): Cached in Redis with 30-second TTL. Cache is invalidated immediately when a patient record is routed to or from the department.
- **Workflow templates**: Cached per-tenant with 5-minute TTL. Invalidated on template update.
- **Feature flags**: Cached in Redis with 1-minute TTL. Invalidated on Super Admin update.
- **User permissions**: Cached per-user JWT with the token's remaining TTL.

Cache keys are namespaced by tenant ID: `tenant:{tenant_id}:dept_queue:{dept_id}`.

### Performance Targets

| Metric | Target | Strategy |
|---|---|---|
| API p95 response time | < 500ms | Redis caching, DB read replicas, query optimization |
| Patient list load (mobile, 4G) | < 3 seconds | Paginated API (20 records/page), CDN for static assets |
| Real-time notification delivery | < 5 seconds | Queue workers with high-priority channel, Soketi WebSocket |
| Tenant provisioning | < 60 seconds | Async queue job, pre-warmed DB connection pool |
| Concurrent users per tenant | 1,000 | Horizontal API scaling, Redis-backed sessions |

### Deployment Architecture

```
                    ┌─────────────────────────────────────┐
                    │         Load Balancer (HTTPS)        │
                    └──────────────┬──────────────────────┘
                                   │
              ┌────────────────────┼────────────────────┐
              ▼                    ▼                    ▼
        ┌──────────┐         ┌──────────┐         ┌──────────┐
        │ Laravel  │         │ Laravel  │         │ Laravel  │
        │ API      │         │ API      │         │ API      │
        │ Instance │         │ Instance │         │ Instance │
        └──────────┘         └──────────┘         └──────────┘
              │                    │                    │
              └────────────────────┼────────────────────┘
                                   │
              ┌────────────────────┼────────────────────┐
              ▼                    ▼                    ▼
        ┌──────────┐         ┌──────────┐         ┌──────────┐
        │  Queue   │         │  Soketi  │         │  Redis   │
        │  Worker  │         │  WS      │         │  Cluster │
        └──────────┘         └──────────┘         └──────────┘
```

**Containerisation:** All components are containerised (Docker). Orchestration via Kubernetes or Docker Swarm. Each tenant's database runs in a shared MySQL cluster with logical database separation (not separate MySQL instances per tenant, unless tenant count exceeds ~500).

**Zero-downtime deployments:** Rolling deployments via Kubernetes. Database migrations are run as a pre-deployment step using `php artisan migrate --force`. Backward-compatible migrations only (no destructive column drops in the same release as the code change).


## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

**Property Reflection:** After analyzing all 60+ acceptance criteria, the following redundancies were identified and consolidated:
- 1.3 (tenant data isolation) subsumes 1.4 (403 rejection) and 3.1 (admin scoping) and 10.4 (role+tenant restriction) — consolidated into Property 1.
- 4.5 / 6.1 / 8.3 all describe the same routing-on-complete behavior — consolidated into Property 5.
- 7.4 and 7.5 (notes attached before routing) are parallel properties — consolidated into Property 8.
- 8.4 and 9.5 (anomaly detection for missing notes) are parallel properties — consolidated into Property 9.
- 10.3 and 10.5 (RBAC enforcement + audit logging) are combined into Property 10.
- 12.1 and 12.2 (audit log completeness for different action types) are combined into Property 12.
- 5.8 / 13.2 / 13.3 (offline queue and sync) are consolidated into Property 15.
- 16.1 / 16.7 (PENDING_PAYMENT exclusion from clinical queues and accounts queue access control) are consolidated into Property 33.
- 16.3 and 16.4 (new vs returning patient invoice item rules) are consolidated into Property 36.
- 16.12 (audit log for invoice/payment events) is covered by existing Property 27.

---

### Property 1: Tenant Data Isolation

*For any* two users belonging to different tenants (hospitals), a request authenticated as user A should never return, modify, or expose data belonging to tenant B. Any cross-tenant data access attempt should result in an HTTP 403 response.

**Validates: Requirements 1.3, 1.4, 3.1, 10.4**

---

### Property 2: Tenant Management Restricted to Super Admin

*For any* user with a role other than Super_Admin, any request to create, suspend, activate, or delete a tenant should return HTTP 403.

**Validates: Requirements 1.5**

---

### Property 3: Session Invalidation on Suspension or Deactivation

*For any* set of active JWT tokens belonging to users of a tenant (or a specific user), suspending that tenant (or deactivating that user) should cause all subsequent API requests using those tokens to be rejected with HTTP 401.

**Validates: Requirements 2.3, 3.5**

---

### Property 4: Audit Log Filter Correctness

*For any* combination of audit log filter parameters (date range, hospital, action type), all entries returned by the query should satisfy every applied filter criterion — no entry outside the filter bounds should appear in the results.

**Validates: Requirements 2.4**

---

### Property 5: Feature Flag Enforcement

*For any* feature flag that is disabled by the Super Admin, any request to the corresponding feature endpoint by any non-Super_Admin user should be rejected (HTTP 403 or 404).

**Validates: Requirements 2.6**

---

### Property 6: Department Deletion Guard

*For any* department that has one or more active patient records currently assigned to it, an attempt to delete that department should be rejected with an error message that includes the count of active records.

**Validates: Requirements 3.7**

---

### Property 7: Department Queue Scoping

*For any* authenticated department staff member, the patient queue endpoint should return only patient records where `current_department_id` matches the authenticated user's `department_id`. No records from other departments should appear.

**Validates: Requirements 4.1**

---

### Property 8: Journey History Completeness and Ordering

*For any* patient record that has undergone N routing or action events, the journey history endpoint should return exactly N events sorted in ascending chronological order (by `created_at`).

**Validates: Requirements 4.3, 7.2**

---

### Property 9: Workflow Step Advancement on Completion

*For any* patient record at step position K in a workflow with M total steps (where K < M), marking the current department step as complete should advance `current_step_order` to K+1 and update `current_department_id` to the department defined at step K+1 in the workflow template.

**Validates: Requirements 4.5, 6.1, 8.3**

---

### Property 10: Registration Validation

*For any* patient registration payload that is missing one or more required fields (full name, date of birth, gender, or contact phone number), the API should return a validation error that identifies each missing field by name.

**Validates: Requirements 5.2**

---

### Property 11: File Upload Size Enforcement

*For any* file upload request where the file size exceeds 10 MB, the API should reject the upload with a validation error. For any file at or below 10 MB of an accepted type (PDF or image), the upload should succeed.

**Validates: Requirements 5.5**

---

### Property 12: Patient ID Uniqueness

*For any* set of patient registrations within a tenant, all generated `patient_number` values should be unique — no two patients in the same tenant should share the same patient number.

**Validates: Requirements 5.6**

---

### Property 13: Signed URL Expiry Enforcement

*For any* document access request, the returned pre-signed URL should have an expiry time no greater than 15 minutes from the time of generation. A request using an expired signed URL should be rejected by the storage service.

**Validates: Requirements 5.7, 15.3**

---

### Property 14: Offline Queue Persistence and Sync

*For any* patient registration or data operation submitted while the mobile app is offline, the operation should be stored in the local encrypted sync queue. Upon connectivity restoration, all queued operations should be submitted to the server and marked as synced.

**Validates: Requirements 5.8, 13.2, 13.3**

---

### Property 15: Journey Event Append-Only Invariant

*For any* routing or action event on a patient record, the total count of journey events for that record should increase by exactly 1, and the new event should contain a non-null `created_at` timestamp. The count of existing events should never decrease.

**Validates: Requirements 6.2, 6.6**

---

### Property 16: Department Edit Authorization

*For any* staff member attempting to edit (add notes, complete step, attach documents) a patient record where `current_department_id` does not match the staff member's `department_id`, the request should be rejected with HTTP 403.

**Validates: Requirements 6.5**

---

### Property 17: In-Transit Edit Prevention

*For any* patient record with status `IN_TRANSIT`, any edit attempt (notes, completion, routing) should be rejected with HTTP 409.

**Validates: Requirements 6.4**

---

### Property 18: Clinical Notes Attached Before Routing

*For any* routing action from a Doctor to the Pharmacy department, the resulting journey event's metadata should contain non-empty prescription notes. For any routing action from a Doctor to the Laboratory department, the resulting journey event's metadata should contain non-empty investigation request notes.

**Validates: Requirements 7.4, 7.5**

---

### Property 19: Missing Notes Anomaly Detection

*For any* patient record that arrives at the Pharmacy department without prescription notes in its most recent routing event metadata, the API response should include a warning flag AND an audit log entry with action `anomaly.missing_prescription` should be created. The same applies to Laboratory arrivals without investigation request notes (`anomaly.missing_investigation`).

**Validates: Requirements 8.4, 9.5**

---

### Property 20: Lab Results Return Routing

*For any* patient record routed from department D to the Laboratory department, when the Lab Technician marks results as complete, the record should be routed back to department D (the originating department).

**Validates: Requirements 9.4**

---

### Property 21: JWT Authentication Enforcement

*For any* protected API endpoint, a request without a valid JWT (absent, malformed, expired, or belonging to a suspended tenant) should return HTTP 401.

**Validates: Requirements 10.1, 10.2**

---

### Property 22: Role-Based Access Control Enforcement

*For any* user role and any API endpoint that requires a different role, the request should return HTTP 403 AND an audit log entry should be created recording the unauthorized access attempt.

**Validates: Requirements 10.3, 10.5**

---

### Property 23: Password Complexity Validation

*For any* password string that fails any of the complexity rules (fewer than 10 characters, no uppercase letter, no lowercase letter, no digit, or no special character), the system should reject it with a descriptive validation error identifying which rule was violated.

**Validates: Requirements 10.6**

---

### Property 24: Account Lockout After Failed Attempts

*For any* user account, after exactly 5 consecutive failed login attempts within a 10-minute window, the account should be locked (subsequent login attempts rejected) and a notification should be dispatched to the Hospital Admin.

**Validates: Requirements 10.7**

---

### Property 25: Appointment SMS Reminder Dispatch

*For any* appointment scheduled within the next 24 hours where `sms_reminder_sent = false`, the reminder scheduler should dispatch an SMS to the patient's registered phone number and set `sms_reminder_sent = true`. Running the scheduler again should not dispatch a duplicate SMS for the same appointment.

**Validates: Requirements 11.2**

---

### Property 26: Notification Failure Handling

*For any* notification that fails delivery on all 3 retry attempts, the notification record's status should be set to `failed` AND an audit log entry with action `notification.delivery_failed` should be created.

**Validates: Requirements 11.4**

---

### Property 27: Audit Log Completeness

*For any* create, update, delete, or route action performed on a patient record, an audit log entry should exist containing the acting user's ID, role, timestamp, and a description of the action. For any authentication event (success, failure, or lockout), an audit log entry should exist containing the user ID, IP address, and timestamp.

**Validates: Requirements 12.1, 12.2**

---

### Property 28: Audit Log Immutability

*For any* audit log entry and any user role (including Super_Admin), any attempt to modify or delete the entry via the API should be rejected with HTTP 403 or HTTP 405.

**Validates: Requirements 12.3**

---

### Property 29: Sync Conflict Detection

*For any* sync operation where the server returns a 409 Conflict response (indicating the record was modified server-side since the local copy was created), the local sync queue item should be marked as `conflict` and surfaced for manual resolution rather than silently overwriting server data.

**Validates: Requirements 13.4**

---

### Property 30: PII Encryption at Rest

*For any* patient record stored in the database, the personally identifiable fields (full name, date of birth, phone number, address) should not be stored as plaintext — the raw database column values should be ciphertext, not the original values.

**Validates: Requirements 15.1**

---

### Property 31: Patient Data Anonymisation Completeness

*For any* patient data deletion request processed by the system, the patient record's PII fields (full name, date of birth, phone, address) should be replaced with placeholder values, while all journey events, lab results, dispensing records, and audit log entries for that patient should remain unchanged and intact.

**Validates: Requirements 15.5**

---

### Property 32: Past Appointment Rejection

*For any* appointment booking request where the `scheduled_at` datetime is before the current server time, the request should be rejected with a validation error.

**Validates: Requirements 7.9**

---

### Property 33: Payment Gate Enforcement

*For any* Patient_Record with status `pending_payment`, any request to the department queue endpoint (`GET /api/v1/department/queue`) authenticated with a clinical role (Doctor, Pharmacist, Lab_Technician, Department_Staff) should not include that record in the response. Additionally, any request to `GET /api/v1/accounts/queue` by a non-Accountant role should be rejected with HTTP 403.

**Validates: Requirements 16.1, 16.7**

---

### Property 34: Invoice Settlement Advances Status

*For any* Patient_Record with status `pending_payment`, after an Accountant successfully calls `POST /api/v1/invoices/{id}/settle` on a fully paid Invoice linked to that record, the Patient_Record's status should be updated to `active` and `current_department_id` should be set to the first clinical department in the configured workflow sequence.

**Validates: Requirements 16.6**

---

### Property 35: Invoice and Payment Immutability

*For any* Invoice with status `settled` and any `invoice_payments` record, any attempt to update or delete either record via the API should be rejected with HTTP 403 or HTTP 405. Corrections must be made via an authorised reversal entry recorded as a separate Audit_Log event, not by modifying the original records.

**Validates: Requirements 16.13**

---

### Property 36: New vs Returning Patient Invoice Rule

*For any* Invoice created for a new patient (`is_new_patient = true`), the Invoice must contain Invoice_Items for both the registration/card fee and the consultation fee — an Invoice missing either item should be rejected with a validation error. *For any* Invoice created for a returning patient (`is_new_patient = false`), the Invoice must contain an Invoice_Item for the consultation fee — an Invoice missing this item should be rejected with a validation error.

**Validates: Requirements 16.3, 16.4**

---

### Property 37: Outstanding Balance Enforcement

*For any* Invoice where `amount_paid` is less than `total_amount`, any attempt by an Accountant to call `POST /api/v1/invoices/{id}/settle` should be rejected with a descriptive error that includes the outstanding balance (`total_amount - amount_paid`). The Invoice status should remain unchanged.

**Validates: Requirements 16.8**


## Error Handling

### Error Classification

| Category | HTTP Status | Error Code | Description |
|---|---|---|---|
| Authentication failure | 401 | `UNAUTHENTICATED` | Missing, invalid, or expired JWT |
| Authorization failure | 403 | `FORBIDDEN` | Valid JWT but insufficient permissions |
| Tenant not found | 404 | `TENANT_NOT_FOUND` | Subdomain/header resolves to no tenant |
| Tenant suspended | 403 | `TENANT_SUSPENDED` | Tenant exists but is suspended |
| Validation error | 422 | `VALIDATION_ERROR` | Request payload fails validation |
| Resource not found | 404 | `NOT_FOUND` | Requested resource does not exist |
| Conflict | 409 | `CONFLICT` | Edit attempted on IN_TRANSIT record or sync conflict |
| Business rule violation | 422 | `BUSINESS_RULE_VIOLATION` | e.g., deleting dept with active records |
| Server error | 500 | `INTERNAL_ERROR` | Unexpected server-side failure |
| Service unavailable | 503 | `SERVICE_UNAVAILABLE` | Queue/DB temporarily unavailable |

### Error Response Format

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": {
      "full_name": ["The full name field is required."],
      "date_of_birth": ["The date of birth field is required."]
    }
  }
}
```

### Graceful Degradation

- **WebSocket unavailable:** The app falls back to polling the department queue endpoint every 10 seconds.
- **SMS gateway unavailable:** SMS jobs are retried with exponential backoff. Failures are logged; the appointment is not cancelled.
- **Redis unavailable:** The application falls back to database-backed queues and disables caching (with a performance warning logged). This is a degraded mode, not a failure.
- **S3 unavailable:** File upload/download endpoints return 503. Patient record creation without files continues normally.

### Tenant Provisioning Failure

If any step in the tenant provisioning job fails, the job catches the exception, marks the tenant as `FAILED`, logs the error, and notifies the Super Admin. The failed tenant record can be retried or deleted from the Super Admin dashboard.


## Testing Strategy

### Overview

The testing strategy uses a dual approach: **property-based tests** for universal correctness properties and **example-based unit/integration tests** for specific scenarios, edge cases, and infrastructure verification.

### Property-Based Testing

**Library:** PHPUnit + [eris](https://github.com/giorgiosironi/eris) (PHP property-based testing library) for backend properties. For Flutter, [dart_test](https://pub.dev/packages/test) with custom generators.

**Configuration:** Each property test runs a minimum of **100 iterations** with randomized inputs.

**Tag format:** Each property test is tagged with a comment:
```
// Feature: hospital-health-management-system, Property N: <property_text>
```

**Properties to implement as PBT (from Correctness Properties section):**

| Property | Test Focus | Key Generators |
|---|---|---|
| P1: Tenant Data Isolation | Cross-tenant query isolation | Random tenant pairs, random resource types |
| P3: Session Invalidation | Token rejection after suspension/deactivation | Random active session sets |
| P4: Audit Log Filter Correctness | Filter result accuracy | Random filter combinations, random log entries |
| P6: Department Deletion Guard | Active record count in error | Random departments with 1..N active records |
| P7: Department Queue Scoping | Queue only shows own dept records | Random staff/department assignments |
| P8: Journey History Completeness | Event count and ordering | Random journey lengths (1..50 events) |
| P9: Workflow Step Advancement | Step counter and dept ID update | Random workflow templates (2..10 steps) |
| P10: Registration Validation | Missing field detection | Random subsets of required fields |
| P11: File Size Enforcement | Accept/reject boundary | Random file sizes around 10 MB boundary |
| P12: Patient ID Uniqueness | No duplicate patient numbers | Random batch registrations (10..100) |
| P13: Signed URL Expiry | URL TTL <= 15 minutes | Random request times |
| P15: Journey Event Append-Only | Event count never decreases | Random action sequences |
| P16: Department Edit Authorization | 403 for wrong department | Random staff/record department mismatches |
| P17: In-Transit Edit Prevention | 409 for IN_TRANSIT records | Random edit types |
| P18: Clinical Notes Attached | Notes present in routing event | Random prescription/investigation content |
| P19: Missing Notes Anomaly | Warning flag + audit log | Records with/without notes |
| P20: Lab Return Routing | Routes back to originating dept | Random originating departments |
| P21: JWT Enforcement | 401 for invalid tokens | Random token states (expired, malformed, absent) |
| P22: RBAC Enforcement | 403 + audit log for wrong role | Random role/endpoint combinations |
| P23: Password Complexity | Reject non-compliant passwords | Random strings violating each rule |
| P24: Account Lockout | Lock after 5 failures in 10 min | Random failure sequences |
| P25: SMS Reminder Idempotency | No duplicate reminders | Random appointment sets |
| P26: Notification Failure Handling | Failed status + audit log | Simulated delivery failures |
| P27: Audit Log Completeness | Entry exists for every action | Random action sequences |
| P28: Audit Log Immutability | 403/405 for all modification attempts | Random roles, random entries |
| P29: Sync Conflict Detection | Conflict flag on 409 response | Random sync payloads |
| P30: PII Encryption at Rest | Ciphertext in DB columns | Random patient data |
| P31: Anonymisation Completeness | PII replaced, clinical data intact | Random patient records |
| P32: Past Appointment Rejection | Reject past dates | Random past datetimes |
| P33: Payment Gate Enforcement | PENDING_PAYMENT excluded from clinical queues; 403 for non-Accountant on accounts queue | Random clinical roles, random PENDING_PAYMENT records |
| P34: Invoice Settlement Advances Status | Status → active, dept set to first clinical dept after invoice settled | Random PENDING_PAYMENT records, random workflow templates |
| P35: Invoice/Payment Immutability | 403/405 for any update/delete on settled invoices or invoice_payments | Random roles, random settled invoices |
| P36: New vs Returning Patient Invoice Rule | New patient invoice requires registration + consultation items; returning requires consultation item | Random is_new_patient values, random invoice item combinations |
| P37: Outstanding Balance Enforcement | Settle rejected when amount_paid < total_amount; error includes outstanding balance | Random invoice totals and partial payment amounts |

### Unit Tests (Example-Based)

Unit tests cover specific scenarios, integration points, and edge cases not covered by property tests:

- **Authentication flow:** Login success, login failure, token refresh, logout.
- **Tenant provisioning:** Full provisioning sequence with mocked DB and email.
- **Workflow template CRUD:** Create, update, clone, set-as-default.
- **Doctor routing actions:** Route to Pharmacy, route to Lab, mark admitted.
- **Dispensing record creation:** All required fields stored correctly.
- **Lab result recording:** All required fields stored correctly.
- **Appointment booking:** Valid future date succeeds, confirmation SMS queued.
- **Feature flag toggling:** Disabled flag hides endpoint.
- **Audit log export:** CSV format and content correctness.
- **Data deletion:** PII anonymisation with correct placeholder values.
- **Department activity reports:** Correct aggregation of throughput and wait times.

### Integration Tests

Integration tests verify end-to-end flows against a real (test) database and mocked external services:

- **Full patient journey:** Registration → Department 1 → Doctor → Pharmacy → Discharge.
- **Offline sync flow:** Queue operations offline → restore connectivity → verify server state.
- **Real-time notification delivery:** Route patient → verify WebSocket event received within 5 seconds.
- **Tenant provisioning timing:** Measure end-to-end provisioning time (target < 60 seconds).
- **SMS appointment reminder:** Scheduler run → verify SMS job dispatched for due appointments.
- **Hospital suspension:** Suspend tenant → verify all active tokens rejected.

### Smoke Tests

Smoke tests verify configuration and infrastructure setup:

- TLS 1.2+ enforced on all endpoints.
- SQLCipher encryption enabled for mobile local storage.
- Audit log retention policy configured (no auto-delete).
- S3 bucket policies deny public access.
- Redis connection healthy.
- Queue workers running and processing jobs.

### Flutter Testing

- **Unit tests:** Repository layer, use cases, sync queue logic, conflict detection.
- **Widget tests:** Registration form validation, offline indicator display, patient queue rendering.
- **Property tests (dart_test):** Offline queue persistence (P14), sync conflict detection (P29), password validation (P23 — client-side).
- **Integration tests:** Full registration flow with mock API, file upload flow, WebSocket notification receipt.

