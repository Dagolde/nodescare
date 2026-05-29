# Accounts Module - Flutter Implementation

## Overview
This module implements the complete Accounts Department workflow for the Hospital Health Management System mobile app, including invoice creation, payment recording, invoice settlement, and receipt generation.

## Features Implemented

### 1. Accounts Queue Screen (`accounts_queue_screen.dart`)
- Displays all patient records with `pending_payment` status
- Shows patient name, patient number, registration timestamp, and chief complaint
- Pull-to-refresh functionality
- Navigation to invoice creation screen
- Error handling with retry capability

### 2. Invoice Creation Screen (`invoice_creation_screen.dart`)
- Patient information display
- Patient type selector (New Patient / Returning)
- Dynamic invoice items with add/remove functionality
- Service name dropdown with common services:
  - Registration Fee
  - Card Fee
  - Consultation Fee
  - Lab Test
  - X-Ray
  - Ultrasound
  - Pharmacy
  - Admission Fee
- Unit price and quantity inputs
- Auto-calculation of subtotals and total
- Validation for required items based on patient type:
  - New patients: Registration/Card Fee + Consultation Fee required
  - Returning patients: Consultation Fee required
- Real-time total calculation
- Navigation to payment recording after successful creation

### 3. Payment Recording Screen (`payment_recording_screen.dart`)
- Invoice summary display with all details
- Invoice items breakdown
- Payment type dropdown:
  - Cash
  - POS/Card
  - HMO/Insurance
- Conditional HMO fields (shown only for HMO payment type):
  - HMO Provider Name (required)
  - Policy/Member Number (required)
  - Claim Reference (optional)
- Amount input with validation
- Receipt/Transaction reference (optional)
- Outstanding balance validation before settlement
- Confirmation dialog for invoice settlement
- Payment history display
- Real-time invoice updates after payment recording
- Receipt generation and printing for settled invoices

### 4. Receipt Generator (`receipt_generator.dart`)
- PDF generation using `pdf` package
- Professional receipt layout with:
  - Hospital header
  - Invoice and patient details
  - Invoice items table
  - Payment totals
  - Payment history
  - Footer
- Print functionality using `printing` package
- Save to file capability

## Architecture

### Entities
- `Invoice`: Complete invoice model with items and payments
- `InvoiceItem`: Individual line items on invoices
- `InvoicePayment`: Payment transaction records
- `PatientRecord`: Patient visit/encounter records
- `Patient`: Patient demographic information
- `Department`: Hospital department information

### Repository Pattern
- `AccountsRepository`: Abstract interface
- `AccountsRepositoryImpl`: Concrete implementation using Dio HTTP client
- API endpoints:
  - `GET /api/v1/accounts/queue`
  - `POST /api/v1/patient-records/{id}/invoices`
  - `GET /api/v1/patient-records/{id}/invoices`
  - `POST /api/v1/invoices/{id}/payments`
  - `POST /api/v1/invoices/{id}/settle`
  - `GET /api/v1/invoices/{id}/receipt`

### State Management (Riverpod)
- `accountsQueueProvider`: FutureProvider for accounts queue
- `invoiceListProvider`: Family provider for invoice lists
- `invoiceCreationProvider`: StateNotifier for invoice creation
- `paymentRecordingProvider`: StateNotifier for payment recording
- `invoiceSettlementProvider`: StateNotifier for invoice settlement

## Dependencies Added
```yaml
pdf: ^3.10.7              # PDF generation
printing: ^5.12.0         # Print functionality
path_provider: ^2.1.2     # File system access
```

## API Integration
All screens integrate with the Laravel backend API endpoints implemented in Task 9.1. The implementation includes:
- Proper error handling with user-friendly messages
- Loading states during API calls
- Success/error notifications
- Automatic data refresh after mutations

## Validation Rules
1. **Invoice Creation**:
   - At least one invoice item required
   - New patients must have registration/card fee and consultation fee
   - Returning patients must have consultation fee
   - All fields (service name, unit price, quantity) required

2. **Payment Recording**:
   - Amount must be greater than 0
   - HMO provider name and policy number required for HMO payments
   - Receipt reference optional for all payment types

3. **Invoice Settlement**:
   - Outstanding balance must be 0
   - Confirmation dialog required
   - Routes patient to first clinical department after settlement

## Offline Support
The implementation is designed to work with the offline sync architecture:
- All API calls go through the RemoteDataSource
- Failed operations can be queued in the local sync_queue table
- Connectivity monitoring via `connectivity_plus` package (already in pubspec.yaml)

## Error Handling
- Network errors display user-friendly messages
- Validation errors shown inline on forms
- API errors extracted from response and displayed
- Retry functionality on queue screen
- Loading states prevent duplicate submissions

## UI/UX Features
- Material Design 3 components
- Responsive layouts
- Pull-to-refresh on queue screen
- Loading indicators during operations
- Success/error snackbar notifications
- Confirmation dialogs for critical actions
- Auto-calculation of totals
- Conditional field display based on payment type

## Testing Recommendations
1. Test invoice creation for both new and returning patients
2. Verify validation rules for required items
3. Test payment recording with all payment types
4. Verify HMO field validation
5. Test invoice settlement workflow
6. Verify receipt generation and printing
7. Test error scenarios (network failures, validation errors)
8. Test offline queue and sync functionality

## Future Enhancements
1. Barcode/QR code scanning for patient lookup
2. Payment method presets (common amounts)
3. Invoice templates for common service combinations
4. Receipt email/SMS delivery
5. Financial reports and analytics
6. Multi-currency support
7. Discount and adjustment support
8. Partial payment installment tracking
