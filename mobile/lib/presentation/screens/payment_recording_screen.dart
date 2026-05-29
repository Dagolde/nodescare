import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../domain/entities/invoice.dart';
import '../../domain/entities/patient_record.dart';
import '../providers/accounts_provider.dart';
import '../utils/receipt_generator.dart';

class PaymentRecordingScreen extends ConsumerStatefulWidget {
  final Invoice invoice;
  final PatientRecord patientRecord;

  const PaymentRecordingScreen({
    super.key,
    required this.invoice,
    required this.patientRecord,
  });

  @override
  ConsumerState<PaymentRecordingScreen> createState() => _PaymentRecordingScreenState();
}

class _PaymentRecordingScreenState extends ConsumerState<PaymentRecordingScreen> {
  final _formKey = GlobalKey<FormState>();
  String _paymentMethod = 'cash';
  final _amountController = TextEditingController();
  final _referenceController = TextEditingController();
  final _notesController = TextEditingController();

  late Invoice _currentInvoice;

  @override
  void initState() {
    super.initState();
    _currentInvoice = widget.invoice;
    _amountController.text = _currentInvoice.outstandingBalance.toStringAsFixed(2);
  }

  @override
  void dispose() {
    _amountController.dispose();
    _referenceController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  void _recordPayment() async {
    if (!_formKey.currentState!.validate()) return;

    final paymentData = {
      'payment_method': _paymentMethod,
      'amount': double.parse(_amountController.text),
      if (_referenceController.text.isNotEmpty) 'reference': _referenceController.text,
      if (_notesController.text.isNotEmpty) 'notes': _notesController.text,
    };

    await ref.read(paymentRecordingProvider.notifier).recordPayment(
          _currentInvoice.id,
          paymentData,
        );
  }

  void _settleInvoice() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Settle Invoice'),
        content: const Text(
          'Settle this invoice and route the patient to the first clinical department?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, true),
            style: ElevatedButton.styleFrom(backgroundColor: Colors.green),
            child: const Text('Settle', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      await ref.read(invoiceSettlementProvider.notifier).settleInvoice(_currentInvoice.id);
    }
  }

  void _generateReceipt() async {
    try {
      await ReceiptGenerator.generateAndPrintReceipt(_currentInvoice, widget.patientRecord);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Receipt generated successfully')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error generating receipt: $e')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final paymentRecordingState = ref.watch(paymentRecordingProvider);
    final settlementState = ref.watch(invoiceSettlementProvider);

    ref.listen<AsyncValue<Invoice?>>(paymentRecordingProvider, (previous, next) {
      next.whenOrNull(
        data: (invoice) {
          if (invoice != null) {
            setState(() {
              _currentInvoice = invoice;
              _amountController.text = invoice.outstandingBalance.toStringAsFixed(2);
            });
            
            // Check if invoice was auto-settled (this would be indicated by the response)
            // For now, we'll check if the invoice is paid and show appropriate message
            if (invoice.isPaid) {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text('Payment recorded and invoice automatically settled! Patient routed to clinical department.'),
                  backgroundColor: Colors.green,
                  duration: Duration(seconds: 4),
                ),
              );
              // Navigate back to accounts queue after auto-settlement
              Future.delayed(const Duration(seconds: 2), () {
                if (mounted) {
                  Navigator.popUntil(context, (route) => route.isFirst);
                }
              });
            } else {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text('Payment recorded successfully'),
                  backgroundColor: Colors.green,
                ),
              );
            }
            ref.read(paymentRecordingProvider.notifier).reset();
          }
        },
        error: (error, stack) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Error: ${error.toString()}'),
              backgroundColor: Colors.red,
            ),
          );
        },
      );
    });

    ref.listen<AsyncValue<Invoice?>>(invoiceSettlementProvider, (previous, next) {
      next.whenOrNull(
        data: (invoice) {
          if (invoice != null) {
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(
                content: Text('Invoice settled! Patient routed to clinical department.'),
                backgroundColor: Colors.green,
              ),
            );
            Navigator.popUntil(context, (route) => route.isFirst);
          }
        },
        error: (error, stack) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Error: ${error.toString()}'),
              backgroundColor: Colors.red,
            ),
          );
        },
      );
    });

    return Scaffold(
      appBar: AppBar(
        title: const Text('Record Payment'),
        backgroundColor: Theme.of(context).colorScheme.inversePrimary,
      ),
      body: SingleChildScrollView(
        child: Column(
          children: [
            // Invoice summary
            Card(
              margin: const EdgeInsets.all(16),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Invoice ${_currentInvoice.invoiceNumber}',
                      style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                    ),
                    const Divider(),
                    _row('Patient', widget.patientRecord.patient?.fullName ?? 'N/A'),
                    _row('Patient No', widget.patientRecord.patient?.patientNumber ?? 'N/A'),
                    _row('Total', '₦${_currentInvoice.totalAmount.toStringAsFixed(2)}'),
                    _row('Paid', '₦${_currentInvoice.paidAmount.toStringAsFixed(2)}'),
                    _row(
                      'Outstanding',
                      '₦${_currentInvoice.outstandingBalance.toStringAsFixed(2)}',
                      highlight: true,
                    ),
                    _row('Status', _currentInvoice.status.toUpperCase()),
                  ],
                ),
              ),
            ),

            // Invoice items
            if (_currentInvoice.items.isNotEmpty)
              Card(
                margin: const EdgeInsets.symmetric(horizontal: 16),
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Items', style: TextStyle(fontWeight: FontWeight.bold)),
                      const Divider(),
                      ..._currentInvoice.items.map((item) => Padding(
                            padding: const EdgeInsets.symmetric(vertical: 4),
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Expanded(child: Text('${item.description} (x${item.quantity})')),
                                Text('₦${item.totalPrice.toStringAsFixed(2)}'),
                              ],
                            ),
                          )),
                    ],
                  ),
                ),
              ),

            const SizedBox(height: 16),

            // Payment form
            if (!_currentInvoice.isPaid)
              Form(
                key: _formKey,
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Record Payment',
                        style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                      ),
                      const SizedBox(height: 16),
                      DropdownButtonFormField<String>(
                        initialValue: _paymentMethod,
                        decoration: const InputDecoration(
                          labelText: 'Payment Method',
                          border: OutlineInputBorder(),
                        ),
                        items: const [
                          DropdownMenuItem(value: 'cash', child: Text('Cash')),
                          DropdownMenuItem(value: 'card', child: Text('Card/POS')),
                          DropdownMenuItem(value: 'transfer', child: Text('Bank Transfer')),
                          DropdownMenuItem(value: 'hmo', child: Text('HMO/Insurance')),
                          DropdownMenuItem(value: 'other', child: Text('Other')),
                        ],
                        onChanged: (value) => setState(() => _paymentMethod = value!),
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: _amountController,
                        decoration: const InputDecoration(
                          labelText: 'Amount (₦)',
                          border: OutlineInputBorder(),
                        ),
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
                        validator: (value) {
                          if (value == null || value.isEmpty) return 'Amount is required';
                          final amount = double.tryParse(value);
                          if (amount == null || amount <= 0) return 'Enter a valid amount';
                          return null;
                        },
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: _referenceController,
                        decoration: const InputDecoration(
                          labelText: 'Reference / Transaction ID (optional)',
                          border: OutlineInputBorder(),
                        ),
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: _notesController,
                        decoration: const InputDecoration(
                          labelText: 'Notes (optional)',
                          border: OutlineInputBorder(),
                        ),
                        maxLines: 2,
                      ),
                      const SizedBox(height: 24),
                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton(
                          onPressed: paymentRecordingState.isLoading ? null : _recordPayment,
                          style: ElevatedButton.styleFrom(
                            padding: const EdgeInsets.symmetric(vertical: 16),
                            backgroundColor: Theme.of(context).colorScheme.primary,
                            foregroundColor: Colors.white,
                          ),
                          child: paymentRecordingState.isLoading
                              ? const SizedBox(
                                  width: 22,
                                  height: 22,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2.5,
                                    color: Colors.white,
                                  ),
                                )
                              : const Text('Record Payment', style: TextStyle(fontSize: 16)),
                        ),
                      ),
                    ],
                  ),
                ),
              ),

            // Manual settle button (fallback if auto-settlement failed)
            if (_currentInvoice.isPaid)
              Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  children: [
                    const Text(
                      'If the patient was not automatically routed, use the button below:',
                      style: TextStyle(fontSize: 12, color: Colors.grey),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 8),
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton.icon(
                        onPressed: settlementState.isLoading ? null : _settleInvoice,
                        icon: const Icon(Icons.check_circle),
                        label: const Text('Manually Settle & Route Patient', style: TextStyle(fontSize: 16)),
                        style: ElevatedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 16),
                          backgroundColor: Colors.orange,
                          foregroundColor: Colors.white,
                        ),
                      ),
                    ),
                  ],
                ),
              ),

            // Receipt button (settled invoices)
            if (_currentInvoice.isPaid)
              Padding(
                padding: const EdgeInsets.all(16),
                child: SizedBox(
                  width: double.infinity,
                  child: OutlinedButton.icon(
                    onPressed: _generateReceipt,
                    icon: const Icon(Icons.receipt_long),
                    label: const Text('Generate & Print Receipt', style: TextStyle(fontSize: 16)),
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 16),
                    ),
                  ),
                ),
              ),

            // Payment history
            if (_currentInvoice.payments.isNotEmpty)
              Card(
                margin: const EdgeInsets.all(16),
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Payment History', style: TextStyle(fontWeight: FontWeight.bold)),
                      const Divider(),
                      ..._currentInvoice.payments.map((payment) => Padding(
                            padding: const EdgeInsets.symmetric(vertical: 8),
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      payment.paymentMethod.toUpperCase(),
                                      style: const TextStyle(fontWeight: FontWeight.w500),
                                    ),
                                    if (payment.reference != null)
                                      Text(
                                        'Ref: ${payment.reference}',
                                        style: const TextStyle(fontSize: 12, color: Colors.grey),
                                      ),
                                    Text(
                                      _formatDate(payment.createdAt),
                                      style: const TextStyle(fontSize: 12, color: Colors.grey),
                                    ),
                                  ],
                                ),
                                Text(
                                  '₦${payment.amount.toStringAsFixed(2)}',
                                  style: const TextStyle(
                                    fontWeight: FontWeight.bold,
                                    color: Colors.green,
                                  ),
                                ),
                              ],
                            ),
                          )),
                    ],
                  ),
                ),
              ),

            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }

  Widget _row(String label, String value, {bool highlight = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: TextStyle(fontWeight: highlight ? FontWeight.bold : FontWeight.normal)),
          Text(
            value,
            style: TextStyle(
              fontWeight: highlight ? FontWeight.bold : FontWeight.normal,
              color: highlight ? Theme.of(context).colorScheme.primary : null,
            ),
          ),
        ],
      ),
    );
  }

  String _formatDate(DateTime dt) =>
      '${dt.day}/${dt.month}/${dt.year} ${dt.hour}:${dt.minute.toString().padLeft(2, '0')}';
}
