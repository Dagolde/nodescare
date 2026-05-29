<?php

namespace App\Http\Controllers;

use App\Models\PatientRecord;
use App\Models\Invoice;
use App\Models\Department;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountsWebController extends Controller
{
    /**
     * Accounts queue page - shows all pending_payment records.
     */
    public function queue(Request $request)
    {
        $query = PatientRecord::where('status', 'pending_payment')
            ->with([
                'patient',
                'currentDepartment',
                'registrar',
                'invoices' => function ($query) {
                    $query->orderBy('created_at', 'desc');
                },
            ]);

        // Optional filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->whereHas('patient', function ($q) use ($search) {
                $q->where('patient_number', 'like', "%{$search}%")
                  ->orWhere('full_name', 'like', "%{$search}%");
            });
        }

        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->input('start_date'));
        }

        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->input('end_date'));
        }

        $pendingRecords = $query->orderBy('created_at', 'asc')->paginate(20);

        return Inertia::render('Accounts/Queue', [
            'pendingRecords' => $pendingRecords,
            'filters' => $request->only(['search', 'start_date', 'end_date']),
        ]);
    }

    /**
     * Invoice creation page for a specific patient record.
     */
    public function createInvoice(string $patientRecordId)
    {
        $patientRecord = PatientRecord::with([
            'patient',
            'registrar',
            'invoices.items',
        ])->findOrFail($patientRecordId);

        // Get common service items (could be from a services table in production)
        $commonServices = [
            ['name' => 'Registration Fee', 'unit_price' => 5000],
            ['name' => 'Card Fee', 'unit_price' => 2000],
            ['name' => 'Consultation Fee', 'unit_price' => 10000],
            ['name' => 'Lab Test - Blood Count', 'unit_price' => 8000],
            ['name' => 'Lab Test - Urinalysis', 'unit_price' => 5000],
            ['name' => 'X-Ray', 'unit_price' => 15000],
            ['name' => 'Ultrasound', 'unit_price' => 20000],
            ['name' => 'Pharmacy - Prescription', 'unit_price' => 0],
        ];

        return Inertia::render('Accounts/CreateInvoice', [
            'patientRecord' => $patientRecord,
            'commonServices' => $commonServices,
        ]);
    }

    /**
     * Invoice details page with payment recording.
     */
    public function invoiceDetails(string $invoiceId)
    {
        $invoice = Invoice::with([
            'items',
            'payments.accountant',
            'patient',
            'patientRecord.registrar',
            'creator',
        ])->findOrFail($invoiceId);

        return Inertia::render('Accounts/InvoiceDetails', [
            'invoice' => $invoice,
        ]);
    }

    /**
     * All invoices page (for viewing and searching).
     */
    public function invoices(Request $request)
    {
        $query = Invoice::with([
            'patient',
            'patientRecord',
            'creator',
        ]);

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('patient', function ($pq) use ($search) {
                      $pq->where('patient_number', 'like', "%{$search}%")
                         ->orWhere('full_name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->input('start_date'));
        }

        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->input('end_date'));
        }

        $invoices = $query->orderBy('created_at', 'desc')->paginate(20);

        return Inertia::render('Accounts/Invoices', [
            'invoices' => $invoices,
            'filters' => $request->only(['status', 'search', 'start_date', 'end_date']),
        ]);
    }
}
