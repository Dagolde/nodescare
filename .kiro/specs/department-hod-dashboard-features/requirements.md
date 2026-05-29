# Requirements Document: Department HOD Dashboard Features

## Introduction

This document specifies the requirements for adding comprehensive management and reporting features to the department Head of Department (HOD) web dashboard in the hospital management system. The HOD dashboard currently provides basic patient queue management and patient detail viewing. This enhancement will add staff management, department statistics, vitals recording, lab results management, prescription management, reporting, and department settings capabilities.

The system uses Laravel with Inertia.js and Vue 3 on the frontend, PostgreSQL for data storage, and session-based authentication for web users. All features must respect multi-tenant boundaries and provide audit logging for compliance.

## Glossary

- **HOD**: Head of Department - a staff member with the `is_hod` flag set to true, authorized to manage department staff and access department-wide reports
- **Department**: An organizational unit within a hospital (e.g., Cardiology, Laboratory, Pharmacy)
- **Staff**: Hospital employees assigned to a department with roles such as doctor, nurse, lab technician, pharmacist, or front desk officer
- **Patient_Record**: A record of a patient's visit to the hospital, including their journey through various departments
- **Vitals**: Patient vital signs including blood pressure, temperature, pulse rate, respiratory rate, oxygen saturation, weight, height, and BMI
- **Lab_Result**: Results from laboratory tests performed on a patient
- **Prescription**: Medication orders issued by doctors for patients
- **Investigation_Notes**: Test types and procedures requested by doctors for patients
- **Tenant**: A hospital organization in the multi-tenant system
- **Audit_Log**: A record of actions performed by users for compliance and security tracking
- **Dashboard**: The main overview page showing statistics and metrics
- **Web_Dashboard**: The browser-based interface used by HODs (as opposed to the mobile app used by other staff)

## Requirements

### Requirement 1: Staff Management - List Staff

**User Story:** As an HOD, I want to view all staff members in my department, so that I can see who is currently working in my department.

#### Acceptance Criteria

1. WHEN an HOD accesses the staff management page, THE Web_Dashboard SHALL display all staff members assigned to the HOD's department
2. THE Web_Dashboard SHALL display staff name, email, phone, role, and active status for each staff member
3. WHERE the HOD applies a role filter, THE Web_Dashboard SHALL display only staff members with the selected role
4. WHERE the HOD applies an active status filter, THE Web_Dashboard SHALL display only staff members matching the selected active status
5. WHERE the HOD enters a search term, THE Web_Dashboard SHALL filter staff by name or email matching the search term
6. THE Web_Dashboard SHALL paginate staff listings with 20 staff members per page
7. THE Web_Dashboard SHALL display staff in alphabetical order by name

### Requirement 2: Staff Management - Create Staff

**User Story:** As an HOD, I want to create new staff accounts for my department, so that new employees can access the system.

#### Acceptance Criteria

1. WHEN an HOD submits a create staff form with valid data, THE Web_Dashboard SHALL create a new staff account in the HOD's department
2. THE Web_Dashboard SHALL require name, email, phone, password, and role fields for staff creation
3. THE Web_Dashboard SHALL validate that the email is unique across all users in the tenant
4. THE Web_Dashboard SHALL validate that the password meets complexity requirements (minimum 10 characters with uppercase, lowercase, number, and special character)
5. THE Web_Dashboard SHALL restrict role selection to doctor, front_desk_officer, pharmacist, lab_technician, and department_staff
6. THE Web_Dashboard SHALL set the new staff member's department_id to the HOD's department_id
7. THE Web_Dashboard SHALL set is_hod to false for all newly created staff accounts
8. WHEN a staff account is created, THE Web_Dashboard SHALL create an audit log entry recording the creation
9. IF the email already exists, THEN THE Web_Dashboard SHALL return an error message indicating the email is already in use
10. THE Web_Dashboard SHALL display a success message after successful staff creation

### Requirement 3: Staff Management - Edit Staff

**User Story:** As an HOD, I want to edit staff member details, so that I can keep staff information up to date.

#### Acceptance Criteria

1. WHEN an HOD submits an edit staff form with valid data, THE Web_Dashboard SHALL update the staff member's information
2. THE Web_Dashboard SHALL allow editing of name, phone, role, and active status fields
3. THE Web_Dashboard SHALL prevent HODs from editing staff members in other departments
4. THE Web_Dashboard SHALL validate that the role is one of the allowed roles (doctor, front_desk_officer, pharmacist, lab_technician, department_staff)
5. WHEN a staff account is updated, THE Web_Dashboard SHALL create an audit log entry recording the update
6. THE Web_Dashboard SHALL display a success message after successful staff update
7. IF the staff member does not exist, THEN THE Web_Dashboard SHALL return a 404 error
8. IF the staff member belongs to a different department, THEN THE Web_Dashboard SHALL return a 403 forbidden error

### Requirement 4: Staff Management - Deactivate Staff

**User Story:** As an HOD, I want to deactivate staff accounts, so that former employees cannot access the system.

#### Acceptance Criteria

1. WHEN an HOD deactivates a staff account, THE Web_Dashboard SHALL set the staff member's is_active flag to false
2. WHEN an HOD deactivates a staff account, THE Web_Dashboard SHALL increment the staff member's token_version to invalidate all active sessions
3. THE Web_Dashboard SHALL prevent HODs from deactivating staff members in other departments
4. WHEN a staff account is deactivated, THE Web_Dashboard SHALL create an audit log entry recording the deactivation
5. THE Web_Dashboard SHALL display a success message after successful staff deactivation
6. IF the staff member is already deactivated, THEN THE Web_Dashboard SHALL return an error message indicating the account is already deactivated
7. IF the staff member does not exist, THEN THE Web_Dashboard SHALL return a 404 error

### Requirement 5: Department Dashboard - Overview Statistics

**User Story:** As an HOD, I want to see key department statistics at a glance, so that I can monitor department performance.

#### Acceptance Criteria

1. WHEN an HOD accesses the department dashboard, THE Web_Dashboard SHALL display the total number of patients currently in the department queue
2. THE Web_Dashboard SHALL display the number of patients seen by the department today
3. THE Web_Dashboard SHALL calculate and display the average wait time for patients in the department queue
4. THE Web_Dashboard SHALL display the count of staff members currently on duty in the department
5. THE Web_Dashboard SHALL refresh statistics when the page is loaded
6. THE Web_Dashboard SHALL display statistics for the HOD's department only
7. THE Web_Dashboard SHALL calculate "patients seen today" as patients who completed their department step on the current date

### Requirement 6: Department Dashboard - Patient Flow Chart

**User Story:** As an HOD, I want to see patient flow trends over time, so that I can identify patterns and plan resources.

#### Acceptance Criteria

1. WHEN an HOD accesses the department dashboard, THE Web_Dashboard SHALL display a chart showing patient flow for the last 7 days
2. THE Web_Dashboard SHALL plot the number of patients who entered the department each day
3. THE Web_Dashboard SHALL plot the number of patients who completed their department step each day
4. THE Web_Dashboard SHALL use a line chart or bar chart visualization
5. THE Web_Dashboard SHALL label the x-axis with dates and the y-axis with patient counts
6. THE Web_Dashboard SHALL display data for the HOD's department only

### Requirement 7: Department Dashboard - Staff Workload Distribution

**User Story:** As an HOD, I want to see how workload is distributed among staff, so that I can balance assignments fairly.

#### Acceptance Criteria

1. WHEN an HOD accesses the department dashboard, THE Web_Dashboard SHALL display a chart showing the number of patients handled by each staff member
2. THE Web_Dashboard SHALL calculate workload based on patient journey events where the staff member is the performer
3. THE Web_Dashboard SHALL display staff names on the x-axis and patient counts on the y-axis
4. THE Web_Dashboard SHALL use a bar chart visualization
5. THE Web_Dashboard SHALL display data for the current day
6. THE Web_Dashboard SHALL include only staff members from the HOD's department

### Requirement 8: Department Dashboard - Recent Activity Feed

**User Story:** As an HOD, I want to see recent department activities, so that I can stay informed about what's happening.

#### Acceptance Criteria

1. WHEN an HOD accesses the department dashboard, THE Web_Dashboard SHALL display the 10 most recent patient journey events in the department
2. THE Web_Dashboard SHALL display the event type, patient name, staff member who performed the action, and timestamp for each event
3. THE Web_Dashboard SHALL order events by timestamp in descending order (most recent first)
4. THE Web_Dashboard SHALL display events for the HOD's department only
5. THE Web_Dashboard SHALL include events such as patient routed, note added, vitals recorded, and step completed

### Requirement 9: Vitals Recording - Record Patient Vitals

**User Story:** As an HOD, I want to record patient vitals from the web dashboard, so that I can document patient health measurements.

#### Acceptance Criteria

1. WHEN an HOD submits a vitals recording form with valid data, THE Web_Dashboard SHALL create a new vitals record for the patient
2. THE Web_Dashboard SHALL accept blood pressure (systolic/diastolic), temperature, pulse rate, respiratory rate, oxygen saturation, weight, and height as input fields
3. THE Web_Dashboard SHALL automatically calculate BMI from weight and height using the formula: BMI = weight(kg) / (height(m) * height(m))
4. THE Web_Dashboard SHALL validate that numeric fields contain valid numbers within reasonable ranges
5. THE Web_Dashboard SHALL associate the vitals record with the patient record ID
6. THE Web_Dashboard SHALL record the HOD's user ID as the recorder
7. THE Web_Dashboard SHALL timestamp the vitals record with the current date and time
8. WHEN vitals are recorded, THE Web_Dashboard SHALL create a patient journey event documenting the vitals recording
9. THE Web_Dashboard SHALL display a success message after successful vitals recording

### Requirement 10: Vitals Recording - View Vitals History

**User Story:** As an HOD, I want to view a patient's vitals history, so that I can track health trends over time.

#### Acceptance Criteria

1. WHEN an HOD accesses a patient's vitals history, THE Web_Dashboard SHALL display all vitals records for the patient in reverse chronological order
2. THE Web_Dashboard SHALL display blood pressure, temperature, pulse rate, respiratory rate, oxygen saturation, weight, height, and BMI for each vitals record
3. THE Web_Dashboard SHALL display the date, time, and recorder name for each vitals record
4. THE Web_Dashboard SHALL indicate whether each vital measurement is within normal range using color coding or icons
5. THE Web_Dashboard SHALL define normal ranges as: systolic BP 90-120 mmHg, diastolic BP 60-80 mmHg, temperature 36.1-37.2°C, pulse 60-100 bpm, respiratory rate 12-20 breaths/min, oxygen saturation 95-100%, BMI 18.5-24.9
6. THE Web_Dashboard SHALL allow HODs to view vitals history for patients in their department only

### Requirement 11: Lab Results Management - View Lab Results

**User Story:** As an HOD, I want to view lab results for patients in my department, so that I can review test outcomes.

#### Acceptance Criteria

1. WHEN an HOD accesses the lab results page, THE Web_Dashboard SHALL display all lab results for patients currently in the HOD's department
2. THE Web_Dashboard SHALL display patient name, patient number, test type, result status, and date for each lab result
3. WHERE the HOD applies a patient filter, THE Web_Dashboard SHALL display only lab results for the selected patient
4. WHERE the HOD applies a date range filter, THE Web_Dashboard SHALL display only lab results within the selected date range
5. WHERE the HOD applies a test type filter, THE Web_Dashboard SHALL display only lab results matching the selected test type
6. THE Web_Dashboard SHALL paginate lab results with 20 results per page
7. THE Web_Dashboard SHALL order lab results by date in descending order (most recent first)

### Requirement 12: Lab Results Management - View Investigation Notes

**User Story:** As an HOD, I want to view investigation notes for patients, so that I can see what tests were requested.

#### Acceptance Criteria

1. WHEN an HOD views a patient's detail page, THE Web_Dashboard SHALL display investigation notes if they exist
2. THE Web_Dashboard SHALL retrieve investigation notes from patient journey events with metadata containing investigation_notes
3. THE Web_Dashboard SHALL display the investigation notes text, requesting doctor name, and timestamp
4. THE Web_Dashboard SHALL allow HODs to view investigation notes for patients in their department only

### Requirement 13: Lab Results Management - Export Lab Results to PDF

**User Story:** As an HOD, I want to export lab results to PDF, so that I can share them with other healthcare providers or keep records.

#### Acceptance Criteria

1. WHEN an HOD clicks the export button for a lab result, THE Web_Dashboard SHALL generate a PDF document containing the lab result details
2. THE PDF SHALL include patient name, patient number, test type, result values, normal ranges, result status, and date
3. THE PDF SHALL include the hospital name and logo
4. THE PDF SHALL include the date and time of export
5. THE Web_Dashboard SHALL download the PDF file to the HOD's browser
6. THE Web_Dashboard SHALL allow HODs to export lab results for patients in their department only

### Requirement 14: Prescription Management - View Prescriptions

**User Story:** As an HOD, I want to view prescriptions issued by department doctors, so that I can monitor medication orders.

#### Acceptance Criteria

1. WHEN an HOD accesses the prescription management page, THE Web_Dashboard SHALL display all prescriptions for patients in the HOD's department
2. THE Web_Dashboard SHALL display patient name, patient number, prescribing doctor, prescription notes, and date for each prescription
3. WHERE the HOD applies a patient filter, THE Web_Dashboard SHALL display only prescriptions for the selected patient
4. WHERE the HOD applies a doctor filter, THE Web_Dashboard SHALL display only prescriptions issued by the selected doctor
5. WHERE the HOD applies a date range filter, THE Web_Dashboard SHALL display only prescriptions within the selected date range
6. THE Web_Dashboard SHALL paginate prescriptions with 20 prescriptions per page
7. THE Web_Dashboard SHALL order prescriptions by date in descending order (most recent first)

### Requirement 15: Prescription Management - View Prescription History

**User Story:** As an HOD, I want to view a patient's prescription history, so that I can see all medications prescribed to the patient.

#### Acceptance Criteria

1. WHEN an HOD accesses a patient's prescription history, THE Web_Dashboard SHALL display all prescriptions for the patient in reverse chronological order
2. THE Web_Dashboard SHALL retrieve prescriptions from patient journey events with metadata containing prescription_notes
3. THE Web_Dashboard SHALL display the prescription notes, prescribing doctor name, and timestamp for each prescription
4. THE Web_Dashboard SHALL allow HODs to view prescription history for patients in their department only

### Requirement 16: Prescription Management - Export Prescription to PDF

**User Story:** As an HOD, I want to export prescriptions to PDF, so that I can provide printed copies to patients or pharmacies.

#### Acceptance Criteria

1. WHEN an HOD clicks the export button for a prescription, THE Web_Dashboard SHALL generate a PDF document containing the prescription details
2. THE PDF SHALL include patient name, patient number, prescribing doctor name, prescription notes, and date
3. THE PDF SHALL include the hospital name and logo
4. THE PDF SHALL include the date and time of export
5. THE Web_Dashboard SHALL download the PDF file to the HOD's browser
6. THE Web_Dashboard SHALL allow HODs to export prescriptions for patients in their department only

### Requirement 17: Department Reports - Daily Patient Summary

**User Story:** As an HOD, I want to generate a daily patient summary report, so that I can review the day's activities.

#### Acceptance Criteria

1. WHEN an HOD requests a daily patient summary report, THE Web_Dashboard SHALL generate a report showing all patients who visited the department on the selected date
2. THE Report SHALL include patient name, patient number, time of arrival, time of departure, chief complaint, and outcome for each patient
3. THE Report SHALL calculate total patients seen, average wait time, and average service time
4. THE Report SHALL include the department name and date at the top
5. THE Web_Dashboard SHALL allow HODs to select any date for the report
6. THE Web_Dashboard SHALL default to the current date if no date is selected
7. THE Web_Dashboard SHALL display the report in the browser with an option to export to PDF

### Requirement 18: Department Reports - Staff Performance Report

**User Story:** As an HOD, I want to generate a staff performance report, so that I can evaluate staff productivity.

#### Acceptance Criteria

1. WHEN an HOD requests a staff performance report, THE Web_Dashboard SHALL generate a report showing performance metrics for all staff in the department
2. THE Report SHALL include staff name, role, number of patients handled, average service time, and number of notes added for each staff member
3. THE Report SHALL calculate metrics based on patient journey events where the staff member is the performer
4. THE Report SHALL allow HODs to select a date range for the report
5. THE Report SHALL default to the current month if no date range is selected
6. THE Web_Dashboard SHALL display the report in the browser with an option to export to PDF

### Requirement 19: Department Reports - Department Efficiency Metrics

**User Story:** As an HOD, I want to see department efficiency metrics, so that I can identify areas for improvement.

#### Acceptance Criteria

1. WHEN an HOD requests a department efficiency report, THE Web_Dashboard SHALL generate a report showing efficiency metrics for the department
2. THE Report SHALL include average wait time, average service time, patient throughput (patients per day), and staff utilization rate
3. THE Report SHALL calculate staff utilization rate as the percentage of working hours spent actively serving patients
4. THE Report SHALL allow HODs to select a date range for the report
5. THE Report SHALL default to the current month if no date range is selected
6. THE Report SHALL display trend indicators showing whether metrics are improving or declining compared to the previous period
7. THE Web_Dashboard SHALL display the report in the browser with an option to export to PDF

### Requirement 20: Department Reports - Custom Date Range Reports

**User Story:** As an HOD, I want to generate reports for custom date ranges, so that I can analyze specific time periods.

#### Acceptance Criteria

1. WHEN an HOD selects a custom date range for any report, THE Web_Dashboard SHALL generate the report using data from the selected date range only
2. THE Web_Dashboard SHALL validate that the start date is before or equal to the end date
3. THE Web_Dashboard SHALL limit custom date ranges to a maximum of 365 days
4. IF the date range exceeds 365 days, THEN THE Web_Dashboard SHALL return an error message indicating the maximum allowed range
5. THE Web_Dashboard SHALL display the selected date range in the report header

### Requirement 21: Department Reports - Export to PDF and Excel

**User Story:** As an HOD, I want to export reports to PDF and Excel formats, so that I can share them with management or perform further analysis.

#### Acceptance Criteria

1. WHEN an HOD clicks the export to PDF button, THE Web_Dashboard SHALL generate a PDF document containing the report data with formatting preserved
2. WHEN an HOD clicks the export to Excel button, THE Web_Dashboard SHALL generate an Excel spreadsheet containing the report data
3. THE Excel_Export SHALL include column headers, data rows, and summary calculations
4. THE PDF_Export SHALL include the hospital name, logo, department name, report title, date range, and generation timestamp
5. THE Web_Dashboard SHALL download the exported file to the HOD's browser
6. THE Web_Dashboard SHALL name the exported file using the format: {report_type}_{department_name}_{date_range}.{extension}

### Requirement 22: Department Settings - Update Department Information

**User Story:** As an HOD, I want to update department information, so that I can keep department details current.

#### Acceptance Criteria

1. WHEN an HOD submits updated department information, THE Web_Dashboard SHALL update the department record with the new information
2. THE Web_Dashboard SHALL allow HODs to update department name, description, and contact information
3. THE Web_Dashboard SHALL prevent HODs from updating other departments' information
4. WHEN department information is updated, THE Web_Dashboard SHALL create an audit log entry recording the update
5. THE Web_Dashboard SHALL display a success message after successful update
6. THE Web_Dashboard SHALL validate that the department name is unique within the tenant

### Requirement 23: Department Settings - Configure Working Hours

**User Story:** As an HOD, I want to configure department working hours, so that the system can track when the department is operational.

#### Acceptance Criteria

1. WHEN an HOD submits working hours configuration, THE Web_Dashboard SHALL store the working hours for the department
2. THE Web_Dashboard SHALL allow HODs to set opening time and closing time for each day of the week
3. THE Web_Dashboard SHALL allow HODs to mark specific days as closed
4. THE Web_Dashboard SHALL validate that opening time is before closing time
5. THE Web_Dashboard SHALL store working hours in 24-hour format
6. WHEN working hours are updated, THE Web_Dashboard SHALL create an audit log entry recording the change

### Requirement 24: Department Settings - Set Notification Preferences

**User Story:** As an HOD, I want to configure notification preferences, so that I can control what alerts I receive.

#### Acceptance Criteria

1. WHEN an HOD updates notification preferences, THE Web_Dashboard SHALL store the preferences for the HOD's user account
2. THE Web_Dashboard SHALL allow HODs to enable or disable notifications for patient arrivals, staff actions, and system alerts
3. THE Web_Dashboard SHALL allow HODs to choose notification delivery methods (email, SMS, in-app)
4. THE Web_Dashboard SHALL apply notification preferences to the HOD's account only
5. WHEN notification preferences are updated, THE Web_Dashboard SHALL display a success message

### Requirement 25: Real-time Updates via WebSocket

**User Story:** As an HOD, I want to see real-time updates on the dashboard, so that I have current information without refreshing the page.

#### Acceptance Criteria

1. WHEN a patient is routed to the HOD's department, THE Web_Dashboard SHALL receive a WebSocket event and update the patient queue display
2. WHEN a patient completes their step in the HOD's department, THE Web_Dashboard SHALL receive a WebSocket event and update the patient queue display
3. WHEN a staff member records vitals or adds notes, THE Web_Dashboard SHALL receive a WebSocket event and update the recent activity feed
4. THE Web_Dashboard SHALL display a connection status indicator showing whether the WebSocket connection is active
5. THE Web_Dashboard SHALL automatically attempt to reconnect if the WebSocket connection is lost
6. THE Web_Dashboard SHALL subscribe to department-specific WebSocket channels using the department ID

### Requirement 26: Multi-tenant Data Isolation

**User Story:** As a system administrator, I want to ensure HODs can only access data from their own hospital, so that tenant data remains isolated.

#### Acceptance Criteria

1. THE Web_Dashboard SHALL scope all database queries to the current tenant using the tenant_id
2. THE Web_Dashboard SHALL prevent HODs from accessing staff, patients, or reports from other tenants
3. THE Web_Dashboard SHALL validate tenant_id on all API requests
4. THE Web_Dashboard SHALL return a 403 forbidden error if an HOD attempts to access data from another tenant
5. THE Web_Dashboard SHALL include tenant_id in all audit log entries

### Requirement 27: Audit Logging for Compliance

**User Story:** As a compliance officer, I want all HOD actions to be logged, so that we can maintain an audit trail.

#### Acceptance Criteria

1. WHEN an HOD creates, updates, or deactivates a staff account, THE Web_Dashboard SHALL create an audit log entry
2. WHEN an HOD updates department settings, THE Web_Dashboard SHALL create an audit log entry
3. WHEN an HOD records patient vitals, THE Web_Dashboard SHALL create an audit log entry
4. WHEN an HOD exports a report, THE Web_Dashboard SHALL create an audit log entry
5. THE Audit_Log SHALL include tenant_id, actor_id, actor_role, action, resource_type, resource_id, description, IP address, and timestamp
6. THE Web_Dashboard SHALL store audit logs in the audit_logs table

### Requirement 28: Authorization - HOD-only Access

**User Story:** As a system administrator, I want to ensure only HODs can access HOD dashboard features, so that access is properly restricted.

#### Acceptance Criteria

1. THE Web_Dashboard SHALL verify that the authenticated user has is_hod set to true before allowing access to HOD features
2. IF a user without is_hod attempts to access HOD features, THEN THE Web_Dashboard SHALL return a 403 forbidden error
3. THE Web_Dashboard SHALL check the is_hod flag on every request to HOD endpoints
4. THE Web_Dashboard SHALL use session-based authentication for web dashboard access
5. THE Web_Dashboard SHALL redirect unauthenticated users to the login page

### Requirement 29: Performance - Page Load Time

**User Story:** As an HOD, I want pages to load quickly, so that I can work efficiently.

#### Acceptance Criteria

1. THE Web_Dashboard SHALL load the department dashboard page within 2 seconds under normal network conditions
2. THE Web_Dashboard SHALL load the staff management page within 2 seconds under normal network conditions
3. THE Web_Dashboard SHALL use pagination to limit the number of records loaded per page
4. THE Web_Dashboard SHALL use database indexes on frequently queried fields (department_id, tenant_id, created_at)
5. THE Web_Dashboard SHALL cache department statistics for 5 minutes to reduce database load

### Requirement 30: Responsive Design for Web Dashboard

**User Story:** As an HOD, I want the web dashboard to work on different screen sizes, so that I can use it on various devices.

#### Acceptance Criteria

1. THE Web_Dashboard SHALL display correctly on desktop screens (1920x1080 and above)
2. THE Web_Dashboard SHALL display correctly on laptop screens (1366x768 and above)
3. THE Web_Dashboard SHALL display correctly on tablet screens (768x1024 and above)
4. THE Web_Dashboard SHALL use responsive CSS layouts that adapt to screen size
5. THE Web_Dashboard SHALL maintain usability and readability on all supported screen sizes
