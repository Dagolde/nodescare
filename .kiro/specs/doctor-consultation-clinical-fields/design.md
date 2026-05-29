# Design Document: Doctor Consultation Clinical Fields

## Overview

This feature adds two structured clinical text fields — **Complaint & History** and **Examination Findings** — to the doctor's consultation workflow. The fields appear before the existing Diagnosis text area in the Flutter mobile app, follow a logical clinical order (history → examination → diagnosis → plan), and are persisted to the `patient_journey_events.metadata` JSON column on the backend. They are also surfaced in the Journey tab of the patient detail screen.

The system is a multi-tenant Laravel + Flutter application. The backend exposes REST endpoints consumed by the mobile app via Riverpod providers. No new database columns or migrations are required — the existing `metadata` JSON column on `patient_journey_events` is used.

---

## Architecture

The change spans two layers:

```
Flutter Mobile App
  └── _ActionBar (patient_detail_screen.dart)
        ├── _showCompleteDialog  ──► POST /patient-records/{id}/complete
        ├── _showRouteDialog     ──► POST /patient-records/{id}/route
        └── _showAddNoteDialog   ──► POST /patient-records/{id}/notes
              │
              │  (new: complaint_history, examination_findings in body)
              ▼
Laravel Backend (DepartmentController)
  ├── addNote()   — validates + persists metadata
  ├── complete()  — validates + passes metadata to WorkflowService
  └── route()     — validates + passes metadata to journey event

PatientJourneyEvent.metadata (JSON)
  ├── complaint_history    (new key)
  ├── examination_findings (new key)
  ├── prescription_notes   (existing)
  └── investigation_notes  (existing)

Flutter Journey Tab
  └── _JourneyEventCard — reads metadata, renders new sections
```

The consultation dialogs in `_ActionBar` are the primary entry point. Each dialog is extended with the two new `TextFormField` widgets. The assembled note text and the raw field values are both sent to the backend — the note text for human-readable display in `event.notes`, and the raw values in `metadata` for structured retrieval.

---

## Components and Interfaces

### Flutter: `_ActionBar` — Consultation Dialogs

The three doctor action dialogs (`_showAddNoteDialog`, `_showCompleteDialog`, `_showRouteDialog`) each need a shared consultation form. Rather than duplicating the fields, a private `_ConsultationFormFields` widget encapsulates the two new fields plus the existing fields for each dialog type.

**New widget: `_ConsultationFormFields`**

```dart
class _ConsultationFormFields extends StatelessWidget {
  final TextEditingController complaintHistoryController;
  final TextEditingController examinationFindingsController;
  // ... existing field controllers passed through
}
```

Field order within each dialog form:
1. Complaint & History (`complaintHistoryController`)
2. Examination Findings (`examinationFindingsController`)
3. Diagnosis (existing, in complete/route dialogs)
4. Prescription / Medications (existing, in route-to-pharmacy dialog)
5. Investigations Requested (existing, in route-to-lab dialog)
6. Additional Notes (existing)

**Note assembly function** (pure, testable):

```dart
String assembleConsultationNote({
  String? complaintHistory,
  String? examinationFindings,
  String? diagnosis,
  String? prescriptionNotes,
  String? investigationNotes,
  String? additionalNotes,
}) {
  final parts = <String>[];
  if (complaintHistory != null && complaintHistory.trim().isNotEmpty)
    parts.add('Complaint & History: ${complaintHistory.trim()}');
  if (examinationFindings != null && examinationFindings.trim().isNotEmpty)
    parts.add('Examination Findings: ${examinationFindings.trim()}');
  if (diagnosis != null && diagnosis.trim().isNotEmpty)
    parts.add('Diagnosis: ${diagnosis.trim()}');
  if (prescriptionNotes != null && prescriptionNotes.trim().isNotEmpty)
    parts.add('Prescription: ${prescriptionNotes.trim()}');
  if (investigationNotes != null && investigationNotes.trim().isNotEmpty)
    parts.add('Investigations: ${investigationNotes.trim()}');
  if (additionalNotes != null && additionalNotes.trim().isNotEmpty)
    parts.add('Notes: ${additionalNotes.trim()}');
  return parts.join('\n\n');
}
```

This function is extracted to a standalone utility so it can be unit/property tested independently of the widget tree.

### Flutter: `_JourneyEventCard` — Display

The existing `_JourneyEventCard` in `patient_detail_screen.dart` already renders `event.notes`. It needs two additional conditional sections reading from `event.metadata`:

```dart
if (event.metadata?['complaint_history'] != null &&
    (event.metadata!['complaint_history'] as String).isNotEmpty) ...[
  const SizedBox(height: 6),
  _ClinicalSection(label: 'Complaint & History', text: event.metadata!['complaint_history']),
],
if (event.metadata?['examination_findings'] != null &&
    (event.metadata!['examination_findings'] as String).isNotEmpty) ...[
  const SizedBox(height: 6),
  _ClinicalSection(label: 'Examination Findings', text: event.metadata!['examination_findings']),
],
```

The `JourneyEvent` domain entity needs a `metadata` field added:

```dart
class JourneyEvent {
  // existing fields ...
  final Map<String, dynamic>? metadata; // new
}
```

### Backend: `DepartmentController`

Three methods are updated:

**`addNote()`** — add optional fields to validation and persist in metadata:

```php
$validator = Validator::make($request->all(), [
    'notes'               => 'required|string|max:5000',
    'complaint_history'   => 'nullable|string|max:5000',  // new
    'examination_findings'=> 'nullable|string|max:5000',  // new
]);

// In PatientJourneyEvent::create():
'metadata' => array_filter([
    'complaint_history'    => $request->input('complaint_history'),
    'examination_findings' => $request->input('examination_findings'),
]),
```

**`complete()`** — same optional fields added to validation; passed into `$metadata` array forwarded to `WorkflowService::routeToNextDepartment()`.

**`route()`** — same optional fields added to validation; merged into the existing `$metadata` array that is already stored on the journey event.

### Backend: `WorkflowService`

`routeToNextDepartment()` already accepts a `$metadata` array and stores it on the created `PatientJourneyEvent`. No structural change needed — the new keys flow through transparently.

---

## Data Models

### `PatientJourneyEvent.metadata` — new keys

| Key | Type | Max length | Required | Description |
|---|---|---|---|---|
| `complaint_history` | string | 5000 chars | No | Patient's presenting complaint and history |
| `examination_findings` | string | 5000 chars | No | Physical examination findings |

Existing keys (`prescription_notes`, `investigation_notes`, `notes`, `return_to_department_id`, `context`) are unchanged.

### Flutter `JourneyEvent` entity

```dart
class JourneyEvent {
  final String id;
  final String eventType;
  final String? notes;
  final String? fromDepartmentName;
  final String? toDepartmentName;
  final String? performedByName;
  final DateTime createdAt;
  final Map<String, dynamic>? metadata; // added
}
```

The `JourneyEvent.fromJson()` factory must be updated to parse the `metadata` field from the API response.

### API request bodies (updated)

**POST `/api/v1/patient-records/{id}/notes`**
```json
{
  "notes": "string (required, max 5000)",
  "complaint_history": "string (optional, max 5000)",
  "examination_findings": "string (optional, max 5000)"
}
```

**POST `/api/v1/patient-records/{id}/complete`**
```json
{
  "notes": "string (optional, max 5000)",
  "complaint_history": "string (optional, max 5000)",
  "examination_findings": "string (optional, max 5000)"
}
```

**POST `/api/v1/patient-records/{id}/route`**
```json
{
  "department_id": "uuid (required)",
  "notes": "string (optional, max 5000)",
  "prescription_notes": "string (optional, max 5000)",
  "investigation_notes": "string (optional, max 5000)",
  "complaint_history": "string (optional, max 5000)",
  "examination_findings": "string (optional, max 5000)"
}
```

---

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property Reflection

Before listing properties, redundancy is eliminated:

- 1.2 and 1.5 both describe the same thing (complaint text appears with its label in the assembled note) → merged into Property 1.
- 2.2 and 2.5 are the same for examination findings → merged into Property 2.
- 1.4 and 2.4 (empty fields omitted) are symmetric → merged into Property 3.
- 4.1, 4.2, and 4.4 (persistence across all three endpoints) → merged into Property 4 (one property covering all endpoints).
- 5.1 and 5.2 (card renders present fields) → merged into Property 5.
- 4.5 (validation rejects oversized strings) → Property 6.
- 5.3 (card omits absent fields) → Property 7.
- 6.1 (tenant_id set correctly) → Property 8.

---

### Property 1: Non-empty complaint history appears in assembled note

*For any* non-empty, non-whitespace `complaint_history` string, the note assembled by `assembleConsultationNote` must contain the substring `"Complaint & History: "` followed by the trimmed value of that string.

**Validates: Requirements 1.2, 1.5**

---

### Property 2: Non-empty examination findings appear in assembled note

*For any* non-empty, non-whitespace `examination_findings` string, the note assembled by `assembleConsultationNote` must contain the substring `"Examination Findings: "` followed by the trimmed value of that string.

**Validates: Requirements 2.2, 2.5**

---

### Property 3: Empty or whitespace-only fields are omitted from the assembled note

*For any* string composed entirely of whitespace (or the empty string), passing it as `complaint_history` or `examination_findings` to `assembleConsultationNote` must produce a note that does not contain `"Complaint & History:"` or `"Examination Findings:"` respectively.

**Validates: Requirements 1.4, 2.4**

---

### Property 4: Clinical fields are persisted in metadata for all consultation endpoints

*For any* non-empty `complaint_history` and/or `examination_findings` string submitted to any of the three consultation endpoints (`POST /notes`, `POST /complete`, `POST /route`), the resulting `PatientJourneyEvent` record's `metadata` JSON must contain those exact values under the keys `complaint_history` and `examination_findings`.

**Validates: Requirements 4.1, 4.2, 4.4**

---

### Property 5: Journey event card renders present clinical metadata fields

*For any* `JourneyEvent` whose `metadata` contains a non-empty `complaint_history` or `examination_findings` value, the rendered `_JourneyEventCard` widget must contain visible text displaying that value.

**Validates: Requirements 5.1, 5.2**

---

### Property 6: Oversized clinical fields are rejected with a validation error

*For any* string with length greater than 5000 characters submitted as `complaint_history` or `examination_findings` to any consultation endpoint, the backend must return HTTP 422 with error code `VALIDATION_ERROR`.

**Validates: Requirement 4.5**

---

### Property 7: Journey event card does not render absent clinical metadata sections

*For any* `JourneyEvent` whose `metadata` does not contain `complaint_history` or `examination_findings` (or contains them as null/empty), the rendered `_JourneyEventCard` must not display the labels `"Complaint & History:"` or `"Examination Findings:"`.

**Validates: Requirement 5.3**

---

### Property 8: Created journey events carry the current tenant ID

*For any* consultation submission that creates a `PatientJourneyEvent` with `complaint_history` or `examination_findings` metadata, the `tenant_id` on the created event must equal the tenant ID resolved from the request context.

**Validates: Requirement 6.1**

---

## Error Handling

| Scenario | Backend response | Mobile handling |
|---|---|---|
| `complaint_history` or `examination_findings` > 5000 chars | 422 `VALIDATION_ERROR` with field-level details | Show snackbar with validation message |
| Backend returns 422 on any consultation action | Existing error handling in `_executeAction` catches and shows error snackbar | No change needed |
| `metadata` key absent from journey event API response | `JourneyEvent.fromJson` treats missing `metadata` as `null`; card renders nothing for those sections | Graceful no-op |
| Network failure during consultation submission | Existing retry/error flow in `_executeAction` | No change needed |

The `_executeAction` helper in `_ActionBar` already wraps all provider calls and shows a `SnackBar` on failure. No new error handling infrastructure is needed.

---

## Testing Strategy

### Unit Tests (Dart)

- `assembleConsultationNote` with all combinations of present/absent fields
- `JourneyEvent.fromJson` correctly parses `metadata` field including `complaint_history` and `examination_findings`
- `_JourneyEventCard` widget test: renders clinical sections when metadata present, omits them when absent
- `_ActionBar` dialog widget tests: all six fields present in correct order, initial state is empty

### Property-Based Tests (Dart — `package:fast_check` or `package:glados`)

Each property test runs a minimum of 100 iterations.

- **Property 1** — `assembleConsultationNote` complaint label inclusion
  Tag: `Feature: doctor-consultation-clinical-fields, Property 1: non-empty complaint history appears in assembled note`

- **Property 2** — `assembleConsultationNote` examination findings label inclusion
  Tag: `Feature: doctor-consultation-clinical-fields, Property 2: non-empty examination findings appear in assembled note`

- **Property 3** — `assembleConsultationNote` whitespace/empty fields omitted
  Tag: `Feature: doctor-consultation-clinical-fields, Property 3: empty or whitespace-only fields are omitted from the assembled note`

- **Property 5** — `_JourneyEventCard` renders present metadata
  Tag: `Feature: doctor-consultation-clinical-fields, Property 5: journey event card renders present clinical metadata fields`

- **Property 7** — `_JourneyEventCard` omits absent metadata sections
  Tag: `Feature: doctor-consultation-clinical-fields, Property 7: journey event card does not render absent clinical metadata sections`

### Property-Based Tests (PHP — `eris` or `phpunit` with data providers for boundary coverage)

- **Property 4** — persistence across all three endpoints
  Tag: `Feature: doctor-consultation-clinical-fields, Property 4: clinical fields are persisted in metadata for all consultation endpoints`

- **Property 6** — oversized strings rejected
  Tag: `Feature: doctor-consultation-clinical-fields, Property 6: oversized clinical fields are rejected with a validation error`

- **Property 8** — tenant_id set correctly
  Tag: `Feature: doctor-consultation-clinical-fields, Property 8: created journey events carry the current tenant ID`

### Integration Tests (PHP — Laravel Feature Tests)

- `POST /patient-records/{id}/notes` with and without new fields — verify 200 and metadata persisted
- `POST /patient-records/{id}/complete` with new fields — verify metadata on resulting journey event
- `POST /patient-records/{id}/route` with new fields — verify metadata on resulting journey event
- `GET /patient-records/{id}/journey` — verify `metadata` is included in response and contains new keys
- Validation: all three endpoints return 422 for strings > 5000 chars
