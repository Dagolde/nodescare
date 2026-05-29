# Requirements Document

## Introduction

This feature extends the patient registration workflow to capture a **visit type** (e.g. General OPD, Pediatric, Pregnant, Eye, Dental, ENT) and an **assigned doctor** at the point of registration by the front desk officer. The assigned doctor is then visible on the nurse/triage queue card so the nurse can route the patient directly to that specific doctor's department after recording vitals.

Visit types are tenant-scoped records stored in a dedicated `visit_types` table, pre-seeded with system defaults. Hospital admins can add, edit, and delete custom visit types per tenant. Doctors are filtered by both their department type and a new `specialties` field on their user profile, so the doctor picker shows doctors whose specialty matches the selected visit type OR whose department type matches.

The feature touches:
- Backend: `visit_types` table + CRUD API, `specialties` column on `users`, new columns on `patient_records`, doctor-by-visit-type endpoint, queue response enrichment, and named permission seeder entries.
- Mobile (Flutter/Riverpod): updated registration form with visit type dropdown and doctor picker, updated `PatientRecord` entity, and updated nurse/triage queue card with visit type badge, assigned doctor name, and "Route to Dr." button.

---

## Glossary

- **Registration_Screen**: The Flutter `PatientRegistrationScreen` widget used by front desk officers to register new patients.
- **Visit_Type**: A tenant-scoped record in the `visit_types` table representing a category of patient visit (e.g. `general_opd`, `pediatric`, `pregnant`, `eye`, `dental`, `ent`). Identified by a URL-safe `slug`.
- **Visit_Type_Slug**: The URL-safe lowercase identifier for a Visit_Type (e.g. `general_opd`). Used as the path parameter in the doctor-filtering endpoint and stored on `patient_records.visit_type`.
- **Default_Visit_Types**: The system-provided set of Visit_Types pre-seeded into every tenant: General OPD, Pediatric, Pregnant, Eye, Dental, ENT.
- **Assigned_Doctor**: The `User` record (role = `doctor`) selected by the front desk officer during registration. Stored as a foreign key (`assigned_doctor_id`) on `patient_records`.
- **Doctor_Picker**: The UI component in the Registration_Screen that lists available doctors filtered by the selected Visit_Type.
- **Doctor_Specialties**: A JSON array column (`specialties`) on the `users` table listing the Visit_Type slugs a doctor handles (e.g. `["pediatric","general_opd"]`).
- **Nurse_Queue_Card**: The `_PatientCard` widget rendered inside `_NurseDashboard` that displays a patient in the triage/nursing queue.
- **Route_Action**: The action a nurse/triage officer performs to move a patient from the triage department to a specific doctor's department.
- **PatientRecord**: The backend `PatientRecord` model and its Flutter entity counterpart, representing a single visit.
- **Department_Queue**: The list of active patient records assigned to a staff member's department, returned by `GET /api/v1/department/queue`.
- **WorkflowService**: The Laravel service responsible for advancing a patient through workflow steps.
- **Visit_Type_Doctor_API**: The backend endpoint `GET /api/v1/doctors/by-visit-type/{visit_type}` that returns doctors eligible for a given visit type.
- **Visit_Type_Management_API**: The CRUD endpoints `GET/POST /api/v1/visit-types` and `PUT/DELETE /api/v1/visit-types/{id}` for hospital admin management of visit types.
- **Tenant**: A hospital instance in the multi-tenant system. All data is scoped to the current tenant.
- **Permission_Seeder**: The `PermissionSeeder` class that defines all named permissions and their default role assignments. No URL-pattern fallback is used.

---

## Requirements

### Requirement 1: Visit Type Data Model and Seeding

**User Story:** As a hospital administrator, I want the system to ship with a standard set of visit types and allow me to manage custom ones, so that my hospital's specific specialties are represented.

#### Acceptance Criteria

1. THE System SHALL maintain a `visit_types` table with columns: `id` (UUID PK), `tenant_id` (UUID FK), `name` (string), `slug` (string, URL-safe), `is_default` (boolean), `is_active` (boolean), `sort_order` (integer).
2. THE System SHALL enforce a unique constraint on `(tenant_id, slug)` in the `visit_types` table.
3. WHEN a new Tenant is provisioned, THE System SHALL seed the following default Visit_Types for that tenant: `General OPD` (slug: `general_opd`), `Pediatric` (slug: `pediatric`), `Pregnant` (slug: `pregnant`), `Eye` (slug: `eye`), `Dental` (slug: `dental`), `ENT` (slug: `ent`), each with `is_default = true` and `is_active = true`.
4. THE System SHALL scope all `visit_types` queries to the current Tenant so that one tenant cannot read or modify another tenant's visit types.

---

### Requirement 2: Visit Type Management API (Hospital Admin)

**User Story:** As a hospital administrator, I want to create, update, and delete custom visit types, so that I can tailor the visit type list to my hospital's needs.

#### Acceptance Criteria

1. THE Visit_Type_Management_API SHALL expose `GET /api/v1/visit-types` returning all active Visit_Types for the current Tenant, ordered by `sort_order`.
2. THE Visit_Type_Management_API SHALL expose `POST /api/v1/visit-types` accepting `name`, `slug`, `sort_order`, and optional `is_active`, creating a new Visit_Type scoped to the current Tenant.
3. WHEN a `POST /api/v1/visit-types` request provides a `slug` that already exists for the current Tenant, THE Visit_Type_Management_API SHALL return HTTP 422 with a descriptive validation error.
4. THE Visit_Type_Management_API SHALL expose `PUT /api/v1/visit-types/{id}` accepting `name`, `slug`, `is_active`, and `sort_order` to update an existing Visit_Type belonging to the current Tenant.
5. THE Visit_Type_Management_API SHALL expose `DELETE /api/v1/visit-types/{id}` to soft-delete (set `is_active = false`) a Visit_Type belonging to the current Tenant.
6. IF a `DELETE /api/v1/visit-types/{id}` request targets a Visit_Type with `is_default = true`, THEN THE Visit_Type_Management_API SHALL return HTTP 422 with an error indicating default visit types cannot be deleted.
7. IF a `PUT` or `DELETE` request targets a Visit_Type that does not belong to the current Tenant, THEN THE Visit_Type_Management_API SHALL return HTTP 404.
8. THE Permission_Seeder SHALL define a named permission `visit_types.manage` assigned by default to the `hospital_admin` role, and all Visit_Type_Management_API endpoints SHALL require this permission via the RBAC middleware.

---

### Requirement 3: Doctor Specialties on User Profile

**User Story:** As a hospital administrator, I want to assign specialties to doctors when creating or editing their staff profile, so that the system can match doctors to the correct visit types.

#### Acceptance Criteria

1. THE System SHALL add a `specialties` JSON column to the `users` table, defaulting to an empty JSON array `[]`.
2. THE `User` model SHALL cast `specialties` as an array and SHALL expose it in API responses for doctor records.
3. WHEN a hospital admin creates a staff member with role `doctor` via `POST /api/v1/staff`, THE `CreateStaffRequest` SHALL accept an optional `specialties` array of Visit_Type slugs.
4. WHEN a hospital admin updates a staff member via `PUT /api/v1/staff/{id}`, THE `UpdateStaffRequest` SHALL accept an optional `specialties` array of Visit_Type slugs.
5. THE `HospitalAdminController` SHALL persist the `specialties` value when creating or updating a doctor.
6. IF a `specialties` array entry does not match any active Visit_Type slug for the current Tenant, THEN THE System SHALL return HTTP 422 with a descriptive validation error.

---

### Requirement 4: Visit Type Selection During Registration

**User Story:** As a front desk officer, I want to select a visit type when registering a patient, so that the system can present the correct list of doctors for that visit category.

#### Acceptance Criteria

1. THE Registration_Screen SHALL display a required "Visit Type" dropdown field populated by fetching active Visit_Types from `GET /api/v1/visit-types`.
2. WHEN a front desk officer submits the registration form without selecting a Visit_Type, THE Registration_Screen SHALL display a validation error and prevent form submission.
3. WHEN a front desk officer selects a Visit_Type, THE Registration_Screen SHALL immediately fetch and display the list of available doctors for that Visit_Type via the Visit_Type_Doctor_API.
4. THE Registration_Screen SHALL disable the Doctor_Picker until a Visit_Type is selected.
5. WHEN the Visit_Type selection changes after a doctor has already been chosen, THE Registration_Screen SHALL clear the previously selected doctor and reload the doctor list for the new Visit_Type.
6. THE `RegisterPatientRequest` SHALL accept and validate a `visit_type` field as a required string matching an active Visit_Type slug belonging to the current Tenant.
7. THE `PatientRecord` backend model SHALL store the `visit_type` slug in a new `visit_type` column (string) on the `patient_records` table.

---

### Requirement 5: Doctor Assignment During Registration

**User Story:** As a front desk officer, I want to assign a specific doctor to a patient at registration time, so that the patient is directed to the correct doctor after triage.

#### Acceptance Criteria

1. THE Registration_Screen SHALL display a required "Assigned Doctor" picker that lists doctors returned by the Visit_Type_Doctor_API for the selected Visit_Type.
2. WHEN the Visit_Type_Doctor_API returns an empty list for the selected Visit_Type, THE Registration_Screen SHALL display a message indicating no doctors are available for that visit type and SHALL allow the front desk officer to proceed without assigning a doctor.
3. WHEN a front desk officer submits the registration form with a Visit_Type selected but no doctor chosen and doctors are available, THE Registration_Screen SHALL display a validation error and prevent form submission.
4. THE `RegisterPatientRequest` SHALL accept and validate an `assigned_doctor_id` field as a nullable UUID that, when provided, must reference an active `User` with role `doctor` belonging to the current Tenant.
5. THE `PatientRecord` backend model SHALL store the `assigned_doctor_id` as a nullable UUID foreign key in a new `assigned_doctor_id` column on the `patient_records` table.
6. WHEN a patient is registered with an `assigned_doctor_id`, THE `PatientController` register() method SHALL persist the `assigned_doctor_id` on the created `PatientRecord`.
7. WHEN a new visit is created for a returning patient via `POST /api/v1/patients/{id}/new-visit`, THE system SHALL accept and persist `visit_type` and `assigned_doctor_id` using the same validation rules as new registration.

---

### Requirement 6: Visit Type Doctor Listing API

**User Story:** As a front desk officer using the mobile app, I want the app to fetch doctors filtered by visit type, so that I only see relevant doctors for the patient's visit category.

#### Acceptance Criteria

1. THE Visit_Type_Doctor_API SHALL expose `GET /api/v1/doctors/by-visit-type/{visit_type}` accessible to authenticated users with the `patients.create` permission.
2. WHEN a valid Visit_Type_Slug is provided, THE Visit_Type_Doctor_API SHALL return active `User` records with role `doctor` belonging to the current Tenant where: (a) the doctor's `specialties` array contains the Visit_Type_Slug, OR (b) the doctor's `department.type` matches the Visit_Type_Slug.
3. THE Visit_Type_Doctor_API SHALL return each doctor record including `id`, `name`, `department_id`, `department.name`, and `specialties`.
4. WHEN an unrecognised Visit_Type_Slug is provided, THE Visit_Type_Doctor_API SHALL return HTTP 422 with a descriptive validation error.
5. THE Visit_Type_Doctor_API SHALL scope all results to the current Tenant and SHALL return an empty array when no matching doctors exist.
6. FOR ALL valid Visit_Type_Slug values, THE Visit_Type_Doctor_API SHALL return only doctors whose `is_active` flag is `true`.

---

### Requirement 7: Assigned Doctor Visible on Nurse/Triage Queue Card

**User Story:** As a nurse/triage officer, I want to see the visit type and assigned doctor on each patient's queue card, so that I know which doctor's department to route the patient to after recording vitals.

#### Acceptance Criteria

1. THE Department_Queue API response SHALL include `visit_type`, `assigned_doctor_id`, `assigned_doctor_name`, and `assigned_doctor_department_id` for each `PatientRecord`.
2. THE Nurse_Queue_Card SHALL display a visit type badge showing the Visit_Type name when `visit_type` is present on the `PatientRecord`.
3. THE Nurse_Queue_Card SHALL display the assigned doctor's name when `assigned_doctor_name` is present on the `PatientRecord`.
4. WHEN a `PatientRecord` has no assigned doctor, THE Nurse_Queue_Card SHALL not display a doctor assignment section and SHALL show the standard routing controls.
5. THE Flutter `PatientRecord` entity SHALL include `visitType`, `assignedDoctorId`, `assignedDoctorName`, and `assignedDoctorDepartmentId` fields parsed from the API response.
6. THE `PatientRecord.fromJson` factory SHALL correctly parse `visit_type`, `assigned_doctor_id`, `assigned_doctor_name`, and `assigned_doctor_department_id` from the API JSON.

---

### Requirement 8: Nurse Routes Patient Directly to Assigned Doctor

**User Story:** As a nurse/triage officer, I want to route a patient directly to their assigned doctor's department with a single action, so that the patient reaches the correct doctor without manual department selection.

#### Acceptance Criteria

1. WHEN a `PatientRecord` in the nurse/triage queue has an `assigned_doctor_department_id`, THE Nurse_Queue_Card SHALL display a "Route to Dr. [name]" button in addition to the standard "Vitals" button.
2. WHEN the nurse taps "Route to Dr. [name]", THE system SHALL call `POST /api/v1/patient-records/{id}/route` with the `assigned_doctor_department_id` as the target department.
3. WHEN the route action succeeds, THE Nurse_Queue_Card SHALL remove the patient from the nurse's queue and refresh the Department_Queue.
4. IF the route action fails due to a network or server error, THEN THE Nurse_Queue_Card SHALL display an error message and SHALL keep the patient in the queue.
5. WHILE a route action is in progress, THE Nurse_Queue_Card SHALL disable the "Route to Dr. [name]" button to prevent duplicate submissions.
6. THE `POST /api/v1/patient-records/{id}/route` endpoint SHALL accept a `to_department_id` parameter and SHALL validate that the target department belongs to the current Tenant.
7. WHEN a nurse routes a patient to a doctor's department, THE system SHALL create a `PatientJourneyEvent` with `event_type` = `routed` recording the `from_department_id`, `to_department_id`, and `performed_by`.

---

### Requirement 9: Visit Type and Doctor Persisted in Patient Journey

**User Story:** As a hospital administrator, I want the visit type and assigned doctor to be recorded in the patient's journey history, so that I have a complete audit trail of how patients were triaged and assigned.

#### Acceptance Criteria

1. WHEN a patient is registered with a `visit_type` and `assigned_doctor_id`, THE `PatientController` SHALL create a `PatientJourneyEvent` with `event_type` = `registered` that includes `visit_type` and `assigned_doctor_id` in the event `metadata`.
2. THE `GET /api/v1/patients/{id}/journey` endpoint SHALL return `visit_type` and `assigned_doctor_id` within the metadata of the registration journey event.
3. THE `PatientRecord` backend model SHALL expose `visit_type` and `assigned_doctor_id` in its API serialisation so that any endpoint returning a `PatientRecord` includes these fields.

---

### Requirement 10: Permissions via Named Permission Seeder

**User Story:** As a system administrator, I want all new permissions for this feature to be defined in the named permission seeder, so that RBAC is consistent and auditable without URL-pattern fallbacks.

#### Acceptance Criteria

1. THE Permission_Seeder SHALL define `visit_types.manage` with `display_name` = `Manage Visit Types`, `group` = `visit_types`, assigned by default to the `hospital_admin` role.
2. THE Permission_Seeder SHALL define `visit_types.view` with `display_name` = `View Visit Types`, `group` = `visit_types`, assigned by default to roles: `hospital_admin`, `front_desk_officer`, `doctor`, `nurse`, `triage`.
3. THE Permission_Seeder SHALL define `doctors.list_by_visit_type` with `display_name` = `List Doctors by Visit Type`, `group` = `doctors`, assigned by default to roles: `hospital_admin`, `front_desk_officer`.
4. THE RBAC middleware SHALL enforce all Visit_Type_Management_API and Visit_Type_Doctor_API endpoints using only the named permissions defined in the Permission_Seeder, with no URL-pattern fallback.
