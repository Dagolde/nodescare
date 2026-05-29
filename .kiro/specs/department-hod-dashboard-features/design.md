# Design Document: Department HOD Dashboard Features

## Overview

This design document specifies the technical implementation for comprehensive management and reporting features in the department Head of Department (HOD) web dashboard. The system extends the existing Laravel/Inertia.js/Vue 3 architecture to provide HODs with staff management, department statistics, vitals recording, lab results management, prescription management, reporting capabilities, and department settings.

### Goals

- Enable HODs to manage department staff through a web interface
- Provide real-time department performance metrics and visualizations
- Allow HODs to record and view patient vitals from the web dashboard
- Enable HODs to view and export lab results and prescriptions
- Provide comprehensive reporting capabilities for department analysis
- Allow HODs to configure department settings and preferences
- Maintain multi-tenant data isolation and audit logging compliance
- Deliver responsive, performant user experience with real-time updates

### Non-Goals

- Mobile app features for HODs (web dashboard only)
- Patient-facing features
- Billing and payment processing (handled by accounts department)
- Workflow template creation (handled by hospital admin)
- Cross-tenant data access or reporting

### Technical Context

**Existing Architecture:**
- Backend: Laravel 10+ with PostgreSQL database
- Frontend: Inertia.js with Vue 3 and Tailwind CSS
- Authentication: Session-based for web users, JWT for mobile API
- Multi-tenancy: Tenant resolution middleware with global scopes
- Real-time: Laravel Echo with Pusher for WebSocket events
- Authorization: Role-based access control (RBAC) with `is_hod` flag

**Key Existing Components:**
- `HodController`: API endpoints for staff management (already implemented)
- `DepartmentWebController`: Web routes for queue and patient detail views
- `PatientVitals` model: Stores patient vital signs
- `LabResult` model: Stores laboratory test results
- `PatientJourneyEvent` model: Stores patient workflow events with metadata
- `AuditLog` model: Compliance and security tracking



## Architecture

### System Architecture

The HOD dashboard follows a three-tier architecture:

```
┌─────────────────────────────────────────────────────────────┐
│                     Browser (Vue 3 + Inertia.js)            │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │  Dashboard   │  │    Staff     │  │   Reports    │     │
│  │  Components  │  │  Management  │  │  Components  │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
└─────────────────────────────────────────────────────────────┘
                            │
                            │ HTTPS (Session-based Auth)
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                    Laravel Application Layer                 │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Middleware Stack                                     │  │
│  │  - TenantResolutionMiddleware                        │  │
│  │  - DepartmentAuthMiddleware (is_hod check)           │  │
│  │  - TenantScopeMiddleware                             │  │
│  └──────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Controllers                                          │  │
│  │  - DepartmentWebController (Inertia pages)           │  │
│  │  - HodController (API endpoints)                     │  │
│  │  - DepartmentApiController (new, for dashboard data) │  │
│  └──────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Services                                             │  │
│  │  - DepartmentStatisticsService                       │  │
│  │  - ReportGenerationService                           │  │
│  │  - PdfExportService                                  │  │
│  │  - ExcelExportService                                │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                    PostgreSQL Database                       │
│  - users (staff)                                            │
│  - departments                                              │
│  - patient_records                                          │
│  - patient_vitals                                           │
│  - lab_results                                              │
│  - patient_journey_events (prescriptions in metadata)       │
│  - audit_logs                                               │
└─────────────────────────────────────────────────────────────┘
```

### Real-time Architecture

```
┌─────────────────┐         ┌─────────────────┐
│  Vue Components │◄────────│  Laravel Echo   │
│  (Dashboard)    │         │  (Pusher)       │
└─────────────────┘         └─────────────────┘
                                    ▲
                                    │
                            WebSocket Events
                                    │
┌─────────────────────────────────────────────┐
│  Laravel Broadcasting                       │
│  - PatientRouted event                      │
│  - PatientStatusChanged event               │
│  - VitalsRecorded event (new)               │
│  Channel: department.{department_id}        │
└─────────────────────────────────────────────┘
```

### Data Flow

**Staff Management Flow:**
1. HOD submits staff form → DepartmentWebController renders Inertia page
2. Vue component calls API → HodController validates and processes
3. Controller creates/updates User record → Audit log created
4. Response returned → Vue component updates UI

**Dashboard Statistics Flow:**
1. Dashboard page loads → DepartmentWebController calls DepartmentStatisticsService
2. Service queries database with tenant/department scoping
3. Statistics calculated and cached (5 minutes)
4. Data passed to Inertia page → Vue renders charts and metrics

**Report Generation Flow:**
1. HOD selects report type and date range → API call to DepartmentApiController
2. Controller calls ReportGenerationService with parameters
3. Service queries database and calculates metrics
4. For PDF/Excel export: PdfExportService or ExcelExportService generates file
5. File streamed to browser for download



## Components and Interfaces

### Backend Components

#### 1. DepartmentApiController (New)

**Purpose:** Handle API requests for dashboard statistics, vitals, lab results, prescriptions, and reports.

**Key Methods:**
```php
// Dashboard Statistics
public function getDashboardStatistics(Request $request): JsonResponse
public function getPatientFlowChart(Request $request): JsonResponse
public function getStaffWorkloadChart(Request $request): JsonResponse
public function getRecentActivity(Request $request): JsonResponse

// Vitals Management
public function recordVitals(string $patientRecordId, Request $request): JsonResponse
public function getVitalsHistory(string $patientRecordId): JsonResponse

// Lab Results Management
public function getLabResults(Request $request): JsonResponse
public function getInvestigationNotes(string $patientRecordId): JsonResponse
public function exportLabResultPdf(string $labResultId): Response

// Prescription Management
public function getPrescriptions(Request $request): JsonResponse
public function getPrescriptionHistory(string $patientRecordId): JsonResponse
public function exportPrescriptionPdf(string $eventId): Response

// Reports
public function getDailySummaryReport(Request $request): JsonResponse
public function getStaffPerformanceReport(Request $request): JsonResponse
public function getDepartmentEfficiencyReport(Request $request): JsonResponse
public function exportReportPdf(Request $request): Response
public function exportReportExcel(Request $request): Response

// Department Settings
public function updateDepartmentInfo(Request $request): JsonResponse
public function updateWorkingHours(Request $request): JsonResponse
public function updateNotificationPreferences(Request $request): JsonResponse
```

**Authorization:** All methods check `is_hod` flag and department ownership.

#### 2. DepartmentStatisticsService (New)

**Purpose:** Calculate department performance metrics and statistics.

**Key Methods:**
```php
public function getQueueStatistics(string $departmentId): array
// Returns: total_in_queue, patients_seen_today, average_wait_time, staff_on_duty

public function getPatientFlowData(string $departmentId, int $days = 7): array
// Returns: array of daily patient counts (entered, completed)

public function getStaffWorkloadData(string $departmentId, Carbon $date): array
// Returns: array of staff names and patient counts

public function getRecentActivityFeed(string $departmentId, int $limit = 10): Collection
// Returns: collection of recent PatientJourneyEvents

public function calculateAverageWaitTime(string $departmentId): float
// Returns: average wait time in minutes

public function calculateAverageServiceTime(string $departmentId, Carbon $startDate, Carbon $endDate): float
// Returns: average service time in minutes

public function calculateStaffUtilization(string $departmentId, Carbon $startDate, Carbon $endDate): float
// Returns: staff utilization percentage
```

**Caching Strategy:** Cache statistics for 5 minutes using Laravel cache with keys like `dept_stats:{department_id}:{metric}`.

#### 3. ReportGenerationService (New)

**Purpose:** Generate various department reports with calculated metrics.

**Key Methods:**
```php
public function generateDailySummary(string $departmentId, Carbon $date): array
// Returns: patient list, summary metrics (total, avg wait, avg service)

public function generateStaffPerformance(string $departmentId, Carbon $startDate, Carbon $endDate): array
// Returns: staff performance metrics (patients handled, avg service time, notes added)

public function generateEfficiencyMetrics(string $departmentId, Carbon $startDate, Carbon $endDate): array
// Returns: efficiency metrics (wait time, service time, throughput, utilization)

public function calculateTrendIndicators(array $currentMetrics, array $previousMetrics): array
// Returns: trend indicators (improving/declining) for each metric
```

#### 4. PdfExportService (New)

**Purpose:** Generate PDF documents for lab results, prescriptions, and reports.

**Dependencies:** Use `barryvdh/laravel-dompdf` package.

**Key Methods:**
```php
public function generateLabResultPdf(LabResult $labResult): Response
// Generates PDF with patient info, test details, hospital branding

public function generatePrescriptionPdf(PatientJourneyEvent $event): Response
// Generates PDF with patient info, prescription details, doctor info

public function generateReportPdf(string $reportType, array $data, array $metadata): Response
// Generates PDF report with charts, tables, and summary metrics
```

**PDF Template Structure:**
- Header: Hospital logo, name, department name
- Body: Report-specific content with tables and charts
- Footer: Generation timestamp, page numbers

#### 5. ExcelExportService (New)

**Purpose:** Generate Excel spreadsheets for reports.

**Dependencies:** Use `maatwebsite/excel` package.

**Key Methods:**
```php
public function generateReportExcel(string $reportType, array $data, array $metadata): Response
// Generates Excel file with formatted data, headers, and calculations
```

### Frontend Components (Vue 3)

#### 1. DepartmentDashboard.vue (New)

**Purpose:** Main dashboard page showing statistics, charts, and activity feed.

**Props:**
- `statistics`: Object with queue stats
- `patientFlowData`: Array for chart
- `staffWorkloadData`: Array for chart
- `recentActivity`: Array of recent events
- `department`: Current department object
- `currentUser`: Authenticated HOD user

**Key Features:**
- Real-time statistics display with auto-refresh
- Patient flow line/bar chart (Chart.js or ApexCharts)
- Staff workload bar chart
- Recent activity feed with live updates
- WebSocket connection status indicator

#### 2. StaffManagement.vue (New)

**Purpose:** Staff listing, creation, editing, and deactivation interface.

**Props:**
- `staff`: Paginated staff list
- `filters`: Current filter values
- `allowedRoles`: Array of role options

**Key Features:**
- Staff table with sorting and filtering
- Create staff modal with form validation
- Edit staff modal
- Deactivate confirmation dialog
- Search and filter controls

#### 3. VitalsRecording.vue (New)

**Purpose:** Record and view patient vitals.

**Props:**
- `patientRecord`: Current patient record
- `vitalsHistory`: Array of previous vitals

**Key Features:**
- Vitals input form with validation
- Automatic BMI calculation
- Normal range indicators (color-coded)
- Vitals history table with trend visualization

#### 4. LabResultsManagement.vue (New)

**Purpose:** View and export lab results.

**Props:**
- `labResults`: Paginated lab results
- `filters`: Current filter values

**Key Features:**
- Lab results table with filtering
- Patient, date range, and test type filters
- Export to PDF button per result
- Pagination controls

#### 5. PrescriptionManagement.vue (New)

**Purpose:** View and export prescriptions.

**Props:**
- `prescriptions`: Paginated prescriptions (from journey events)
- `filters`: Current filter values

**Key Features:**
- Prescriptions table with filtering
- Patient, doctor, and date range filters
- Export to PDF button per prescription
- Pagination controls

#### 6. DepartmentReports.vue (New)

**Purpose:** Generate and export various department reports.

**Props:**
- `reportTypes`: Available report types
- `department`: Current department

**Key Features:**
- Report type selector
- Date range picker with validation
- Generate report button
- Report preview display
- Export to PDF/Excel buttons
- Trend indicators for efficiency metrics

#### 7. DepartmentSettings.vue (New)

**Purpose:** Configure department information, working hours, and notifications.

**Props:**
- `department`: Current department object
- `workingHours`: Current working hours configuration
- `notificationPreferences`: Current notification settings

**Key Features:**
- Department info form
- Working hours configuration (per day)
- Notification preferences toggles
- Save confirmation

### API Endpoints

#### Staff Management (Existing - HodController)
```
GET    /api/v1/my-department/staff
POST   /api/v1/my-department/staff
PUT    /api/v1/my-department/staff/{id}
PATCH  /api/v1/my-department/staff/{id}/deactivate
```

#### Dashboard Statistics (New - DepartmentApiController)
```
GET    /department/api/dashboard/statistics
GET    /department/api/dashboard/patient-flow
GET    /department/api/dashboard/staff-workload
GET    /department/api/dashboard/recent-activity
```

#### Vitals Management (New)
```
POST   /department/api/patient-records/{id}/vitals
GET    /department/api/patient-records/{id}/vitals
```

#### Lab Results Management (New)
```
GET    /department/api/lab-results
GET    /department/api/patient-records/{id}/investigation-notes
GET    /department/api/lab-results/{id}/export-pdf
```

#### Prescription Management (New)
```
GET    /department/api/prescriptions
GET    /department/api/patient-records/{id}/prescriptions
GET    /department/api/prescriptions/{eventId}/export-pdf
```

#### Reports (New)
```
GET    /department/api/reports/daily-summary
GET    /department/api/reports/staff-performance
GET    /department/api/reports/efficiency-metrics
POST   /department/api/reports/export-pdf
POST   /department/api/reports/export-excel
```

#### Department Settings (New)
```
PUT    /department/api/settings/info
PUT    /department/api/settings/working-hours
PUT    /department/api/settings/notifications
```

### WebSocket Events

#### New Events to Broadcast

**VitalsRecorded Event:**
```php
class VitalsRecorded implements ShouldBroadcast
{
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("department.{$this->departmentId}"),
        ];
    }
    
    public function broadcastWith(): array
    {
        return [
            'patient_record_id' => $this->patientRecordId,
            'vitals' => $this->vitals,
            'recorded_by' => $this->recorder->name,
        ];
    }
}
```

**LabResultAdded Event:**
```php
class LabResultAdded implements ShouldBroadcast
{
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("department.{$this->departmentId}"),
        ];
    }
}
```



## Data Models

### Existing Models (No Changes Required)

#### User Model
```php
// Relevant fields for HOD features
id: uuid
tenant_id: uuid
department_id: uuid
name: string
email: string
phone: string
role: enum (doctor, nurse, lab_technician, pharmacist, etc.)
is_active: boolean
is_hod: boolean
token_version: integer
created_by: uuid
```

#### Department Model
```php
id: uuid
tenant_id: uuid
name: string
description: text
type: string
code: string
is_active: boolean
contact_email: string
contact_phone: string
```

#### PatientVitals Model
```php
id: uuid
tenant_id: uuid
patient_record_id: uuid
recorded_by: uuid
blood_pressure: string (e.g., "120/80")
temperature: decimal(5,2) // °C
pulse_rate: integer // bpm
respiratory_rate: integer // breaths/min
oxygen_saturation: integer // %
weight: decimal(6,2) // kg
height: decimal(6,2) // cm
bmi: decimal(5,2) // calculated
blood_glucose: integer // mg/dL
notes: text
created_at: timestamp
updated_at: timestamp
```

#### LabResult Model
```php
id: uuid
tenant_id: uuid
patient_record_id: uuid
test_name: string
result: text
unit: string
reference_range: string
status: enum (pending, completed, abnormal)
notes: text
technician_id: uuid
created_at: timestamp
updated_at: timestamp
```

#### PatientJourneyEvent Model
```php
id: uuid
tenant_id: uuid
patient_record_id: uuid
event_type: string
from_department_id: uuid
to_department_id: uuid
notes: text
metadata: json // Contains prescription_notes, investigation_notes, etc.
performed_by: uuid
created_at: timestamp
updated_at: timestamp
```

#### AuditLog Model
```php
id: uuid
tenant_id: uuid
actor_id: uuid
actor_role: string
action: string
resource_type: string
resource_id: uuid
description: text
ip_address: string
created_at: timestamp
```

### New Models

#### DepartmentWorkingHours Model (New)
```php
id: uuid
tenant_id: uuid
department_id: uuid
day_of_week: integer (0-6, 0=Sunday)
is_open: boolean
opening_time: time
closing_time: time
created_at: timestamp
updated_at: timestamp

// Indexes
index: [department_id, day_of_week]
unique: [department_id, day_of_week]
```

**Migration:**
```php
Schema::create('department_working_hours', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id');
    $table->uuid('department_id');
    $table->integer('day_of_week'); // 0-6
    $table->boolean('is_open')->default(true);
    $table->time('opening_time')->nullable();
    $table->time('closing_time')->nullable();
    $table->timestamps();
    
    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
    $table->foreign('department_id')->references('id')->on('departments')->onDelete('cascade');
    $table->unique(['department_id', 'day_of_week']);
    $table->index(['department_id', 'day_of_week']);
});
```

#### UserNotificationPreferences Model (New)
```php
id: uuid
tenant_id: uuid
user_id: uuid
patient_arrivals_enabled: boolean
staff_actions_enabled: boolean
system_alerts_enabled: boolean
email_enabled: boolean
sms_enabled: boolean
in_app_enabled: boolean
created_at: timestamp
updated_at: timestamp

// Indexes
unique: [user_id]
```

**Migration:**
```php
Schema::create('user_notification_preferences', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id');
    $table->uuid('user_id');
    $table->boolean('patient_arrivals_enabled')->default(true);
    $table->boolean('staff_actions_enabled')->default(true);
    $table->boolean('system_alerts_enabled')->default(true);
    $table->boolean('email_enabled')->default(true);
    $table->boolean('sms_enabled')->default(false);
    $table->boolean('in_app_enabled')->default(true);
    $table->timestamps();
    
    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
    $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
    $table->unique('user_id');
});
```

### Data Relationships

```
Department
  ├── hasMany(User) // staff members
  ├── hasMany(PatientRecord) // current patients
  ├── hasMany(DepartmentWorkingHours)
  └── hasMany(PatientJourneyEvent) // department activities

User (HOD)
  ├── belongsTo(Department)
  ├── hasMany(User) // created staff (via created_by)
  ├── hasMany(PatientVitals) // recorded vitals (via recorded_by)
  ├── hasMany(AuditLog) // actions performed (via actor_id)
  └── hasOne(UserNotificationPreferences)

PatientRecord
  ├── hasMany(PatientVitals)
  ├── hasMany(LabResult)
  ├── hasMany(PatientJourneyEvent)
  └── belongsTo(Department) // current_department_id

PatientJourneyEvent
  ├── belongsTo(PatientRecord)
  ├── belongsTo(User) // performer
  ├── belongsTo(Department) // from_department_id
  └── belongsTo(Department) // to_department_id
```

### Metadata Structures

#### PatientJourneyEvent Metadata Examples

**Prescription Notes:**
```json
{
  "prescription_notes": "Paracetamol 500mg, 3 times daily for 5 days\nAmoxicillin 250mg, twice daily for 7 days",
  "prescribing_doctor_id": "uuid",
  "prescribing_doctor_name": "Dr. John Doe"
}
```

**Investigation Notes:**
```json
{
  "investigation_notes": "Full Blood Count\nLiver Function Test\nChest X-Ray",
  "requesting_doctor_id": "uuid",
  "requesting_doctor_name": "Dr. Jane Smith"
}
```

### Vitals Normal Ranges (Constants)

```php
class VitalsNormalRanges
{
    public const SYSTOLIC_BP_MIN = 90;
    public const SYSTOLIC_BP_MAX = 120;
    public const DIASTOLIC_BP_MIN = 60;
    public const DIASTOLIC_BP_MAX = 80;
    public const TEMPERATURE_MIN = 36.1;
    public const TEMPERATURE_MAX = 37.2;
    public const PULSE_MIN = 60;
    public const PULSE_MAX = 100;
    public const RESPIRATORY_RATE_MIN = 12;
    public const RESPIRATORY_RATE_MAX = 20;
    public const OXYGEN_SATURATION_MIN = 95;
    public const OXYGEN_SATURATION_MAX = 100;
    public const BMI_MIN = 18.5;
    public const BMI_MAX = 24.9;
}
```



## Error Handling

### Error Categories

#### 1. Authentication and Authorization Errors

**Unauthenticated Access:**
- **Scenario:** User not logged in attempts to access HOD dashboard
- **Response:** HTTP 302 redirect to `/department/login`
- **Message:** "Please log in to continue"

**Non-HOD Access:**
- **Scenario:** User without `is_hod=true` attempts to access HOD features
- **Response:** HTTP 403 Forbidden
- **Error Code:** `FORBIDDEN`
- **Message:** "Only Heads of Department can access this feature"

**Cross-Department Access:**
- **Scenario:** HOD attempts to access staff/data from another department
- **Response:** HTTP 403 Forbidden
- **Error Code:** `FORBIDDEN`
- **Message:** "You can only manage staff/data in your own department"

**Cross-Tenant Access:**
- **Scenario:** HOD attempts to access data from another tenant
- **Response:** HTTP 403 Forbidden
- **Error Code:** `FORBIDDEN`
- **Message:** "Access denied"

#### 2. Validation Errors

**Staff Creation Validation:**
- **Duplicate Email:** HTTP 422, code `DUPLICATE_EMAIL`, message "Email already exists"
- **Invalid Role:** HTTP 422, code `INVALID_ROLE`, message "Invalid role selected"
- **Weak Password:** HTTP 422, code `WEAK_PASSWORD`, message "Password must be at least 10 characters with uppercase, lowercase, number, and special character"
- **Missing Required Fields:** HTTP 422, code `VALIDATION_ERROR`, message with field-specific errors

**Vitals Recording Validation:**
- **Invalid Numeric Values:** HTTP 422, code `INVALID_VALUE`, message "Invalid value for {field}"
- **Out of Range Values:** HTTP 422, code `OUT_OF_RANGE`, message "{field} value is outside acceptable range"
- **Missing Patient Record:** HTTP 404, code `PATIENT_NOT_FOUND`, message "Patient record not found"

**Report Generation Validation:**
- **Invalid Date Range:** HTTP 422, code `INVALID_DATE_RANGE`, message "Start date must be before end date"
- **Date Range Too Large:** HTTP 422, code `DATE_RANGE_EXCEEDED`, message "Date range cannot exceed 365 days"
- **Future Dates:** HTTP 422, code `INVALID_DATE`, message "Cannot generate reports for future dates"

**Department Settings Validation:**
- **Duplicate Department Name:** HTTP 422, code `DUPLICATE_NAME`, message "Department name already exists"
- **Invalid Working Hours:** HTTP 422, code `INVALID_HOURS`, message "Opening time must be before closing time"

#### 3. Resource Not Found Errors

**Staff Not Found:**
- **Response:** HTTP 404
- **Error Code:** `STAFF_NOT_FOUND`
- **Message:** "Staff account not found"

**Patient Record Not Found:**
- **Response:** HTTP 404
- **Error Code:** `PATIENT_NOT_FOUND`
- **Message:** "Patient record not found"

**Lab Result Not Found:**
- **Response:** HTTP 404
- **Error Code:** `LAB_RESULT_NOT_FOUND`
- **Message:** "Lab result not found"

**Department Not Found:**
- **Response:** HTTP 404
- **Error Code:** `DEPARTMENT_NOT_FOUND`
- **Message:** "Department not found"

#### 4. Business Logic Errors

**Already Deactivated:**
- **Scenario:** Attempting to deactivate already inactive staff
- **Response:** HTTP 400 Bad Request
- **Error Code:** `ALREADY_DEACTIVATED`
- **Message:** "Staff account is already deactivated"

**Cannot Deactivate Self:**
- **Scenario:** HOD attempts to deactivate their own account
- **Response:** HTTP 400 Bad Request
- **Error Code:** `CANNOT_DEACTIVATE_SELF`
- **Message:** "You cannot deactivate your own account"

**Patient Not in Department:**
- **Scenario:** HOD attempts to record vitals for patient not in their department
- **Response:** HTTP 403 Forbidden
- **Error Code:** `PATIENT_NOT_IN_DEPARTMENT`
- **Message:** "Patient is not currently in your department"

#### 5. System Errors

**Database Connection Error:**
- **Response:** HTTP 500 Internal Server Error
- **Error Code:** `DATABASE_ERROR`
- **Message:** "A database error occurred. Please try again"
- **Logging:** Full error logged with context

**PDF Generation Error:**
- **Response:** HTTP 500 Internal Server Error
- **Error Code:** `PDF_GENERATION_FAILED`
- **Message:** "Failed to generate PDF. Please try again"
- **Logging:** Full error logged with lab result/prescription ID

**Excel Export Error:**
- **Response:** HTTP 500 Internal Server Error
- **Error Code:** `EXCEL_EXPORT_FAILED`
- **Message:** "Failed to export to Excel. Please try again"
- **Logging:** Full error logged with report type and parameters

**Cache Error:**
- **Response:** Fallback to database query (transparent to user)
- **Logging:** Cache error logged for monitoring

**WebSocket Connection Error:**
- **Frontend Handling:** Display connection status indicator, attempt reconnection
- **User Message:** "Real-time updates temporarily unavailable. Refresh to see latest data"

### Error Response Format

**JSON API Responses:**
```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable error message",
    "details": {
      "field": "Specific field error (for validation)"
    }
  }
}
```

**Inertia Error Responses:**
```php
return redirect()->back()->withErrors([
    'error' => 'Error message'
]);
```

### Error Logging Strategy

**Log Levels:**
- **ERROR:** System failures, database errors, unexpected exceptions
- **WARNING:** Business logic violations, validation failures
- **INFO:** Successful operations, audit trail

**Log Context:**
```php
\Log::error('Operation failed', [
    'error' => $exception->getMessage(),
    'user_id' => $user->id,
    'tenant_id' => app('current_tenant_id'),
    'department_id' => $user->department_id,
    'resource_id' => $resourceId,
    'ip_address' => $request->ip(),
]);
```

### User-Facing Error Messages

**Principles:**
- Clear and actionable
- No technical jargon or stack traces
- Suggest next steps when possible
- Consistent tone and formatting

**Examples:**
- ✅ "Email already exists. Please use a different email address"
- ✅ "Date range cannot exceed 365 days. Please select a shorter period"
- ❌ "SQLSTATE[23000]: Integrity constraint violation"
- ❌ "Exception in line 42 of DepartmentController.php"

### Retry and Recovery

**Automatic Retry:**
- WebSocket reconnection: Exponential backoff (1s, 2s, 4s, 8s, max 30s)
- Cache failures: Immediate fallback to database

**Manual Retry:**
- Failed API requests: Display "Try Again" button
- Failed exports: Allow user to retry generation

**Data Recovery:**
- Form data preserved on validation errors
- Unsaved changes warning before navigation



## Testing Strategy

### Why Property-Based Testing Does Not Apply

Property-based testing (PBT) is **not appropriate** for this feature because:

1. **Web UI and User Interactions:** The feature is primarily a web dashboard with forms, tables, charts, and user interactions. PBT is not suitable for testing UI rendering and layout.

2. **CRUD Operations:** Staff management, vitals recording, and settings updates are simple CRUD operations with no complex transformation logic. Example-based unit tests are more appropriate.

3. **Reporting and Export:** Report generation involves querying data and formatting it for display or export. These are deterministic operations best tested with specific examples and snapshot tests.

4. **External Dependencies:** PDF and Excel generation rely on third-party libraries (dompdf, maatwebsite/excel). Testing these integrations requires example-based tests with known inputs and expected outputs.

5. **Authorization and Access Control:** Testing role-based access control and multi-tenant isolation requires specific scenarios, not universal properties.

**Alternative Testing Approaches:**
- **Unit Tests:** Test individual service methods with specific examples
- **Integration Tests:** Test API endpoints with database interactions
- **Feature Tests:** Test complete user workflows end-to-end
- **Snapshot Tests:** Test PDF/Excel output formatting
- **Browser Tests:** Test Vue components and user interactions

### Testing Layers

#### 1. Unit Tests (PHPUnit)

**Target:** Individual service methods and business logic

**DepartmentStatisticsService Tests:**
```php
class DepartmentStatisticsServiceTest extends TestCase
{
    public function test_calculates_queue_statistics_correctly()
    {
        // Arrange: Create department with 5 patients in queue
        $department = Department::factory()->create();
        PatientRecord::factory()->count(5)->create([
            'current_department_id' => $department->id,
            'status' => 'in_progress',
        ]);
        
        // Act
        $stats = $this->service->getQueueStatistics($department->id);
        
        // Assert
        $this->assertEquals(5, $stats['total_in_queue']);
    }
    
    public function test_calculates_average_wait_time()
    {
        // Test with known patient journey events
        // Assert calculated average matches expected value
    }
    
    public function test_returns_zero_for_empty_queue()
    {
        // Edge case: no patients in queue
    }
}
```

**ReportGenerationService Tests:**
```php
class ReportGenerationServiceTest extends TestCase
{
    public function test_generates_daily_summary_with_correct_metrics()
    {
        // Arrange: Create patients with known timestamps
        // Act: Generate daily summary
        // Assert: Metrics match expected calculations
    }
    
    public function test_filters_patients_by_date_correctly()
    {
        // Test date filtering logic
    }
    
    public function test_calculates_staff_performance_metrics()
    {
        // Test staff workload calculations
    }
}
```

**VitalsNormalRanges Tests:**
```php
class VitalsNormalRangesTest extends TestCase
{
    public function test_identifies_normal_blood_pressure()
    {
        $this->assertTrue(VitalsNormalRanges::isNormalSystolic(110));
        $this->assertFalse(VitalsNormalRanges::isNormalSystolic(150));
    }
    
    public function test_calculates_bmi_correctly()
    {
        $bmi = VitalsNormalRanges::calculateBMI(70, 175); // kg, cm
        $this->assertEquals(22.86, $bmi, '', 0.01);
    }
}
```

#### 2. Feature Tests (Laravel HTTP Tests)

**Target:** API endpoints with database interactions

**Staff Management Tests:**
```php
class HodStaffManagementTest extends TestCase
{
    public function test_hod_can_list_department_staff()
    {
        $hod = User::factory()->hod()->create();
        $staff = User::factory()->count(3)->create([
            'department_id' => $hod->department_id,
        ]);
        
        $response = $this->actingAs($hod, 'web')
            ->getJson('/api/v1/my-department/staff');
        
        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }
    
    public function test_hod_can_create_staff_in_own_department()
    {
        $hod = User::factory()->hod()->create();
        
        $response = $this->actingAs($hod, 'web')
            ->postJson('/api/v1/my-department/staff', [
                'name' => 'New Doctor',
                'email' => 'doctor@example.com',
                'phone' => '1234567890',
                'password' => 'SecurePass123!',
                'role' => 'doctor',
            ]);
        
        $response->assertCreated()
            ->assertJsonPath('data.department_id', $hod->department_id);
        
        $this->assertDatabaseHas('users', [
            'email' => 'doctor@example.com',
            'department_id' => $hod->department_id,
        ]);
    }
    
    public function test_hod_cannot_create_staff_with_duplicate_email()
    {
        $hod = User::factory()->hod()->create();
        User::factory()->create(['email' => 'existing@example.com']);
        
        $response = $this->actingAs($hod, 'web')
            ->postJson('/api/v1/my-department/staff', [
                'email' => 'existing@example.com',
                // ... other fields
            ]);
        
        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }
    
    public function test_hod_cannot_edit_staff_in_other_department()
    {
        $hod = User::factory()->hod()->create();
        $otherStaff = User::factory()->create(); // Different department
        
        $response = $this->actingAs($hod, 'web')
            ->putJson("/api/v1/my-department/staff/{$otherStaff->id}", [
                'name' => 'Updated Name',
            ]);
        
        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }
    
    public function test_non_hod_cannot_access_staff_management()
    {
        $regularStaff = User::factory()->create(['is_hod' => false]);
        
        $response = $this->actingAs($regularStaff, 'web')
            ->getJson('/api/v1/my-department/staff');
        
        $response->assertForbidden();
    }
}
```

**Dashboard Statistics Tests:**
```php
class DepartmentDashboardTest extends TestCase
{
    public function test_returns_correct_queue_statistics()
    {
        $hod = User::factory()->hod()->create();
        PatientRecord::factory()->count(5)->create([
            'current_department_id' => $hod->department_id,
        ]);
        
        $response = $this->actingAs($hod, 'web')
            ->getJson('/department/api/dashboard/statistics');
        
        $response->assertOk()
            ->assertJsonPath('data.total_in_queue', 5);
    }
    
    public function test_returns_patient_flow_data_for_last_7_days()
    {
        // Test patient flow chart data
    }
    
    public function test_caches_statistics_for_5_minutes()
    {
        // Test caching behavior
    }
}
```

**Vitals Recording Tests:**
```php
class VitalsRecordingTest extends TestCase
{
    public function test_hod_can_record_vitals_for_patient_in_department()
    {
        $hod = User::factory()->hod()->create();
        $patientRecord = PatientRecord::factory()->create([
            'current_department_id' => $hod->department_id,
        ]);
        
        $response = $this->actingAs($hod, 'web')
            ->postJson("/department/api/patient-records/{$patientRecord->id}/vitals", [
                'blood_pressure' => '120/80',
                'temperature' => 36.5,
                'pulse_rate' => 72,
                'respiratory_rate' => 16,
                'oxygen_saturation' => 98,
                'weight' => 70,
                'height' => 175,
            ]);
        
        $response->assertCreated();
        
        $this->assertDatabaseHas('patient_vitals', [
            'patient_record_id' => $patientRecord->id,
            'recorded_by' => $hod->id,
            'temperature' => 36.5,
        ]);
        
        // Assert BMI was calculated
        $vitals = PatientVitals::latest()->first();
        $this->assertEqualsWithDelta(22.86, $vitals->bmi, 0.01);
    }
    
    public function test_validates_vitals_numeric_ranges()
    {
        $hod = User::factory()->hod()->create();
        $patientRecord = PatientRecord::factory()->create([
            'current_department_id' => $hod->department_id,
        ]);
        
        $response = $this->actingAs($hod, 'web')
            ->postJson("/department/api/patient-records/{$patientRecord->id}/vitals", [
                'temperature' => 50, // Invalid
            ]);
        
        $response->assertStatus(422);
    }
    
    public function test_creates_audit_log_for_vitals_recording()
    {
        // Test audit logging
    }
}
```

**Report Generation Tests:**
```php
class ReportGenerationTest extends TestCase
{
    public function test_generates_daily_summary_report()
    {
        $hod = User::factory()->hod()->create();
        
        $response = $this->actingAs($hod, 'web')
            ->getJson('/department/api/reports/daily-summary', [
                'date' => '2024-01-15',
            ]);
        
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'patients',
                    'summary' => ['total', 'average_wait_time', 'average_service_time'],
                ],
            ]);
    }
    
    public function test_validates_date_range_maximum()
    {
        $hod = User::factory()->hod()->create();
        
        $response = $this->actingAs($hod, 'web')
            ->getJson('/department/api/reports/staff-performance', [
                'start_date' => '2023-01-01',
                'end_date' => '2024-12-31', // > 365 days
            ]);
        
        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'DATE_RANGE_EXCEEDED');
    }
}
```

**PDF Export Tests:**
```php
class PdfExportTest extends TestCase
{
    public function test_exports_lab_result_to_pdf()
    {
        $hod = User::factory()->hod()->create();
        $labResult = LabResult::factory()->create([
            'patient_record' => PatientRecord::factory()->create([
                'current_department_id' => $hod->department_id,
            ]),
        ]);
        
        $response = $this->actingAs($hod, 'web')
            ->get("/department/api/lab-results/{$labResult->id}/export-pdf");
        
        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment');
    }
    
    public function test_pdf_contains_hospital_branding()
    {
        // Test PDF content includes hospital name and logo
    }
}
```

#### 3. Authorization Tests

**Target:** Multi-tenant isolation and role-based access control

```php
class AuthorizationTest extends TestCase
{
    public function test_hod_cannot_access_other_tenant_data()
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        
        $hod1 = User::factory()->hod()->create(['tenant_id' => $tenant1->id]);
        $staff2 = User::factory()->create(['tenant_id' => $tenant2->id]);
        
        app()->instance('current_tenant_id', $tenant1->id);
        
        $response = $this->actingAs($hod1, 'web')
            ->getJson("/api/v1/my-department/staff/{$staff2->id}");
        
        $response->assertNotFound(); // Global scope filters it out
    }
    
    public function test_regular_staff_cannot_access_hod_dashboard()
    {
        $staff = User::factory()->create(['is_hod' => false]);
        
        $response = $this->actingAs($staff, 'web')
            ->get('/department/queue');
        
        $response->assertForbidden();
    }
}
```

#### 4. Browser Tests (Laravel Dusk)

**Target:** End-to-end user workflows with Vue components

```php
class HodDashboardBrowserTest extends DuskTestCase
{
    public function test_hod_can_view_dashboard_statistics()
    {
        $hod = User::factory()->hod()->create();
        
        $this->browse(function (Browser $browser) use ($hod) {
            $browser->loginAs($hod, 'web')
                ->visit('/department/queue')
                ->assertSee('Department Dashboard')
                ->assertSee('Patients in Queue')
                ->assertSee('Patients Seen Today');
        });
    }
    
    public function test_hod_can_create_staff_member()
    {
        $hod = User::factory()->hod()->create();
        
        $this->browse(function (Browser $browser) use ($hod) {
            $browser->loginAs($hod, 'web')
                ->visit('/department/staff')
                ->click('@create-staff-button')
                ->waitFor('@staff-form-modal')
                ->type('name', 'New Doctor')
                ->type('email', 'newdoctor@example.com')
                ->type('phone', '1234567890')
                ->type('password', 'SecurePass123!')
                ->select('role', 'doctor')
                ->click('@submit-button')
                ->waitForText('Staff account created successfully')
                ->assertSee('newdoctor@example.com');
        });
    }
    
    public function test_dashboard_updates_in_realtime()
    {
        $hod = User::factory()->hod()->create();
        
        $this->browse(function (Browser $browser) use ($hod) {
            $browser->loginAs($hod, 'web')
                ->visit('/department/queue')
                ->assertSee('Patients in Queue: 0');
            
            // Simulate patient routed to department
            event(new PatientRouted($patientRecord, $hod->department_id));
            
            $browser->waitForText('Patients in Queue: 1');
        });
    }
}
```

#### 5. Component Tests (Vue Test Utils)

**Target:** Vue component logic and rendering

```php
// tests/JavaScript/Components/DepartmentDashboard.spec.js
import { mount } from '@vue/test-utils'
import DepartmentDashboard from '@/Pages/Department/Dashboard.vue'

describe('DepartmentDashboard', () => {
  it('renders statistics correctly', () => {
    const wrapper = mount(DepartmentDashboard, {
      props: {
        statistics: {
          total_in_queue: 5,
          patients_seen_today: 12,
          average_wait_time: 15.5,
          staff_on_duty: 8,
        },
      },
    })
    
    expect(wrapper.text()).toContain('5')
    expect(wrapper.text()).toContain('12')
    expect(wrapper.text()).toContain('15.5')
  })
  
  it('displays patient flow chart', () => {
    const wrapper = mount(DepartmentDashboard, {
      props: {
        patientFlowData: [
          { date: '2024-01-01', entered: 10, completed: 8 },
          { date: '2024-01-02', entered: 12, completed: 11 },
        ],
      },
    })
    
    expect(wrapper.find('.patient-flow-chart').exists()).toBe(true)
  })
})
```

### Test Coverage Goals

- **Unit Tests:** 80%+ coverage for service classes
- **Feature Tests:** 100% coverage for API endpoints
- **Authorization Tests:** 100% coverage for access control rules
- **Browser Tests:** Critical user workflows (staff creation, vitals recording, report generation)
- **Component Tests:** Key Vue components with complex logic

### Test Data Management

**Factories:**
- Use Laravel factories for consistent test data generation
- Create specific factory states for HODs, departments, patients with vitals, etc.

**Database Transactions:**
- Wrap tests in database transactions for isolation
- Use `RefreshDatabase` trait for clean state

**Mocking:**
- Mock external services (Pusher, PDF generation) in unit tests
- Use real implementations in integration tests

### Continuous Integration

**Test Execution:**
- Run unit and feature tests on every commit
- Run browser tests on pull requests
- Generate code coverage reports

**Performance Testing:**
- Monitor query counts for dashboard statistics
- Test report generation with large datasets (1000+ patients)
- Verify cache effectiveness



## Implementation Considerations

### Performance Optimization

#### Database Query Optimization

**Indexes Required:**
```sql
-- Patient records by department and status
CREATE INDEX idx_patient_records_dept_status 
ON patient_records(current_department_id, status);

-- Patient journey events by department and date
CREATE INDEX idx_journey_events_dept_date 
ON patient_journey_events(to_department_id, created_at);

-- Patient vitals by patient record and date
CREATE INDEX idx_vitals_patient_date 
ON patient_vitals(patient_record_id, created_at);

-- Lab results by patient record and date
CREATE INDEX idx_lab_results_patient_date 
ON lab_results(patient_record_id, created_at);

-- Users by department and active status
CREATE INDEX idx_users_dept_active 
ON users(department_id, is_active);
```

**Query Optimization Strategies:**
- Use eager loading for relationships (`with()`) to avoid N+1 queries
- Implement query result caching for dashboard statistics (5-minute TTL)
- Use database views for complex report queries
- Paginate all list endpoints (20 items per page)
- Use `select()` to limit columns retrieved

**Example Optimized Query:**
```php
// Bad: N+1 query problem
$staff = User::where('department_id', $departmentId)->get();
foreach ($staff as $member) {
    echo $member->department->name; // N queries
}

// Good: Eager loading
$staff = User::with('department')
    ->where('department_id', $departmentId)
    ->get();
```

#### Caching Strategy

**Cache Keys:**
```php
"dept_stats:{department_id}:queue"
"dept_stats:{department_id}:patients_today"
"dept_stats:{department_id}:wait_time"
"dept_flow:{department_id}:7days"
"dept_workload:{department_id}:{date}"
```

**Cache Invalidation:**
- Invalidate on patient routed to/from department
- Invalidate on vitals recorded
- Invalidate on staff status change
- Use cache tags for bulk invalidation

**Implementation:**
```php
public function getQueueStatistics(string $departmentId): array
{
    return Cache::remember(
        "dept_stats:{$departmentId}:queue",
        now()->addMinutes(5),
        function () use ($departmentId) {
            return [
                'total_in_queue' => PatientRecord::where('current_department_id', $departmentId)
                    ->where('status', 'in_progress')
                    ->count(),
                // ... other stats
            ];
        }
    );
}
```

#### Frontend Performance

**Code Splitting:**
- Lazy load chart libraries (Chart.js/ApexCharts)
- Lazy load PDF viewer components
- Split routes for dashboard, staff, reports, settings

**Data Loading:**
- Implement infinite scroll for long lists
- Use debouncing for search inputs (300ms)
- Show loading skeletons during data fetch

**Chart Optimization:**
- Limit data points displayed (max 30 days for flow chart)
- Use canvas-based charts for better performance
- Implement chart data caching in component state

### Security Considerations

#### Input Validation

**Server-Side Validation:**
- Validate all inputs using Laravel Form Requests
- Sanitize HTML in text fields to prevent XSS
- Validate file uploads (PDF/Excel exports)
- Enforce maximum string lengths

**Client-Side Validation:**
- Use Vuelidate or VeeValidate for form validation
- Provide immediate feedback on invalid inputs
- Prevent form submission until valid

#### SQL Injection Prevention

- Use Eloquent ORM query builder (parameterized queries)
- Never concatenate user input into raw SQL
- Use `DB::raw()` only with trusted, validated data

#### Cross-Site Scripting (XSS) Prevention

- Escape all user-generated content in Vue templates (automatic with `{{ }}`)
- Use `v-html` only with sanitized content
- Set Content-Security-Policy headers

#### Cross-Site Request Forgery (CSRF) Prevention

- Laravel CSRF protection enabled for all POST/PUT/DELETE requests
- Include CSRF token in Inertia forms
- Verify token on server side

#### Authorization Checks

**Middleware Stack:**
```php
Route::prefix('department')->middleware([
    'tenant.resolve',      // Resolve tenant from subdomain
    'department.auth',     // Verify authenticated and is_hod
    'tenant.scope',        // Apply global tenant scope
])->group(function () {
    // HOD routes
});
```

**Controller-Level Checks:**
```php
public function updateStaff(string $id, Request $request): JsonResponse
{
    $staff = User::findOrFail($id);
    
    // Verify staff belongs to HOD's department
    if ($staff->department_id !== auth()->user()->department_id) {
        abort(403, 'You can only manage staff in your own department');
    }
    
    // ... update logic
}
```

#### Audit Logging

**What to Log:**
- All staff CRUD operations
- Vitals recording
- Report generation and exports
- Department settings changes
- Failed authorization attempts

**Audit Log Format:**
```php
AuditLog::create([
    'tenant_id' => app('current_tenant_id'),
    'actor_id' => auth()->id(),
    'actor_role' => auth()->user()->role,
    'action' => 'staff.created',
    'resource_type' => 'User',
    'resource_id' => $staff->id,
    'description' => "HOD created staff account: {$staff->name} ({$staff->role})",
    'ip_address' => $request->ip(),
    'metadata' => json_encode([
        'department_id' => $staff->department_id,
        'staff_role' => $staff->role,
    ]),
]);
```

### Scalability Considerations

#### Database Scaling

**Read Replicas:**
- Use read replicas for report generation
- Route dashboard statistics queries to replicas
- Keep writes on primary database

**Partitioning:**
- Consider partitioning `patient_journey_events` by date
- Partition `audit_logs` by month for compliance retention

**Connection Pooling:**
- Use PgBouncer for PostgreSQL connection pooling
- Configure appropriate pool size based on load

#### Application Scaling

**Horizontal Scaling:**
- Deploy multiple Laravel application instances behind load balancer
- Use Redis for session storage (shared across instances)
- Use Redis for cache (shared across instances)

**Queue Workers:**
- Offload report generation to background jobs
- Use Laravel queues with Redis driver
- Scale queue workers independently

**WebSocket Scaling:**
- Use Pusher (managed service) for WebSocket connections
- Alternative: Self-hosted Laravel WebSockets with Redis adapter

### Deployment Strategy

#### Environment Configuration

**Required Environment Variables:**
```env
# Database
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=hospital_db
DB_USERNAME=hospital_user
DB_PASSWORD=secure_password

# Cache
CACHE_DRIVER=redis
REDIS_HOST=localhost
REDIS_PORT=6379

# Broadcasting
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=your_cluster

# PDF Generation
DOMPDF_ENABLE_REMOTE=true
DOMPDF_ENABLE_CSS_FLOAT=true
```

#### Migration Strategy

**Database Migrations:**
```bash
# Create new tables
php artisan migrate

# Seed default working hours for existing departments
php artisan db:seed --class=DepartmentWorkingHoursSeeder

# Seed notification preferences for existing HODs
php artisan db:seed --class=UserNotificationPreferencesSeeder
```

**Zero-Downtime Deployment:**
1. Deploy new code to staging environment
2. Run migrations on staging database
3. Test all features on staging
4. Deploy to production with blue-green deployment
5. Run migrations on production database
6. Switch traffic to new version
7. Monitor for errors

#### Rollback Plan

**Database Rollback:**
- Keep migration rollback methods up to date
- Test rollback on staging before production deployment
- Backup database before running migrations

**Application Rollback:**
- Keep previous version deployed and ready
- Switch load balancer back to previous version if issues detected
- Rollback database migrations if necessary

### Monitoring and Observability

#### Application Monitoring

**Metrics to Track:**
- Dashboard page load time
- API endpoint response times
- Database query execution time
- Cache hit/miss ratio
- PDF/Excel generation time
- WebSocket connection count

**Tools:**
- Laravel Telescope for local development
- New Relic or Datadog for production monitoring
- Sentry for error tracking

#### Database Monitoring

**Metrics to Track:**
- Query execution time
- Connection pool usage
- Slow query log
- Database size growth

**Alerts:**
- Alert on slow queries (> 1 second)
- Alert on high connection count
- Alert on database disk usage (> 80%)

#### User Activity Monitoring

**Metrics to Track:**
- Number of HODs logged in
- Staff creation rate
- Report generation frequency
- Vitals recording frequency

**Analytics:**
- Track feature usage with Google Analytics or Mixpanel
- Identify most-used features for optimization priority

### Documentation Requirements

#### API Documentation

- Document all new API endpoints with request/response examples
- Use OpenAPI/Swagger specification
- Include authentication requirements
- Document error codes and messages

#### User Documentation

- Create HOD user guide with screenshots
- Document staff management workflows
- Document report generation procedures
- Create video tutorials for key features

#### Developer Documentation

- Document service class methods with PHPDoc
- Document Vue component props and events
- Document database schema changes
- Document caching strategy and invalidation rules



## Summary

This design document specifies the implementation of comprehensive HOD dashboard features for the hospital management system. The solution extends the existing Laravel/Inertia.js/Vue 3 architecture with:

**Key Features:**
- Staff management (create, edit, deactivate)
- Real-time dashboard with statistics and charts
- Patient vitals recording and history
- Lab results and prescription management
- Comprehensive reporting with PDF/Excel export
- Department settings configuration
- Real-time updates via WebSocket

**Technical Approach:**
- Follows existing architectural patterns (session-based auth, multi-tenancy, RBAC)
- Introduces new service classes for statistics, reporting, and export
- Leverages existing models with minimal schema changes (2 new tables)
- Implements caching for performance (5-minute TTL on statistics)
- Uses Laravel broadcasting for real-time updates
- Maintains strict authorization and audit logging

**Testing Strategy:**
- Unit tests for service logic
- Feature tests for API endpoints
- Authorization tests for access control
- Browser tests for critical workflows
- Component tests for Vue components
- Property-based testing not applicable (web UI, CRUD, reporting)

**Performance Targets:**
- Dashboard page load < 2 seconds
- API response time < 500ms
- Report generation < 5 seconds
- PDF/Excel export < 10 seconds

**Security Measures:**
- Multi-tenant data isolation with global scopes
- Role-based access control (is_hod flag)
- Department-level authorization checks
- Comprehensive audit logging
- Input validation and sanitization
- CSRF and XSS protection

The design maintains backward compatibility with existing features while providing HODs with powerful tools to manage their departments effectively.

