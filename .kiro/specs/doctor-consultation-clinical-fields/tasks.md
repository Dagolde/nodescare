# Implementation Plan: Doctor Consultation Clinical Fields

## Overview

Add "Complaint & History" and "Examination Findings" fields to the doctor consultation dialogs in the Flutter mobile app, persist them in `PatientJourneyEvent.metadata` on the Laravel backend, and display them in the Journey tab of the patient detail screen.

## Tasks

- [x] 1. Update `JourneyEvent` entity and `fromJson` to include metadata
  - Add `final Map<String, dynamic>? metadata` field to the `JourneyEvent` class in the domain entities file
  - Update `JourneyEvent.fromJson()` to parse the `metadata` key from the API response (treat missing key as `null`)
  - _Requirements: 5.1, 5.2, 5.3_

  - [ ]* 1.1 Write unit tests for `JourneyEvent.fromJson` metadata parsing
    - Test that `metadata` is parsed correctly when present with `complaint_history` and `examination_findings`
    - Test that `metadata` is `null` when the key is absent from the JSON
    - _Requirements: 5.1, 5.2, 5.3_

- [x] 2. Implement `assembleConsultationNote` utility function
  - Create a standalone Dart function `assembleConsultationNote` (e.g. in `mobile/lib/presentation/utils/consultation_utils.dart`) with the signature from the design
  - The function joins non-empty, non-whitespace fields with `\n\n`, prefixing each with its label
  - _Requirements: 1.2, 1.5, 2.2, 2.5, 1.4, 2.4_

  - [ ]* 2.1 Write property test for Property 1 — non-empty complaint history appears in assembled note
    - **Property 1: Non-empty complaint history appears in assembled note**
    - **Validates: Requirements 1.2, 1.5**

  - [ ]* 2.2 Write property test for Property 2 — non-empty examination findings appear in assembled note
    - **Property 2: Non-empty examination findings appear in assembled note**
    - **Validates: Requirements 2.2, 2.5**

  - [ ]* 2.3 Write property test for Property 3 — empty or whitespace-only fields are omitted
    - **Property 3: Empty or whitespace-only fields are omitted from the assembled note**
    - **Validates: Requirements 1.4, 2.4**

- [x] 3. Update `_JourneyEventCard` to render clinical metadata sections
  - In `patient_detail_screen.dart`, add two conditional sections inside `_JourneyEventCard.build()` that read `event.metadata?['complaint_history']` and `event.metadata?['examination_findings']`
  - Render a `_ClinicalSection` widget (label + text) for each non-null, non-empty value; render nothing when absent
  - _Requirements: 5.1, 5.2, 5.3_

  - [ ]* 3.1 Write property test for Property 5 — card renders present clinical metadata fields
    - **Property 5: Journey event card renders present clinical metadata fields**
    - **Validates: Requirements 5.1, 5.2**

  - [ ]* 3.2 Write property test for Property 7 — card does not render absent clinical metadata sections
    - **Property 7: Journey event card does not render absent clinical metadata sections**
    - **Validates: Requirement 5.3**

- [ ] 4. Checkpoint — Ensure all Flutter unit and property tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 5. Extend consultation dialogs in `_ActionBar` with the two new fields
  - Create a private `_ConsultationFormFields` widget in `patient_detail_screen.dart` that encapsulates `TextFormField` widgets for "Complaint & History" and "Examination Findings" (multi-line, max 5000 chars each)
  - Update `_showAddNoteDialog` to include `_ConsultationFormFields` above the existing notes field; assemble the note using `assembleConsultationNote` and pass `complaint_history` / `examination_findings` in the request body
  - Update `_showCompleteDialog` to include `_ConsultationFormFields`; pass the new fields in the complete request body
  - Update `_showRouteDialog` to include `_ConsultationFormFields` above the existing destination-specific field; pass the new fields in the route request body
  - Field order in every dialog: Complaint & History → Examination Findings → Diagnosis/Prescription/Investigation → Additional Notes
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 2.1, 2.2, 2.3, 2.4, 3.1, 3.2_

  - [ ]* 5.1 Write widget tests for consultation dialogs
    - Test that all six fields are present in the correct order in each dialog
    - Test that initial state of all fields is empty
    - _Requirements: 3.1, 3.2_

- [x] 6. Update backend `addNote()` in `DepartmentController`
  - Add `'complaint_history' => 'nullable|string|max:5000'` and `'examination_findings' => 'nullable|string|max:5000'` to the validator in `addNote()`
  - In the `PatientJourneyEvent::create()` call, add a `metadata` key built with `array_filter(['complaint_history' => ..., 'examination_findings' => ...])`
  - _Requirements: 4.1, 4.2, 4.3, 4.5_

- [x] 7. Update backend `complete()` in `DepartmentController`
  - Add the same two optional validation rules to `complete()`
  - Merge `complaint_history` and `examination_findings` into the `$metadata` array that is forwarded to `WorkflowService::routeToNextDepartment()`
  - _Requirements: 4.4, 4.5_

- [x] 8. Update backend `route()` in `DepartmentController`
  - Add the same two optional validation rules to `route()`
  - Merge `complaint_history` and `examination_findings` into the existing `$metadata` array stored on the journey event
  - _Requirements: 4.4, 4.5_

- [x] 9. Verify `GET /patient-records/{id}/journey` returns `metadata` in the response
  - Confirm that `PatientJourneyEvent` is serialised with its `metadata` column in the `getJourneyHistory()` response (the model already casts `metadata` to array; verify it is not hidden)
  - If `metadata` is not included in the JSON output, add it to the resource/serialisation layer
  - _Requirements: 5.1, 5.2_

  - [ ]* 9.1 Write property test for Property 4 — clinical fields persisted in metadata for all endpoints
    - **Property 4: Clinical fields are persisted in metadata for all consultation endpoints**
    - **Validates: Requirements 4.1, 4.2, 4.4**

  - [ ]* 9.2 Write property test for Property 6 — oversized clinical fields are rejected
    - **Property 6: Oversized clinical fields are rejected with a validation error**
    - **Validates: Requirement 4.5**

  - [ ]* 9.3 Write property test for Property 8 — created journey events carry the current tenant ID
    - **Property 8: Created journey events carry the current tenant ID**
    - **Validates: Requirement 6.1**

  - [ ]* 9.4 Write Laravel feature integration tests
    - `POST /notes` with and without new fields — verify 200 and metadata persisted
    - `POST /complete` with new fields — verify metadata on resulting journey event
    - `POST /route` with new fields — verify metadata on resulting journey event
    - `GET /journey` — verify `metadata` key present in response with new fields
    - All three endpoints return 422 for strings > 5000 chars
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 6.1_

- [ ] 10. Final checkpoint — Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- No database migrations are required — the `metadata` JSON column on `patient_journey_events` already exists
- `WorkflowService::routeToNextDepartment()` already forwards the `$metadata` array transparently; no changes needed there
- Property tests use `package:glados` (Dart) and PHPUnit data providers / `eris` (PHP)
- Each task references specific requirements for traceability
