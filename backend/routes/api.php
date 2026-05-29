<?php

use App\Http\Controllers\Api\V1\AccountsController;
use App\Http\Controllers\Api\V1\AppVersionController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\ClinicalController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\DoctorController;
use App\Http\Controllers\Api\V1\HodController;
use App\Http\Controllers\Api\V1\HospitalAdminController;
use App\Http\Controllers\Api\V1\PatientController;
use App\Http\Controllers\Api\V1\SuperAdminController;
use App\Http\Controllers\Api\V1\VisitTypeController;
use App\Http\Controllers\Api\V1\WardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Super Admin routes (no tenant resolution, central DB only)
    Route::prefix('admin')->middleware(['super.admin.auth'])->group(function () {
        Route::get('/hospitals', [SuperAdminController::class, 'listHospitals']);
        Route::post('/hospitals', [SuperAdminController::class, 'createHospital']);
        Route::get('/hospitals/{id}', [SuperAdminController::class, 'getHospital']);
        Route::put('/hospitals/{id}', [SuperAdminController::class, 'updateHospital']);
        Route::patch('/hospitals/{id}/suspend', [SuperAdminController::class, 'suspendHospital']);
        Route::patch('/hospitals/{id}/activate', [SuperAdminController::class, 'activateHospital']);
        Route::get('/audit-logs', [SuperAdminController::class, 'getAuditLogs']);
        Route::get('/audit-logs/export', [SuperAdminController::class, 'exportAuditLogs']);
        Route::get('/settings', [SuperAdminController::class, 'getSettings']);
        Route::put('/settings', [SuperAdminController::class, 'updateSettings']);
        Route::get('/feature-flags', [SuperAdminController::class, 'getFeatureFlags']);
        Route::put('/feature-flags/{key}', [SuperAdminController::class, 'updateFeatureFlag']);
        Route::get('/system-health', [SuperAdminController::class, 'systemHealth']);
        Route::get('/plans', [SuperAdminController::class, 'listPlans']);
        Route::post('/plans', [SuperAdminController::class, 'createPlan']);
        Route::put('/plans/{id}', [SuperAdminController::class, 'updatePlan']);
        Route::delete('/plans/{id}', [SuperAdminController::class, 'deletePlan']);
        Route::post('/broadcast', [SuperAdminController::class, 'broadcast']);
    });

    // Authentication routes (tenant resolution only, no JWT auth required)
    Route::middleware(['tenant.resolve'])->prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/password/reset', [AuthController::class, 'passwordReset']);
    });

    // Mobile app version check (no auth required, tenant resolution only)
    Route::middleware(['tenant.resolve'])->prefix('app')->group(function () {
        Route::get('/version', [AppVersionController::class, 'version']);
    });

    // Protected routes - Full middleware stack applied in order:
    // 1. MaintenanceModeMiddleware - check maintenance mode
    // 2. TenantResolutionMiddleware - resolve tenant
    // 3. JWTAuthMiddleware - validate JWT
    // 4. RBACMiddleware - check permissions
    // 5. TenantScopeMiddleware - apply tenant scope
    Route::middleware(['maintenance.mode', 'tenant.resolve', 'jwt.auth', 'tenant.verify', 'rbac', 'tenant.scope'])->group(function () {
        Route::get('/user', function (Request $request) {
            return response()->json([
                'success' => true,
                'data' => $request->user(),
            ]);
        });

        // Hospital Admin routes
        Route::prefix('departments')->group(function () {
            Route::get('/', [HospitalAdminController::class, 'listDepartments'])
                ->middleware('rbac:departments.manage');
            Route::post('/', [HospitalAdminController::class, 'createDepartment'])
                ->middleware('rbac:departments.manage');
            Route::put('/{id}', [HospitalAdminController::class, 'updateDepartment'])
                ->middleware('rbac:departments.manage');
            Route::delete('/{id}', [HospitalAdminController::class, 'deleteDepartment'])
                ->middleware('rbac:departments.manage');
        });

        Route::prefix('staff')->group(function () {
            Route::get('/', [HospitalAdminController::class, 'listStaff'])
                ->middleware('rbac:staff.manage');
            Route::post('/', [HospitalAdminController::class, 'createStaff'])
                ->middleware('rbac:staff.manage');
            Route::put('/{id}', [HospitalAdminController::class, 'updateStaff'])
                ->middleware('rbac:staff.manage');
            Route::patch('/{id}/deactivate', [HospitalAdminController::class, 'deactivateStaff'])
                ->middleware('rbac:staff.manage');
        });

        Route::prefix('workflows/templates')->group(function () {
            Route::get('/', [HospitalAdminController::class, 'listWorkflowTemplates'])
                ->middleware('rbac:workflows.manage');
            Route::post('/', [HospitalAdminController::class, 'createWorkflowTemplate'])
                ->middleware('rbac:workflows.manage');
            Route::put('/{id}', [HospitalAdminController::class, 'updateWorkflowTemplate'])
                ->middleware('rbac:workflows.manage');
        });

        Route::prefix('reports')->group(function () {
            Route::get('/department-activity', [HospitalAdminController::class, 'getDepartmentActivityReport'])
                ->middleware('rbac:reports.view');
            Route::get('/financial', [HospitalAdminController::class, 'getFinancialReport'])
                ->middleware('rbac:reports.view');
        });

        Route::prefix('audit-logs')->group(function () {
            Route::get('/', [HospitalAdminController::class, 'getAuditLogs'])
                ->middleware('rbac:audit_logs.view');
            Route::get('/export', [HospitalAdminController::class, 'exportAuditLogs'])
                ->middleware('rbac:audit_logs.view');
        });

        // Department queue endpoint
        Route::get('/department/queue', [DepartmentController::class, 'getQueue'])
            ->middleware('rbac:department.queue.view');

        // Public department lookup by type — any authenticated staff can use this
        // (needed for doctors to find pharmacy/lab/ward departments to route patients)
        Route::get('/departments/by-type/{type}', [DepartmentController::class, 'getByType']);

        // Visit Types — read for all staff, CRUD for hospital admin
        Route::prefix('visit-types')->group(function () {
            Route::get('/',       [VisitTypeController::class, 'index']); // any authenticated staff
            Route::post('/',      [VisitTypeController::class, 'store'])
                ->middleware('rbac:visit_types.manage');
            Route::put('/{id}',   [VisitTypeController::class, 'update'])
                ->middleware('rbac:visit_types.manage');
            Route::delete('/{id}', [VisitTypeController::class, 'destroy'])
                ->middleware('rbac:visit_types.manage');
        });

        // Doctor listing by visit type — any authenticated staff
        Route::get('/doctors/by-visit-type/{visit_type}', [DoctorController::class, 'byVisitType']);

        // Department workflow endpoints (patient-record actions)
        Route::prefix('patient-records')->group(function () {
            Route::post('/{id}/notes', [DepartmentController::class, 'addNote'])
                ->middleware('rbac:patient_records.manage');
            Route::post('/{id}/complete', [DepartmentController::class, 'complete'])
                ->middleware('rbac:patient_records.manage');
            Route::post('/{id}/route', [DepartmentController::class, 'route'])
                ->middleware('rbac:patient_records.manage');
            Route::post('/{id}/admit', [BillingController::class, 'admit'])
                ->middleware('rbac:patient_records.manage');
            Route::post('/{id}/pharmacy-billing', [BillingController::class, 'pharmacyBilling'])
                ->middleware('rbac:billing.pharmacy');
            Route::post('/{id}/lab-billing', [BillingController::class, 'labBilling'])
                ->middleware('rbac:billing.lab');
            Route::post('/{id}/confirm-dispense', [BillingController::class, 'confirmDispense'])
                ->middleware('rbac:patient_records.manage');
            Route::post('/{id}/vitals', [DepartmentController::class, 'recordVitals'])
                ->middleware('rbac:patient_records.manage');
            Route::get('/{id}/vitals', [DepartmentController::class, 'getVitals'])
                ->middleware('rbac:patient_records.manage');
            Route::get('/{id}/journey', [DepartmentController::class, 'getJourneyHistory'])
                ->middleware('rbac:patient_records.manage');
            Route::get('/{id}/prescription', [DepartmentController::class, 'getPrescription'])
                ->middleware('rbac:patient_records.manage');
            Route::get('/{id}/investigation', [DepartmentController::class, 'getInvestigation'])
                ->middleware('rbac:patient_records.manage');
            Route::post('/{id}/invoices', [AccountsController::class, 'createInvoice'])
                ->middleware('rbac:invoices.create');
            Route::get('/{id}/invoices', [AccountsController::class, 'listInvoices'])
                ->middleware('rbac:patient_records.manage');
            // Pharmacy — Dispensing endpoint (Task 12.2)
            Route::post('/{id}/dispensing', [ClinicalController::class, 'recordDispensing'])
                ->middleware('rbac:patient_records.manage');
            // Laboratory — Lab results endpoint (Task 12.3)
            Route::post('/{id}/lab-results', [ClinicalController::class, 'recordLabResults'])
                ->middleware('rbac:patient_records.manage');
            Route::get('/{id}/lab-results', [ClinicalController::class, 'getLabResults'])
                ->middleware('rbac:patient_records.manage');
        });
        // Patient Management endpoints
        Route::prefix('patients')->group(function () {
            Route::get('/search', [PatientController::class, 'search']);
            Route::get('/registered-today', [PatientController::class, 'registeredToday']);
            Route::post('/', [PatientController::class, 'register'])
                ->middleware('rbac:patients.create');
            Route::get('/{id}', [PatientController::class, 'show'])
                ->middleware('rbac:patients.view');
            Route::get('/{id}/journey', [PatientController::class, 'journey'])
                ->middleware('rbac:patients.view');
            Route::post('/{id}/documents', [PatientController::class, 'attachDocument'])
                ->middleware('rbac:documents.upload');
            Route::get('/{id}/documents/{docId}/url', [PatientController::class, 'getDocumentUrl'])
                ->middleware('rbac:documents.view');
            Route::get('/{id}/photo/url', [PatientController::class, 'getPhotoUrl'])
                ->middleware('rbac:documents.view');
            Route::get('/{id}/photo', [PatientController::class, 'getPhoto'])
                ->middleware('rbac:documents.view');
            Route::post('/{id}/anonymise', [PatientController::class, 'anonymise'])
                ->middleware('rbac:patients.anonymise');
            Route::post('/{id}/new-visit', [PatientController::class, 'newVisit'])
                ->middleware('rbac:patients.new_visit');
        });

        // Upload pre-signed URL endpoint
        Route::get('/upload/presigned-url', [PatientController::class, 'getPresignedUploadUrl']);

        // Doctor — Appointment endpoints (Task 12.1)
        Route::prefix('appointments')->group(function () {
            Route::get('/today', [ClinicalController::class, 'todaysAppointments'])
                ->middleware('rbac:appointments.manage');
            Route::get('/all', [ClinicalController::class, 'allAppointments'])
                ->middleware('rbac:appointments.manage');
            Route::post('/', [ClinicalController::class, 'bookAppointment'])
                ->middleware('rbac:appointments.manage');
            Route::get('/', [ClinicalController::class, 'listAppointments'])
                ->middleware('rbac:appointments.manage');
            Route::patch('/{id}', [ClinicalController::class, 'updateAppointment'])
                ->middleware('rbac:appointments.manage');
        });

        // Accounts Department endpoints
        Route::prefix('accounts')->group(function () {
            Route::get('/queue', [AccountsController::class, 'getQueue'])
                ->middleware('rbac:accounts.queue.view');
        });

        // Ward endpoints
        Route::prefix('ward')->group(function () {
            Route::get('/admitted', [WardController::class, 'admittedPatients'])
                ->middleware('rbac:ward.manage');
            Route::post('/discharge/{id}', [WardController::class, 'dischargePatient'])
                ->middleware('rbac:ward.manage');
        });

        // FCM device token registration
        Route::post('/device-tokens', [DeviceTokenController::class, 'store']);

        Route::prefix('invoices')->group(function () {
            Route::post('/{id}/payments', [AccountsController::class, 'recordPayment'])
                ->middleware('rbac:invoices.settle');
            Route::post('/{id}/settle', [AccountsController::class, 'settleInvoice'])
                ->middleware('rbac:invoices.settle');
            Route::get('/{id}/receipt', [AccountsController::class, 'generateReceipt'])
                ->middleware('rbac:invoices.settle');
        });

        // HOD (Head of Department) staff management
        Route::prefix('my-department')->group(function () {
            Route::get('/staff', [HodController::class, 'listDepartmentStaff'])
                ->middleware('rbac:my_department.manage');
            Route::post('/staff', [HodController::class, 'createDepartmentStaff'])
                ->middleware('rbac:my_department.manage');
            Route::put('/staff/{id}', [HodController::class, 'updateDepartmentStaff'])
                ->middleware('rbac:my_department.manage');
            Route::patch('/staff/{id}/deactivate', [HodController::class, 'deactivateDepartmentStaff'])
                ->middleware('rbac:my_department.manage');
        });
    });
});
