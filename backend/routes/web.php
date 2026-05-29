<?php

use App\Http\Controllers\SuperAdminAuthController;
use App\Http\Controllers\SuperAdminWebController;
use App\Http\Controllers\HospitalAdminAuthController;
use App\Http\Controllers\HospitalAdminWebController;
use App\Http\Controllers\AccountsWebController;
use App\Http\Controllers\DepartmentWebController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
});

// Super Admin login (no auth required)
Route::get('/admin/login', function () {
    return Inertia::render('SuperAdmin/Login');
})->name('admin.login');

Route::post('/admin/login', [SuperAdminAuthController::class, 'login']);
Route::post('/admin/logout', [SuperAdminAuthController::class, 'logout'])->name('admin.logout');

// Super Admin web routes
Route::prefix('admin')->middleware(['super.admin.auth'])->group(function () {
    Route::get('/dashboard', [SuperAdminWebController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/hospitals/create', [SuperAdminWebController::class, 'createHospitalForm'])->name('admin.hospitals.create');
    Route::get('/hospitals/{id}', [SuperAdminWebController::class, 'hospitalDetail'])->name('admin.hospital-detail');
    Route::get('/audit-logs', [SuperAdminWebController::class, 'auditLogs'])->name('admin.audit-logs');
    Route::get('/settings', [SuperAdminWebController::class, 'settings'])->name('admin.settings');
    Route::get('/system-health', [SuperAdminWebController::class, 'systemHealth'])->name('admin.system-health');
    Route::get('/plans', [SuperAdminWebController::class, 'plans'])->name('admin.plans');
    Route::get('/wallets', [SuperAdminWebController::class, 'wallets'])->name('admin.wallets');
});

// Super Admin AJAX/API routes — session-based auth (web middleware handles session)
Route::prefix('admin/api')->middleware(['super.admin.auth'])->group(function () {
    Route::get('/hospitals', [\App\Http\Controllers\Api\V1\SuperAdminController::class, 'listHospitals']);
    Route::post('/hospitals', [\App\Http\Controllers\Api\V1\SuperAdminController::class, 'createHospital']);
    Route::get('/hospitals/{id}', [\App\Http\Controllers\Api\V1\SuperAdminController::class, 'getHospital']);
    Route::put('/hospitals/{id}', [\App\Http\Controllers\Api\V1\SuperAdminController::class, 'updateHospital']);
    Route::patch('/hospitals/{id}/suspend', [\App\Http\Controllers\Api\V1\SuperAdminController::class, 'suspendHospital']);
    Route::patch('/hospitals/{id}/activate', [\App\Http\Controllers\Api\V1\SuperAdminController::class, 'activateHospital']);
    Route::get('/audit-logs', [\App\Http\Controllers\Api\V1\SuperAdminController::class, 'getAuditLogs']);
    Route::get('/audit-logs/export', [\App\Http\Controllers\Api\V1\SuperAdminController::class, 'exportAuditLogs']);
    Route::get('/settings', [\App\Http\Controllers\Api\V1\SuperAdminController::class, 'getSettings']);
    Route::put('/settings', [\App\Http\Controllers\Api\V1\SuperAdminController::class, 'updateSettings']);
    Route::get('/feature-flags', [\App\Http\Controllers\Api\V1\SuperAdminController::class, 'getFeatureFlags']);
    Route::put('/feature-flags/{key}', [\App\Http\Controllers\Api\V1\SuperAdminController::class, 'updateFeatureFlag']);
    Route::get('/system-health', [\App\Http\Controllers\Api\V1\SuperAdminController::class, 'systemHealth']);
    Route::get('/plans', [\App\Http\Controllers\Api\V1\SuperAdminController::class, 'listPlans']);
    Route::post('/plans', [\App\Http\Controllers\Api\V1\SuperAdminController::class, 'createPlan']);
    Route::put('/plans/{id}', [\App\Http\Controllers\Api\V1\SuperAdminController::class, 'updatePlan']);
    Route::delete('/plans/{id}', [\App\Http\Controllers\Api\V1\SuperAdminController::class, 'deletePlan']);
    Route::post('/broadcast', [\App\Http\Controllers\Api\V1\SuperAdminController::class, 'broadcast']);

    // Wallet management
    Route::get('/wallets', [\App\Http\Controllers\Api\V1\WalletController::class, 'listWallets']);
    Route::get('/wallets/{tenantId}', [\App\Http\Controllers\Api\V1\WalletController::class, 'getWallet']);
    Route::post('/wallets/{tenantId}/topup', [\App\Http\Controllers\Api\V1\WalletController::class, 'topUp']);
    Route::post('/wallets/{tenantId}/adjust', [\App\Http\Controllers\Api\V1\WalletController::class, 'adjust']);
    Route::put('/wallets/{tenantId}/settings', [\App\Http\Controllers\Api\V1\WalletController::class, 'updateSettings']);
    Route::get('/wallets/{tenantId}/transactions', [\App\Http\Controllers\Api\V1\WalletController::class, 'getTransactions']);

    // Deduction config
    Route::get('/wallet-configs', [\App\Http\Controllers\Api\V1\WalletController::class, 'listConfigs']);
    Route::post('/wallet-configs', [\App\Http\Controllers\Api\V1\WalletController::class, 'saveConfig']);
    Route::delete('/wallet-configs/{id}', [\App\Http\Controllers\Api\V1\WalletController::class, 'deleteConfig']);
});

// Hospital Admin login (no auth required, NO tenant resolution for GET)
Route::get('/hospital-admin/login', function () {
    return Inertia::render('HospitalAdmin/Login');
})->name('hospital-admin.login');

// Hospital Admin login POST (tenant resolution required)
Route::middleware(['tenant.resolve'])->group(function () {
    Route::post('/hospital-admin/login', [HospitalAdminAuthController::class, 'login']);
});

Route::post('/hospital-admin/logout', [HospitalAdminAuthController::class, 'logout'])->name('hospital-admin.logout');

// Hospital Admin web routes (tenant resolution + auth required)
Route::prefix('hospital-admin')->middleware(['tenant.resolve', 'hospital.admin.auth'])->group(function () {
    Route::get('/dashboard', [HospitalAdminWebController::class, 'dashboard'])->name('hospital-admin.dashboard');
    Route::get('/departments', [HospitalAdminWebController::class, 'departments'])->name('hospital-admin.departments');
    Route::get('/staff', [HospitalAdminWebController::class, 'staff'])->name('hospital-admin.staff');
    Route::get('/workflow-templates', [HospitalAdminWebController::class, 'workflowTemplates'])->name('hospital-admin.workflow-templates');
    Route::get('/reports/department-activity', [HospitalAdminWebController::class, 'departmentReports'])->name('hospital-admin.reports.department');
    Route::get('/reports/financial', [HospitalAdminWebController::class, 'financialReports'])->name('hospital-admin.reports.financial');
    Route::get('/audit-logs', [HospitalAdminWebController::class, 'auditLogs'])->name('hospital-admin.audit-logs');
    Route::get('/wallet', [HospitalAdminWebController::class, 'wallet'])->name('hospital-admin.wallet');
    Route::get('/ward', [HospitalAdminWebController::class, 'ward'])->name('hospital-admin.ward');
});

// Accounts Department web routes (tenant resolution + auth required)
Route::prefix('accounts')->middleware(['tenant.resolve', 'hospital.admin.auth'])->group(function () {
    Route::get('/queue', [AccountsWebController::class, 'queue'])->name('accounts.queue');
    Route::get('/invoices', [AccountsWebController::class, 'invoices'])->name('accounts.invoices');
    Route::get('/patient-records/{id}/create-invoice', [AccountsWebController::class, 'createInvoice'])->name('accounts.create-invoice');
    Route::get('/invoices/{id}', [AccountsWebController::class, 'invoiceDetails'])->name('accounts.invoice-details');
});

// Department web routes (HOD only — regular staff use mobile app)

// HOD login (no auth required)
Route::get('/department/login', function () {
    return Inertia::render('Department/Login');
})->name('department.login');

Route::middleware(['tenant.resolve'])->group(function () {
    Route::post('/department/login', [\App\Http\Controllers\DepartmentAuthController::class, 'login']);
});

Route::post('/department/logout', [\App\Http\Controllers\DepartmentAuthController::class, 'logout'])->name('department.logout');

// HOD dashboard (is_hod = true required)
Route::prefix('department')->middleware(['tenant.resolve', 'department.auth'])->group(function () {
    Route::get('/dashboard', [DepartmentWebController::class, 'dashboard'])->name('department.dashboard');
    Route::get('/queue', [DepartmentWebController::class, 'queue'])->name('department.queue');
    Route::get('/patient-records/{id}', [DepartmentWebController::class, 'patientDetail'])->name('department.patient-detail');
    Route::get('/vitals/{id?}', [DepartmentWebController::class, 'vitalsRecording'])->name('department.vitals');
    Route::get('/staff', [DepartmentWebController::class, 'staffManagement'])->name('department.staff');
    Route::get('/lab-results', [DepartmentWebController::class, 'labResults'])->name('department.lab-results');
    Route::get('/prescriptions', [DepartmentWebController::class, 'prescriptions'])->name('department.prescriptions');
    Route::get('/reports', [DepartmentWebController::class, 'reports'])->name('department.reports');
    Route::get('/settings', [DepartmentWebController::class, 'settings'])->name('department.settings');
});

// Department API routes (HOD only - session-based auth)
Route::prefix('department/api')->middleware(['tenant.resolve', 'department.auth', 'tenant.scope'])->group(function () {
    // Staff management (HOD only)
    Route::get('/staff', [\App\Http\Controllers\Api\V1\HodController::class, 'listDepartmentStaff']);
    Route::post('/staff', [\App\Http\Controllers\Api\V1\HodController::class, 'createDepartmentStaff']);
    Route::put('/staff/{id}', [\App\Http\Controllers\Api\V1\HodController::class, 'updateDepartmentStaff']);
    Route::patch('/staff/{id}/deactivate', [\App\Http\Controllers\Api\V1\HodController::class, 'deactivateDepartmentStaff']);
    // Dashboard statistics
    Route::get('/dashboard/statistics', [\App\Http\Controllers\Api\V1\DepartmentApiController::class, 'getDashboardStatistics']);
    Route::get('/dashboard/patient-flow', [\App\Http\Controllers\Api\V1\DepartmentApiController::class, 'getPatientFlowChart']);
    Route::get('/dashboard/staff-workload', [\App\Http\Controllers\Api\V1\DepartmentApiController::class, 'getStaffWorkloadChart']);
    Route::get('/dashboard/recent-activity', [\App\Http\Controllers\Api\V1\DepartmentApiController::class, 'getRecentActivity']);

    // Vitals management
    Route::post('/patient-records/{id}/vitals', [\App\Http\Controllers\Api\V1\DepartmentApiController::class, 'recordVitals']);
    Route::get('/patient-records/{id}/vitals', [\App\Http\Controllers\Api\V1\DepartmentApiController::class, 'getVitalsHistory']);

    // Lab results
    Route::get('/lab-results', [\App\Http\Controllers\Api\V1\DepartmentApiController::class, 'getLabResults']);
    Route::get('/patient-records/{id}/investigation-notes', [\App\Http\Controllers\Api\V1\DepartmentApiController::class, 'getInvestigationNotes']);
    Route::get('/lab-results/{id}/export-pdf', [\App\Http\Controllers\Api\V1\DepartmentApiController::class, 'exportLabResultPdf']);

    // Prescriptions
    Route::get('/prescriptions', [\App\Http\Controllers\Api\V1\DepartmentApiController::class, 'getPrescriptions']);
    Route::get('/patient-records/{id}/prescriptions', [\App\Http\Controllers\Api\V1\DepartmentApiController::class, 'getPrescriptionHistory']);
    Route::get('/prescriptions/{eventId}/export-pdf', [\App\Http\Controllers\Api\V1\DepartmentApiController::class, 'exportPrescriptionPdf']);

    // Reports
    Route::get('/reports/daily-summary', [\App\Http\Controllers\Api\V1\DepartmentApiController::class, 'getDailySummaryReport']);
    Route::get('/reports/staff-performance', [\App\Http\Controllers\Api\V1\DepartmentApiController::class, 'getStaffPerformanceReport']);
    Route::get('/reports/efficiency-metrics', [\App\Http\Controllers\Api\V1\DepartmentApiController::class, 'getDepartmentEfficiencyReport']);
    Route::post('/reports/export-pdf', [\App\Http\Controllers\Api\V1\DepartmentApiController::class, 'exportReportPdf']);
    Route::post('/reports/export-excel', [\App\Http\Controllers\Api\V1\DepartmentApiController::class, 'exportReportExcel']);

    // Settings
    Route::put('/settings/info', [\App\Http\Controllers\Api\V1\DepartmentApiController::class, 'updateDepartmentInfo']);
    Route::put('/settings/working-hours', [\App\Http\Controllers\Api\V1\DepartmentApiController::class, 'updateWorkingHours']);
    Route::put('/settings/notifications', [\App\Http\Controllers\Api\V1\DepartmentApiController::class, 'updateNotificationPreferences']);
});
// These mirror the API routes but use web session authentication
Route::prefix('hospital-admin/api')->middleware(['tenant.resolve', 'hospital.admin.auth'])->group(function () {
    // Departments
    Route::get('/departments', [\App\Http\Controllers\Api\V1\HospitalAdminController::class, 'listDepartments']);
    Route::post('/departments', [\App\Http\Controllers\Api\V1\HospitalAdminController::class, 'createDepartment']);
    Route::put('/departments/{id}', [\App\Http\Controllers\Api\V1\HospitalAdminController::class, 'updateDepartment']);
    Route::delete('/departments/{id}', [\App\Http\Controllers\Api\V1\HospitalAdminController::class, 'deleteDepartment']);

    // Staff
    Route::get('/staff', [\App\Http\Controllers\Api\V1\HospitalAdminController::class, 'listStaff']);
    Route::post('/staff', [\App\Http\Controllers\Api\V1\HospitalAdminController::class, 'createStaff']);
    Route::put('/staff/{id}', [\App\Http\Controllers\Api\V1\HospitalAdminController::class, 'updateStaff']);
    Route::patch('/staff/{id}/deactivate', [\App\Http\Controllers\Api\V1\HospitalAdminController::class, 'deactivateStaff']);

    // Workflow Templates
    Route::get('/workflows/templates', [\App\Http\Controllers\Api\V1\HospitalAdminController::class, 'listWorkflowTemplates']);
    Route::post('/workflows/templates', [\App\Http\Controllers\Api\V1\HospitalAdminController::class, 'createWorkflowTemplate']);
    Route::put('/workflows/templates/{id}', [\App\Http\Controllers\Api\V1\HospitalAdminController::class, 'updateWorkflowTemplate']);

    // Reports
    Route::get('/reports/department-activity', [\App\Http\Controllers\Api\V1\HospitalAdminController::class, 'getDepartmentActivityReport']);
    Route::get('/reports/financial', [\App\Http\Controllers\Api\V1\HospitalAdminController::class, 'getFinancialReport']);

    // Audit Logs
    Route::get('/audit-logs', [\App\Http\Controllers\Api\V1\HospitalAdminController::class, 'getAuditLogs']);
    Route::get('/audit-logs/export', [\App\Http\Controllers\Api\V1\HospitalAdminController::class, 'exportAuditLogs']);

    // Wallet (Hospital Admin view + Paystack top-up)
    Route::get('/wallet', [\App\Http\Controllers\Api\V1\WalletController::class, 'myWallet']);
    Route::post('/wallet/paystack/initiate', [\App\Http\Controllers\Api\V1\WalletController::class, 'initiatePaystack']);
    Route::post('/wallet/paystack/verify', [\App\Http\Controllers\Api\V1\WalletController::class, 'verifyPaystack']);

    // Ward — discharge patient from web dashboard
    Route::post('/ward/discharge/{id}', [\App\Http\Controllers\Api\V1\WardController::class, 'dischargePatient']);
    Route::get('/ward/admitted', [\App\Http\Controllers\Api\V1\WardController::class, 'admittedPatients']);

    // Visit Types — for staff form specialties picker
    Route::get('/visit-types', [\App\Http\Controllers\Api\V1\VisitTypeController::class, 'index']);
    Route::post('/visit-types', [\App\Http\Controllers\Api\V1\VisitTypeController::class, 'store']);
    Route::put('/visit-types/{id}', [\App\Http\Controllers\Api\V1\VisitTypeController::class, 'update']);
    Route::delete('/visit-types/{id}', [\App\Http\Controllers\Api\V1\VisitTypeController::class, 'destroy']);
});

