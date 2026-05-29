# Accounts Department Web Dashboard

This directory contains Vue.js 3 + Inertia.js pages for the Accounts Department web dashboard.

## Pages

### 1. Queue.vue
- **Route**: `/accounts/queue`
- **Purpose**: Displays all patient records with `pending_payment` status
- **Features**:
  - Patient list with name, patient number, and registration timestamp
  - Search and filter by date range
  - Quick access to create invoice for each patient
  - View existing invoices

### 2. CreateInvoice.vue
- **Route**: `/accounts/patient-records/{id}/create-invoice`
- **Purpose**: Create a new invoice for a patient record
- **Features**:
  - Patient information display
  - New patient vs. returning patient toggle
  - Dynamic invoice items (add/remove rows)
  - Service name dropdown with common services
  - Unit price, quantity, and auto-calculated subtotal
  - Real-time total amount calculation
  - Validation for required fees (registration + consultation for new patients)

### 3. InvoiceDetails.vue
- **Route**: `/accounts/invoices/{id}`
- **Purpose**: View invoice details and record payments
- **Features**:
  - Invoice information and status
  - Invoice items table with totals
  - Payment history with full details
  - Payment recording form with:
    - Payment type dropdown (cash, POS/card, HMO/insurance)
    - Amount input
    - Receipt reference
    - Conditional HMO fields (provider, policy, claim reference)
  - Settle invoice action (validates outstanding balance)
  - Receipt download button (PDF generation)

### 4. Invoices.vue
- **Route**: `/accounts/invoices`
- **Purpose**: List and search all invoices
- **Features**:
  - Searchable invoice list
  - Filter by status, date range
  - Quick view of invoice details
  - Pagination

## API Integration

All pages interact with the backend API endpoints:
- `GET /api/v1/accounts/queue` - Get pending payment records
- `POST /api/v1/patient-records/{id}/invoices` - Create invoice
- `GET /api/v1/patient-records/{id}/invoices` - List invoices for patient
- `POST /api/v1/invoices/{id}/payments` - Record payment
- `POST /api/v1/invoices/{id}/settle` - Settle invoice and route patient
- `GET /api/v1/invoices/{id}/receipt` - Download PDF receipt

## Navigation

The Accounts section is accessible from the Hospital Admin layout navigation menu under "Accounts".

## Styling

All pages use scoped CSS with a consistent design system matching the existing Hospital Admin dashboard:
- Color scheme: Primary blue (#007bff), success green (#28a745), danger red (#dc3545)
- Card-based layouts with shadows
- Responsive grid layouts
- Form controls with focus states
- Status badges with color coding
- Data tables with hover effects

## Requirements Validation

The implementation validates:
- **Requirement 16.1**: Routes pending_payment records to Accounts queue
- **Requirement 16.2**: Invoice creation with multiple items
- **Requirement 16.5**: Payment recording with type, amount, and HMO fields
- **Requirement 16.6**: Settlement validation and patient routing
- **Requirement 16.8**: Outstanding balance validation before settlement
- **Requirement 16.14**: Receipt generation and PDF export
