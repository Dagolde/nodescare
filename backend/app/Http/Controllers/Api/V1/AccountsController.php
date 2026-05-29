<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\PatientJourneyEvent;
use App\Models\PatientRecord;
use App\Models\WorkflowTemplateStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountsController extends Controller
{
    /**
     * List pending_payment patient records for the accounts queue.
     * GET /api/v1/accounts/queue
     */
    public function getQueue(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Get all pending_payment records
        // These are patients waiting for payment before being routed to lab/pharmacy
        $query = PatientRecord::where('status', 'pending_payment')
            ->with(['patient', 'currentDepartment', 'invoices', 'assignedDoctor.department'])
            ->orderBy('created_at', 'asc');

        // If user has a department_id, filter by that department (Accounts department)
        // This ensures accountants only see patients assigned to their Accounts department
        if ($user && $user->department_id) {
            $query->where('current_department_id', $user->department_id);
        }

        $records = $query->get()->map(function ($record) {
            // Add payment context information for the accountant
            $data = $record->toArray();
            $data['payment_context'] = $record->pending_payment_context; // e.g., 'lab_referral', 'pharmacy_referral'
            $data['return_to_department_id'] = $record->return_to_department_id;
            
            // Add human-readable payment reason
            $paymentReason = 'Payment Required';
            if ($record->pending_payment_context === 'lab_referral') {
                $paymentReason = 'Lab Test Payment';
            } elseif ($record->pending_payment_context === 'pharmacy_referral') {
                $paymentReason = 'Pharmacy/Drugs Payment';
            } elseif ($record->pending_payment_context === 'pharmacy_billing') {
                $paymentReason = 'Pharmacy/Drugs Payment';
            } elseif ($record->pending_payment_context === 'lab_billing') {
                $paymentReason = 'Lab Investigation Payment';
            } elseif ($record->pending_payment_context === 'admission_billing') {
                $paymentReason = 'Admission Payment';
            }
            $data['payment_reason'] = $paymentReason;
            
            // Fetch prescription or investigation notes from journey events
            $relevantNotes = null;
            if ($record->pending_payment_context === 'pharmacy_referral') {
                // Get prescription notes
                $prescriptionEvent = PatientJourneyEvent::where('patient_record_id', $record->id)
                    ->whereNotNull('metadata->prescription_notes')
                    ->orderBy('created_at', 'desc')
                    ->first();
                $relevantNotes = $prescriptionEvent->metadata['prescription_notes'] ?? null;
            } elseif ($record->pending_payment_context === 'lab_referral') {
                // Get investigation notes
                $investigationEvent = PatientJourneyEvent::where('patient_record_id', $record->id)
                    ->whereNotNull('metadata->investigation_notes')
                    ->orderBy('created_at', 'desc')
                    ->first();
                $relevantNotes = $investigationEvent->metadata['investigation_notes'] ?? null;
            }
            
            $data['clinical_notes'] = $relevantNotes; // Prescription or investigation notes

            // Assigned doctor info
            $data['visit_type']                    = $record->visit_type;
            $data['assigned_doctor_id']            = $record->assigned_doctor_id;
            $data['assigned_doctor_name']          = $record->assignedDoctor?->name;
            $data['assigned_doctor_department_id'] = $record->assignedDoctor?->department_id;

            // New billing contexts — include structured bill items
            if ($record->pending_payment_context === 'pharmacy_billing') {
                $billingEvent = PatientJourneyEvent::where('patient_record_id', $record->id)
                    ->whereNotNull('metadata->pharmacy_bill_items')
                    ->orderBy('created_at', 'desc')
                    ->first();
                $data['pharmacy_bill_items'] = $billingEvent->metadata['pharmacy_bill_items'] ?? [];
            } elseif ($record->pending_payment_context === 'lab_billing') {
                $billingEvent = PatientJourneyEvent::where('patient_record_id', $record->id)
                    ->whereNotNull('metadata->lab_bill_items')
                    ->orderBy('created_at', 'desc')
                    ->first();
                $data['lab_bill_items'] = $billingEvent->metadata['lab_bill_items'] ?? [];
            } elseif ($record->pending_payment_context === 'admission_billing') {
                $admissionEvent = PatientJourneyEvent::where('patient_record_id', $record->id)
                    ->whereNotNull('metadata->ward_department_id')
                    ->orderBy('created_at', 'desc')
                    ->first();
                $data['ward_department_id'] = $admissionEvent->metadata['ward_department_id'] ?? $record->return_to_department_id;
            }

            return $data;
        });

        return response()->json([
            'success' => true,
            'data'    => $records,
            'meta'    => ['total' => $records->count()],
        ]);
    }

    /**
     * Create invoice for a patient record.
     * POST /api/v1/patient-records/{id}/invoices
     */
    public function createInvoice(string $id, Request $request): JsonResponse
    {
        $patientRecord = PatientRecord::find($id);

        if (!$patientRecord) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'NOT_FOUND', 'message' => 'Patient record not found.'],
            ], 404);
        }

        // Only block if there's an UNPAID invoice (issued or partially_paid)
        // Allow creating new invoices if previous invoices are fully paid
        $existingUnpaid = Invoice::where('patient_record_id', $id)
            ->whereIn('status', ['issued', 'partially_paid'])
            ->first();

        if ($existingUnpaid) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'UNPAID_INVOICE_EXISTS',
                    'message' => 'An unpaid invoice already exists for this patient record. Complete payment on the existing invoice first.',
                    'data'    => ['invoice_id' => $existingUnpaid->id, 'status' => $existingUnpaid->status],
                ],
            ], 422);
        }

        $request->validate([
            'items'                  => 'required|array|min:1',
            'items.*.description'    => 'required|string|max:255',
            'items.*.unit_price'     => 'required|numeric|min:0',
            'items.*.quantity'       => 'required|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            $invoiceNumber = $this->generateInvoiceNumber();
            $totalAmount   = 0;

            foreach ($request->items as $item) {
                $totalAmount += $item['unit_price'] * $item['quantity'];
            }

            $invoice = Invoice::create([
                'id'                => Str::uuid(),
                'tenant_id'         => app('current_tenant_id'),
                'patient_record_id' => $patientRecord->id,
                'invoice_number'    => $invoiceNumber,
                'total_amount'      => $totalAmount,
                'paid_amount'       => 0,
                'status'            => 'issued',
                'created_by'        => request()->user()?->id ?? auth('web')->id(),
            ]);

            foreach ($request->items as $item) {
                $totalPrice = $item['unit_price'] * $item['quantity'];
                InvoiceItem::create([
                    'id'          => Str::uuid(),
                    'tenant_id'   => app('current_tenant_id'),
                    'invoice_id'  => $invoice->id,
                    'description' => $item['description'],
                    'unit_price'  => $item['unit_price'],
                    'quantity'    => $item['quantity'],
                    'total_price' => $totalPrice,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data'    => $invoice->load('items'),
                'message' => 'Invoice created successfully.',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'CREATION_FAILED', 'message' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * List invoices for a patient record.
     * GET /api/v1/patient-records/{id}/invoices
     */
    public function listInvoices(string $id): JsonResponse
    {
        $patientRecord = PatientRecord::find($id);

        if (!$patientRecord) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'NOT_FOUND', 'message' => 'Patient record not found.'],
            ], 404);
        }

        $invoices = Invoice::where('patient_record_id', $id)
            ->with(['items', 'payments'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $invoices]);
    }

    /**
     * Record a payment against an invoice.
     * POST /api/v1/invoices/{id}/payments
     */
    public function recordPayment(string $id, Request $request): JsonResponse
    {
        $invoice = Invoice::find($id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'NOT_FOUND', 'message' => 'Invoice not found.'],
            ], 404);
        }

        $request->validate([
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,card,transfer,hmo,other',
            'reference'      => 'nullable|string|max:100',
            'notes'          => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $payment = InvoicePayment::create([
                'id'             => Str::uuid(),
                'tenant_id'      => app('current_tenant_id'),
                'invoice_id'     => $invoice->id,
                'amount'         => $request->amount,
                'payment_method' => $request->payment_method,
                'reference'      => $request->reference,
                'notes'          => $request->notes,
                'accountant_id'  => request()->user()?->id ?? auth('web')->id() ?? 'system',
            ]);

            $newPaid = $invoice->paid_amount + $request->amount;
            $newStatus = $newPaid >= $invoice->total_amount ? 'paid' : 'partially_paid';
            
            $invoice->update([
                'paid_amount' => $newPaid,
                'status'      => $newStatus,
            ]);

            // Auto-settle if fully paid
            $shouldAutoSettle = $newStatus === 'paid';

            DB::commit();

            // If invoice is now fully paid, automatically settle it
            if ($shouldAutoSettle) {
                \Log::info('Invoice fully paid, attempting auto-settlement', [
                    'invoice_id' => $invoice->id,
                    'total_amount' => $invoice->total_amount,
                    'paid_amount' => $newPaid,
                    'has_user' => request()->user() !== null,
                    'user_id' => request()->user()?->id,
                ]);
                
                try {
                    // Call settle invoice internally
                    $settlementResponse = $this->settleInvoice($invoice->id);
                    
                    \Log::info('Settlement response received', [
                        'invoice_id' => $invoice->id,
                        'status_code' => $settlementResponse->getStatusCode(),
                        'response_preview' => substr($settlementResponse->getContent(), 0, 200),
                    ]);
                    
                    if ($settlementResponse->getStatusCode() === 200) {
                        $settlementData = json_decode($settlementResponse->getContent(), true);
                        \Log::info('Auto-settlement successful', [
                            'invoice_id' => $invoice->id,
                            'settlement_data' => $settlementData,
                        ]);
                        return response()->json([
                            'success' => true,
                            'data'    => [
                                'payment' => $payment, 
                                'invoice' => $settlementData['data']['invoice'],
                                'patient_record' => $settlementData['data']['patient_record'],
                                'auto_settled' => true,
                            ],
                            'message' => 'Payment recorded and invoice automatically settled.',
                        ], 201);
                    } else {
                        // Settlement failed, but payment was recorded
                        $settlementContent = $settlementResponse->getContent();
                        \Log::warning('Auto-settlement failed after payment', [
                            'invoice_id' => $invoice->id,
                            'settlement_status' => $settlementResponse->getStatusCode(),
                            'settlement_response' => $settlementContent,
                        ]);
                    }
                } catch (\Exception $e) {
                    \Log::error('Auto-settlement exception', [
                        'invoice_id' => $invoice->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'data'    => ['payment' => $payment, 'invoice' => $invoice->fresh(['items', 'payments'])],
                'message' => 'Payment recorded.',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'PAYMENT_FAILED', 'message' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Settle invoice and route patient to first clinical department.
     * POST /api/v1/invoices/{id}/settle
     */
    public function settleInvoice(string $id): JsonResponse
    {
        \Log::info('settleInvoice method called', [
            'invoice_id' => $id,
            'user_id' => request()->user()?->id ?? auth('web')->id(),
            'has_request_user' => request()->user() !== null,
            'has_web_user' => auth('web')->user() !== null,
        ]);

        $invoice = Invoice::with(['patientRecord.patient'])->find($id);

        if (!$invoice) {
            \Log::warning('Invoice not found for settlement', ['invoice_id' => $id]);
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'NOT_FOUND', 'message' => 'Invoice not found.'],
            ], 404);
        }

        $patientRecord = $invoice->patientRecord;

        \Log::info('Invoice found for settlement', [
            'invoice_id' => $invoice->id,
            'invoice_status' => $invoice->status,
            'total_amount' => $invoice->total_amount,
            'paid_amount' => $invoice->paid_amount,
            'patient_record_id' => $invoice->patient_record_id,
            'patient_record_status' => $patientRecord?->status,
        ]);

        if ($invoice->status === 'paid' && $patientRecord->status === 'active') {
            \Log::info('Invoice already settled, skipping', [
                'invoice_id' => $id,
                'invoice_status' => $invoice->status,
                'patient_status' => $patientRecord->status,
            ]);
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'ALREADY_SETTLED', 'message' => 'Invoice is already settled and patient routed.'],
            ], 422);
        }

        // Check if invoice is fully paid
        if ($invoice->paid_amount < $invoice->total_amount) {
            \Log::warning('Invoice not fully paid, cannot settle', [
                'invoice_id' => $id,
                'paid_amount' => $invoice->paid_amount,
                'total_amount' => $invoice->total_amount,
            ]);
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'PAYMENT_INCOMPLETE', 'message' => 'Invoice must be fully paid before settlement.'],
            ], 422);
        }

        try {
            \Log::info('Starting invoice settlement', ['invoice_id' => $id]);

            // Get the user performing the settlement (accountant or system)
            $performingUser = request()->user() ?? auth('web')->user();
            
            if (!$performingUser) {
                $performingUser = \App\Models\User::withoutGlobalScopes()
                    ->where('email', 'system@nodescare.com')
                    ->where('tenant_id', $patientRecord->tenant_id)
                    ->first();
                    
                if (!$performingUser) {
                    $performingUser = \App\Models\User::withoutGlobalScopes()->create([
                        'id' => \Illuminate\Support\Str::uuid(),
                        'tenant_id' => $patientRecord->tenant_id,
                        'name' => 'System Auto-Settlement',
                        'email' => 'system@nodescare.com',
                        'password' => bcrypt(\Illuminate\Support\Str::random(32)),
                        'role' => 'accountant',
                        'is_active' => false,
                        'email_verified_at' => now(),
                    ]);
                }
            }

            $tenantId = $patientRecord->tenant_id;
            $tenant = \App\Models\Tenant::find($tenantId);

            if (!$tenant) {
                throw new \Exception("Tenant with ID {$tenantId} not found.");
            }

            // Determine wallet deduction amounts BEFORE the transaction
            // so we don't have nested DB::transaction() calls
            $walletService = app(\App\Services\WalletService::class);
            $planId = $tenant->plan_id;
            $previousVisits = \App\Models\PatientRecord::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('patient_id', $patientRecord->patient_id)
                ->where('id', '!=', $patientRecord->id)
                ->count();
            $isNewPatient = $previousVisits === 0;

            $config = \App\Models\WalletDeductionConfig::forPlan($planId);
            $wallet = $walletService->getOrCreateWallet($tenantId);

            \Log::info('Pre-settlement checks passed', [
                'invoice_id' => $id,
                'tenant_id' => $tenantId,
                'is_new_patient' => $isNewPatient,
                'wallet_balance' => $wallet->balance,
                'wallet_active' => $wallet->is_active,
            ]);

            // Run everything in ONE flat transaction — no nesting
            DB::beginTransaction();

            // STEP 1: Route patient to next department (or return_to_department if set for lab/pharmacy referral)
            $returnToDeptId = $patientRecord->return_to_department_id;
            $paymentContext = $patientRecord->pending_payment_context;

            if ($returnToDeptId) {
                // Lab/pharmacy referral payment — route directly to the target department
                $fromDeptId = $patientRecord->current_department_id;
                
                // Retrieve investigation/prescription notes from the previous journey event
                $previousEvent = \App\Models\PatientJourneyEvent::where('patient_record_id', $patientRecord->id)
                    ->where('to_department_id', $fromDeptId) // Event that brought patient to Accounts
                    ->whereNotNull('metadata')
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                $preservedMetadata = [];
                if ($previousEvent && $previousEvent->metadata) {
                    // Preserve investigation_notes for lab or prescription_notes for pharmacy
                    if (isset($previousEvent->metadata['investigation_notes'])) {
                        $preservedMetadata['investigation_notes'] = $previousEvent->metadata['investigation_notes'];
                    }
                    if (isset($previousEvent->metadata['prescription_notes'])) {
                        $preservedMetadata['prescription_notes'] = $previousEvent->metadata['prescription_notes'];
                    }
                    // Preserve new billing item metadata
                    if (isset($previousEvent->metadata['pharmacy_bill_items'])) {
                        $preservedMetadata['pharmacy_bill_items'] = $previousEvent->metadata['pharmacy_bill_items'];
                    }
                    if (isset($previousEvent->metadata['lab_bill_items'])) {
                        $preservedMetadata['lab_bill_items'] = $previousEvent->metadata['lab_bill_items'];
                    }
                }

                // Admission billing: set status to 'admitted' instead of 'active'
                if ($paymentContext === 'admission_billing') {
                    $patientRecord->update([
                        'status'                  => 'admitted',
                        'admitted_at'             => now(),
                        'current_department_id'   => $returnToDeptId,
                        'return_to_department_id' => null,
                        'pending_payment_context' => null,
                    ]);

                    \App\Models\PatientJourneyEvent::create([
                        'id'                 => \Illuminate\Support\Str::uuid(),
                        'tenant_id'          => $patientRecord->tenant_id,
                        'patient_record_id'  => $patientRecord->id,
                        'from_department_id' => $fromDeptId,
                        'to_department_id'   => $returnToDeptId,
                        'event_type'         => 'admitted',
                        'performed_by'       => $performingUser->id,
                        'notes'              => 'Admission payment settled. Patient admitted to ward.',
                        'metadata'           => ['ward_department_id' => $returnToDeptId],
                    ]);
                } else {
                    $patientRecord->update([
                        'status'                  => 'active',
                        'current_department_id'   => $returnToDeptId,
                        'return_to_department_id' => null,
                        'pending_payment_context' => null,
                    ]);

                    \App\Models\PatientJourneyEvent::create([
                        'id'                 => \Illuminate\Support\Str::uuid(),
                        'tenant_id'          => $patientRecord->tenant_id,
                        'patient_record_id'  => $patientRecord->id,
                        'from_department_id' => $fromDeptId,
                        'to_department_id'   => $returnToDeptId,
                        'event_type'         => 'routed',
                        'performed_by'       => $performingUser->id,
                        'notes'              => 'Payment settled. Patient routed to ' . ($paymentContext ?? 'referred department') . '.',
                        'metadata'           => $preservedMetadata,
                    ]);
                }

                \App\Models\AuditLog::create([
                    'tenant_id'     => $patientRecord->tenant_id,
                    'actor_id'      => $performingUser->id,
                    'actor_role'    => $performingUser->role,
                    'action'        => 'patient_record.routed',
                    'resource_type' => 'PatientRecord',
                    'resource_id'   => $patientRecord->id,
                    'description'   => "Patient routed to {$returnToDeptId} after {$paymentContext} payment",
                    'ip_address'    => request()->ip(),
                    'metadata'      => ['from_department_id' => $fromDeptId, 'to_department_id' => $returnToDeptId],
                ]);

                $updatedPatientRecord = $patientRecord->fresh();
            } else {
                // Standard workflow — route to next step
                $workflowTemplate = $patientRecord->workflowTemplate;
                if (!$workflowTemplate) {
                    throw new \Exception('Patient record has no workflow template assigned.');
                }

                $currentStepOrder = $patientRecord->current_step_order ?? 0;
                $nextStep = \App\Models\WorkflowTemplateStep::where('template_id', $workflowTemplate->id)
                    ->where('step_order', '>', $currentStepOrder)
                    ->orderBy('step_order')
                    ->first();

                if (!$nextStep) {
                    $patientRecord->update([
                        'status' => 'completed',
                        'discharged_at' => now(),
                        'current_department_id' => null,
                        'current_step_order' => null,
                    ]);
                } else {
                    $fromDepartmentId = $patientRecord->current_department_id;
                    $patientRecord->update([
                        'status' => 'active',
                        'current_department_id' => $nextStep->department_id,
                        'current_step_order' => $nextStep->step_order,
                    ]);

                    \App\Models\PatientJourneyEvent::create([
                        'id' => \Illuminate\Support\Str::uuid(),
                        'tenant_id' => $patientRecord->tenant_id,
                        'patient_record_id' => $patientRecord->id,
                        'from_department_id' => $fromDepartmentId,
                        'to_department_id' => $nextStep->department_id,
                        'event_type' => 'routed',
                        'performed_by' => $performingUser->id,
                        'notes' => 'Payment settled. Patient routed to next department in workflow.',
                    ]);

                    \App\Models\AuditLog::create([
                        'tenant_id' => $patientRecord->tenant_id,
                        'actor_id' => $performingUser->id,
                        'actor_role' => $performingUser->role,
                        'action' => 'patient_record.routed',
                        'resource_type' => 'PatientRecord',
                        'resource_id' => $patientRecord->id,
                        'description' => "Patient routed to department {$nextStep->department_id} after payment settlement",
                        'ip_address' => request()->ip(),
                        'metadata' => ['from_department_id' => $fromDepartmentId, 'to_department_id' => $nextStep->department_id],
                    ]);
                }

                $updatedPatientRecord = $patientRecord->fresh();
            }

            // STEP 2: Wallet deduction — mandatory, in the same transaction
            if ($wallet->is_active) {
                if ($isNewPatient) {
                    if ($config->deduct_on_new_patient && $config->new_patient_card_fee > 0) {
                        $balanceBefore = (float) $wallet->balance;
                        $wallet->balance = $balanceBefore - (float) $config->new_patient_card_fee;
                        $wallet->save();
                        \App\Models\WalletTransaction::create([
                            'id' => \Illuminate\Support\Str::uuid(),
                            'tenant_id' => $tenantId,
                            'wallet_id' => $wallet->id,
                            'type' => 'debit',
                            'reason' => 'new_patient_card',
                            'amount' => $config->new_patient_card_fee,
                            'balance_before' => $balanceBefore,
                            'balance_after' => $wallet->balance,
                            'description' => 'New patient registration card fee',
                            'patient_id' => $patientRecord->patient_id,
                        ]);
                    }
                    if ($config->deduct_on_consultation && $config->consultation_fee > 0) {
                        $balanceBefore = (float) $wallet->balance;
                        $wallet->balance = $balanceBefore - (float) $config->consultation_fee;
                        $wallet->save();
                        \App\Models\WalletTransaction::create([
                            'id' => \Illuminate\Support\Str::uuid(),
                            'tenant_id' => $tenantId,
                            'wallet_id' => $wallet->id,
                            'type' => 'debit',
                            'reason' => 'consultation',
                            'amount' => $config->consultation_fee,
                            'balance_before' => $balanceBefore,
                            'balance_after' => $wallet->balance,
                            'description' => 'Consultation fee',
                            'patient_id' => $patientRecord->patient_id,
                        ]);
                    }
                } else {
                    if ($config->deduct_on_consultation && $config->consultation_fee > 0) {
                        $balanceBefore = (float) $wallet->balance;
                        $wallet->balance = $balanceBefore - (float) $config->consultation_fee;
                        $wallet->save();
                        \App\Models\WalletTransaction::create([
                            'id' => \Illuminate\Support\Str::uuid(),
                            'tenant_id' => $tenantId,
                            'wallet_id' => $wallet->id,
                            'type' => 'debit',
                            'reason' => 'consultation',
                            'amount' => $config->consultation_fee,
                            'balance_before' => $balanceBefore,
                            'balance_after' => $wallet->balance,
                            'description' => 'Consultation fee (returning patient)',
                            'patient_id' => $patientRecord->patient_id,
                        ]);
                    }
                }
                \Log::info('Wallet deduction completed in settlement', [
                    'invoice_id' => $id,
                    'wallet_balance' => $wallet->balance,
                ]);
            }

            DB::commit();

            \Log::info('Invoice settlement completed successfully', [
                'invoice_id' => $id,
                'patient_record_id' => $patientRecord->id,
            ]);

            // Notify department staff and broadcast (after commit, non-fatal)
            try {
                $patientRecord->refresh();
                if ($patientRecord->current_department_id) {
                    $workflowService->notifyDepartmentStaffPublic($patientRecord->current_department_id, $patientRecord);
                }
                event(new \App\Events\InvoiceSettled(
                    $tenantId,
                    $patientRecord->id,
                    $invoice->id,
                    $patientRecord->patient->full_name ?? 'Unknown',
                    $patientRecord->patient->patient_number ?? 'N/A'
                ));
            } catch (\Exception $e) {
                \Log::warning('Post-settlement notifications failed (non-fatal)', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'invoice'        => $invoice->fresh(['items', 'payments']),
                    'patient_record' => $patientRecord->fresh(['currentDepartment']),
                ],
                'message' => 'Invoice settled and patient routed.',
            ]);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            \Log::error('Invoice settlement failed', [
                'invoice_id' => $id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'SETTLEMENT_FAILED', 'message' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Generate PDF receipt.
     * GET /api/v1/invoices/{id}/receipt
     */
    public function generateReceipt(string $id): mixed
    {
        $invoice = Invoice::with(['items', 'payments', 'patientRecord.patient'])->find($id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'NOT_FOUND', 'message' => 'Invoice not found.'],
            ], 404);
        }

        try {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('receipts.invoice', ['invoice' => $invoice]);
            return $pdf->download("receipt-{$invoice->invoice_number}.pdf");
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'RECEIPT_FAILED', 'message' => $e->getMessage()],
            ], 500);
        }
    }

    private function generateInvoiceNumber(): string
    {
        $date   = now()->format('Ymd');
        $prefix = "INV-{$date}-";

        $last = Invoice::where('invoice_number', 'like', $prefix . '%')
            ->orderBy('invoice_number', 'desc')
            ->first();

        $seq = $last ? ((int) substr($last->invoice_number, -4)) + 1 : 1;

        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
