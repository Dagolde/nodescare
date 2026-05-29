# Task 7.1 Implementation: Patient Registration API

## Overview
Implemented complete patient registration API with all required endpoints as specified in the Hospital Health Management System design document.

## Implementation Summary

### 1. Request Validation Classes

#### RegisterPatientRequest (`app/Http/Requests/Patient/RegisterPatientRequest.php`)
- Validates all required fields: full_name, date_of_birth, gender, phone, chief_complaint
- Validates optional fields: address, next_of_kin details, HMO information, photo_object_key
- Enforces date_of_birth must be before today
- Enforces gender enum: male, female, other
- Provides custom validation error messages

#### AttachDocumentRequest (`app/Http/Requests/Patient/AttachDocumentRequest.php`)
- Validates patient_record_id exists in database
- Validates document_type enum: registration, lab_result, prescription, other
- Validates object_key, original_filename, file_size_bytes
- Enforces 10 MB maximum file size
- Provides custom validation error messages

### 2. PatientController (`app/Http/Controllers/Api/V1/PatientController.php`)

Implements all 6 required endpoints:

#### POST /api/v1/patients - Register New Patient
**Functionality:**
- Validates required fields (full_name, DOB, gender, phone, chief_complaint)
- Encrypts PII fields using Laravel Crypt (AES-256) via Patient model accessors
- Generates unique patient_number in format: P-YYYYMMDD-XXXX
  - P prefix for Patient
  - YYYYMMDD for current date
  - XXXX for 4-digit sequence number (auto-increments daily)
- Creates Patient record
- Creates PatientRecord with status 'pending_payment'
- Routes to Accounts department queue
- Creates PatientJourneyEvent for registration
- Uses database transaction for atomicity
- Returns 201 Created with patient and patient_record data

**Error Handling:**
- Returns 500 if Accounts department not found
- Rolls back transaction on any error
- Returns descriptive error messages

#### GET /api/v1/patients/{id} - Get Patient Record
**Functionality:**
- Retrieves patient by ID
- Returns patient with decrypted PII fields
- Includes related patient_records with current department and registrar
- Returns 404 if patient not found

**Response includes:**
- All patient demographics (decrypted)
- Patient number
- HMO information
- Photo object key
- All patient records

#### GET /api/v1/patients/{id}/journey - Get Full Journey History
**Functionality:**
- Retrieves all patient records for the patient
- Loads all journey events with relationships:
  - fromDepartment
  - toDepartment
  - performer (user who performed action)
- Flattens and sorts all journey events chronologically
- Returns complete journey history across all visits

**Response includes:**
- Patient ID and patient number
- Chronologically sorted journey events with full context

#### POST /api/v1/patients/{id}/documents - Attach Document
**Functionality:**
- Validates patient exists
- Creates PatientDocument record with:
  - patient_record_id
  - uploader_id (authenticated user)
  - document_type
  - object_key (R2 storage key)
  - original_filename
  - file_size_bytes
  - uploaded_at timestamp
- Creates PatientJourneyEvent for document attachment
- Uses database transaction
- Returns 201 Created with document data

**Error Handling:**
- Returns 404 if patient not found
- Validates document belongs to patient record
- Rolls back transaction on error

#### GET /api/v1/patients/{id}/documents/{docId}/url - Generate Signed URL
**Functionality:**
- Validates patient and document exist
- Verifies document belongs to patient (security check)
- Generates time-limited signed R2 URL (15-minute expiry)
- Uses Laravel Storage facade with S3 driver
- Returns signed URL with expiry timestamp

**Security:**
- Validates document ownership
- Returns 403 if document doesn't belong to patient
- Returns 404 if patient or document not found
- 15-minute URL expiry enforces access control

**Response includes:**
- Signed download URL
- Expiry timestamp (ISO 8601 format)
- Document metadata (id, filename, type, size)

#### GET /api/v1/upload/presigned-url - Generate Pre-signed Upload URL
**Functionality:**
- Validates filename, content_type, file_size
- Enforces 10 MB maximum file size
- Generates unique object key: uploads/{uniqid}_{timestamp}.{extension}
- Creates pre-signed PUT URL for direct R2 upload (15-minute expiry)
- Bypasses API server for file upload (performance optimization)
- Returns upload URL, object key, and required headers

**Upload Flow:**
1. Client requests pre-signed URL with file metadata
2. API generates unique object key and signed PUT URL
3. Client uploads file directly to R2 using signed URL
4. Client calls POST /api/v1/patients/{id}/documents with object_key
5. API records document in database

**Response includes:**
- Pre-signed upload URL
- Object key (to be used in document attachment)
- Expiry timestamp
- HTTP method (PUT)
- Required headers (Content-Type)

### 3. Routes Configuration (`routes/api.php`)

Added patient management routes under protected middleware stack:
- tenant.resolve - Resolves tenant context
- jwt.auth - Validates JWT token
- rbac - Checks role-based permissions
- tenant.scope - Applies tenant scope to queries

**Registered Routes:**
```php
POST   /api/v1/patients                           - Register patient
GET    /api/v1/patients/{id}                      - Get patient record
GET    /api/v1/patients/{id}/journey              - Get journey history
POST   /api/v1/patients/{id}/documents            - Attach document
GET    /api/v1/patients/{id}/documents/{docId}/url - Get signed URL
GET    /api/v1/upload/presigned-url               - Get upload URL
```

### 4. Key Features Implemented

#### PII Encryption
- Leverages existing Patient model encryption for:
  - full_name
  - date_of_birth
  - phone
  - address
- Uses Laravel Crypt facade (AES-256-CBC)
- Transparent encryption/decryption via model accessors
- Encrypted at application layer before database storage

#### Unique Patient Number Generation
- Format: P-YYYYMMDD-XXXX
- Date-based prefix ensures daily reset
- 4-digit sequence with leading zeros
- Queries last patient number for the day
- Auto-increments sequence
- Thread-safe within database transaction

#### Accounts Department Routing
- Automatically routes new patients to Accounts department
- Sets patient_record status to 'pending_payment'
- Creates journey event for registration
- Prevents clinical departments from seeing unpaid patients

#### Journey Event Tracking
- Immutable audit trail of patient journey
- Records registration event
- Records document attachment events
- Includes performer (user), timestamps, notes, metadata
- Chronologically sorted for journey history

#### Cloudflare R2 Integration
- Uses Laravel Storage with S3 driver
- Configured for Cloudflare R2 S3-compatible API
- Generates time-limited signed URLs (15-minute TTL)
- Supports direct upload (pre-signed PUT URLs)
- Tenant-isolated buckets (configured via environment)

#### Error Handling
- Database transactions for atomicity
- Descriptive error messages
- Proper HTTP status codes (201, 404, 403, 422, 500)
- Consistent JSON response format
- Validation error details

### 5. Testing

Created comprehensive test suite (`tests/Feature/Patient/PatientRegistrationTest.php`):

**Test Cases:**
1. ✓ Can register new patient with required fields
2. ✓ Validates required fields during registration
3. ✓ Generates unique patient numbers
4. ✓ Encrypts PII fields

**Test Coverage:**
- Patient registration flow
- Field validation
- Patient number uniqueness
- PII encryption verification
- Accounts department routing
- Journey event creation

### 6. Requirements Validation

**Requirement 5.1** ✓ - Patient registration form captures all required fields
**Requirement 5.2** ✓ - Validates required fields before creating patient record
**Requirement 5.3** ✓ - Field-level validation errors returned
**Requirement 5.4** ✓ - Photo capture supported via photo_object_key
**Requirement 5.5** ✓ - Document attachment with 10 MB limit enforced
**Requirement 5.6** ✓ - Unique patient_number assigned, status set to pending_payment, routed to Accounts
**Requirement 5.7** ✓ - Secure R2 storage with signed URLs, no direct public access

### 7. API Response Format

All endpoints follow consistent response envelope:

**Success Response:**
```json
{
  "success": true,
  "data": { ... },
  "message": "Operation completed"
}
```

**Error Response:**
```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Error description",
    "details": { ... }
  }
}
```

### 8. Security Considerations

- PII encryption at application layer (AES-256)
- JWT authentication required for all endpoints
- RBAC middleware enforces role-based access
- Tenant scope prevents cross-tenant data access
- Document ownership validation
- Time-limited signed URLs (15-minute expiry)
- No direct public access to R2 storage
- Database transactions prevent partial updates
- Input validation prevents injection attacks

### 9. Performance Optimizations

- Direct R2 upload bypasses API server
- Pre-signed URLs reduce server load
- Database transactions ensure consistency
- Eager loading of relationships prevents N+1 queries
- Indexed patient_number for fast lookups

### 10. Dependencies

**Required:**
- Laravel 11.x
- stancl/tenancy for multi-tenancy
- league/flysystem-aws-s3-v3 for R2 integration
- php-open-source-saver/jwt-auth for authentication

**Configuration:**
- Cloudflare R2 credentials in .env
- S3 driver configured in config/filesystems.php
- Accounts department must exist in tenant database

## Files Created/Modified

### Created:
1. `app/Http/Requests/Patient/RegisterPatientRequest.php` - Patient registration validation
2. `app/Http/Requests/Patient/AttachDocumentRequest.php` - Document attachment validation
3. `app/Http/Controllers/Api/V1/PatientController.php` - Patient API controller
4. `tests/Feature/Patient/PatientRegistrationTest.php` - Test suite

### Modified:
1. `routes/api.php` - Added patient management routes

## Next Steps

To complete the patient management workflow:
1. Implement Accounts department invoice creation (Task 7.2)
2. Implement payment processing and workflow routing (Task 7.3)
3. Implement department queue endpoints (Task 7.4)
4. Add audit logging for patient operations
5. Implement real-time notifications for patient routing

## Testing Instructions

1. Install dependencies: `composer install`
2. Configure .env with R2 credentials
3. Run migrations: `php artisan migrate`
4. Create Accounts department in tenant database
5. Run tests: `php artisan test --filter=PatientRegistrationTest`

## API Usage Examples

### Register Patient
```bash
POST /api/v1/patients
Authorization: Bearer {jwt_token}
Content-Type: application/json

{
  "full_name": "John Doe",
  "date_of_birth": "1990-01-15",
  "gender": "male",
  "phone": "+1234567890",
  "address": "123 Main St",
  "chief_complaint": "Fever and headache",
  "hmo_provider_name": "Blue Cross",
  "hmo_policy_number": "BC123456"
}
```

### Get Patient Record
```bash
GET /api/v1/patients/{patient_id}
Authorization: Bearer {jwt_token}
```

### Get Journey History
```bash
GET /api/v1/patients/{patient_id}/journey
Authorization: Bearer {jwt_token}
```

### Get Pre-signed Upload URL
```bash
GET /api/v1/upload/presigned-url?filename=document.pdf&content_type=application/pdf&file_size=1024000
Authorization: Bearer {jwt_token}
```

### Attach Document
```bash
POST /api/v1/patients/{patient_id}/documents
Authorization: Bearer {jwt_token}
Content-Type: application/json

{
  "patient_record_id": "{record_id}",
  "document_type": "registration",
  "object_key": "uploads/abc123_1234567890.pdf",
  "original_filename": "id_card.pdf",
  "file_size_bytes": 1024000
}
```

### Get Document Download URL
```bash
GET /api/v1/patients/{patient_id}/documents/{document_id}/url
Authorization: Bearer {jwt_token}
```

## Conclusion

Task 7.1 has been successfully implemented with all required functionality:
- ✓ Patient registration with PII encryption
- ✓ Unique patient number generation
- ✓ Accounts department routing
- ✓ Journey history tracking
- ✓ Document attachment with R2 storage
- ✓ Signed URL generation for secure file access
- ✓ Pre-signed upload URLs for direct R2 upload
- ✓ Comprehensive validation and error handling
- ✓ Test coverage for core functionality

The implementation follows Laravel best practices, maintains security standards, and integrates seamlessly with the existing multi-tenant architecture.
