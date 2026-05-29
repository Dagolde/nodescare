<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\DispensingRecord;
use App\Models\Invoice;
use App\Models\PatientJourneyEvent;
use App\Models\PatientRecord;
use App\Services\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BillingController extends Controller
{
    public function __construct(protected WorkflowService $workflowService) {}

    // ─── Shared helpers ───────────────────────────────────────────────────────

    /**
     * Find the Accounts department for the current tenant.
     */
    private function findAccountsDepartment(string $tenantId): ?Department
    {
        return Department::where('tenant_id', $tenantId)
            ->where('type', 'accounts')
            ->first()
            ?? Department::where('tenant_id', $tenantId)
                ->where('name', 'like', '%account%')
                ->first();
    }

    /**
     * Validate that the patient record is accessible and not already pending payment.
     */
    private function validatePatientRecord(string $id, Request $request): PatientRecord|JsonResponse
    {
        $user = $request->user();
        $record = PatientRecord::find($id);

        if (!$record) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Patient record not found.'],
            ], 404);
        }

        if ($record->status === 'pending_payment') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'CONFLICT', 'message' => 'Patient record is already pending payment.'],
            ], 409);
        }

        return $record;
    }

    /**
     * Route patient to Accounts and create journey event + audit log.
     */
    private function routeToAccounts(
        PatientRecord $record,
        string $context,
        string $returnToDeptId,
        array $metadata,
        string $auditAction,
        Request $request
    ): void {
        $user = $request->user();
        $accountsDept = $this->findAccountsDepartment($record->tenant_id);

        if (!$accountsDept) {
            throw new \Exception('Accounts department not found for this hospital. Please contact your administrator.');
        }

        $fromDeptId = $record->current_department_id;

        $record->update([
            'status'                  => 'pending_payment',
            'pending_payment_context' => $context,
            'return_to_department_id' => $returnToDeptId,
            'current_department_id'   => $accountsDept->id,
        ]);

        PatientJourneyEvent::create([
            'id'                 => Str::uuid(),
            'tenant_id'          => $record->tenant_id,
            'patient_record_id'  => $record->id,
            'from_department_id' => $fromDeptId,
            'to_department_id'   => $accountsDept->id,
            'event_type'         => 'routed',
            'performed_by'       => $user->id,
            'notes'              => 'Sent to Accounts for ' . $context . ' payment.',
            'metadata'           => $metadata,
        ]);

        AuditLog::create([
            'tenant_id'     => $record->tenant_id,
            'actor_id'      => $user->id,
            'actor_role'    => $user->role,
            'action'        => $auditAction,
            'resource_type' => 'PatientRecord',
            'resource_id'   => $record->id,
            'description'   => "Patient sent to Accounts for {$context} payment by {$user->name}",
            'ip_address'    => $request->ip(),
            'metadata'      => $metadata,
        ]);
    }

    // ─── Pharmacy Billing ─────────────────────────────────────────────────────

    /**
     * POST /api/v1/patient-records/{id}/pharmacy-billing
     * Pharmacist costs drugs and sends patient to Accounts for payment.
     */
    public function pharmacyBilling(string $id, Request $request): JsonResponse
    {
        $record = $this->validatePatientRecord($id, $request);
        if ($record instanceof JsonResponse) return $record;

        $validated = $request->validate([
            'items'              => 'required|array|min:1',
            'items.*.drug_name'  => 'required|string|max:255',
            'items.*.unit_price' => 'required|numeric|min:0.01',
            'items.*.quantity'   => 'required|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            $this->routeToAccounts(
                $record,
                'pharmacy_billing',
                $record->current_department_id, // return to pharmacy
                ['pharmacy_bill_items' => $validated['items']],
                'pharmacy_billing.submitted',
                $request
            );

            DB::commit();

            // Clear queue cache for pharmacy dept (patient moved to Accounts)
            $cacheKey = sprintf('tenant:%s:dept_queue:%s', $record->tenant_id, $record->current_department_id);
            \Illuminate\Support\Facades\Cache::forget($cacheKey);

            return response()->json([
                'success' => true,
                'data'    => $record->fresh(['patient', 'currentDepartment']),
                'message' => 'Patient sent to Accounts for drug payment.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Pharmacy billing failed', ['error' => $e->getMessage(), 'record_id' => $id]);
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'BILLING_FAILED', 'message' => $e->getMessage()],
            ], 500);
        }
    }

    // ─── Lab Billing ──────────────────────────────────────────────────────────

    /**
     * POST /api/v1/patient-records/{id}/lab-billing
     * Lab technician costs investigations and sends patient to Accounts for payment.
     */
    public function labBilling(string $id, Request $request): JsonResponse
    {
        $record = $this->validatePatientRecord($id, $request);
        if ($record instanceof JsonResponse) return $record;

        $validated = $request->validate([
            'items'              => 'required|array|min:1',
            'items.*.test_name'  => 'required|string|max:255',
            'items.*.unit_price' => 'required|numeric|min:0.01',
            'items.*.quantity'   => 'required|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            $this->routeToAccounts(
                $record,
                'lab_billing',
                $record->current_department_id, // return to lab
                ['lab_bill_items' => $validated['items']],
                'lab_billing.submitted',
                $request
            );

            DB::commit();

            // Clear queue cache for lab dept (patient moved to Accounts)
            $cacheKey = sprintf('tenant:%s:dept_queue:%s', $record->tenant_id, $record->current_department_id);
            \Illuminate\Support\Facades\Cache::forget($cacheKey);

            return response()->json([
                'success' => true,
                'data'    => $record->fresh(['patient', 'currentDepartment']),
                'message' => 'Patient sent to Accounts for investigation payment.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lab billing failed', ['error' => $e->getMessage(), 'record_id' => $id]);
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'BILLING_FAILED', 'message' => $e->getMessage()],
            ], 500);
        }
    }

    // ─── Admission Billing ────────────────────────────────────────────────────

    /**
     * POST /api/v1/patient-records/{id}/admit
     * Doctor admits patient — routes to Accounts for admission payment.
     */
    public function admit(string $id, Request $request): JsonResponse
    {
        $record = $this->validatePatientRecord($id, $request);
        if ($record instanceof JsonResponse) return $record;

        $validated = $request->validate([
            'ward_department_id' => 'required|uuid',
        ]);

        // Verify ward department exists in this tenant
        $wardDept = Department::where('id', $validated['ward_department_id'])
            ->where('tenant_id', $record->tenant_id)
            ->first();

        if (!$wardDept) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'DEPARTMENT_NOT_FOUND', 'message' => 'The specified ward department does not exist.'],
            ], 422);
        }

        try {
            DB::beginTransaction();

            $this->routeToAccounts(
                $record,
                'admission_billing',
                $validated['ward_department_id'], // return to ward after payment
                ['admission_billing' => true, 'ward_department_id' => $validated['ward_department_id']],
                'admission_billing.submitted',
                $request
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'data'    => $record->fresh(['patient', 'currentDepartment']),
                'message' => 'Patient sent to Accounts for admission payment.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Admission billing failed', ['error' => $e->getMessage(), 'record_id' => $id]);
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'BILLING_FAILED', 'message' => $e->getMessage()],
            ], 500);
        }
    }

    // ─── Confirm Dispense ─────────────────────────────────────────────────────

    /**
     * POST /api/v1/patient-records/{id}/confirm-dispense
     * Pharmacist confirms drugs have been physically handed to the patient.
     */
    public function confirmDispense(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $record = PatientRecord::find($id);

        if (!$record) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'NOT_FOUND', 'message' => 'Patient record not found.'],
            ], 404);
        }

        // Verify there is a settled (paid) invoice with pharmacy_billing context
        $settledInvoice = Invoice::where('patient_record_id', $id)
            ->where('status', 'paid')
            ->first();

        if (!$settledInvoice) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'DISPENSE_NOT_ALLOWED', 'message' => 'No settled pharmacy invoice found. Bill must be created and paid before dispensing.'],
            ], 422);
        }

        // Verify pharmacy bill items exist
        $billingEvent = PatientJourneyEvent::where('patient_record_id', $id)
            ->whereNotNull('metadata->pharmacy_bill_items')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$billingEvent) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'DISPENSE_NOT_ALLOWED', 'message' => 'No pharmacy billing event found. Bill must be created before dispensing.'],
            ], 422);
        }

        $pharmacyBillItems = $billingEvent->metadata['pharmacy_bill_items'] ?? [];

        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            // Find front desk department BEFORE the transaction work
            $frontDesk = \App\Models\Department::where('type', 'front_desk')->first()
                ?? \App\Models\Department::where('name', 'like', '%front%')->first();

            $fromDeptId = $record->current_department_id;

            // Create dispensing record
            DispensingRecord::create([
                'id'                => Str::uuid(),
                'tenant_id'         => $record->tenant_id,
                'patient_record_id' => $record->id,
                'pharmacist_id'     => $user->id,
                'medications'       => array_map(fn($item) => [
                    'drug_name' => $item['drug_name'],
                    'quantity'  => $item['quantity'],
                ], $pharmacyBillItems),
                'notes'             => $validated['notes'] ?? null,
            ]);

            // Create dispensed journey event
            PatientJourneyEvent::create([
                'id'                 => Str::uuid(),
                'tenant_id'          => $record->tenant_id,
                'patient_record_id'  => $record->id,
                'from_department_id' => $fromDeptId,
                'to_department_id'   => $fromDeptId,
                'event_type'         => 'dispensed',
                'performed_by'       => $user->id,
                'notes'              => $validated['notes'] ?? 'Drugs dispensed to patient.',
                'metadata'           => ['pharmacy_bill_items' => $pharmacyBillItems],
            ]);

            AuditLog::create([
                'tenant_id'     => $record->tenant_id,
                'actor_id'      => $user->id,
                'actor_role'    => $user->role,
                'action'        => 'dispensing.confirmed',
                'resource_type' => 'PatientRecord',
                'resource_id'   => $record->id,
                'description'   => "Drugs dispensed to patient by {$user->name}",
                'ip_address'    => $request->ip(),
            ]);

            // Route to Front Desk inside the transaction
            if ($frontDesk) {
                // Update directly on the model (not on a discarded fresh() instance)
                $record->status                = 'drugs_given';
                $record->current_department_id = $frontDesk->id;
                $record->save();

                PatientJourneyEvent::create([
                    'id'                 => Str::uuid(),
                    'tenant_id'          => $record->tenant_id,
                    'patient_record_id'  => $record->id,
                    'from_department_id' => $fromDeptId,
                    'to_department_id'   => $frontDesk->id,
                    'event_type'         => 'drugs_given',
                    'performed_by'       => $user->id,
                    'notes'              => 'Drugs dispensed. Patient returned to Front Desk.',
                    'metadata'           => ['pharmacy_bill_items' => $pharmacyBillItems],
                ]);
            } else {
                // No front desk found — just mark as drugs_given in current dept
                $record->status = 'drugs_given';
                $record->save();
            }

            DB::commit();

            // Invalidate queue caches after commit
            $cacheKeyPharm = sprintf('tenant:%s:dept_queue:%s', $record->tenant_id, $fromDeptId);
            \Illuminate\Support\Facades\Cache::forget($cacheKeyPharm);
            if ($frontDesk) {
                $cacheKeyFD = sprintf('tenant:%s:dept_queue:%s', $record->tenant_id, $frontDesk->id);
                \Illuminate\Support\Facades\Cache::forget($cacheKeyFD);
            }

            $updatedRecord = $record->fresh()->load(['patient', 'currentDepartment']);

            return response()->json([
                'success' => true,
                'data'    => $updatedRecord,
                'message' => 'Drugs dispensed successfully. Patient routed to next step.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Confirm dispense failed', ['error' => $e->getMessage(), 'record_id' => $id]);
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'DISPENSE_FAILED', 'message' => $e->getMessage()],
            ], 500);
        }
    }
}
