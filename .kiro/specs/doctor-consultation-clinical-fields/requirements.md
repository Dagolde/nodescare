# Requirements Document

## Introduction

This feature adds two new clinical text fields — **Complaint & History** and **Examination Findings** — to the doctor's consultation screen in the Flutter mobile app. These fields appear before the existing Diagnosis text box, allowing doctors to document the patient's presenting complaint/history and physical examination findings as part of a structured consultation record. The data is persisted to the backend via the existing `patient_journey_events` metadata JSON column and is displayed when viewing patient records.

The system is a multi-tenant hospital management system with a Flutter mobile frontend and a Laravel REST API backend.

---

## Glossary

- **Consultation_Screen**: The `DoctorConsultationScreen` widget in the Flutter mobile app where a doctor records clinical findings and routes the patient.
- **Complaint_History**: The free-text field capturing the patient's presenting complaint and history of the complaint (e.g., "3-day history of fever and cough").
- **Examination_Findings**: The free-text field capturing physical examination findings (e.g., "Temperature 38.5°C, bilateral crepitations on auscultation").
- **Consultation_Note**: The structured text block assembled from all clinical fields and saved as a `note_added` journey event.
- **PatientJourneyEvent**: The backend model (`patient_journey_events` table) that stores clinical events with a `metadata` JSON column.
- **ClinicalController**: The Laravel controller (`ClinicalController.php`) that handles clinical API endpoints.
- **DepartmentController**: The Laravel controller (`DepartmentController.php`) that handles the `addNote` and `complete` endpoints used by the consultation screen.
- **Tenant**: A hospital instance in the multi-tenant architecture.

---

## Requirements

### Requirement 1: Complaint & History Field

**User Story:** As a doctor, I want to document the patient's presenting complaint and history of the complaint, so that the clinical record captures the reason for the visit in a structured way.

#### Acceptance Criteria

1. THE Consultation_Screen SHALL display a multi-line text area labelled "Complaint & History" positioned before the Diagnosis text area.
2. WHEN a doctor enters text in the Complaint & History field, THE Consultation_Screen SHALL include that text in the Consultation_Note submitted to the backend.
3. THE Consultation_Screen SHALL accept up to 5000 characters in the Complaint & History field.
4. IF the Complaint & History field is left empty, THEN THE Consultation_Screen SHALL submit the consultation without that field (it is optional).
5. WHEN the Consultation_Note is assembled, THE Consultation_Screen SHALL prefix the Complaint & History value with the label "Complaint & History:" in the note text.

---

### Requirement 2: Examination Findings Field

**User Story:** As a doctor, I want to document physical examination findings, so that the clinical record contains objective clinical data from the consultation.

#### Acceptance Criteria

1. THE Consultation_Screen SHALL display a multi-line text area labelled "Examination Findings" positioned after the Complaint & History field and before the Diagnosis text area.
2. WHEN a doctor enters text in the Examination Findings field, THE Consultation_Screen SHALL include that text in the Consultation_Note submitted to the backend.
3. THE Consultation_Screen SHALL accept up to 5000 characters in the Examination Findings field.
4. IF the Examination Findings field is left empty, THEN THE Consultation_Screen SHALL submit the consultation without that field (it is optional).
5. WHEN the Consultation_Note is assembled, THE Consultation_Screen SHALL prefix the Examination Findings value with the label "Examination Findings:" in the note text.

---

### Requirement 3: Field Ordering in the Consultation Form

**User Story:** As a doctor, I want the consultation form fields to follow a logical clinical order, so that I can complete the form in the same sequence I conduct a consultation.

#### Acceptance Criteria

1. THE Consultation_Screen SHALL render the consultation fields in the following order from top to bottom: Complaint & History, Examination Findings, Diagnosis, Prescription / Medications, Investigations Requested, Additional Notes.
2. WHEN the Consultation_Screen is first displayed, THE Consultation_Screen SHALL render all six fields empty with no pre-filled text.

---

### Requirement 4: Backend Persistence of New Fields

**User Story:** As a doctor, I want the complaint, history, and examination findings I enter to be saved to the patient record, so that other clinical staff can view the full consultation history.

#### Acceptance Criteria

1. WHEN a consultation is submitted with a non-empty Complaint & History value, THE DepartmentController SHALL persist that value in the `metadata` JSON column of the created `PatientJourneyEvent` under the key `complaint_history`.
2. WHEN a consultation is submitted with a non-empty Examination Findings value, THE DepartmentController SHALL persist that value in the `metadata` JSON column of the created `PatientJourneyEvent` under the key `examination_findings`.
3. THE DepartmentController SHALL accept `complaint_history` and `examination_findings` as optional string fields (max 5000 characters each) in the request body of the `POST /api/v1/patient-records/{id}/notes` endpoint.
4. WHEN a consultation is submitted via the `POST /api/v1/patient-records/{id}/complete` or `POST /api/v1/patient-records/{id}/route` endpoints, THE DepartmentController SHALL also accept and persist `complaint_history` and `examination_findings` in the metadata of the resulting journey event.
5. IF `complaint_history` or `examination_findings` exceed 5000 characters, THEN THE DepartmentController SHALL return a 422 Unprocessable Entity response with a `VALIDATION_ERROR` code.

---

### Requirement 5: Display of Saved Clinical Fields in Patient Record View

**User Story:** As a doctor or clinical staff member, I want to see the complaint, history, and examination findings when reviewing a patient's journey, so that I have the full clinical picture.

#### Acceptance Criteria

1. WHEN the Journey tab of the patient detail screen displays a `note_added` journey event that contains `complaint_history` in its metadata, THE _JourneyEventCard SHALL render the complaint and history text beneath the event header.
2. WHEN the Journey tab of the patient detail screen displays a `note_added` journey event that contains `examination_findings` in its metadata, THE _JourneyEventCard SHALL render the examination findings text beneath the event header.
3. WHEN a journey event contains neither `complaint_history` nor `examination_findings` in its metadata, THE _JourneyEventCard SHALL not render those sections (no empty labels).

---

### Requirement 6: Data Integrity Across Tenants

**User Story:** As a hospital administrator, I want clinical data to be scoped to the correct tenant, so that patient data from one hospital is never visible to another.

#### Acceptance Criteria

1. WHEN a `PatientJourneyEvent` is created with `complaint_history` or `examination_findings` metadata, THE DepartmentController SHALL set the `tenant_id` field to the currently resolved tenant's ID.
2. WHILE the `TenantScope` global scope is active, THE PatientJourneyEvent SHALL only return records matching the current tenant's ID.
