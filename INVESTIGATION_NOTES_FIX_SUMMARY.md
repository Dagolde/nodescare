# Investigation Notes Fix Summary

## Problem
Investigation notes are showing in patient journey events but NOT displaying in the lab results screen on the mobile app.

## Root Cause
The mobile app was encountering an error when trying to fetch investigation notes from the API, even though the notes exist in the database.

## Changes Made

### 1. Backend - Enhanced Logging (`DepartmentController.php`)
Added comprehensive logging to the `getInvestigation()` method to track:
- When the endpoint is called
- Patient record details
- All journey events with metadata
- Whether investigation notes were found

**Location**: `backend/app/Http/Controllers/Api/V1/DepartmentController.php`

**What it does**:
- Logs every step of the investigation notes retrieval process
- Shows all journey events with metadata for debugging
- Helps identify if notes exist but aren't being found

### 2. Mobile App - Improved Error Handling (`lab_results_screen.dart`)
Fixed the investigation notes loading logic:
- Moved API call to `addPostFrameCallback` to avoid `ref.read` issues in `initState`
- Added better error logging with stack traces
- Improved error messages to distinguish between "no notes found" vs "API error"
- Added debug prints for successful loads

**Location**: `mobile/lib/presentation/screens/lab_results_screen.dart`

**What it does**:
- Prevents timing issues with Riverpod ref.read in initState
- Provides detailed error information for debugging
- Only shows error snackbar for real errors (not when notes are simply not found)

### 3. Diagnostic Tools

#### Command: `CheckInvestigationNotes.php`
**Usage**: `php artisan check:investigation-notes {patient_record_id}`

Shows:
- Patient record details
- All journey events with metadata
- Specifically checks for investigation notes

#### Command: `TestInvestigationEndpoint.php`
**Usage**: `php artisan test:investigation-endpoint {patient_record_id}`

Tests:
- Calls the investigation endpoint directly
- Shows the exact JSON response
- Displays HTTP status code

## Testing Steps

### Step 1: Check if Investigation Notes Exist
```bash
php artisan check:investigation-notes {patient_record_id}
```

Expected output:
- Should show journey events with `investigation_notes` in metadata
- Should show "✓ Investigation notes found!"

### Step 2: Test the API Endpoint
```bash
php artisan test:investigation-endpoint {patient_record_id}
```

Expected output:
```json
{
  "success": true,
  "data": {
    "patient_record_id": "xxx",
    "patient_name": "John Doe",
    "patient_number": "P001",
    "investigation_notes": "Full Blood Count, Malaria Test",
    "requested_by": "Dr. Smith",
    "requested_at": "2024-04-22 10:30:00"
  }
}
```

### Step 3: Check Laravel Logs
```bash
tail -f storage/logs/laravel.log | grep investigation
```

Look for:
- `getInvestigation called` - Endpoint was hit
- `Investigation notes found` - Notes were retrieved successfully
- Any error messages

### Step 4: Test on Mobile App
1. Open lab results screen for a patient with investigation notes
2. Check if notes display in the blue card
3. If error appears, check the error message details

## Common Issues and Solutions

### Issue 1: "No investigation notes found" in logs
**Cause**: Investigation notes weren't stored when doctor routed patient

**Solution**: Ensure doctor provides investigation notes when routing:
```dart
await departmentRepository.routeToLab(
  patientRecordId,
  "Full Blood Count, Malaria Test", // Must not be empty
);
```

### Issue 2: Notes exist but API returns null
**Cause**: Query `whereNotNull('metadata->investigation_notes')` not finding records

**Solution**: Check if metadata column is JSON type:
```sql
SHOW COLUMNS FROM patient_journey_events WHERE Field = 'metadata';
```

Should show type: `json`

If not:
```sql
ALTER TABLE patient_journey_events 
MODIFY COLUMN metadata JSON NULL;
```

### Issue 3: Mobile app shows error even though API works
**Cause**: Network error, authentication issue, or JSON parsing error

**Solution**: 
1. Check mobile app logs for detailed error
2. Test API endpoint with curl:
```bash
curl -H "Authorization: Bearer {token}" \
     https://your-domain.com/api/v1/patient-records/{id}/investigation
```

### Issue 4: Notes not preserved after payment settlement
**Cause**: `settleInvoice()` method not preserving metadata

**Solution**: Already fixed in `AccountsController.php` (lines 436-470)
- Retrieves previous journey event
- Extracts investigation_notes from metadata
- Preserves in new journey event

## Deployment Checklist

- [ ] Deploy updated `DepartmentController.php` with enhanced logging
- [ ] Deploy updated `lab_results_screen.dart` with improved error handling
- [ ] Deploy diagnostic commands (`CheckInvestigationNotes.php`, `TestInvestigationEndpoint.php`)
- [ ] Test on staging environment first
- [ ] Monitor Laravel logs after deployment
- [ ] Test complete workflow: Doctor → Accounts → Lab
- [ ] Verify investigation notes display correctly

## Verification

After deployment, verify:

1. ✅ Doctor can route patient to lab with investigation notes
2. ✅ Investigation notes visible in patient journey
3. ✅ Investigation notes visible in Accounts queue
4. ✅ After payment, patient routed to Lab with notes preserved
5. ✅ Lab technician sees investigation notes on lab results screen
6. ✅ No errors in mobile app
7. ✅ No errors in Laravel logs

## Files Modified

1. `backend/app/Http/Controllers/Api/V1/DepartmentController.php`
   - Enhanced `getInvestigation()` method with comprehensive logging

2. `mobile/lib/presentation/screens/lab_results_screen.dart`
   - Improved `_loadInvestigationNotes()` method with better error handling
   - Moved API call to `addPostFrameCallback`

3. `backend/app/Console/Commands/CheckInvestigationNotes.php` (NEW)
   - Diagnostic command to check investigation notes

4. `backend/app/Console/Commands/TestInvestigationEndpoint.php` (NEW)
   - Test command to verify API endpoint response

## Next Steps

1. Deploy the changes to production
2. Run diagnostic commands on affected patient records
3. Monitor Laravel logs for any errors
4. Test the complete workflow with a new patient
5. Verify existing patients in lab queue can see investigation notes
