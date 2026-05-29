# Design Document

## Feature: Patient Visit Type & Doctor Assignment

---

## Overview

This feature adds visit type selection and doctor assignment to the patient registration flow. It introduces a tenant-scoped `visit_types` table (pre-seeded with defaults, admin-manageable), a `specialties` JSON column on `users`, two new columns on `patient_records`, a doctor-filtering endpoint, and enriched queue/nurse-card UI.

---

## Architecture

### Component Map

```
Mobile (Flutter/Riverpod)
  PatientRegistrationScreen
    └─ visit type dropdown  ──► GET /api/v1/visit-types
    └─ doctor picker        ──► GET /api/v1/doctors/by-visit-type/{slug}
    └─ submit               ──► POST /api/v1/patients  (+ visit_type, assigned_doctor_id)

  _NurseDashboard / _PatientCard
    └─ visit type badge
    └─ assigned doctor name
    └─ "Route to Dr." button ──► POST /api/v1/patient-records/{id}/route

Backend (Laravel)
  VisitTypeController          ──► CRUD /api/v1/visit-types
  DoctorController             ──► GET  /api/v1/doctors/by-visit-type/{slug}
  HospitalAdminController      ──► staff create/update (+ specialties)
  PatientController            ──► register / new-visit (+ visit_type, assigned_doctor_id)
  DepartmentController.getQueue ──► enriched with visit_type + assigned doctor fields

DB
  visit_types (new table)
  users.specialties (new JSON column)
  patient_records.visit_type (new string column)
  patient_records.assigned_doctor_id (new UUID FK column)
```

---

## Database Changes

### 1. New table: `visit_types`

Migration: `2026_XX_XX_000001_create_visit_types_table.php`

```php
Schema::create('visit_types', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id');
    $table->string('name');
    $table->string('slug');          // URL-safe, e.g. "general_opd"
    $table->boolean('is_default')->default(false);
    $table->boolean('is_active')->default(true);
    $table->unsignedSmallInteger('sort_order')->default(0);
    $table->timestamps();

    $table->unique(['tenant_id', 'slug']);
    $table->index('tenant_id');
    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
});
```

Default slugs seeded per tenant: `general_opd`, `pediatric`, `pregnant`, `eye`, `dental`, `ent`.

### 2. New column: `users.specialties`

Migration: `2026_XX_XX_000002_add_specialties_to_users_table.php`

```php
Schema::table('users', function (Blueprint $table) {
    $table->json('specialties')->nullable()->after('is_hod');
});
```

Default: `null` (treated as empty array in model cast).

### 3. New columns: `patient_records`

Migration: `2026_XX_XX_000003_add_visit_type_and_doctor_to_patient_records.php`

```php
Schema::table('patient_records', function (Blueprint $table) {
    $table->string('visit_type')->nullable()->after('registered_by');
    $table->uuid('assigned_doctor_id')->nullable()->after('visit_type');
    $table->foreign('assigned_doctor_id')->references('id')->on('users')->nullOnDelete();
    $table->index('assigned_doctor_id');
});
```

---

## Backend Implementation

### New Model: `VisitType`

**File:** `backend/app/Models/VisitType.php`

```php
class VisitType extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'is_default', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }
}
```

### Updated Model: `User`

Add to `$fillable`: `'specialties'`
Add to `$casts`: `'specialties' => 'array'`

### Updated Model: `PatientRecord`

Add to `$fillable`: `'visit_type'`, `'assigned_doctor_id'`

Add relationship:
```php
public function assignedDoctor()
{
    return $this->belongsTo(User::class, 'assigned_doctor_id');
}
```

### New Controller: `VisitTypeController`

**File:** `backend/app/Http/Controllers/Api/V1/VisitTypeController.php`

```
GET    /api/v1/visit-types          → index()   rbac:visit_types.view
POST   /api/v1/visit-types          → store()   rbac:visit_types.manage
PUT    /api/v1/visit-types/{id}     → update()  rbac:visit_types.manage
DELETE /api/v1/visit-types/{id}     → destroy() rbac:visit_types.manage
```

**`index()`** — returns active visit types ordered by `sort_order`:
```php
VisitType::where('is_active', true)->orderBy('sort_order')->get()
```

**`store()`** — validates `name` (required), `slug` (required, unique per tenant), `sort_order` (nullable int), `is_active` (nullable bool). Creates with `tenant_id` from container.

**`update()`** — validates same fields as store (all nullable). Returns 404 if not found in tenant scope.

**`destroy()`** — returns 422 if `is_default = true`. Otherwise sets `is_active = false` (soft-delete). Returns 404 if not found in tenant scope.

### New Controller: `DoctorController`

**File:** `backend/app/Http/Controllers/Api/V1/DoctorController.php`

```
GET /api/v1/doctors/by-visit-type/{visit_type}  → byVisitType()  rbac:doctors.list_by_visit_type
```

**`byVisitType(string $slug)`**:

1. Validate `$slug` exists in `visit_types` for current tenant (422 if not found).
2. Query active doctors in tenant:
   ```php
   User::with('department')
       ->where('role', 'doctor')
       ->where('is_active', true)
       ->where(function ($q) use ($slug) {
           // (a) specialty match
           $q->whereJsonContains('specialties', $slug)
           // (b) department type match
             ->orWhereHas('department', fn($d) => $d->where('type', $slug));
       })
       ->get(['id', 'name', 'department_id', 'specialties'])
   ```
3. Return each doctor with `id`, `name`, `department_id`, `department.name`, `specialties`.

### Updated: `HospitalAdminController`

**`createStaff()`** — add `specialties` to the `User::create()` call:
```php
'specialties' => $request->input('specialties', []),
```

**`updateStaff()`** — include `specialties` in `$updateData` from `$request->validated()`.

### Updated: `CreateStaffRequest` / `UpdateStaffRequest`

Add to `rules()`:
```php
'specialties'   => 'nullable|array',
'specialties.*' => 'string',   // validated against active slugs in controller
```

Slug validation against active `visit_types` for the tenant is done in the controller after the form request passes, to avoid circular dependency.

### Updated: `PatientController`

**`register()`** — add to `PatientRecord::create()`:
```php
'visit_type'         => $request->input('visit_type'),
'assigned_doctor_id' => $request->input('assigned_doctor_id'),
```

Add to `PatientJourneyEvent::create()` metadata:
```php
'metadata' => [
    'visit_type'         => $request->input('visit_type'),
    'assigned_doctor_id' => $request->input('assigned_doctor_id'),
],
```

**`newVisit()`** — same additions as `register()`.

### Updated: `RegisterPatientRequest`

Add to `rules()`:
```php
'visit_type'         => 'required|string',
'assigned_doctor_id' => 'nullable|uuid',
```

Slug existence and doctor ownership validation are done in the controller (after tenant context is available).

### Updated: `DepartmentController.getQueue()`

In the `map()` closure, after existing enrichments, add:

```php
// Assigned doctor info for nurse/triage
$assignedDoctor = null;
if ($record->assigned_doctor_id) {
    $assignedDoctor = \App\Models\User::with('department')
        ->find($record->assigned_doctor_id);
}
$data['visit_type']                  = $record->visit_type;
$data['assigned_doctor_id']          = $record->assigned_doctor_id;
$data['assigned_doctor_name']        = $assignedDoctor?->name;
$data['assigned_doctor_department_id'] = $assignedDoctor?->department_id;
```

To avoid N+1, eager-load `assignedDoctor.department` in the base query:
```php
PatientRecord::with([
    'patient',
    'currentDepartment',
    'workflowTemplate',
    'invoices',
    'assignedDoctor.department',   // ← new
])
```

### Updated: `PermissionSeeder`

Add three new entries to the `$permissions` array:

```php
['name' => 'visit_types.view',            'display_name' => 'View Visit Types',           'group' => 'visit_types', 'roles' => ['hospital_admin','front_desk_officer','doctor','nurse','triage']],
['name' => 'visit_types.manage',          'display_name' => 'Manage Visit Types',          'group' => 'visit_types', 'roles' => ['hospital_admin']],
['name' => 'doctors.list_by_visit_type',  'display_name' => 'List Doctors by Visit Type',  'group' => 'doctors',     'roles' => ['hospital_admin','front_desk_officer']],
```

### Updated: `routes/api.php`

Inside the protected middleware group, add:

```php
use App\Http\Controllers\Api\V1\VisitTypeController;
use App\Http\Controllers\Api\V1\DoctorController;

// Visit Types (hospital admin CRUD + read for front desk/nurse)
Route::prefix('visit-types')->group(function () {
    Route::get('/',      [VisitTypeController::class, 'index'])
        ->middleware('rbac:visit_types.view');
    Route::post('/',     [VisitTypeController::class, 'store'])
        ->middleware('rbac:visit_types.manage');
    Route::put('/{id}',  [VisitTypeController::class, 'update'])
        ->middleware('rbac:visit_types.manage');
    Route::delete('/{id}', [VisitTypeController::class, 'destroy'])
        ->middleware('rbac:visit_types.manage');
});

// Doctor listing by visit type
Route::get('/doctors/by-visit-type/{visit_type}', [DoctorController::class, 'byVisitType'])
    ->middleware('rbac:doctors.list_by_visit_type');
```

### New Seeder: `VisitTypeSeeder`

**File:** `backend/database/seeders/VisitTypeSeeder.php`

Seeds default visit types for all existing tenants (and is called from `TenantProvisioningService` / `DatabaseSeeder` for new tenants):

```php
$defaults = [
    ['name' => 'General OPD', 'slug' => 'general_opd', 'sort_order' => 1],
    ['name' => 'Pediatric',   'slug' => 'pediatric',   'sort_order' => 2],
    ['name' => 'Pregnant',    'slug' => 'pregnant',    'sort_order' => 3],
    ['name' => 'Eye',         'slug' => 'eye',         'sort_order' => 4],
    ['name' => 'Dental',      'slug' => 'dental',      'sort_order' => 5],
    ['name' => 'ENT',         'slug' => 'ent',         'sort_order' => 6],
];
```

Each entry is upserted with `is_default = true`, `is_active = true` per tenant.

---

## API Reference

### `GET /api/v1/visit-types`

**Permission:** `visit_types.view`

**Response 200:**
```json
{
  "success": true,
  "data": [
    { "id": "uuid", "name": "General OPD", "slug": "general_opd", "is_default": true, "is_active": true, "sort_order": 1 },
    { "id": "uuid", "name": "Pediatric",   "slug": "pediatric",   "is_default": true, "is_active": true, "sort_order": 2 }
  ]
}
```

### `POST /api/v1/visit-types`

**Permission:** `visit_types.manage`

**Body:** `{ "name": "Cardiology", "slug": "cardiology", "sort_order": 7 }`

**Response 201:** Created visit type object.

**Response 422:** Slug already exists for tenant.

### `PUT /api/v1/visit-types/{id}`

**Permission:** `visit_types.manage`

**Body:** Any subset of `name`, `slug`, `is_active`, `sort_order`.

**Response 200:** Updated visit type object.

**Response 404:** Not found in tenant scope.

### `DELETE /api/v1/visit-types/{id}`

**Permission:** `visit_types.manage`

**Response 200:** `{ "success": true, "message": "Visit type deactivated." }`

**Response 422:** Cannot delete a default visit type.

**Response 404:** Not found in tenant scope.

### `GET /api/v1/doctors/by-visit-type/{visit_type}`

**Permission:** `doctors.list_by_visit_type`

**Path param:** `visit_type` — a Visit_Type_Slug (e.g. `pediatric`).

**Response 200:**
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "name": "Dr. Amara Osei",
      "department_id": "uuid",
      "department": { "id": "uuid", "name": "Pediatrics" },
      "specialties": ["pediatric", "general_opd"]
    }
  ]
}
```

**Response 422:** Unrecognised visit type slug.

### `POST /api/v1/patients` (updated)

**Additional body fields:**
```json
{
  "visit_type": "pediatric",
  "assigned_doctor_id": "uuid-or-null"
}
```

### `GET /api/v1/department/queue` (updated)

Each record now includes:
```json
{
  "visit_type": "pediatric",
  "assigned_doctor_id": "uuid",
  "assigned_doctor_name": "Dr. Amara Osei",
  "assigned_doctor_department_id": "uuid"
}
```

---

## Mobile Implementation

### New Provider: `visitTypesProvider`

**File:** `mobile/lib/presentation/providers/visit_type_provider.dart`

```dart
final visitTypesProvider = FutureProvider<List<VisitType>>((ref) async {
  final repo = ref.watch(visitTypeRepositoryProvider);
  return repo.getVisitTypes();
});

final doctorsByVisitTypeProvider =
    FutureProvider.family<List<DoctorSummary>, String>((ref, slug) async {
  final repo = ref.watch(visitTypeRepositoryProvider);
  return repo.getDoctorsByVisitType(slug);
});
```

### New Entity: `VisitType`

**File:** `mobile/lib/domain/entities/visit_type.dart`

```dart
class VisitType {
  final String id;
  final String name;
  final String slug;
  final bool isDefault;
  final bool isActive;
  final int sortOrder;

  factory VisitType.fromJson(Map<String, dynamic> json) => VisitType(
    id: json['id'],
    name: json['name'],
    slug: json['slug'],
    isDefault: json['is_default'] ?? false,
    isActive: json['is_active'] ?? true,
    sortOrder: json['sort_order'] ?? 0,
  );
}
```

### New Entity: `DoctorSummary`

**File:** `mobile/lib/domain/entities/doctor_summary.dart`

```dart
class DoctorSummary {
  final String id;
  final String name;
  final String? departmentId;
  final String? departmentName;
  final List<String> specialties;

  factory DoctorSummary.fromJson(Map<String, dynamic> json) => DoctorSummary(
    id: json['id'],
    name: json['name'],
    departmentId: json['department_id'],
    departmentName: json['department']?['name'],
    specialties: List<String>.from(json['specialties'] ?? []),
  );
}
```

### Updated Entity: `PatientRecord`

Add fields:
```dart
final String? visitType;
final String? assignedDoctorId;
final String? assignedDoctorName;
final String? assignedDoctorDepartmentId;
```

Update `fromJson`:
```dart
visitType: json['visit_type'] as String?,
assignedDoctorId: json['assigned_doctor_id'] as String?,
assignedDoctorName: json['assigned_doctor_name'] as String?,
assignedDoctorDepartmentId: json['assigned_doctor_department_id'] as String?,
```

### Updated: `PatientRegistrationData`

Add fields:
```dart
final String visitType;
final String? assignedDoctorId;
```

### Updated: `PatientRegistrationScreen`

**New state fields:**
```dart
String? _selectedVisitTypeSlug;
String? _selectedDoctorId;
List<VisitType> _visitTypes = [];
List<DoctorSummary> _availableDoctors = [];
bool _loadingDoctors = false;
bool _noDoctorsAvailable = false;
```

**Visit Type dropdown** (placed after Chief Complaint section):
- Populated from `visitTypesProvider` on `initState`.
- On change: clears `_selectedDoctorId`, sets `_loadingDoctors = true`, calls `getDoctorsByVisitType(slug)`, populates `_availableDoctors`.

**Doctor picker** (shown after visit type is selected):
- `DropdownButtonFormField<String>` disabled when `_selectedVisitTypeSlug == null`.
- When `_noDoctorsAvailable`, shows an info chip "No doctors available for this visit type" and skips validation.
- Validator: required when `_availableDoctors.isNotEmpty`.

**Submit** — adds `visitType` and `assignedDoctorId` to `PatientRegistrationData`.

### Updated: `_NurseDashboard` / `_PatientCard`

**Visit type badge** — shown below patient name when `record.visitType != null`:
```dart
Container(
  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
  decoration: BoxDecoration(
    color: Colors.blue.shade50,
    borderRadius: BorderRadius.circular(8),
    border: Border.all(color: Colors.blue.shade300),
  ),
  child: Text(record.visitType!, style: TextStyle(fontSize: 10, color: Colors.blue.shade700)),
)
```

**Assigned doctor name** — shown below visit type badge when `record.assignedDoctorName != null`:
```dart
Text('Dr: ${record.assignedDoctorName}', style: TextStyle(fontSize: 11, color: Colors.grey[700]))
```

**"Route to Dr." button** — shown in the `_NurseDashboard` `trailingBuilder` when `record.assignedDoctorDepartmentId != null`:
```dart
ElevatedButton.icon(
  onPressed: _routing ? null : () => _routeToDoctor(record),
  icon: const Icon(Icons.send, size: 14),
  label: Text('Route to Dr. ${record.assignedDoctorName ?? ""}'),
  style: ElevatedButton.styleFrom(backgroundColor: Colors.teal),
)
```

The `_routeToDoctor` method calls `POST /api/v1/patient-records/{id}/route` with `to_department_id = record.assignedDoctorDepartmentId`, then refreshes the queue on success or shows a `SnackBar` on error.

---

## Data Flow

### Registration Flow

```
Front Desk opens Registration Screen
  → app fetches GET /api/v1/visit-types
  → user selects "Pediatric"
  → app fetches GET /api/v1/doctors/by-visit-type/pediatric
  → user selects Dr. Amara Osei
  → user submits form
  → POST /api/v1/patients { ..., visit_type: "pediatric", assigned_doctor_id: "uuid" }
  → PatientRecord created with visit_type + assigned_doctor_id
  → PatientJourneyEvent created with metadata { visit_type, assigned_doctor_id }
```

### Nurse Queue Flow

```
Nurse opens dashboard
  → GET /api/v1/department/queue
  → each record includes visit_type, assigned_doctor_name, assigned_doctor_department_id
  → _PatientCard renders visit type badge + doctor name
  → nurse taps "Route to Dr. Amara Osei"
  → POST /api/v1/patient-records/{id}/route { to_department_id: "uuid" }
  → patient moves to doctor's department queue
  → nurse queue refreshes
```

---

## Error Handling

| Scenario | HTTP | Response |
|---|---|---|
| `visit_type` slug not found in tenant | 422 | `VALIDATION_ERROR` with field detail |
| `assigned_doctor_id` not active doctor in tenant | 422 | `VALIDATION_ERROR` with field detail |
| DELETE default visit type | 422 | `CANNOT_DELETE_DEFAULT` |
| PUT/DELETE visit type from another tenant | 404 | `NOT_FOUND` |
| `specialties` entry not a valid slug | 422 | `VALIDATION_ERROR` |
| Doctor list empty for visit type | 200 | `data: []` (not an error) |

---

## Correctness Properties

### P1 — Doctor filter is a superset of specialty match and department match

For any valid `visit_type` slug `s`, the set of doctors returned by `GET /api/v1/doctors/by-visit-type/{s}` equals the union of:
- doctors whose `specialties` JSON array contains `s`
- doctors whose `department.type` equals `s`

No doctor satisfying either condition is omitted; no doctor satisfying neither condition is included.

### P2 — Visit type slug uniqueness per tenant

For any two distinct `VisitType` records belonging to the same tenant, their `slug` values are different. Inserting a duplicate slug for the same tenant returns HTTP 422.

### P3 — Default visit types are immutable via DELETE

For any `VisitType` with `is_default = true`, calling `DELETE /api/v1/visit-types/{id}` returns HTTP 422 and the record remains `is_active = true`.

### P4 — Queue enrichment completeness

For any `PatientRecord` with a non-null `assigned_doctor_id`, the queue response includes non-null `assigned_doctor_name` and `assigned_doctor_department_id` matching the referenced `User` record.

### P5 — Tenant isolation

For any two tenants A and B, visit types created for tenant A are never returned in queries scoped to tenant B, and vice versa.
