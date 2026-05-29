# Implementation Plan: Pharmacy, Lab, and Admission Billing

## Overview

Implement three billing workflows (pharmacy, lab, admission) by adding a new `BillingController`, updating `AccountsController::getQueue` and `settleInvoice`, registering new routes, and updating the Flutter mobile app with billing providers, a reusable billing form screen, and updated action bar logic.

## Tasks

- [x] 1. Create `BillingController` with pharmacy and lab billing endpoints
  - Create `backend/app/Http/Controllers/Api/V1/BillingController.php` injecting `WorkflowService`
  - Implement `pharmacyBilling(string $id, Request $request)`: validate `items` array (non-empty, each item has `drug_name` string max 255, `unit_price` numeric > 0, `quantity` integer >= 1); reject with 409 if `PatientRecord.status == pending_payment`; find Accounts department by `type = 'accounts'`; update `PatientRecord` (`status = pending_payment`, `pending_payment_context = pharmacy_billing`, `return_to_department_id = user->department_id`); create `PatientJourneyEvent` (`event_type = routed`, `metadata = {pharmacy_bill_items: [...]}`) routing to Accounts; create `AuditLog` (`action = pharmacy_billing.submitted`); return updated record
  - Implement `labBilling(string $id, Request $request)`: symmetric to `pharmacyBilling` but validates `test_name` field, sets `pending_payment_context = lab_billing`, stores `lab_bill_items` in journey event metadata
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 4.1, 4.2, 4.3, 4.4, 4.5, 13.1, 13.2, 13.4_

  - [ ]* 1.1 Write property test for pharmacy billing PatientRecord field correctness
    - **Property 1: Pharmacy billing sets PatientRecord fields correctly**
    - **Validates: Requirements 1.1**

  - [ ]* 1.2 Write property test for billing validation rejection
    - **Property 2: Billing validation rejects invalid line items**
    - **Validates: Requirements 1.2, 1.3, 4.2, 4.3**

  - [ ]* 1.3 Write property test for bill items preserved in journey event metadata
    - **Property 3: Bill items are preserved in journey event metadata**
    - **Validates: Requirements 1.4, 4.4, 13.3**

  - [ ]* 1.4 Write property test for lab billing PatientRecord field correctness
    - **Property 4: Lab billing sets PatientRecord fields correctly**
    - **Validates: Requirements 4.1**

- [x] 2. Implement `BillingController::admit` and `confirmDispense` endpoints
  - Implement `admit(string $id, Request $request)`: validate `ward_department_id` (required UUID, must exist in tenant's departments — return 422 `DEPARTMENT_NOT_FOUND` if not); reject with 409 if already `pending_payment`; find Accounts department; update `PatientRecord` (`status = pending_payment`, `pending_payment_context = admission_billing`, `return_to_department_id = ward_department_id`); create `PatientJourneyEvent` (`event_type = routed`, `metadata = {admission_billing: true, ward_department_id: "..."}`) routing to Accounts; create `AuditLog` (`action = admission_billing.submitted`); return updated record
  - Implement `confirmDispense(string $id, Request $request)`: verify patient has a settled invoice with `pharmacy_billing` context (check `Invoice.status = paid` + journey event with `pharmacy_bill_items`); return 422 `DISPENSE_NOT_ALLOWED` if not; create `DispensingRecord` with `medications` derived from `pharmacy_bill_items`; create `PatientJourneyEvent` (`event_type = dispensed`); call `WorkflowService::routeToNextDepartment`; create `AuditLog` (`action = dispensing.confirmed`)
  - _Requirements: 3.2, 3.3, 3.4, 3.5, 7.1, 7.2, 7.3, 7.4, 7.5, 13.1, 13.2, 13.4_

  - [ ]* 2.1 Write property test for admission billing PatientRecord field correctness
    - **Property 9: Admission billing sets PatientRecord fields correctly**
    - **Validates: Requirements 7.1, 7.4**

  - [ ]* 2.2 Write property test for admission billing rejecting non-existent ward departments
    - **Property 10: Admission billing rejects non-existent ward departments**
    - **Validates: Requirements 7.2, 7.3**

  - [ ]* 2.3 Write property test for confirm dispense requiring a settled pharmacy invoice
    - **Property 7: Confirm dispense requires a settled pharmacy invoice**
    - **Validates: Requirements 3.3**

  - [ ]* 2.4 Write property test for confirm dispense producing all required records
    - **Property 8: Confirm dispense produces all required records**
    - **Validates: Requirements 3.2, 3.5**

- [x] 3. Register new routes and migrate existing `admit` route to `BillingController`
  - Add `use App\Http\Controllers\Api\V1\BillingController;` import to `backend/routes/api.php`
  - Inside the `patient-records` prefix group, add:
    - `Route::post('/{id}/pharmacy-billing', [BillingController::class, 'pharmacyBilling']);`
    - `Route::post('/{id}/lab-billing', [BillingController::class, 'labBilling']);`
    - `Route::post('/{id}/confirm-dispense', [BillingController::class, 'confirmDispense']);`
  - Replace the existing `Route::post('/{id}/admit', [DepartmentController::class, 'admit'])` with `Route::post('/{id}/admit', [BillingController::class, 'admit']);`
  - _Requirements: 1.5, 3.4, 4.5, 7.5_

- [x] 4. Update `AccountsController::getQueue` to include billing-specific metadata
  - Extend the `getQueue` map closure to handle the three new `pending_payment_context` values:
    - `pharmacy_billing`: set `payment_reason = 'Pharmacy/Drugs Payment'`; query most recent `PatientJourneyEvent` with `pharmacy_bill_items` in metadata; include `pharmacy_bill_items` array in response
    - `lab_billing`: set `payment_reason = 'Lab Investigation Payment'`; query most recent journey event with `lab_bill_items`; include `lab_bill_items` array in response
    - `admission_billing`: set `payment_reason = 'Admission Payment'`; query most recent journey event with `admission_billing` metadata; include `ward_department_id` in response
  - Ensure `payment_context` field is always included in each queue entry
  - _Requirements: 2.1, 5.1, 8.1, 9.1, 9.2, 9.3, 9.4, 9.5_

  - [ ]* 4.1 Write property test for accounts queue including correct bill items per context
    - **Property 12: Accounts queue includes correct bill items per context**
    - **Validates: Requirements 2.1, 5.1, 8.1, 9.1, 9.2, 9.3, 9.4, 9.5**

- [x] 5. Update `AccountsController::settleInvoice` for admission billing special case
  - Inside the existing `if ($returnToDeptId)` block, add a conditional branch keyed on `$paymentContext === 'admission_billing'`:
    - Set `PatientRecord.status = admitted` and `PatientRecord.admitted_at = now()` instead of `active`
    - Create `PatientJourneyEvent` with `event_type = admitted` (instead of `routed`)
    - Preserve `ward_department_id` in the journey event metadata
  - For `pharmacy_billing` and `lab_billing` contexts, extend the `$preservedMetadata` logic to also carry `pharmacy_bill_items` and `lab_bill_items` from the previous journey event (in addition to the existing `prescription_notes`/`investigation_notes` preservation)
  - _Requirements: 2.3, 2.4, 5.3, 5.4, 8.3, 8.4, 13.2_

  - [ ]* 5.1 Write property test for pharmacy billing round-trip routing back to Pharmacy
    - **Property 5: Pharmacy billing round-trip routes back to Pharmacy**
    - **Validates: Requirements 2.3, 2.4**

  - [ ]* 5.2 Write property test for lab billing round-trip routing back to Lab
    - **Property 6: Lab billing round-trip routes back to Lab**
    - **Validates: Requirements 5.3, 5.4**

  - [ ]* 5.3 Write property test for admission settlement routing to ward with admitted status
    - **Property 11: Admission settlement routes to ward with admitted status**
    - **Validates: Requirements 8.3, 8.4**

- [ ] 6. Checkpoint — Ensure all backend tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 7. Update `PatientRecord` Flutter entity to support new billing fields
  - Add fields to `PatientRecord` entity in `mobile/lib/domain/entities/patient_record.dart`:
    - `pharmacyBillItems` (`List<Map<String, dynamic>>?`) — parsed from `pharmacy_bill_items` in JSON
    - `labBillItems` (`List<Map<String, dynamic>>?`) — parsed from `lab_bill_items` in JSON
    - `wardDepartmentId` (`String?`) — parsed from `ward_department_id` in JSON
  - Update `PatientRecord.fromJson` to parse these new fields
  - _Requirements: 9.3, 9.4, 9.5, 12.1_

- [x] 8. Create `billing_provider.dart` with billing state notifiers
  - Create `mobile/lib/presentation/providers/billing_provider.dart`
  - Implement `PharmacyBillingNotifier extends StateNotifier<AsyncValue<PatientRecord?>>` with `submitPharmacyBilling(String patientRecordId, List<Map<String, dynamic>> items)` calling `POST /api/v1/patient-records/{id}/pharmacy-billing`
  - Implement `LabBillingNotifier extends StateNotifier<AsyncValue<PatientRecord?>>` with `submitLabBilling(String patientRecordId, List<Map<String, dynamic>> items)` calling `POST /api/v1/patient-records/{id}/lab-billing`
  - Implement `AdmissionBillingNotifier extends StateNotifier<AsyncValue<PatientRecord?>>` with `submitAdmission(String patientRecordId, String wardDepartmentId)` calling `POST /api/v1/patient-records/{id}/admit`
  - Implement `ConfirmDispenseNotifier extends StateNotifier<AsyncValue<PatientRecord?>>` with `confirmDispense(String patientRecordId, {String? notes})` calling `POST /api/v1/patient-records/{id}/confirm-dispense`
  - Register all four as `StateNotifierProvider`s
  - _Requirements: 10.1, 10.2, 10.4, 10.5, 11.1, 11.2, 11.4_

- [x] 9. Create `BillingFormScreen` for pharmacy and lab billing
  - Create `mobile/lib/presentation/screens/billing_form_screen.dart`
  - Define `enum BillingType { pharmacy, lab }` and `BillingFormScreen extends ConsumerStatefulWidget` accepting `patientRecordId` and `billingType`
  - Render a dynamic list of line items; each row has: name field (`drug_name` for pharmacy, `test_name` for lab), unit price field (numeric), quantity stepper (integer >= 1)
  - Add/remove row buttons; validate at least one item, all fields required, price > 0, quantity >= 1
  - Submit button calls `PharmacyBillingNotifier` or `LabBillingNotifier` based on `billingType`; on success show confirmation snackbar and pop screen; on error show error snackbar
  - _Requirements: 10.2, 10.3, 10.4, 11.2, 11.3, 11.4_

- [x] 10. Update `_ActionBar` in `patient_detail_screen.dart` with role-specific billing actions
  - Read the authenticated user's role from the auth provider (replace `_kDemoRole` constant with actual role from `authProvider`)
  - Pharmacist role: if `record.status != 'pending_payment'` and no settled pharmacy invoice detected (check `record.paymentContext != 'pharmacy_billing'` or `record.invoiceStatus != 'paid'`), show "Bill Drugs" button navigating to `BillingFormScreen(billingType: BillingType.pharmacy)`; if patient returned after pharmacy payment (detect via `record.paymentContext == 'pharmacy_billing'` and `record.invoiceStatus == 'paid'`), show "Confirm Dispense" button calling `ConfirmDispenseNotifier`
  - Lab Technician role: if no settled lab invoice, show "Bill Investigations" button navigating to `BillingFormScreen(billingType: BillingType.lab)`; if patient returned after lab payment, show existing lab result entry form
  - Doctor role: update existing "Admit" button to show a ward selection dialog (fetch departments list, filter by type or name containing "ward"), then call `AdmissionBillingNotifier.submitAdmission` with selected `wardDepartmentId`
  - _Requirements: 10.1, 10.5, 11.1, 11.5, 3.1_

- [x] 11. Update `AccountsQueueScreen` and `InvoiceCreationScreen` to display billing context
  - In `AccountsQueueScreen`, update each queue card to show a `paymentReason` badge (coloured chip: purple for pharmacy, blue for lab, green for admission) below the patient name
  - In `InvoiceCreationScreen`, update the patient info card to handle the three new `paymentContext` values (`pharmacy_billing`, `lab_billing`, `admission_billing`):
    - For `pharmacy_billing`: display `pharmacy_bill_items` as pre-populated line items (drug name as description, unit price, quantity) in the invoice items list
    - For `lab_billing`: display `lab_bill_items` as pre-populated line items (test name as description, unit price, quantity)
    - For `admission_billing`: display "Admission Payment" label; leave items list empty for accountant to fill
  - _Requirements: 2.2, 5.2, 8.2, 9.1, 9.2, 12.1, 12.2_

- [ ] 12. Write backend feature tests for BillingController and AccountsController updates
  - Create `backend/tests/Feature/Billing/BillingControllerTest.php`
  - Test `pharmacyBilling`: valid request sets PatientRecord fields and creates journey event with `pharmacy_bill_items`; empty items returns 422; invalid item fields return 422; already `pending_payment` returns 409
  - Test `labBilling`: symmetric to pharmacy tests
  - Test `admit`: valid `ward_department_id` sets PatientRecord fields; non-existent `ward_department_id` returns 422 `DEPARTMENT_NOT_FOUND`; missing `ward_department_id` returns 422
  - Test `confirmDispense`: patient with settled pharmacy invoice creates `DispensingRecord` + `dispensed` journey event; patient without settled invoice returns 422 `DISPENSE_NOT_ALLOWED`
  - Test `AccountsController::getQueue`: `pharmacy_billing` entry includes `pharmacy_bill_items`; `lab_billing` entry includes `lab_bill_items`; `admission_billing` entry includes `ward_department_id`
  - Test `AccountsController::settleInvoice`: `admission_billing` path sets `status = admitted` and creates `admitted` journey event
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 2.1, 3.2, 3.3, 4.1, 4.2, 4.3, 4.4, 5.1, 7.1, 7.2, 7.3, 8.3, 8.4, 9.3, 9.4, 9.5_

  - [ ]* 12.1 Write property test for all billing actions creating AuditLog entries with correct tenant scope
    - **Property 13: All billing actions create AuditLog entries with correct tenant scope**
    - **Validates: Requirements 13.1, 13.4**

- [ ] 13. Final checkpoint — Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- The existing `DepartmentController::admit` method is superseded by `BillingController::admit`; the old method can be removed or left in place but the route must point to `BillingController`
- The `settleInvoice` admission branch is a conditional inside the existing `if ($returnToDeptId)` block — no structural refactor needed
- Property tests use PestPHP with randomised inputs (minimum 100 iterations per property)
- All new records must carry `tenant_id = patientRecord->tenant_id` for multi-tenant isolation
