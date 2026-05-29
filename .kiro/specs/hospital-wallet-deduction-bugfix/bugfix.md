# Bugfix Requirements Document

## Introduction

The hospital wallet is not being deducted when the accounts department processes an invoice payment for a patient. The expected behavior is that when an invoice is paid (via the `recordPayment` endpoint), the hospital wallet should be automatically deducted based on the configured deduction rules (new patient card fee + consultation fee for new patients, or consultation fee only for returning patients). Currently, the wallet deduction only occurs when the `settleInvoice` endpoint is called, which is a separate operation that routes the patient to the first clinical department. This creates a scenario where invoices can be marked as paid without the corresponding wallet deduction occurring.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN the accounts department calls the `POST /api/v1/invoices/{id}/payments` endpoint to record a payment that brings the invoice to fully paid status THEN the system updates the invoice status to 'paid' but does NOT deduct from the hospital wallet

1.2 WHEN the accounts department calls the `POST /api/v1/invoices/{id}/payments` endpoint multiple times to record partial payments THEN the system updates the invoice status to 'partially_paid' or 'paid' but does NOT deduct from the hospital wallet at any point during payment recording

1.3 WHEN the `POST /api/v1/invoices/{id}/settle` endpoint is called THEN the system deducts from the hospital wallet, but this endpoint is intended for routing the patient to the first clinical department, not for payment processing

### Expected Behavior (Correct)

2.1 WHEN the accounts department calls the `POST /api/v1/invoices/{id}/payments` endpoint to record a payment that brings the invoice to fully paid status (paid_amount >= total_amount) THEN the system SHALL deduct from the hospital wallet based on the configured deduction rules (new patient card fee + consultation fee for new patients, or consultation fee only for returning patients)

2.2 WHEN the accounts department calls the `POST /api/v1/invoices/{id}/payments` endpoint to record a payment that brings the invoice to fully paid status THEN the system SHALL deduct from the hospital wallet exactly once per patient visit, regardless of how many payment transactions were recorded

2.3 WHEN the `POST /api/v1/invoices/{id}/settle` endpoint is called and the wallet deduction has already occurred during payment recording THEN the system SHALL NOT deduct from the hospital wallet again to prevent double deduction

### Unchanged Behavior (Regression Prevention)

3.1 WHEN the accounts department calls the `POST /api/v1/invoices/{id}/payments` endpoint to record a partial payment that does NOT bring the invoice to fully paid status (paid_amount < total_amount) THEN the system SHALL CONTINUE TO update the invoice status to 'partially_paid' without deducting from the hospital wallet

3.2 WHEN the `POST /api/v1/invoices/{id}/settle` endpoint is called THEN the system SHALL CONTINUE TO route the patient to the first clinical department as it currently does

3.3 WHEN wallet deduction fails due to any error (inactive wallet, configuration issues, etc.) THEN the system SHALL CONTINUE TO log the error as a warning and allow the invoice payment to proceed successfully

3.4 WHEN determining whether a patient is new or returning THEN the system SHALL CONTINUE TO count previous patient records for the same patient_id (excluding the current record) to determine the appropriate deduction amount

3.5 WHEN the wallet deduction occurs THEN the system SHALL CONTINUE TO use the tenant's plan_id to retrieve the appropriate WalletDeductionConfig and apply the configured fees
