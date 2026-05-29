# Investigation Notes Debugging Guide

## Issue
Mobile app shows error: "Could not load test type - Exception: Failed to fetch investigation notes"

## Root Cause Analysis

The investigation notes flow through multiple steps:
1. **Doctor routes patient to Lab** → Investigation notes stored in journey event metadata
2. **Patient goes to Accounts** → Investigation notes should be preserved
3. **Payment completed** → Patient routed to Lab with notes preserved
4. **Lab technician opens screen** → Should see investigation notes

## Potential Issues

### 1. Investigation Notes Not Being Stored Initially
**When**: Doctor routes patient to lab
**Where**: `DepartmentController::route()` method
**Check**: Journey event metadata should contain `investigation_notes` key

### 2. Investigation Notes Not Preserved During Settlement
**When**: Accountant settles invoice after payment
**Where**: `AccountsController::settleInvoice()` method
**Check**: New journey event when routing from Accounts to Lab should preserve metadata

### 3. API Endpoint Not Finding Notes
**When**: Lab technician opens lab results screen
**Where**: `DepartmentController::getInvestigation()` method
**Check**: Query for journey events with `metadata->investigation_notes` returns results

## Diagnostic Steps

### Step 1: Check if Investigation Notes Are Being Stored

Run the diagnostic command:
```bash
php artisan check:investigation-notes
```

This will:
- List recent patient records
- Show all journey events with metadata
- Check specifically for investigation notes

### Step 2: Check Laravel Logs

The `getInvestigation` method now has comprehensive logging. Check:
```bash
tail -f storage/logs/laravel.log | grep investigation
```

Look for:
- `getInvestigation called` - API endpoint was hit
- `Patient record found` - Patient record exists
- `No investigation notes found` - No journey events with investigation_notes
- `All journey events with metadata` - Shows what metadata exists
- `Investigation notes found` - Success case

### Step 3: Test the Complete Workflow

1. **Doctor routes patient to lab with investigation notes**:
   ```
   POST /api/v1/patient-records/{id}/route
   {
     "department_id": "{lab_department_id}",
     "investigation_notes": "Full Blood Count, Malaria Test",
     "requires_payment": true
   }
   ```

2. **Check journey event was created**:
   ```bash
   php artisan check:investigation-notes {patient_record_id}
   ```

3. **Accountant creates invoice and records payment**:
   ```
   POST /api/v1/patient-records/{id}/invoices
   POST /api/v1/invoices/{invoice_id}/payments
   ```

4. **Check if notes were preserved after settlement**:
   ```bash
   php artisan check:investigation-notes {patient_record_id}
   ```
   
   Should show TWO journey events with investigation_notes:
   - One when routed to Accounts
   - One when routed from Accounts to Lab

5. **Test the API endpoint directly**:
   ```bash
   curl -H "Authorization: Bearer {token}" \
        https://your-domain.com/api/v1/patient-records/{id}/investigation
   ```

## Common Issues and Fixes

### Issue 1: Investigation Notes Not in Initial Journey Event

**Symptom**: No investigation_notes in journey event when doctor routes to Accounts

**Fix**: Ensure the mobile app or frontend is sending `investigation_notes` in the route request:
```dart
// Mobile app
await departmentRepository.routeToLab(
  patientRecordId,
  investigationNotes, // Must not be empty
);
```

### Issue 2: Metadata Not Preserved During Settlement

**Symptom**: Investigation notes exist in first journey event but not in second

**Fix**: Already implemented in `AccountsController::settleInvoice()` (lines 436-470)
- Retrieves previous event with `to_department_id` = Accounts
- Extracts investigation_notes from metadata
- Preserves in new journey event

### Issue 3: Database Column Type Issue

**Symptom**: Metadata is stored but query with `whereNotNull('metadata->investigation_notes')` returns nothing

**Fix**: Ensure `metadata` column is JSON type:
```sql
ALTER TABLE patient_journey_events 
MODIFY COLUMN metadata JSON NULL;
```

### Issue 4: Tenant Scope Issue

**Symptom**: API returns 404 or empty results

**Fix**: Ensure tenant middleware is working correctly and patient record belongs to current tenant

## Quick Fix for Production

If investigation notes are missing for existing patients in the lab queue:

1. **Find the patient record ID** from the lab queue
2. **Find the original journey event** with investigation notes (when routed to Accounts)
3. **Manually create a new journey event** with preserved metadata:

```php
use App\Models\PatientJourneyEvent;
use App\Models\PatientRecord;

$patientRecordId = 'xxx';
$patientRecord = PatientRecord::find($patientRecordId);

// Find original event with investigation notes
$originalEvent = PatientJourneyEvent::where('patient_record_id', $patientRecordId)
    ->where('to_department_id', $patientRecord->current_department_id)
    ->whereNotNull('metadata->investigation_notes')
    ->orderBy('created_at', 'desc')
    ->first();

if ($originalEvent) {
    // Create new event with preserved metadata
    PatientJourneyEvent::create([
        'id' => \Illuminate\Support\Str::uuid(),
        'tenant_id' => $patientRecord->tenant_id,
        'patient_record_id' => $patientRecord->id,
        'from_department_id' => $patientRecord->current_department_id,
        'to_department_id' => $patientRecord->current_department_id,
        'event_type' => 'note_added',
        'performed_by' => auth()->id(),
        'notes' => 'Investigation notes restored',
        'metadata' => [
            'investigation_notes' => $originalEvent->metadata['investigation_notes']
        ],
    ]);
}
```

## Verification

After applying fixes, verify:

1. ✅ Doctor can route patient to lab with investigation notes
2. ✅ Investigation notes visible in Accounts queue
3. ✅ After payment, patient routed to Lab with notes preserved
4. ✅ Lab technician sees investigation notes on lab results screen
5. ✅ No errors in mobile app or Laravel logs

## Files Modified

1. `backend/app/Http/Controllers/Api/V1/DepartmentController.php`
   - Added comprehensive logging to `getInvestigation()` method

2. `backend/app/Http/Controllers/Api/V1/AccountsController.php`
   - Already has fix to preserve investigation notes in `settleInvoice()` method

3. `backend/app/Console/Commands/CheckInvestigationNotes.php`
   - New diagnostic command to check investigation notes

## Next Steps

1. Deploy the updated code to production
2. Run the diagnostic command on a patient record that's showing the error
3. Check Laravel logs for detailed error information
4. Apply appropriate fix based on findings
