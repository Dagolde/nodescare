# Implementation Plan: Department HOD Dashboard Features

## Overview

This implementation plan breaks down the Department HOD Dashboard Features into discrete coding tasks. The feature adds comprehensive management and reporting capabilities to the HOD web dashboard, including staff management, real-time statistics, vitals recording, lab results management, prescription management, reporting, and department settings.

The implementation follows the existing Laravel/Inertia.js/Vue 3 architecture with session-based authentication and multi-tenant data isolation. All tasks build incrementally, with checkpoints to ensure functionality is validated before proceeding.

## Tasks

- [x] 1. Set up database schema and models
  - [x] 1.1 Create DepartmentWorkingHours migration and model
    - Create migration for `department_working_hours` table with fields: id, tenant_id, department_id, day_of_week, is_open, opening_time, closing_time, timestamps
    - Add foreign keys and indexes as specified in design
    - Create `DepartmentWorkingHours` Eloquent model with tenant scope
    - Define relationship: `belongsTo(Department)`
    - _Requirements: 23_
  
  - [x] 1.2 Create UserNotificationPreferences migration and model
    - Create migration for `user_notification_preferences` table with fields: id, tenant_id, user_id, patient_arrivals_enabled, staff_actions_enabled, system_alerts_enabled, email_enabled, sms_enabled, in_app_enabled, timestamps
    - Add foreign keys and unique constraint on user_id
    - Create `UserNotificationPreferences` Eloquent model with tenant scope
    - Define relationship: `belongsTo(User)`
    - _Requirements: 24_
  
  - [x] 1.3 Add database indexes for performance optimization
    - Create index on `patient_records(current_department_id, status)`
    - Create index on `patient_journey_events(to_department_id, created_at)`
    - Create index on `patient_vitals(patient_record_id, created_at)`
    - Create index on `lab_results(patient_record_id, created_at)`
    - Create index on `users(department_id, is_active)`
    - _Requirements: 29_
  
  - [x] 1.4 Update existing models with new relationships
    - Add `hasMany(DepartmentWorkingHours)` to Department model
    - Add `hasOne(UserNotificationPreferences)` to User model
    - Verify existing relationships are intact
    - _Requirements: 23, 24_

- [x] 2. Implement backend services for statistics and reporting
  - [x] 2.1 Create DepartmentStatisticsService
    - Create `app/Services/DepartmentStatisticsService.php`
    - Implement `getQueueStatistics()` method to calculate total_in_queue, patients_seen_today, average_wait_time, staff_on_duty
    - Implement `getPatientFlowData()` method to return daily patient counts for last N days
    - Implement `getStaffWorkloadData()` method to return patient counts per staff member
    - Implement `getRecentActivityFeed()` method to return recent PatientJourneyEvents
    - Implement `calculateAverageWaitTime()` helper method
    - Add caching with 5-minute TTL using cache keys like `dept_stats:{department_id}:{metric}`
    - _Requirements: 5, 6, 7, 8, 29_
  
  - [x] 2.2 Create ReportGenerationService
    - Create `app/Services/ReportGenerationService.php`
    - Implement `generateDailySummary()` method to return patient list and summary metrics
    - Implement `generateStaffPerformance()` method to return staff performance metrics
    - Implement `generateEfficiencyMetrics()` method to return efficiency metrics (wait time, service time, throughput, utilization)
    - Implement `calculateTrendIndicators()` method to compare current vs previous period metrics
    - Implement `calculateAverageServiceTime()` helper method
    - Implement `calculateStaffUtilization()` helper method
    - _Requirements: 17, 18, 19, 20_
  
  - [x] 2.3 Create PdfExportService
    - Create `app/Services/PdfExportService.php`
    - Install `barryvdh/laravel-dompdf` package via composer
    - Implement `generateLabResultPdf()` method to create PDF with patient info, test details, hospital branding
    - Implement `generatePrescriptionPdf()` method to create PDF with prescription details
    - Implement `generateReportPdf()` method to create PDF reports with charts and tables
    - Create Blade templates for PDF layouts (header with logo, body, footer with timestamp)
    - _Requirements: 13, 16, 21_
  
  - [x] 2.4 Create ExcelExportService
    - Create `app/Services/ExcelExportService.php`
    - Install `maatwebsite/excel` package via composer
    - Implement `generateReportExcel()` method to create Excel files with formatted data
    - Create export classes for different report types
    - Include column headers, data rows, and summary calculations
    - _Requirements: 21_

- [x] 3. Checkpoint - Verify services and database setup
  - Ensure migrations run successfully
  - Ensure all service classes are created with proper namespaces
  - Ensure dependencies are installed (dompdf, maatwebsite/excel)
  - Ask the user if questions arise

- [x] 4. Implement DepartmentApiController for dashboard and vitals
  - [x] 4.1 Create DepartmentApiController with dashboard statistics endpoints
    - Create `app/Http/Controllers/Api/V1/DepartmentApiController.php`
    - Implement `getDashboardStatistics()` method calling DepartmentStatisticsService
    - Implement `getPatientFlowChart()` method for chart data
    - Implement `getStaffWorkloadChart()` method for workload visualization
    - Implement `getRecentActivity()` method for activity feed
    - Add authorization checks (is_hod flag and department ownership)
    - Add tenant scoping to all queries
    - _Requirements: 5, 6, 7, 8, 26, 28_
  
  - [x] 4.2 Add vitals management endpoints to DepartmentApiController
    - Implement `recordVitals()` method to create PatientVitals record
    - Calculate BMI automatically from weight and height
    - Validate numeric ranges for all vitals fields
    - Create PatientJourneyEvent for vitals recording
    - Create audit log entry
    - Implement `getVitalsHistory()` method to return patient vitals with normal range indicators
    - Add authorization check: patient must be in HOD's department
    - _Requirements: 9, 10, 27_
  
  - [x] 4.3 Create VitalsNormalRanges helper class
    - Create `app/Helpers/VitalsNormalRanges.php` with constants for normal ranges
    - Implement static methods: `isNormalSystolic()`, `isNormalDiastolic()`, `isNormalTemperature()`, etc.
    - Implement `calculateBMI()` static method
    - Implement `isNormalBMI()` method
    - _Requirements: 10_

- [x] 5. Implement lab results and prescription management endpoints
  - [x] 5.1 Add lab results endpoints to DepartmentApiController
    - Implement `getLabResults()` method with filtering (patient, date range, test type) and pagination
    - Implement `getInvestigationNotes()` method to retrieve investigation notes from PatientJourneyEvent metadata
    - Implement `exportLabResultPdf()` method calling PdfExportService
    - Add authorization: only lab results for patients in HOD's department
    - _Requirements: 11, 12, 13_
  
  - [x] 5.2 Add prescription management endpoints to DepartmentApiController
    - Implement `getPrescriptions()` method with filtering (patient, doctor, date range) and pagination
    - Query PatientJourneyEvents with metadata containing prescription_notes
    - Implement `getPrescriptionHistory()` method for specific patient
    - Implement `exportPrescriptionPdf()` method calling PdfExportService
    - Add authorization: only prescriptions for patients in HOD's department
    - _Requirements: 14, 15, 16_

- [x] 6. Implement reporting endpoints
  - [x] 6.1 Add report generation endpoints to DepartmentApiController
    - Implement `getDailySummaryReport()` method calling ReportGenerationService
    - Implement `getStaffPerformanceReport()` method with date range validation
    - Implement `getDepartmentEfficiencyReport()` method with trend indicators
    - Validate date ranges: start before end, maximum 365 days
    - Add authorization: reports only for HOD's department
    - _Requirements: 17, 18, 19, 20_
  
  - [x] 6.2 Add report export endpoints to DepartmentApiController
    - Implement `exportReportPdf()` method calling PdfExportService
    - Implement `exportReportExcel()` method calling ExcelExportService
    - Generate filename format: `{report_type}_{department_name}_{date_range}.{extension}`
    - Set appropriate headers for file download
    - Create audit log entries for exports
    - _Requirements: 21, 27_

- [x] 7. Implement department settings endpoints
  - [x] 7.1 Add department settings endpoints to DepartmentApiController
    - Implement `updateDepartmentInfo()` method to update name, description, contact info
    - Validate department name uniqueness within tenant
    - Add authorization: HOD can only update own department
    - Create audit log entry
    - _Requirements: 22, 27_
  
  - [x] 7.2 Add working hours configuration endpoint
    - Implement `updateWorkingHours()` method to store/update DepartmentWorkingHours records
    - Validate opening time is before closing time
    - Handle all 7 days of the week
    - Create audit log entry
    - _Requirements: 23, 27_
  
  - [x] 7.3 Add notification preferences endpoint
    - Implement `updateNotificationPreferences()` method to store/update UserNotificationPreferences
    - Apply preferences to authenticated HOD's account only
    - Return success message
    - _Requirements: 24_

- [x] 8. Checkpoint - Test all API endpoints
  - Ensure all endpoints return correct data
  - Ensure authorization checks work properly
  - Ensure audit logs are created
  - Ask the user if questions arise

- [x] 9. Create API routes for HOD dashboard
  - [x] 9.1 Add dashboard statistics routes
    - Add routes in `routes/api.php` or `routes/web.php` under `/department/api` prefix
    - Apply middleware: `tenant.resolve`, `department.auth`, `tenant.scope`
    - Route: `GET /department/api/dashboard/statistics` → `DepartmentApiController@getDashboardStatistics`
    - Route: `GET /department/api/dashboard/patient-flow` → `DepartmentApiController@getPatientFlowChart`
    - Route: `GET /department/api/dashboard/staff-workload` → `DepartmentApiController@getStaffWorkloadChart`
    - Route: `GET /department/api/dashboard/recent-activity` → `DepartmentApiController@getRecentActivity`
    - _Requirements: 5, 6, 7, 8, 28_
  
  - [x] 9.2 Add vitals, lab results, and prescription routes
    - Route: `POST /department/api/patient-records/{id}/vitals` → `DepartmentApiController@recordVitals`
    - Route: `GET /department/api/patient-records/{id}/vitals` → `DepartmentApiController@getVitalsHistory`
    - Route: `GET /department/api/lab-results` → `DepartmentApiController@getLabResults`
    - Route: `GET /department/api/patient-records/{id}/investigation-notes` → `DepartmentApiController@getInvestigationNotes`
    - Route: `GET /department/api/lab-results/{id}/export-pdf` → `DepartmentApiController@exportLabResultPdf`
    - Route: `GET /department/api/prescriptions` → `DepartmentApiController@getPrescriptions`
    - Route: `GET /department/api/patient-records/{id}/prescriptions` → `DepartmentApiController@getPrescriptionHistory`
    - Route: `GET /department/api/prescriptions/{eventId}/export-pdf` → `DepartmentApiController@exportPrescriptionPdf`
    - _Requirements: 9, 10, 11, 12, 13, 14, 15, 16_
  
  - [x] 9.3 Add reporting and settings routes
    - Route: `GET /department/api/reports/daily-summary` → `DepartmentApiController@getDailySummaryReport`
    - Route: `GET /department/api/reports/staff-performance` → `DepartmentApiController@getStaffPerformanceReport`
    - Route: `GET /department/api/reports/efficiency-metrics` → `DepartmentApiController@getDepartmentEfficiencyReport`
    - Route: `POST /department/api/reports/export-pdf` → `DepartmentApiController@exportReportPdf`
    - Route: `POST /department/api/reports/export-excel` → `DepartmentApiController@exportReportExcel`
    - Route: `PUT /department/api/settings/info` → `DepartmentApiController@updateDepartmentInfo`
    - Route: `PUT /department/api/settings/working-hours` → `DepartmentApiController@updateWorkingHours`
    - Route: `PUT /department/api/settings/notifications` → `DepartmentApiController@updateNotificationPreferences`
    - _Requirements: 17, 18, 19, 20, 21, 22, 23, 24_

- [x] 10. Create Vue components for dashboard
  - [x] 10.1 Create DepartmentDashboard.vue main page
    - Create `resources/js/Pages/Department/Dashboard.vue`
    - Accept props: statistics, patientFlowData, staffWorkloadData, recentActivity, department, currentUser
    - Display statistics cards: total_in_queue, patients_seen_today, average_wait_time, staff_on_duty
    - Integrate Chart.js or ApexCharts for patient flow line/bar chart
    - Integrate Chart.js or ApexCharts for staff workload bar chart
    - Display recent activity feed with event type, patient name, staff name, timestamp
    - Add WebSocket connection status indicator
    - _Requirements: 5, 6, 7, 8, 25, 30_
  
  - [x] 10.2 Set up Laravel Echo for real-time updates
    - Configure Laravel Echo in `resources/js/bootstrap.js` with Pusher
    - Subscribe to `department.{department_id}` private channel in DepartmentDashboard.vue
    - Listen for `PatientRouted`, `PatientStatusChanged`, `VitalsRecorded` events
    - Update statistics and activity feed when events received
    - Implement automatic reconnection with exponential backoff
    - _Requirements: 25_
  
  - [x] 10.3 Create VitalsRecording.vue component
    - Create `resources/js/Pages/Department/VitalsRecording.vue`
    - Accept props: patientRecord, vitalsHistory
    - Create form with fields: blood_pressure, temperature, pulse_rate, respiratory_rate, oxygen_saturation, weight, height
    - Implement automatic BMI calculation on weight/height change
    - Add form validation with error messages
    - Display vitals history table with color-coded normal range indicators
    - Call API endpoint on form submit
    - _Requirements: 9, 10, 30_

- [x] 11. Create Vue components for staff management
  - [x] 11.1 Create StaffManagement.vue page
    - Create `resources/js/Pages/Department/StaffManagement.vue`
    - Accept props: staff (paginated), filters, allowedRoles
    - Display staff table with columns: name, email, phone, role, active status
    - Add search input with debouncing (300ms)
    - Add role filter dropdown
    - Add active status filter dropdown
    - Add pagination controls
    - Add "Create Staff" button to open modal
    - _Requirements: 1, 30_
  
  - [x] 11.2 Create staff creation and editing modals
    - Create modal component for staff creation with form fields: name, email, phone, password, role
    - Implement client-side validation (required fields, email format, password complexity)
    - Call HodController API endpoint on submit
    - Create modal component for staff editing (name, phone, role, active status)
    - Display success/error messages
    - Refresh staff list after successful operation
    - _Requirements: 2, 3_
  
  - [x] 11.3 Add staff deactivation functionality
    - Add "Deactivate" button for each active staff member
    - Show confirmation dialog before deactivation
    - Call HodController API endpoint for deactivation
    - Update staff list after successful deactivation
    - Display success message
    - _Requirements: 4_

- [x] 12. Create Vue components for lab results and prescriptions
  - [x] 12.1 Create LabResultsManagement.vue page
    - Create `resources/js/Pages/Department/LabResultsManagement.vue`
    - Accept props: labResults (paginated), filters
    - Display lab results table with columns: patient name, patient number, test type, result status, date
    - Add patient filter dropdown
    - Add date range picker
    - Add test type filter dropdown
    - Add "Export PDF" button for each result
    - Add pagination controls
    - _Requirements: 11, 13, 30_
  
  - [x] 12.2 Create PrescriptionManagement.vue page
    - Create `resources/js/Pages/Department/PrescriptionManagement.vue`
    - Accept props: prescriptions (paginated), filters
    - Display prescriptions table with columns: patient name, patient number, prescribing doctor, prescription notes, date
    - Add patient filter dropdown
    - Add doctor filter dropdown
    - Add date range picker
    - Add "Export PDF" button for each prescription
    - Add pagination controls
    - _Requirements: 14, 16, 30_
  
  - [x] 12.3 Create investigation notes display component
    - Create component to display investigation notes in patient detail view
    - Fetch investigation notes from API
    - Display notes text, requesting doctor name, timestamp
    - Integrate into existing patient detail page
    - _Requirements: 12_

- [x] 13. Create Vue components for reporting
  - [x] 13.1 Create DepartmentReports.vue page
    - Create `resources/js/Pages/Department/DepartmentReports.vue`
    - Accept props: reportTypes, department
    - Add report type selector (daily summary, staff performance, efficiency metrics)
    - Add date range picker with validation (start before end, max 365 days)
    - Add "Generate Report" button
    - Display report preview area
    - Add "Export to PDF" and "Export to Excel" buttons
    - _Requirements: 17, 18, 19, 20, 21, 30_
  
  - [x] 13.2 Create report display components
    - Create component for daily summary report display (patient list, summary metrics)
    - Create component for staff performance report display (staff metrics table)
    - Create component for efficiency metrics report display (metrics with trend indicators)
    - Use icons or colors for trend indicators (improving/declining)
    - Format dates and numbers appropriately
    - _Requirements: 17, 18, 19_

- [x] 14. Create Vue components for department settings
  - [x] 14.1 Create DepartmentSettings.vue page
    - Create `resources/js/Pages/Department/DepartmentSettings.vue`
    - Accept props: department, workingHours, notificationPreferences
    - Create tabs or sections for: Department Info, Working Hours, Notifications
    - _Requirements: 22, 23, 24, 30_
  
  - [x] 14.2 Implement department info form
    - Add form fields: department name, description, contact email, contact phone
    - Add client-side validation
    - Call API endpoint on submit
    - Display success message
    - _Requirements: 22_
  
  - [x] 14.3 Implement working hours configuration
    - Create form with 7 rows (one per day of week)
    - Add checkbox for "is_open" per day
    - Add time pickers for opening_time and closing_time
    - Validate opening time is before closing time
    - Call API endpoint on submit
    - _Requirements: 23_
  
  - [x] 14.4 Implement notification preferences
    - Add toggle switches for: patient_arrivals_enabled, staff_actions_enabled, system_alerts_enabled
    - Add toggle switches for delivery methods: email_enabled, sms_enabled, in_app_enabled
    - Call API endpoint on submit
    - Display success message
    - _Requirements: 24_

- [x] 15. Checkpoint - Test frontend components
  - Ensure all Vue components render correctly
  - Ensure forms submit data properly
  - Ensure real-time updates work
  - Ask the user if questions arise

- [x] 16. Update DepartmentWebController for Inertia pages
  - [x] 16.1 Add dashboard page route
    - Update `app/Http/Controllers/DepartmentWebController.php`
    - Create `dashboard()` method to render DepartmentDashboard.vue
    - Fetch initial statistics, patient flow data, staff workload data, recent activity
    - Pass data to Inertia page
    - _Requirements: 5, 6, 7, 8_
  
  - [x] 16.2 Add staff management page route
    - Create `staffManagement()` method to render StaffManagement.vue
    - Fetch paginated staff list with filters
    - Pass allowed roles array
    - _Requirements: 1_
  
  - [x] 16.3 Add vitals recording page route
    - Create `vitalsRecording()` method to render VitalsRecording.vue
    - Fetch patient record and vitals history
    - Verify patient is in HOD's department
    - _Requirements: 9, 10_
  
  - [x] 16.4 Add lab results and prescription pages routes
    - Create `labResults()` method to render LabResultsManagement.vue
    - Create `prescriptions()` method to render PrescriptionManagement.vue
    - Fetch paginated data with filters
    - _Requirements: 11, 14_
  
  - [x] 16.5 Add reports and settings page routes
    - Create `reports()` method to render DepartmentReports.vue
    - Create `settings()` method to render DepartmentSettings.vue
    - Fetch necessary data for each page
    - _Requirements: 17, 22_

- [x] 17. Create WebSocket events for real-time updates
  - [x] 17.1 Create VitalsRecorded event
    - Create `app/Events/VitalsRecorded.php` implementing ShouldBroadcast
    - Broadcast on `department.{department_id}` private channel
    - Include patient_record_id, vitals data, recorded_by name in broadcast data
    - Dispatch event after vitals are recorded
    - _Requirements: 25_
  
  - [x] 17.2 Create LabResultAdded event
    - Create `app/Events/LabResultAdded.php` implementing ShouldBroadcast
    - Broadcast on `department.{department_id}` private channel
    - Include lab result details in broadcast data
    - Dispatch event when lab results are added
    - _Requirements: 25_
  
  - [x] 17.3 Update existing events to broadcast to HOD dashboard
    - Verify PatientRouted event broadcasts to department channel
    - Verify PatientStatusChanged event broadcasts to department channel
    - Ensure HOD dashboard receives these events
    - _Requirements: 25_

- [x] 18. Implement cache invalidation logic
  - [x] 18.1 Add cache invalidation on patient routing
    - Invalidate `dept_stats:{department_id}:*` cache when patient routed to/from department
    - Use cache tags or manual key deletion
    - _Requirements: 29_
  
  - [x] 18.2 Add cache invalidation on vitals recording
    - Invalidate department statistics cache when vitals recorded
    - _Requirements: 29_
  
  - [x] 18.3 Add cache invalidation on staff status change
    - Invalidate staff-related cache when staff created, updated, or deactivated
    - _Requirements: 29_

- [x] 19. Create Form Request classes for validation
  - [x] 19.1 Create RecordVitalsRequest
    - Create `app/Http/Requests/Department/RecordVitalsRequest.php`
    - Add validation rules for all vitals fields (numeric, ranges)
    - Add custom error messages
    - _Requirements: 9_
  
  - [x] 19.2 Create UpdateDepartmentInfoRequest
    - Create `app/Http/Requests/Department/UpdateDepartmentInfoRequest.php`
    - Add validation rules for department name (unique within tenant), description, contact info
    - _Requirements: 22_
  
  - [x] 19.3 Create UpdateWorkingHoursRequest
    - Create `app/Http/Requests/Department/UpdateWorkingHoursRequest.php`
    - Add validation rules for working hours (opening before closing)
    - _Requirements: 23_
  
  - [x] 19.4 Create GenerateReportRequest
    - Create `app/Http/Requests/Department/GenerateReportRequest.php`
    - Add validation rules for date range (start before end, max 365 days)
    - _Requirements: 20_

- [x] 20. Add audit logging to all HOD actions
  - [x] 20.1 Add audit logs for staff management
    - Create audit log entry when staff created (action: staff.created)
    - Create audit log entry when staff updated (action: staff.updated)
    - Create audit log entry when staff deactivated (action: staff.deactivated)
    - Include tenant_id, actor_id, actor_role, resource_type, resource_id, description, IP address
    - _Requirements: 27_
  
  - [x] 20.2 Add audit logs for vitals and settings
    - Create audit log entry when vitals recorded (action: vitals.recorded)
    - Create audit log entry when department info updated (action: department.updated)
    - Create audit log entry when working hours updated (action: working_hours.updated)
    - _Requirements: 27_
  
  - [x] 20.3 Add audit logs for report exports
    - Create audit log entry when report exported to PDF (action: report.exported_pdf)
    - Create audit log entry when report exported to Excel (action: report.exported_excel)
    - Include report type and date range in metadata
    - _Requirements: 27_

- [x] 21. Checkpoint - Test authorization and audit logging
  - Ensure only HODs can access HOD features
  - Ensure cross-department access is blocked
  - Ensure audit logs are created correctly
  - Ask the user if questions arise

- [x] 22. Create database seeders for initial data
  - [x] 22.1 Create DepartmentWorkingHoursSeeder
    - Create seeder to populate default working hours for existing departments
    - Set default hours: Monday-Friday 8:00-17:00, Saturday-Sunday closed
    - Run seeder as part of deployment
    - _Requirements: 23_
  
  - [x] 22.2 Create UserNotificationPreferencesSeeder
    - Create seeder to populate default notification preferences for existing HODs
    - Set all preferences to enabled by default
    - Run seeder as part of deployment
    - _Requirements: 24_

- [x] 23. Add navigation links to HOD dashboard
  - [x] 23.1 Update department navigation menu
    - Add navigation links in department layout component
    - Links: Dashboard, Staff Management, Lab Results, Prescriptions, Reports, Settings
    - Highlight active page
    - _Requirements: 1, 5, 11, 14, 17, 22_

- [x] 24. Implement responsive design for all pages
  - [x] 24.1 Add responsive CSS for dashboard
    - Use Tailwind CSS responsive utilities
    - Ensure statistics cards stack on mobile
    - Ensure charts resize appropriately
    - Test on desktop (1920x1080), laptop (1366x768), tablet (768x1024)
    - _Requirements: 30_
  
  - [x] 24.2 Add responsive CSS for tables and forms
    - Make staff table responsive (horizontal scroll on mobile)
    - Make forms stack vertically on mobile
    - Ensure modals are mobile-friendly
    - _Requirements: 30_

- [x] 25. Create PDF templates for exports
  - [x] 25.1 Create lab result PDF template
    - Create Blade template `resources/views/pdf/lab-result.blade.php`
    - Include header with hospital logo and name
    - Include patient info, test details, result values, normal ranges
    - Include footer with generation timestamp
    - _Requirements: 13_
  
  - [x] 25.2 Create prescription PDF template
    - Create Blade template `resources/views/pdf/prescription.blade.php`
    - Include header with hospital logo and name
    - Include patient info, doctor info, prescription notes
    - Include footer with generation timestamp
    - _Requirements: 16_
  
  - [x] 25.3 Create report PDF templates
    - Create Blade template `resources/views/pdf/daily-summary.blade.php`
    - Create Blade template `resources/views/pdf/staff-performance.blade.php`
    - Create Blade template `resources/views/pdf/efficiency-metrics.blade.php`
    - Include charts, tables, and summary metrics
    - _Requirements: 21_

- [x] 26. Implement error handling and user feedback
  - [x] 26.1 Add error handling to API controllers
    - Wrap controller methods in try-catch blocks
    - Return appropriate HTTP status codes (400, 403, 404, 422, 500)
    - Return consistent error response format with code and message
    - Log errors with context (user_id, tenant_id, resource_id, IP address)
    - _Requirements: All_
  
  - [x] 26.2 Add error handling to Vue components
    - Display error messages from API responses
    - Show toast notifications for success/error
    - Preserve form data on validation errors
    - Add "Try Again" buttons for failed operations
    - _Requirements: All_
  
  - [x] 26.3 Add loading states to components
    - Show loading spinners during API calls
    - Show skeleton loaders for dashboard statistics
    - Disable form submit buttons during submission
    - _Requirements: All_

- [x] 27. Final checkpoint - End-to-end testing
  - Test complete staff management workflow (create, edit, deactivate)
  - Test dashboard statistics display and real-time updates
  - Test vitals recording and history viewing
  - Test lab results and prescription viewing with filters
  - Test report generation and export (PDF and Excel)
  - Test department settings updates
  - Verify multi-tenant isolation
  - Verify authorization checks
  - Verify audit logging
  - Ensure all tests pass, ask the user if questions arise

## Notes

- All tasks reference specific requirements for traceability
- Tasks build incrementally with checkpoints for validation
- Authorization and audit logging are integrated throughout
- Multi-tenant data isolation is enforced at all layers
- Real-time updates enhance user experience
- Responsive design ensures usability across devices
- Comprehensive error handling provides good user experience
- PDF and Excel exports enable data sharing and analysis
