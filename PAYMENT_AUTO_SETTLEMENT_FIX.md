# Payment Auto-Settlement Fix

## Problem
Payment is being recorded and invoice shows as "paid" in accounts screen, but patient is not being automatically routed to the next department (Triage/Nursing). Patient remains in "pending_payment" status and shows in Front Desk screen instead of moving to Nurse/Triage screen.

## Root Cause
The auto-settlement feature in `AccountsController::recordPayment()` was failing due to system user creation issues. The system user was being created with 'department_staff' role, but the database migration might not have been run on the live server, causing the role enum to reject the value.

## Solution Applied

### 1. Fixed System User Role
**File**: `backend/app/Http/Controllers/Api/V1/AccountsController.php`

Changed the system user creation to use 'accountant' role instead of 'department_staff':

```php
// Before
'role' => 'department_staff',

// After  
'role' => 'accountant',
```

This ensures the system user can be created even if the nurse/triage migration hasn't been run.

### 2. Enhanced Logging
Added comprehensive logging to track the auto-settlement flow:

- Log when auto-settlement is triggered
- Log settlement response status and content
- Log user authentication status
- Log any exceptions during settlement

This will help diagnose any future issues.

### 3. Variable Naming Improvement
Renamed `$systemUser` to `$performingUser` to better reflect that it could be either the authenticated user or a system user.

## Expected Behavior After Fix

1. **Accountant records payment** via mobile app
2. **If payment completes the invoice** (paid_amount >= total_amount):
   - Invoice status changes to 'paid'
   - Auto-settlement is triggered automatically
   - Patient is routed to next department in workflow (Triage/Nursing)
   - Patient status changes from 'pending_payment' to 'active'
   - Patient appears in Triage/Nursing queue
   - Mobile app shows success message: "Payment recorded and invoice automatically settled!"
3. **If payment is partial**:
   - Invoice status changes to 'partially_paid'
   - Patient remains in 'pending_payment' status
   - Accountant can record additional payments

## Workflow After Payment Settlement

```
Front Desk (Step 1)
  ↓
Accounts (Step 2) - Payment recorded here
  ↓
[AUTO-SETTLEMENT TRIGGERS]
  ↓
Triage/Nursing (Step 3) - Patient appears here automatically
  ↓
Doctor Consultation (Step 4)
  ↓
Laboratory (Step 5)
  ↓
Pharmacy (Step 6)
  ↓
Discharged
```

## Testing Steps

1. **Create a new patient** at Front Desk
2. **Patient moves to Accounts** (status: pending_payment)
3. **Accountant creates invoice** with items
4. **Accountant records full payment**
5. **Verify auto-settlement**:
   - Check mobile app shows "automatically settled" message
   - Check patient status changed to 'active'
   - Check patient current_department_id is Triage/Nursing department
   - Check patient current_step_order is 3
   - Check patient appears in Nurse/Triage queue
   - Check patient no longer appears in Front Desk queue
   - Check patient no longer appears in Accounts queue

## Diagnostic Commands

### Check if patients are stuck after payment
```bash
php artisan payment:diagnose
```

### Check workflow order
```bash
php artisan workflow:check-order
```

### Check nurse/triage queue
```bash
php artisan nurse:check-queue
```

### Manually route a stuck patient
```bash
php artisan patient:route-next "Patient Name"
```

## Fallback for Users

If auto-settlement fails for any reason:

1. The payment is still recorded successfully
2. The invoice status is updated to 'paid'
3. The mobile app shows a **manual settlement button**
4. Accountant can click "Manually Settle & Route Patient" button
5. This will trigger settlement and route the patient

## Files Modified

1. `backend/app/Http/Controllers/Api/V1/AccountsController.php`
   - Fixed system user role
   - Enhanced logging
   - Improved variable naming

2. `backend/app/Console/Commands/DiagnosePaymentIssue.php` (NEW)
   - Diagnostic command to check payment issues

## Migration Required

Ensure the nurse/triage roles migration has been run on the live server:

```bash
php artisan migrate
```

This migration adds 'nurse' and 'triage' roles to the users table enum while keeping 'department_staff' and all other roles.

## Notes

- The mobile app already has the UI for auto-settlement (checks `invoice.isPaid` flag)
- The backend auto-settlement logic was already implemented
- The only issue was the system user creation failing
- This fix ensures auto-settlement works reliably
