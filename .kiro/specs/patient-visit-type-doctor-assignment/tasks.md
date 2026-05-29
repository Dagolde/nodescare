# Implementation Plan: Patient Visit Type & Doctor Assignment

## Overview

Implement visit type selection and doctor assignment during patient registration. The feature adds a `visit_types` table, a `specialties` column on `users`, two new columns on `patient_records`, a doctor-filtering endpoint, enriched queue responses, and updated mobile UI for the registration screen and nurse/triage queue card.

## Tasks

- [x] 1. Create database migrations
  - Create migration `2026_XX_XX_000001_create_visit_types_table.php` with columns: `id` (UUID PK), `tenant_id` (UUID FK → tenants), `name`, `slug`, `is_default` (bool), `is_active` (bool, default true), `sort_order` (smallint), `timestamps`. Add unique constraint on `(tenant_id, slug)` and index on `tenant_id`.
  - Create migration `2026_XX_XX_000002_add_specialties_to_users_table.php` adding a nullable JSON `specialties` column after `is_hod` on the `users` table.
  - Create migration `2026_XX_XX_000003_add_visit_type_and_doctor_to_patient_records.php` adding nullable `visit_type` (string) and nullable `assigned_doctor_id` (UUID FK → users, nullOnDelete) columns to `patient_records`, with an index on `assigned_doctor_id`.
  - _Requirements: 1.1, 1.2, 3.1, 4.7, 5.5_

- [x] 2. Update backend models
  - [x] 2.1 Create `VisitType` model at `backend/app/Models/VisitType.php`
    - Use `HasUuids` trait; set `$fillable` to `['tenant_id','name','slug','is_default','is_active','sort_order']`; cast `is_default`, `is_active` as boolean and `sort_order` as integer; register `TenantScope` global scope in `booted()`.
    - _Requirements: 1.1, 1.4_

  - [x] 2.2 Update `User` model at `backend/app/Models/User.php`
    - Add `'specialties'` to `$fillable`; add `'specialties' => 'array'` to `$casts`.
    - _Requirements: 3.1, 3.2_

  - [x] 2.3 Update `PatientRecord` model at `backend/app/Models/PatientRecord.php`
    - Add `'visit_type'` and `'assigned_doctor_id'` to `$fillable`; add `assignedDoctor()` belongsTo relationship to `User` via `assigned_doctor_id`.
    - _Requirements: 4.7, 5.5, 9.3_

- [x] 3. Create `VisitTypeSeeder` and update `DatabaseSeeder`
  - Create `backend/database/seeders/VisitTypeSeeder.php` that iterates all tenants and upserts the six default visit types (`general_opd`, `pediatric`, `pregnant`, `eye`, `dental`, `ent`) with `is_default = true`, `is_active = true`, and sequential `sort_order` values 1–6.
  - Add `VisitTypeSeeder::class` to the `$this->call([...])` array in `DatabaseSeeder`.
  - _Requirements: 1.3_

- [x] 4. Update `PermissionSeeder` with new permissions
  - Add three entries to the `$permissions` array in `backend/database/seeders/PermissionSeeder.php`:
    - `visit_types.view` — display_name `View Visit Types`, group `visit_types`, roles: `hospital_admin`, `front_desk_officer`, `doctor`, `nurse`, `triage`.
    - `visit_types.manage` — display_name `Manage Visit Types`, group `visit_types`, roles: `hospital_admin`.
    - `doctors.list_by_visit_type` — display_name `List Doctors by Visit Type`, group `doctors`, roles: `hospital_admin`, `front_desk_officer`.
  - _Requirements: 2.8, 10.1, 10.2, 10.3_

- [x] 5. Create `VisitTypeController` (CRUD)
  - Create `backend/app/Http/Controllers/Api/V1/VisitTypeController.php` with four methods:
    - `index()` — return active visit types ordered by `sort_order` for the current tenant.
    - `store()` — validate `name` (required), `slug` (required, unique per tenant), `sort_order` (nullable int), `is_active` (nullable bool); create with `tenant_id` from container; return 422 on duplicate slug.
    - `update()` — validate same fields (all nullable); return 404 if not found in tenant scope; return updated record.
    - `destroy()` — return 422 if `is_default = true`; otherwise set `is_active = false`; return 404 if not found in tenant scope.
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7_

- [x] 6. Create `DoctorController` with `byVisitType` endpoint
  - Create `backend/app/Http/Controllers/Api/V1/DoctorController.php` with a single `byVisitType(string $slug)` method.
  - Validate that `$slug` exists as an active visit type for the current tenant; return 422 if not found.
  - Query active doctors (`role = 'doctor'`, `is_active = true`) in the current tenant where `specialties` JSON array contains `$slug` OR `department.type` equals `$slug`; eager-load `department`.
  - Return each doctor with `id`, `name`, `department_id`, `department.name`, `specialties`.
  - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6_

- [x] 7. Update `HospitalAdminController`, `CreateStaffRequest`, and `UpdateStaffRequest` for specialties
  - In `CreateStaffRequest::rules()`, add `'specialties' => 'nullable|array'` and `'specialties.*' => 'string'`.
  - In `UpdateStaffRequest::rules()`, add the same two rules.
  - In `HospitalAdminController::createStaff()`, add `'specialties' => $request->input('specialties', [])` to the `User::create()` call; after creation, validate each specialty slug against active visit types for the tenant and return 422 if any are invalid.
  - In `HospitalAdminController::updateStaff()`, include `specialties` in the update data; apply the same slug validation.
  - _Requirements: 3.3, 3.4, 3.5, 3.6_

- [x] 8. Update `PatientController` `register()` and `newVisit()` methods
  - In `register()`, add `'visit_type' => $request->input('visit_type')` and `'assigned_doctor_id' => $request->input('assigned_doctor_id')` to the `PatientRecord::create()` call.
  - In `register()`, add `'visit_type'` and `'assigned_doctor_id'` to the `metadata` array of the `PatientJourneyEvent::create()` call.
  - In `newVisit()`, add the same two fields to `PatientRecord::create()` and to the journey event metadata.
  - After the form request passes in both methods, validate that `visit_type` matches an active slug for the current tenant (422 if not), and that `assigned_doctor_id` (when provided) references an active doctor in the current tenant (422 if not).
  - _Requirements: 4.6, 5.6, 5.7, 9.1_

- [x] 9. Update `RegisterPatientRequest` validation rules
  - Add `'visit_type' => 'required|string'` and `'assigned_doctor_id' => 'nullable|uuid'` to `rules()` in `backend/app/Http/Requests/Patient/RegisterPatientRequest.php`.
  - _Requirements: 4.6, 5.4_

- [x] 10. Update `DepartmentController::getQueue()` to enrich with assigned doctor fields
  - In the `PatientRecord::with([...])` eager-load call inside `getQueue()`, add `'assignedDoctor.department'`.
  - In the `map()` closure, after existing enrichments, resolve the assigned doctor from the eager-loaded relation and append `visit_type`, `assigned_doctor_id`, `assigned_doctor_name`, and `assigned_doctor_department_id` to `$data`.
  - _Requirements: 7.1, 9.3_

- [x] 11. Update `routes/api.php` with new routes
  - Add `use` statements for `VisitTypeController` and `DoctorController`.
  - Inside the protected middleware group, add a `Route::prefix('visit-types')` group with `GET /` (`rbac:visit_types.view`), `POST /` (`rbac:visit_types.manage`), `PUT /{id}` (`rbac:visit_types.manage`), `DELETE /{id}` (`rbac:visit_types.manage`).
  - Add `Route::get('/doctors/by-visit-type/{visit_type}', ...)` with `rbac:doctors.list_by_visit_type`.
  - _Requirements: 2.1, 2.8, 6.1, 10.4_

- [x] 12. Create mobile domain entities `VisitType` and `DoctorSummary`
  - Create `mobile/lib/domain/entities/visit_type.dart` with fields `id`, `name`, `slug`, `isDefault`, `isActive`, `sortOrder` and a `fromJson` factory.
  - Create `mobile/lib/domain/entities/doctor_summary.dart` with fields `id`, `name`, `departmentId`, `departmentName`, `specialties` (List<String>) and a `fromJson` factory.
  - _Requirements: 7.5_

- [x] 13. Update `PatientRecord` Flutter entity
  - In `mobile/lib/domain/entities/patient_record.dart`, add four nullable fields: `visitType` (String?), `assignedDoctorId` (String?), `assignedDoctorName` (String?), `assignedDoctorDepartmentId` (String?).
  - Update the constructor and `fromJson` factory to parse `visit_type`, `assigned_doctor_id`, `assigned_doctor_name`, and `assigned_doctor_department_id` from the JSON map.
  - _Requirements: 7.5, 7.6_

- [x] 14. Create mobile providers `visitTypesProvider` and `doctorsByVisitTypeProvider`
  - Create `mobile/lib/presentation/providers/visit_type_provider.dart` with:
    - A `visitTypeRepositoryProvider` that constructs a repository using the API client.
    - A `visitTypesProvider` (`FutureProvider<List<VisitType>>`) that calls `GET /api/v1/visit-types`.
    - A `doctorsByVisitTypeProvider` (`FutureProvider.family<List<DoctorSummary>, String>`) that calls `GET /api/v1/doctors/by-visit-type/{slug}`.
  - _Requirements: 4.1, 4.3, 6.1_

- [x] 15. Update `PatientRegistrationScreen` with visit type dropdown and doctor picker
  - Add state fields: `_selectedVisitTypeSlug` (String?), `_selectedDoctorId` (String?), `_availableDoctors` (List<DoctorSummary>), `_loadingDoctors` (bool), `_noDoctorsAvailable` (bool).
  - In `initState` (or via `ref.watch`), load visit types from `visitTypesProvider` and populate a `DropdownButtonFormField` placed after the Chief Complaint section; add a required validator.
  - On visit type change: clear `_selectedDoctorId`, set `_loadingDoctors = true`, fetch doctors via `doctorsByVisitTypeProvider(slug)`, update `_availableDoctors` and `_noDoctorsAvailable`.
  - Render a doctor picker `DropdownButtonFormField` disabled when `_selectedVisitTypeSlug == null`; when `_noDoctorsAvailable`, show an info chip and skip the required validator; otherwise require a selection when doctors are available.
  - In `_submit()`, add `visitType` and `assignedDoctorId` to `PatientRegistrationData` before calling `register()`.
  - Update `PatientRegistrationData` class (in `patient_repository.dart` or wherever it is defined) to include `visitType` (String) and `assignedDoctorId` (String?) fields, and pass them in the API request body.
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 5.1, 5.2, 5.3_

- [x] 16. Update `_NurseDashboard` / `_PatientCard` in `role_dashboard_screen.dart`
  - In `_PatientCard.build()`, after the chief complaint text, add a visit type badge (`Container` with blue styling) when `record.visitType != null`, and an assigned doctor name line when `record.assignedDoctorName != null`.
  - In `_NurseDashboard.build()`, update the `trailingBuilder` to return a `Column` with the existing "Vitals" button and, when `record.assignedDoctorDepartmentId != null`, a "Route to Dr. [name]" `ElevatedButton.icon`.
  - Add a `_routing` state bool (or per-record loading map) to `_NurseDashboard`; disable the route button while in progress.
  - Implement `_routeToDoctor(PatientRecord record)` that calls `POST /api/v1/patient-records/{id}/route` with `to_department_id = record.assignedDoctorDepartmentId`, refreshes the queue on success, and shows a `SnackBar` on error.
  - _Requirements: 7.2, 7.3, 7.4, 8.1, 8.2, 8.3, 8.4, 8.5_

- [x] 17. Final checkpoint — ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Tasks are ordered by dependency: DB → models → seeders → controllers → routes → mobile
- The design document has a Correctness Properties section (P1–P5), but all properties are integration-level (tenant isolation, DB constraints, API filter logic) and are best validated through integration/feature tests rather than property-based tests; no PBT sub-tasks are added
- Each task references specific requirements for traceability
