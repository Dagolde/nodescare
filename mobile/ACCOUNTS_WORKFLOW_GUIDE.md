# Accounts Department Workflow Guide

## User Journey

### 1. Accounts Queue Screen
**Entry Point**: Home screen → "Accounts Department" button

**What the user sees:**
- List of all patients with pending payment status
- Each card shows:
  - Patient name (with initial avatar)
  - Patient number
  - Registration date and time
  - Chief complaint

**Actions:**
- Pull down to refresh the queue
- Tap on any patient card to create invoice

---

### 2. Invoice Creation Screen
**Entry Point**: Tap patient card in Accounts Queue

**What the user sees:**
- Patient information card (name, number, chief complaint)
- Patient type selector: "New Patient" or "Returning"
- List of invoice items (initially one item)
- Total amount at bottom

**For each invoice item:**
- Service name dropdown (Registration Fee, Card Fee, Consultation Fee, Lab Test, etc.)
- Unit price input field (₦)
- Quantity input field
- Auto-calculated subtotal

**Actions:**
- Select patient type (New/Returning)
- Fill in service details for each item
- Tap "Add Item" to add more line items
- Tap delete icon to remove an item
- Tap "Create Invoice" to submit

**Validation:**
- New patients must have:
  - Registration/Card Fee
  - Consultation Fee
- Returning patients must have:
  - Consultation Fee
- All fields required for each item

---

### 3. Payment Recording Screen
**Entry Point**: Automatically after invoice creation

**What the user sees:**

**Invoice Summary Card:**
- Invoice number
- Patient name and number
- Total amount
- Amount paid
- Outstanding balance (highlighted)
- Status

**Invoice Items Card:**
- List of all services with quantities and amounts

**Payment Form:**
- Payment type dropdown:
  - Cash
  - POS/Card
  - HMO/Insurance
- Amount input (pre-filled with outstanding balance)
- Receipt/Transaction reference (optional)

**If HMO/Insurance selected:**
- HMO Provider Name (required)
- Policy/Member Number (required)
- Claim Reference (optional)

**Payment History Card:**
- Shows all previous payments with:
  - Payment type
  - Amount
  - Reference
  - Date and time

**Actions:**
1. **Record Payment:**
   - Select payment type
   - Enter amount
   - Fill in optional/required fields
   - Tap "Record Payment"
   - Invoice updates automatically
   - Can record multiple payments

2. **Settle Invoice** (only when balance = 0):
   - Tap "Settle Invoice & Route Patient"
   - Confirm in dialog
   - Patient routed to first clinical department
   - Returns to Accounts Queue

3. **Generate Receipt** (only for settled invoices):
   - Tap "Generate & Print Receipt"
   - PDF receipt opens in print dialog
   - Can print or save

---

## Screen Flow Diagram

```
Home Screen
    │
    ├─→ Accounts Department Button
    │
    ▼
Accounts Queue Screen
    │
    ├─→ Tap Patient Card
    │
    ▼
Invoice Creation Screen
    │
    ├─→ Fill invoice items
    ├─→ Create Invoice
    │
    ▼
Payment Recording Screen
    │
    ├─→ Record Payment(s) ──→ (Loop until balance = 0)
    │
    ├─→ Settle Invoice ──→ Confirmation Dialog ──→ Back to Queue
    │
    └─→ Generate Receipt ──→ Print Dialog
```

---

## Common Scenarios

### Scenario 1: New Patient - Full Cash Payment
1. Accountant opens Accounts Queue
2. Taps on new patient card
3. Selects "New Patient"
4. Adds items:
   - Registration Fee: ₦2,000
   - Consultation Fee: ₦5,000
5. Creates invoice (Total: ₦7,000)
6. Selects "Cash" payment type
7. Enters amount: ₦7,000
8. Records payment
9. Settles invoice
10. Patient routed to clinical department

### Scenario 2: Returning Patient - HMO Payment
1. Accountant opens Accounts Queue
2. Taps on returning patient card
3. Selects "Returning"
4. Adds item:
   - Consultation Fee: ₦5,000
5. Creates invoice (Total: ₦5,000)
6. Selects "HMO/Insurance" payment type
7. Enters amount: ₦5,000
8. Fills HMO details:
   - Provider: "NHIS"
   - Policy Number: "12345678"
   - Claim Reference: "CLM-2024-001"
9. Records payment
10. Settles invoice
11. Generates and prints receipt

### Scenario 3: Multiple Payments (Partial Payment)
1. Accountant creates invoice for ₦10,000
2. Patient pays ₦5,000 cash
3. Records first payment (Outstanding: ₦5,000)
4. Patient returns later with ₦5,000
5. Records second payment (Outstanding: ₦0)
6. Settles invoice
7. Patient routed to clinical department

### Scenario 4: Complex Invoice with Multiple Services
1. New patient requires multiple services
2. Accountant adds items:
   - Registration Fee: ₦2,000
   - Consultation Fee: ₦5,000
   - Lab Test: ₦3,000
   - X-Ray: ₦4,000
3. Creates invoice (Total: ₦14,000)
4. Records payment(s)
5. Settles and routes patient

---

## Error Handling

### Validation Errors
- **Missing required items**: "New patient invoice must include both registration/card fee and consultation fee"
- **Invalid amount**: "Please enter a valid amount"
- **Missing HMO details**: "HMO provider name and policy number are required for HMO payments"

### Business Logic Errors
- **Settle with balance**: "Cannot settle invoice with outstanding balance"
- **Already settled**: "Invoice is already settled"

### Network Errors
- **Connection failed**: "Error: Failed to fetch accounts queue"
- **Retry button** available on queue screen

---

## Tips for Accountants

1. **Patient Type Selection**
   - Always verify if patient is new or returning
   - New patients require registration fee
   - Returning patients only need consultation fee

2. **Service Selection**
   - Use dropdown for common services
   - Ensure correct unit prices
   - Double-check quantities

3. **Payment Recording**
   - Pre-filled amount is the outstanding balance
   - Can be changed if partial payment
   - Always get receipt reference for POS/Card payments
   - HMO details must match patient's insurance card

4. **Invoice Settlement**
   - Only settle when balance is exactly ₦0
   - Confirmation required - cannot be undone
   - Patient immediately routed to clinical department

5. **Receipt Generation**
   - Only available for settled invoices
   - Can be printed multiple times
   - Contains complete payment history

---

## Keyboard Shortcuts & Gestures

- **Pull down** on queue screen: Refresh
- **Tap** patient card: Open invoice creation
- **Tap** delete icon: Remove invoice item
- **Tap** "Add Item": Add new invoice item
- **Swipe back**: Return to previous screen

---

## Offline Behavior

When device is offline:
- Queue screen shows last cached data
- "Offline" indicator displayed
- Operations queued locally
- Auto-sync when connection restored
- Conflict resolution if needed

---

## Receipt Format

The generated PDF receipt includes:
- Hospital header
- Invoice number and dates
- Patient information
- Invoice items table
- Payment totals
- Payment history
- Footer with thank you message

---

## Support & Troubleshooting

### Common Issues

**Issue**: Cannot see patient in queue
- **Solution**: Pull down to refresh, check patient status

**Issue**: Cannot settle invoice
- **Solution**: Verify outstanding balance is ₦0

**Issue**: HMO fields not showing
- **Solution**: Select "HMO/Insurance" payment type

**Issue**: Receipt not generating
- **Solution**: Ensure invoice is settled first

**Issue**: Network error
- **Solution**: Check internet connection, tap retry button
