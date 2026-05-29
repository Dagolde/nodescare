# Requirements Document

## Introduction

This feature covers three billing workflows in the hospital management system: Pharmacy Billing, Lab Investigation Billing, and Admission Billing. Each workflow follows a common pattern: a clinical staff member costs and bills items for a patient, the system routes the patient to Accounts for payment, Accounts creates and settles an invoice, and after payment the patient is routed back to the originating department (or to the ward for admissions). The system is a multi-tenant Laravel backend with a Flutter mobile app using Riverpod providers.

The existing system already supports `pending_payment` status, `return_to_department_id`, and `pending_payment_context` on `PatientRecord`, and the `AccountsController` already handles invoice creation, payment recording, and settlement with routing. This feature formalises and extends those flows with explicit billing steps for pharmacy, lab, and admission.

## Glossary

- **Billing_System**: The combined backend and mobile app components responsible for creating, paying, and settling invoices.
- **Pharmacist**: A hospital staff member with role `pharmacist` who dispenses drugs.
- **Lab_Technician**: A hospital staff member with role `lab_technician` who performs investigations.
- **Doctor**: A hospital staff member with role `doctor` who consults patients and may book admissions.
- **Accountant**: A hospital staff member with role `accountant` who processes payments.
- **Front_Desk_Officer**: A hospital staff member with role `front_desk_officer` who monitors patient status.
- **PatientRecord**: The active visit record for a patient, carrying `status`, `current_department_id`, `return_to_department_id`, and `pending_payment_context`.
- **Invoice**: A billing document linked to a `PatientRecord` containing one or more line items.
- **InvoiceItem**: A single line on an Invoice with a description, unit price, and quantity.
- **WorkflowService**: The backend service responsible for routing patients between departments.
- **pending_payment**: A `PatientRecord` status indicating the patient is waiting at Accounts for payment.
- **pharmacy_billing**: The value of `pending_payment_context` used when a patient is sent to Accounts for drug payment.
- **lab_billing**: The value of `pending_payment_context` used when a patient is sent to Accounts for investigation payment.
- **admission_billing**: The value of `pending_payment_context` used when a patient is sent to Accounts for admission payment.
- **Dispensing_Confirmation**: The explicit action by a Pharmacist confirming that drugs have been physically handed to the patient.
- **Ward**: The hospital department to which a patient is routed after admission payment is settled.

---

## Requirements

### Requirement 1: Pharmacy Billing — Cost and Bill Drugs

**User Story:** As a Pharmacist, I want to enter drug names and prices for a patient and send them to Accounts for payment, so that drugs are billed before they are dispensed.

#### Acceptance Criteria

1. WHEN a Pharmacist submits a billing request for a patient record with at least one drug line item (name and unit price), THE Billing_System SHALL set the `PatientRecord` status to `pending_payment`, set `pending_payment_context` to `pharmacy_billing`, set `return_to_department_id` to the Pharmacy department ID, and route the patient to the Accounts department.
2. THE Billing_System SHALL require each drug line item to include a non-empty drug name (string, max 255 characters), a unit price greater than zero, and a quantity of at least 1.
3. IF a Pharmacist submits a billing request with no line items or with a line item missing a required field, THEN THE Billing_System SHALL return a `VALIDATION_ERROR` response and SHALL NOT modify the `PatientRecord`.
4. WHEN the pharmacy billing request is accepted, THE Billing_System SHALL record a `PatientJourneyEvent` of type `routed` with `metadata` containing the drug line items as `pharmacy_bill_items`.
5. THE Billing_System SHALL expose a `POST /api/v1/patient-records/{id}/pharmacy-billing` endpoint accessible to users with role `pharmacist`.

---

### Requirement 2: Pharmacy Billing — Accounts Creates and Settles Invoice

**User Story:** As an Accountant, I want to see pharmacy-billed patients in my queue with their drug items, create an invoice, and settle it after payment, so that the patient is returned to Pharmacy.

#### Acceptance Criteria

1. WHEN an Accountant fetches the accounts queue, THE Billing_System SHALL include all `PatientRecord` entries with `pending_payment_context` of `pharmacy_billing` and SHALL include the drug line items from the journey event metadata in the response.
2. WHEN an Accountant creates an invoice for a pharmacy-billed patient record, THE Billing_System SHALL pre-populate the invoice items from the `pharmacy_bill_items` stored in the journey event metadata, while still allowing the Accountant to adjust items before submission.
3. WHEN an invoice for a pharmacy-billed patient is fully paid and settled, THE Billing_System SHALL set `PatientRecord` status to `active`, clear `pending_payment_context` and `return_to_department_id`, and route the patient back to the Pharmacy department.
4. WHEN the patient is routed back to Pharmacy after payment, THE Billing_System SHALL record a `PatientJourneyEvent` of type `routed` preserving the `pharmacy_bill_items` metadata.

---

### Requirement 3: Pharmacy Billing — Confirm Dispense

**User Story:** As a Pharmacist, I want to see a "Confirm Dispense" button for patients who have paid, so that I can confirm drugs have been physically handed to the patient before they leave Pharmacy.

#### Acceptance Criteria

1. WHEN a patient with `pending_payment_context` previously set to `pharmacy_billing` is returned to the Pharmacy department with status `active`, THE Billing_System SHALL present a "Confirm Dispense" action for that patient in the Pharmacist's queue.
2. WHEN a Pharmacist submits a Confirm Dispense action for a patient record, THE Billing_System SHALL record a `DispensingRecord` for that patient, create a `PatientJourneyEvent` of type `dispensed`, and route the patient to the next department in the workflow.
3. IF a Pharmacist attempts to confirm dispense for a patient record that has no settled invoice with `pharmacy_billing` context, THEN THE Billing_System SHALL return a `DISPENSE_NOT_ALLOWED` error and SHALL NOT route the patient.
4. THE Billing_System SHALL expose a `POST /api/v1/patient-records/{id}/confirm-dispense` endpoint accessible to users with role `pharmacist`.
5. WHEN dispense is confirmed, THE Billing_System SHALL route the patient to the next step in the workflow (e.g., Front Desk), and the Front Desk SHALL see the patient with a status of `active` or `completed` indicating treatment is complete.

---

### Requirement 4: Lab Investigation Billing — Cost and Bill Investigations

**User Story:** As a Lab Technician, I want to enter investigation names and prices for a patient and send them to Accounts for payment, so that tests are billed before they are performed.

#### Acceptance Criteria

1. WHEN a Lab Technician submits a billing request for a patient record with at least one investigation line item (test name and unit price), THE Billing_System SHALL set the `PatientRecord` status to `pending_payment`, set `pending_payment_context` to `lab_billing`, set `return_to_department_id` to the Lab department ID, and route the patient to the Accounts department.
2. THE Billing_System SHALL require each investigation line item to include a non-empty test name (string, max 255 characters), a unit price greater than zero, and a quantity of at least 1.
3. IF a Lab Technician submits a billing request with no line items or with a line item missing a required field, THEN THE Billing_System SHALL return a `VALIDATION_ERROR` response and SHALL NOT modify the `PatientRecord`.
4. WHEN the lab billing request is accepted, THE Billing_System SHALL record a `PatientJourneyEvent` of type `routed` with `metadata` containing the investigation line items as `lab_bill_items`.
5. THE Billing_System SHALL expose a `POST /api/v1/patient-records/{id}/lab-billing` endpoint accessible to users with role `lab_technician`.

---

### Requirement 5: Lab Investigation Billing — Accounts Creates and Settles Invoice

**User Story:** As an Accountant, I want to see lab-billed patients in my queue with their investigation items, create an invoice, and settle it after payment, so that the patient is returned to the Lab.

#### Acceptance Criteria

1. WHEN an Accountant fetches the accounts queue, THE Billing_System SHALL include all `PatientRecord` entries with `pending_payment_context` of `lab_billing` and SHALL include the investigation line items from the journey event metadata in the response.
2. WHEN an Accountant creates an invoice for a lab-billed patient record, THE Billing_System SHALL pre-populate the invoice items from the `lab_bill_items` stored in the journey event metadata, while still allowing the Accountant to adjust items before submission.
3. WHEN an invoice for a lab-billed patient is fully paid and settled, THE Billing_System SHALL set `PatientRecord` status to `active`, clear `pending_payment_context` and `return_to_department_id`, and route the patient back to the Lab department.
4. WHEN the patient is routed back to the Lab after payment, THE Billing_System SHALL record a `PatientJourneyEvent` of type `routed` preserving the `lab_bill_items` metadata.

---

### Requirement 6: Lab Investigation Billing — Perform Tests and Enter Results

**User Story:** As a Lab Technician, I want to enter test results for a patient who has returned after payment, so that results are recorded and the patient continues their workflow.

#### Acceptance Criteria

1. WHEN a patient with `pending_payment_context` previously set to `lab_billing` is returned to the Lab department with status `active`, THE Billing_System SHALL present the lab result entry form for that patient in the Lab Technician's queue.
2. WHEN a Lab Technician submits results for a patient record, THE Billing_System SHALL record a `LabResult` entry and create a `PatientJourneyEvent` of type `note_added` with the test name and result.
3. WHEN lab results are submitted, THE Billing_System SHALL route the patient to the originating doctor's department (determined from the journey event that sent the patient to the Lab).
4. IF no originating department can be determined from the journey history, THEN THE Billing_System SHALL log a warning and SHALL NOT block the result submission.

---

### Requirement 7: Admission Billing — Route to Accounts After Admit

**User Story:** As a Doctor, I want the system to automatically route a patient to Accounts for admission payment when I click "Admit", so that admission fees are collected before the patient is sent to the ward.

#### Acceptance Criteria

1. WHEN a Doctor submits an Admit action for a patient record, THE Billing_System SHALL set the `PatientRecord` status to `pending_payment`, set `pending_payment_context` to `admission_billing`, set `return_to_department_id` to the Ward department ID, and route the patient to the Accounts department.
2. THE Billing_System SHALL require the Admit request to include a `ward_department_id` (UUID of the target ward department) that exists in the tenant's departments.
3. IF the specified `ward_department_id` does not exist for the tenant, THEN THE Billing_System SHALL return a `DEPARTMENT_NOT_FOUND` error and SHALL NOT modify the `PatientRecord`.
4. WHEN the admission billing request is accepted, THE Billing_System SHALL record a `PatientJourneyEvent` of type `routed` with `metadata` containing `admission_billing` context and the target ward department ID.
5. THE Billing_System SHALL expose a `POST /api/v1/patient-records/{id}/admit` endpoint accessible to users with role `doctor`.

---

### Requirement 8: Admission Billing — Accounts Creates and Settles Invoice

**User Story:** As an Accountant, I want to see admission-billed patients in my queue, create an admission invoice, and settle it after payment, so that the patient is routed to the ward.

#### Acceptance Criteria

1. WHEN an Accountant fetches the accounts queue, THE Billing_System SHALL include all `PatientRecord` entries with `pending_payment_context` of `admission_billing`.
2. WHEN an Accountant creates an invoice for an admission-billed patient record, THE Billing_System SHALL accept invoice items describing the admission charges (e.g., bed fee, admission fee).
3. WHEN an invoice for an admission-billed patient is fully paid and settled, THE Billing_System SHALL set `PatientRecord` status to `admitted`, clear `pending_payment_context` and `return_to_department_id`, and route the patient to the Ward department stored in `return_to_department_id`.
4. WHEN the patient is routed to the Ward after admission payment, THE Billing_System SHALL record a `PatientJourneyEvent` of type `admitted` with the ward department as the destination.
5. WHILE a `PatientRecord` has status `admitted`, THE Billing_System SHALL display the patient as admitted in the ward department's queue.

---

### Requirement 9: Accounts Queue — Payment Context Display

**User Story:** As an Accountant, I want to see the payment reason and relevant clinical items for each patient in my queue, so that I can create accurate invoices without needing to look up other records.

#### Acceptance Criteria

1. THE Billing_System SHALL include a `payment_context` field in each accounts queue entry with one of the values: `pharmacy_billing`, `lab_billing`, or `admission_billing`.
2. THE Billing_System SHALL include a human-readable `payment_reason` label in each accounts queue entry (e.g., "Pharmacy/Drugs Payment", "Lab Investigation Payment", "Admission Payment").
3. WHERE `payment_context` is `pharmacy_billing`, THE Billing_System SHALL include the `pharmacy_bill_items` array in the queue entry.
4. WHERE `payment_context` is `lab_billing`, THE Billing_System SHALL include the `lab_bill_items` array in the queue entry.
5. WHERE `payment_context` is `admission_billing`, THE Billing_System SHALL include the `ward_department_id` in the queue entry metadata.

---

### Requirement 10: Mobile App — Pharmacist Billing UI

**User Story:** As a Pharmacist using the mobile app, I want a billing form to enter drug names and prices, so that I can send the patient to Accounts without leaving the app.

#### Acceptance Criteria

1. WHEN a Pharmacist views a patient in their queue, THE Billing_System SHALL display a "Bill Drugs" action button on the patient detail screen.
2. WHEN a Pharmacist taps "Bill Drugs", THE Billing_System SHALL present a form allowing the Pharmacist to add one or more drug line items, each with a drug name, unit price, and quantity.
3. THE Billing_System SHALL require at least one line item before the form can be submitted.
4. WHEN the billing form is submitted successfully, THE Billing_System SHALL remove the patient from the Pharmacist's queue and display a confirmation message.
5. WHEN a patient returns to the Pharmacy queue after payment, THE Billing_System SHALL display a "Confirm Dispense" button instead of the "Bill Drugs" button for that patient.

---

### Requirement 11: Mobile App — Lab Technician Billing UI

**User Story:** As a Lab Technician using the mobile app, I want a billing form to enter investigation names and prices, so that I can send the patient to Accounts before running tests.

#### Acceptance Criteria

1. WHEN a Lab Technician views a patient in their queue, THE Billing_System SHALL display a "Bill Investigations" action button on the patient detail screen.
2. WHEN a Lab Technician taps "Bill Investigations", THE Billing_System SHALL present a form allowing the Technician to add one or more investigation line items, each with a test name, unit price, and quantity.
3. THE Billing_System SHALL require at least one line item before the form can be submitted.
4. WHEN the billing form is submitted successfully, THE Billing_System SHALL remove the patient from the Lab Technician's queue and display a confirmation message.
5. WHEN a patient returns to the Lab queue after payment, THE Billing_System SHALL display the existing lab result entry form for that patient.

---

### Requirement 12: Mobile App — Accounts Invoice and Payment UI

**User Story:** As an Accountant using the mobile app, I want to create an invoice pre-populated with billed items and record payment in one flow, so that settlement is fast and accurate.

#### Acceptance Criteria

1. WHEN an Accountant views a patient in the accounts queue, THE Billing_System SHALL display the payment context, payment reason, and pre-populated bill items (drugs or investigations) on the patient detail screen.
2. WHEN an Accountant taps "Create Invoice", THE Billing_System SHALL pre-fill the invoice form with the bill items from the queue entry, allowing the Accountant to edit quantities and prices before confirming.
3. WHEN an Accountant taps "Record Payment" on an existing invoice, THE Billing_System SHALL present a payment form with fields for amount, payment method (cash, card, transfer, HMO, other), and optional reference.
4. WHEN payment is recorded and the invoice becomes fully paid, THE Billing_System SHALL automatically settle the invoice and display a confirmation showing the department the patient has been routed to.
5. IF invoice creation or payment recording fails, THE Billing_System SHALL display a descriptive error message and SHALL NOT navigate away from the current screen.

---

### Requirement 13: Audit and Journey Integrity

**User Story:** As a Hospital Administrator, I want every billing routing action to be recorded in the audit log and patient journey, so that the full billing trail is traceable.

#### Acceptance Criteria

1. THE Billing_System SHALL create an `AuditLog` entry for every pharmacy billing request, lab billing request, admit action, invoice creation, payment recording, invoice settlement, and dispense confirmation.
2. THE Billing_System SHALL create a `PatientJourneyEvent` for every routing action triggered by a billing workflow, including the `from_department_id`, `to_department_id`, and relevant billing metadata.
3. WHEN a patient is routed as part of a billing workflow, THE Billing_System SHALL preserve the bill items metadata in the journey event so that the Accountant can retrieve them from the queue.
4. THE Billing_System SHALL scope all billing records (invoices, journey events, audit logs) to the correct `tenant_id` for multi-tenancy isolation.
