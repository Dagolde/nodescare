<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - {{ $invoice->invoice_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #333;
            padding: 20px;
        }

        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 30px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
        }

        .header h1 {
            font-size: 24px;
            color: #007bff;
            margin-bottom: 5px;
        }

        .header .subtitle {
            font-size: 14px;
            color: #666;
        }

        .receipt-info {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }

        .receipt-info-left,
        .receipt-info-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .info-section {
            margin-bottom: 20px;
        }

        .info-section h3 {
            font-size: 14px;
            color: #007bff;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-row {
            margin-bottom: 8px;
        }

        .info-label {
            font-weight: 600;
            color: #666;
            display: inline-block;
            width: 140px;
        }

        .info-value {
            color: #333;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items-table thead {
            background: #f8f9fa;
        }

        .items-table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
        }

        .items-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e9ecef;
        }

        .items-table tbody tr:last-child td {
            border-bottom: 2px solid #dee2e6;
        }

        .items-table tfoot td {
            padding: 10px 12px;
            font-weight: 600;
        }

        .text-right {
            text-align: right;
        }

        .total-row {
            font-size: 14px;
            color: #007bff;
        }

        .payments-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
        }

        .payments-section h3 {
            font-size: 14px;
            color: #007bff;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .payment-item {
            background: #f8f9fa;
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 4px;
        }

        .payment-item-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .payment-type {
            color: #007bff;
        }

        .payment-amount {
            color: #28a745;
        }

        .payment-details {
            font-size: 11px;
            color: #666;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
            text-align: center;
            color: #666;
            font-size: 11px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            background: #d4edda;
            color: #155724;
        }

        @media print {
            body {
                padding: 0;
            }

            .receipt-container {
                border: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="header">
            <h1>PAYMENT RECEIPT</h1>
            <div class="subtitle">Hospital Health Management System</div>
        </div>

        <div class="receipt-info">
            <div class="receipt-info-left">
                <div class="info-section">
                    <h3>Receipt Information</h3>
                    <div class="info-row">
                        <span class="info-label">Invoice Number:</span>
                        <span class="info-value">{{ $invoice->invoice_number }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status:</span>
                        <span class="status-badge">SETTLED</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Issue Date:</span>
                        <span class="info-value">{{ $invoice->created_at->format('d M Y, h:i A') }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Settlement Date:</span>
                        <span class="info-value">{{ $invoice->settled_at->format('d M Y, h:i A') }}</span>
                    </div>
                </div>
            </div>

            <div class="receipt-info-right">
                <div class="info-section">
                    <h3>Patient Information</h3>
                    <div class="info-row">
                        <span class="info-label">Patient Number:</span>
                        <span class="info-value">{{ $patient->patient_number }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Patient Name:</span>
                        <span class="info-value">{{ $patient->full_name }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Patient Type:</span>
                        <span class="info-value">{{ $invoice->is_new_patient ? 'New Patient' : 'Returning Patient' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="info-section">
            <h3>Invoice Items</h3>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Service Name</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Quantity</th>
                        <th class="text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->items as $item)
                    <tr>
                        <td>{{ $item->service_name }}</td>
                        <td class="text-right">₦{{ number_format($item->unit_price, 2) }}</td>
                        <td class="text-right">{{ $item->quantity }}</td>
                        <td class="text-right">₦{{ number_format($item->subtotal, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-right">Subtotal:</td>
                        <td class="text-right">₦{{ number_format($invoice->subtotal, 2) }}</td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="3" class="text-right">Total Amount:</td>
                        <td class="text-right">₦{{ number_format($invoice->total_amount, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="payments-section">
            <h3>Payment Details</h3>
            @foreach($invoice->payments as $payment)
            <div class="payment-item">
                <div class="payment-item-header">
                    <span class="payment-type">
                        @if($payment->payment_type === 'cash')
                            Cash Payment
                        @elseif($payment->payment_type === 'pos_card')
                            POS/Card Payment
                        @elseif($payment->payment_type === 'hmo_insurance')
                            HMO/Insurance Payment
                        @endif
                    </span>
                    <span class="payment-amount">₦{{ number_format($payment->amount, 2) }}</span>
                </div>
                <div class="payment-details">
                    <div>Date: {{ $payment->created_at->format('d M Y, h:i A') }}</div>
                    @if($payment->receipt_reference)
                    <div>Reference: {{ $payment->receipt_reference }}</div>
                    @endif
                    @if($payment->payment_type === 'hmo_insurance')
                    <div>HMO Provider: {{ $payment->hmo_provider_name }}</div>
                    <div>Policy Number: {{ $payment->hmo_policy_number }}</div>
                    @if($payment->hmo_claim_reference)
                    <div>Claim Reference: {{ $payment->hmo_claim_reference }}</div>
                    @endif
                    @endif
                    <div>Recorded by: {{ $payment->accountant->name }}</div>
                </div>
            </div>
            @endforeach

            <div style="margin-top: 20px; padding: 15px; background: #d4edda; border-radius: 4px;">
                <div style="display: flex; justify-content: space-between; font-weight: 600; font-size: 14px;">
                    <span>Total Amount Paid:</span>
                    <span style="color: #155724;">₦{{ number_format($invoice->amount_paid, 2) }}</span>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>This is a computer-generated receipt and does not require a signature.</p>
            <p>Generated on {{ now()->format('d M Y, h:i A') }}</p>
            <p style="margin-top: 10px;">Thank you for choosing our services.</p>
        </div>
    </div>
</body>
</html>
