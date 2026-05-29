# HHMS API Documentation

Base URL: `https://{subdomain}.hhms.app/api/v1`

All requests must include the `X-Tenant-ID` header (or use the subdomain) to identify the hospital tenant. All responses use the standard envelope format described below.

---

## Authentication Flow

### 1. Login

```
POST /auth/login
```

**Request:**
```json
{
  "email": "doctor@citygeneral.com",
  "password": "SecurePass1!"
}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "refresh_token": "def50200...",
    "token_type": "bearer",
    "expires_in": 3600
  }
}
```

The JWT payload contains: `sub` (user ID), `tenant_id`, `role`, `token_version`, `iat`, `exp`.

### 2. Authenticated Requests

Include the access token in the `Authorization` header:

```
Authorization: Bearer <access_token>
```

### 3. Refresh Token

```
POST /auth/refresh
```

**Request:**
```json
{ "refresh_token": "def50200..." }
```

**Response (200):** Same structure as login.

### 4. Logout

```
POST /auth/logout
```

Invalidates the refresh token. No request body required.

### 5. Password Reset

```
POST /auth/password/reset
```

**Request:**
```json
{ "email": "doctor@citygeneral.com" }
```

---

## Rate Limiting

Rate limits are configured per tenant by the Super Admin. Default: **60 requests/minute** per IP. When exceeded, the API returns:

```json
{
  "success": false,
  "error": {
    "code": "TOO_MANY_REQUESTS",
    "message": "Rate limit exceeded. Retry after 60 seconds."
  }
}
```

HTTP status: `429 Too Many Requests`. The `Retry-After` header indicates when to retry.

---

## Standard Response Envelope

**Success:**
```json
{
  "success": true,
  "data": { ... },
  "message": "Operation completed",
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 100,
      "last_page": 5
    }
  }
}
```

**Error:**
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

---

## Module: Super Admin

All endpoints require Super Admin authentication (separate from tenant JWT).

### List Hospitals

```
GET /admin/hospitals
```

Query params: `status` (active|suspended|inactive), `search`, `page`, `per_page`

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "name": "City General Hospital",
      "subdomain": "citygeneral",
      "status": "active",
      "plan": { "id": "uuid", "name": "Standard" },
      "created_at": "2024-01-01T00:00:00Z"
    }
  ],
  "meta": { "pagination": { "current_page": 1, "per_page": 20, "total": 5, "last_page": 1 } }
}
```

### Create Hospital

```
POST /admin/hospitals
```

**Request:**
```json
{
  "name": "City General Hospital",
  "subdomain": "citygeneral",
  "plan_id": "uuid",
  "admin_email": "admin@citygeneral.com",
  "admin_name": "John Admin"
}
```

**Response (201):** Returns the created tenant object.

### Suspend / Activate Hospital

```
PATCH /admin/hospitals/{id}/suspend
PATCH /admin/hospitals/{id}/activate
```

No request body. Suspension immediately invalidates all user sessions.

### Feature Flags

```
GET  /admin/feature-flags
PUT  /admin/feature-flags/{key}
```

**Update request:**
```json
{ "enabled": true }
```

Available keys: `feature_sms_notifications`, `feature_lab_module`, `feature_pharmacy_module`, `feature_appointments`, `feature_mobile_app`

### System Settings

```
PUT /admin/settings
```

**Request:**
```json
{
  "mobile_app_min_version": "2.0.0",
  "api_rate_limit": 120
}
```

### Audit Logs (System-wide)

```
GET /admin/audit-logs
GET /admin/audit-logs/export
```

Query params: `start_date`, `end_date`, `action`, `user_id`, `page`, `per_page`

---

## Module: Hospital Admin

Requires `hospital_admin` role.

### Departments

```
GET    /departments          — List departments (paginated, 20/page)
POST   /departments          — Create department
PUT    /departments/{id}     — Update department
DELETE /departments/{id}     — Delete department (guarded: fails if active records exist)
```

**Create/Update request:**
```json
{
  "name": "Cardiology",
  "code": "CARDIO",
  "type": "clinical",
  "is_active": true
}
```

Department types: `clinical`, `pharmacy`, `laboratory`, `front_desk`, `triage`, `ward`, `icu_ae`, `accounts`, `other`

### Staff

```
GET    /staff                — List staff (paginated, 20/page)
POST   /staff                — Create staff account
PUT    /staff/{id}           — Update staff account
PATCH  /staff/{id}/deactivate — Deactivate staff (invalidates all sessions)
```

**Create request:**
```json
{
  "name": "Dr. Jane Smith",
  "email": "jane@citygeneral.com",
  "phone": "+2348012345678",
  "password": "SecurePass1!",
  "role": "doctor",
  "department_id": "uuid"
}
```

Roles: `hospital_admin`, `doctor`, `front_desk_officer`, `accountant`, `pharmacist`, `lab_technician`, `department_staff`

### Workflow Templates

```
GET  /workflows/templates        — List templates (cached 5 min)
POST /workflows/templates        — Create template (invalidates cache)
PUT  /workflows/templates/{id}   — Update template (invalidates cache)
```

**Create request:**
```json
{
  "name": "Standard OPD",
  "description": "Standard outpatient journey",
  "is_default": true,
  "steps": [
    { "department_id": "uuid-triage", "step_order": 1, "is_terminal": false },
    { "department_id": "uuid-doctor", "step_order": 2, "is_terminal": false },
    { "department_id": "uuid-pharmacy", "step_order": 3, "is_terminal": true }
  ]
}
```

### Reports

```
GET /reports/department-activity  — Throughput, wait times, pending counts
GET /reports/financial            — Revenue, outstanding invoices, payment breakdown
```

Query params: `start_date`, `end_date`, `department_id`

### Audit Logs (Hospital-scoped)

```
GET /audit-logs         — Paginated audit logs
GET /audit-logs/export  — CSV export
```

---

## Module: Auth

```
POST /auth/login
POST /auth/refresh
POST /auth/logout
POST /auth/password/reset
```

See [Authentication Flow](#authentication-flow) above.

---

## Module: Patients

Requires JWT authentication.

### Register Patient

```
POST /patients
```

**Request:**
```json
{
  "full_name": "John Doe",
  "date_of_birth": "1985-06-15",
  "gender": "male",
  "phone": "+2348012345678",
  "address": "12 Main Street, Lagos",
  "next_of_kin_name": "Jane Doe",
  "next_of_kin_phone": "+2348087654321",
  "chief_complaint": "Chest pain",
  "hmo_provider_name": "NHIS",
  "hmo_policy_number": "NHIS-12345",
  "photo_object_key": "uploads/abc123.jpg"
}
```

Required fields: `full_name`, `date_of_birth`, `gender`, `phone`, `chief_complaint`

**Response (201):** Returns patient and patient_record objects. Record status is `pending_payment`.

### Get Patient

```
GET /patients/{id}
```

Returns patient with decrypted PII fields.

### Patient Journey

```
GET /patients/{id}/journey
```

Returns all journey events sorted chronologically.

### Attach Document

```
POST /patients/{id}/documents
```

**Request:**
```json
{
  "patient_record_id": "uuid",
  "document_type": "registration",
  "object_key": "uploads/doc123.pdf",
  "original_filename": "id_card.pdf",
  "file_size_bytes": 204800
}
```

Document types: `registration`, `lab_result`, `prescription`, `other`

### Get Document URL

```
GET /patients/{id}/documents/{docId}/url
```

Returns a 15-minute signed URL for secure document download.

### Anonymise Patient

```
POST /patients/{id}/anonymise
```

Requires `hospital_admin` role. Replaces PII with placeholders while retaining clinical history.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "patient_number": "P-20240101-0001",
    "full_name": "[ANONYMISED]",
    "date_of_birth": "1900-01-01",
    "phone": "[ANONYMISED]",
    "address": "[ANONYMISED]",
    "is_anonymised": true
  }
}
```

### Pre-signed Upload URL

```
GET /upload/presigned-url?filename=photo.jpg&content_type=image/jpeg&file_size=204800
```

Returns a pre-signed R2 PUT URL for direct file upload (bypasses API server).

---

## Module: Department Workflow

### Department Queue

```
GET /department/queue
```

Returns patient records assigned to the authenticated user's department. Excludes `pending_payment` records. Cached with 30-second TTL.

**Response (200):**
```json
{
  "success": true,
  "data": [ { "id": "uuid", "patient": { ... }, "status": "active", ... } ],
  "meta": {
    "pagination": { "current_page": 1, "per_page": 20, "total": 8, "last_page": 1 },
    "department_id": "uuid"
  }
}
```

### Add Note

```
POST /patient-records/{id}/notes
```

**Request:**
```json
{ "notes": "Patient reports worsening symptoms since yesterday." }
```

### Complete Step

```
POST /patient-records/{id}/complete
```

Marks the current step complete and routes the patient to the next department in the workflow.

**Request:**
```json
{ "notes": "Triage complete. Vitals stable." }
```

### Route to Specific Department (Doctor only)

```
POST /patient-records/{id}/route
```

**Request:**
```json
{
  "department_id": "uuid-pharmacy",
  "prescription_notes": "Amoxicillin 500mg TDS x 5 days",
  "notes": "Routing to pharmacy"
}
```

### Admit Patient (Doctor only)

```
POST /patient-records/{id}/admit
```

**Request:**
```json
{ "notes": "Patient requires inpatient observation." }
```

---

## Module: Accounts

Requires `accountant` role.

### Accounts Queue

```
GET /accounts/queue
```

Returns all `pending_payment` patient records.

### Create Invoice

```
POST /patient-records/{id}/invoices
```

**Request:**
```json
{
  "is_new_patient": true,
  "items": [
    { "service_name": "Registration Fee", "unit_price": 500.00, "quantity": 1 },
    { "service_name": "Consultation Fee", "unit_price": 2000.00, "quantity": 1 }
  ]
}
```

New patients require both registration and consultation items. Returning patients require only consultation.

### List Invoices

```
GET /patient-records/{id}/invoices
```

### Record Payment

```
POST /invoices/{id}/payments
```

**Request:**
```json
{
  "payment_type": "cash",
  "amount": 2500.00,
  "receipt_reference": "RCP-001"
}
```

For HMO payments:
```json
{
  "payment_type": "hmo_insurance",
  "amount": 2500.00,
  "hmo_provider_name": "NHIS",
  "hmo_policy_number": "NHIS-12345",
  "hmo_claim_reference": "CLM-2024-001"
}
```

Payment types: `cash`, `pos_card`, `hmo_insurance`

### Settle Invoice

```
POST /invoices/{id}/settle
```

Validates `amount_paid >= total_amount`. On success, updates patient record status to `active` and routes to first clinical department.

### Generate Receipt

```
GET /invoices/{id}/receipt
```

Returns a PDF receipt for a settled invoice.

---

## Module: Clinical

### Appointments (Doctor only)

```
POST   /appointments       — Book appointment
GET    /appointments       — List appointments (paginated, 20/page)
PATCH  /appointments/{id}  — Update appointment
```

**Book request:**
```json
{
  "patient_id": "uuid",
  "patient_record_id": "uuid",
  "scheduled_at": "2024-06-15T10:00:00Z",
  "notes": "Follow-up for hypertension management"
}
```

`scheduled_at` must be in the future. Past dates return HTTP 422.

### Dispensing (Pharmacist only)

```
POST /patient-records/{id}/dispensing
```

**Request:**
```json
{
  "drug_name": "Amoxicillin",
  "dosage": "500mg",
  "quantity": "15 capsules",
  "dispensed_at": "2024-01-15T14:30:00Z"
}
```

If no prescription notes are found on the record, a warning is returned and an anomaly is logged.

### Lab Results (Lab Technician only)

```
POST /patient-records/{id}/lab-results
```

**Request:**
```json
{
  "test_name": "Full Blood Count",
  "result_value": "WBC: 7.2 x10^9/L, RBC: 4.8 x10^12/L",
  "reference_range": "WBC: 4.0-11.0, RBC: 4.5-5.5",
  "resulted_at": "2024-01-15T15:00:00Z",
  "result_document_key": "uploads/fbc_result.pdf"
}
```

On success, the patient record is automatically routed back to the originating doctor's department.

---

## Module: Notifications

### Register Device Token (FCM)

```
POST /device-tokens
```

**Request:**
```json
{
  "token": "fcm-device-token-string",
  "platform": "android"
}
```

---

## Error Code Reference

| HTTP Status | Code | Description |
|---|---|---|
| 400 | `BAD_REQUEST` | Invalid request parameters |
| 400 | `ALREADY_ANONYMISED` | Patient record already anonymised |
| 400 | `ALREADY_SUSPENDED` | Hospital already suspended |
| 400 | `DEPARTMENT_HAS_ACTIVE_RECORDS` | Cannot delete department with active records |
| 401 | `UNAUTHORIZED` | Missing, invalid, or expired JWT |
| 403 | `FORBIDDEN` | Authenticated but insufficient permissions |
| 404 | `NOT_FOUND` | Resource not found |
| 409 | `CONFLICT` | Concurrent edit conflict (record in transit) |
| 422 | `VALIDATION_ERROR` | Request validation failed |
| 422 | `PAST_APPOINTMENT` | Appointment date is in the past |
| 429 | `TOO_MANY_REQUESTS` | Rate limit exceeded |
| 500 | `INTERNAL_ERROR` | Unexpected server error |
| 500 | `REGISTRATION_ERROR` | Patient registration failed |
| 500 | `ANONYMISATION_ERROR` | Patient anonymisation failed |
