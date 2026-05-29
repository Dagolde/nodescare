# Task 4 Implementation: Tenant Database Schema and Models

## Overview
Implemented complete tenant database schema with 16 migrations and 14 Eloquent models for the Hospital Health Management System multi-tenant architecture.

## Migrations Created (database/migrations/tenant/)

All migrations use UUID primary keys and follow the database-per-tenant strategy:

1. **2024_01_02_000001_create_departments_table.php**
   - Departments with type ENUM (clinical, pharmacy, laboratory, front_desk, triage, ward, icu_ae, accounts, other)

2. **2024_01_02_000002_create_users_table.php**
   - Staff accounts with role ENUM (hospital_admin, doctor, front_desk_officer, accountant, pharmacist, lab_technician, department_staff)
   - Includes authentication fields (token_version, failed_login_attempts, locked_until)

3. **2024_01_02_000003_create_workflow_templates_table.php**
   - Reusable patient journey workflow templates

4. **2024_01_02_000004_create_workflow_template_steps_table.php**
   - Ordered steps in workflow templates

5. **2024_01_02_000005_create_patients_table.php**
   - Patient demographics with PII fields (full_name, date_of_birth, phone, address) marked for application-layer encryption
   - Includes HMO/insurance information

6. **2024_01_02_000006_create_patient_records_table.php**
   - Patient visit/encounter records with status ENUM (pending_payment, active, in_transit, admitted, discharged, completed)

7. **2024_01_02_000007_create_patient_journey_events_table.php**
   - Immutable log of patient routing events (no updated_at)

8. **2024_01_02_000008_create_patient_documents_table.php**
   - File attachments with R2 object keys

9. **2024_01_02_000009_create_dispensing_records_table.php**
   - Pharmacy dispensing log

10. **2024_01_02_000010_create_lab_results_table.php**
    - Laboratory test results

11. **2024_01_02_000011_create_appointments_table.php**
    - Scheduled follow-up consultations

12. **2024_01_02_000012_create_notifications_table.php**
    - Notification delivery log

13. **2024_01_02_000013_create_audit_logs_table.php**
    - Immutable system audit trail (no updated_at)

14. **2024_01_02_000014_create_invoices_table.php**
    - Itemized bills with status ENUM (draft, issued, partially_paid, settled, reversed)

15. **2024_01_02_000015_create_invoice_items_table.php**
    - Line items on invoices

16. **2024_01_02_000016_create_invoice_payments_table.php**
    - Payment transactions with payment_type ENUM (cash, pos_card, hmo_insurance)

## Models Created/Updated

### New Models (app/Models/)

1. **WorkflowTemplate.php**
   - Relationships: creator (User), steps (WorkflowTemplateStep), patientRecords (PatientRecord)

2. **WorkflowTemplateStep.php**
   - Relationships: template (WorkflowTemplate), department (Department)
   - No updated_at (immutable once created)

3. **Patient.php**
   - **Application-layer PII encryption** using Laravel Crypt facade for:
     - full_name
     - date_of_birth
     - phone
     - address
   - Relationships: creator (User), records (PatientRecord), appointments (Appointment), invoices (Invoice)

4. **PatientRecord.php**
   - Relationships: patient (Patient), workflowTemplate (WorkflowTemplate), currentDepartment (Department), registrar (User), journeyEvents, documents, dispensingRecords, labResults, invoices, appointments

5. **PatientJourneyEvent.php**
   - Relationships: patientRecord, fromDepartment, toDepartment, performer (User)
   - No updated_at (immutable audit trail)

6. **PatientDocument.php**
   - Relationships: patientRecord, uploader (User)
   - No updated_at

7. **DispensingRecord.php**
   - Relationships: patientRecord, pharmacist (User)
   - No updated_at

8. **LabResult.php**
   - Relationships: patientRecord, technician (User)
   - No updated_at

9. **Appointment.php**
   - Relationships: patient, patientRecord, doctor (User)

10. **Notification.php**
    - Relationships: recipient (User)
    - No updated_at

11. **Invoice.php**
    - Relationships: patientRecord, patient, creator (User), reverser (User), items (InvoiceItem), payments (InvoicePayment)

12. **InvoiceItem.php**
    - Relationships: invoice
    - No updated_at

13. **InvoicePayment.php**
    - Relationships: invoice, accountant (User)
    - No updated_at (immutable payment record)

### Updated Models

1. **User.php**
   - Added relationships: createdPatients, registeredPatientRecords, createdWorkflowTemplates, performedJourneyEvents, uploadedDocuments, dispensingRecords, labResults, appointments, notifications, createdInvoices, invoicePayments

2. **Department.php**
   - Added relationships: workflowTemplateSteps, currentPatientRecords, journeyEventsFrom, journeyEventsTo

3. **AuditLog.php**
   - Already existed with correct structure (immutable, no updated_at)

## Key Features Implemented

### 1. Application-Layer PII Encryption
- Patient model uses Laravel Crypt facade with automatic encryption/decryption
- Encrypted fields: full_name, date_of_birth, phone, address
- Per-tenant encryption keys (to be stored in secrets manager)

### 2. Immutable Records
Models without updated_at timestamp:
- WorkflowTemplateStep
- PatientJourneyEvent
- PatientDocument
- DispensingRecord
- LabResult
- Notification
- AuditLog
- InvoiceItem
- InvoicePayment

### 3. ENUM Types
- users.role: 7 role types
- departments.type: 9 department types
- patient_records.status: 6 status types
- invoices.status: 5 status types
- invoice_payments.payment_type: 3 payment types

### 4. UUID Primary Keys
All tables use UUID primary keys via HasUuids trait

### 5. Comprehensive Relationships
All models have complete relationship definitions for:
- BelongsTo (parent relationships)
- HasMany (child relationships)
- Proper foreign key constraints with cascade/set null actions

## Requirements Validated

- **1.1**: Multi-tenant isolation (database-per-tenant migrations)
- **3.3**: Hospital Admin management (departments, workflow templates)
- **5.1**: Patient registration data capture (patients table with all required fields)
- **5.2**: PII encryption (application-layer encryption for sensitive fields)
- **5.6**: Patient journey workflow (workflow_templates, workflow_template_steps, patient_journey_events)
- **10.3**: Role-based access control (users.role ENUM with all required roles)
- **15.1**: Data security (PII encryption at application layer)
- **16.2**: Accounts department (invoices, invoice_items, invoice_payments with payment gate)

## Migration Order

Migrations are ordered to respect foreign key dependencies:
1. departments (no dependencies)
2. users (→ departments)
3. workflow_templates (→ users)
4. workflow_template_steps (→ workflow_templates, departments)
5. patients (→ users)
6. patient_records (→ patients, workflow_templates, departments, users)
7. Remaining tables (→ patient_records, users, departments)

## Configuration

Tenant migrations are configured in `config/tenancy.php`:
```php
'migration_parameters' => [
    '--force' => true,
    '--path' => [database_path('migrations/tenant')],
    '--realpath' => true,
],
```

## Next Steps

To run the tenant migrations:
```bash
php artisan tenants:migrate
```

To create a new tenant with migrations:
```bash
php artisan tenants:create {tenant_name}
```
