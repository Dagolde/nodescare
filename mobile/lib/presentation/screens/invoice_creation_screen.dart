import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../domain/entities/invoice.dart';
import '../../domain/entities/patient_record.dart';
import '../providers/accounts_provider.dart';
import 'payment_recording_screen.dart';

class InvoiceCreationScreen extends ConsumerStatefulWidget {
  final PatientRecord patientRecord;

  const InvoiceCreationScreen({
    super.key,
    required this.patientRecord,
  });

  @override
  ConsumerState<InvoiceCreationScreen> createState() => _InvoiceCreationScreenState();
}

class _InvoiceCreationScreenState extends ConsumerState<InvoiceCreationScreen> {
  final _formKey = GlobalKey<FormState>();
  final List<_InvoiceItemForm> _items = [];

  final List<String> _commonServices = [
    'Registration Fee',
    'Card Fee',
    'Consultation Fee',
    'Lab Test',
    'X-Ray',
    'Ultrasound',
    'Pharmacy',
    'Admission Fee',
    'Nursing Care',
    'Procedure Fee',
  ];

  @override
  void initState() {
    super.initState();
    _initializeItems();
  }

  void _initializeItems() {
    final record = widget.patientRecord;
    // Pre-populate from pharmacy bill items
    if (record.pharmacyBillItems != null && record.pharmacyBillItems!.isNotEmpty) {
      for (final item in record.pharmacyBillItems!) {
        _items.add(_InvoiceItemForm(
          descriptionController: TextEditingController(
            text: item['drug_name']?.toString() ?? '',
          ),
          unitPriceController: TextEditingController(
            text: item['unit_price']?.toString() ?? '',
          ),
          quantityController: TextEditingController(
            text: item['quantity']?.toString() ?? '1',
          ),
        ));
      }
    }
    // Pre-populate from lab bill items
    else if (record.labBillItems != null && record.labBillItems!.isNotEmpty) {
      for (final item in record.labBillItems!) {
        _items.add(_InvoiceItemForm(
          descriptionController: TextEditingController(
            text: item['test_name']?.toString() ?? '',
          ),
          unitPriceController: TextEditingController(
            text: item['unit_price']?.toString() ?? '',
          ),
          quantityController: TextEditingController(
            text: item['quantity']?.toString() ?? '1',
          ),
        ));
      }
    }
    // Default: add one empty item
    else {
      _addItem();
    }
  }

  void _addItem() {
    setState(() {
      _items.add(_InvoiceItemForm(
        descriptionController: TextEditingController(),
        unitPriceController: TextEditingController(),
        quantityController: TextEditingController(text: '1'),
      ));
    });
  }

  void _removeItem(int index) {
    setState(() {
      _items[index].dispose();
      _items.removeAt(index);
    });
  }

  double _calculateItemTotal(_InvoiceItemForm item) {
    final unitPrice = double.tryParse(item.unitPriceController.text) ?? 0;
    final quantity = int.tryParse(item.quantityController.text) ?? 0;
    return unitPrice * quantity;
  }

  double _calculateTotal() {
    return _items.fold(0, (sum, item) => sum + _calculateItemTotal(item));
  }

  void _submitInvoice() async {
    if (!_formKey.currentState!.validate()) return;

    if (_items.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please add at least one invoice item')),
      );
      return;
    }

    final invoiceItems = _items.map((item) {
      final unitPrice = double.parse(item.unitPriceController.text);
      final quantity = int.parse(item.quantityController.text);
      return InvoiceItem(
        id: '',
        invoiceId: '',
        description: item.descriptionController.text,
        unitPrice: unitPrice,
        quantity: quantity,
        totalPrice: unitPrice * quantity,
      );
    }).toList();

    await ref.read(invoiceCreationProvider.notifier).createInvoice(
          widget.patientRecord.id,
          invoiceItems,
        );
  }

  /// Load the existing invoice for this patient record and go to payment screen.
  Future<void> _loadExistingInvoiceAndRedirect() async {
    try {
      final remote = ref.read(remoteDataSourceProvider);
      final response = await remote.get(
        '/api/v1/patient-records/${widget.patientRecord.id}/invoices',
      );
      if (response.data['success'] == true) {
        final List<dynamic> data = response.data['data'];
        if (data.isNotEmpty) {
          final invoice = Invoice.fromJson(data.first);
          if (!mounted) return;
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Invoice already exists — opening for payment.'),
              backgroundColor: Colors.orange,
            ),
          );
          Navigator.pushReplacement(
            context,
            MaterialPageRoute(
              builder: (_) => PaymentRecordingScreen(
                invoice: invoice,
                patientRecord: widget.patientRecord,
              ),
            ),
          );
          return;
        }
      }
    } catch (_) {}
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('An invoice already exists for this patient.'),
          backgroundColor: Colors.orange,
        ),
      );
    }
  }

  IconData _paymentContextIcon(String? context) {
    switch (context) {
      case 'pharmacy_billing':
        return Icons.medication;
      case 'lab_billing':
        return Icons.science;
      case 'admission_billing':
        return Icons.local_hospital;
      case 'lab_referral':
        return Icons.science;
      default:
        return Icons.payment;
    }
  }

  Color _paymentContextColor(String? context) {
    switch (context) {
      case 'pharmacy_billing':
        return Colors.purple;
      case 'lab_billing':
        return Colors.blue;
      case 'admission_billing':
        return Colors.green;
      case 'lab_referral':
        return Colors.blue;
      default:
        return Colors.grey;
    }
  }

  @override
  void dispose() {
    for (var item in _items) {
      item.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final invoiceCreationState = ref.watch(invoiceCreationProvider);

    ref.listen<AsyncValue<Invoice?>>(invoiceCreationProvider, (previous, next) {
      next.whenOrNull(
        data: (invoice) {
          if (invoice != null) {
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(
                content: Text('Invoice created successfully'),
                backgroundColor: Colors.green,
              ),
            );
            Navigator.pushReplacement(
              context,
              MaterialPageRoute(
                builder: (context) => PaymentRecordingScreen(
                  invoice: invoice,
                  patientRecord: widget.patientRecord,
                ),
              ),
            );
          }
        },
        error: (error, stack) {
          final msg = error.toString();
          // If invoice already exists, load it and go to payment screen
          if (msg.contains('INVOICE_EXISTS') || msg.contains('already exists')) {
            _loadExistingInvoiceAndRedirect();
          } else {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text('Error: $msg'),
                backgroundColor: Colors.red,
              ),
            );
          }
        },
      );
    });

    return Scaffold(
      appBar: AppBar(
        title: const Text('Create Invoice'),
        backgroundColor: Theme.of(context).colorScheme.inversePrimary,
      ),
      body: Form(
        key: _formKey,
        child: Column(
          children: [
            // Patient info card
            Card(
              margin: const EdgeInsets.all(16),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      widget.patientRecord.patient?.fullName ?? 'Unknown Patient',
                      style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 4),
                    Text('Patient No: ${widget.patientRecord.patient?.patientNumber ?? 'N/A'}'),
                    Text('Chief Complaint: ${widget.patientRecord.patient?.chiefComplaint ?? 'N/A'}'),
                    
                    // Display payment reason and clinical notes
                    if (widget.patientRecord.paymentReason != null) ...[
                      const SizedBox(height: 8),
                      const Divider(),
                      const SizedBox(height: 8),
                      Row(
                        children: [
                          Icon(
                            _paymentContextIcon(widget.patientRecord.paymentContext),
                            size: 20,
                            color: _paymentContextColor(widget.patientRecord.paymentContext),
                          ),
                          const SizedBox(width: 8),
                          Text(
                            widget.patientRecord.paymentReason!,
                            style: TextStyle(
                              fontWeight: FontWeight.bold,
                              color: _paymentContextColor(widget.patientRecord.paymentContext),
                            ),
                          ),
                        ],
                      ),
                      if (widget.patientRecord.paymentContext == 'pharmacy_billing' &&
                          widget.patientRecord.pharmacyBillItems != null) ...[
                        const SizedBox(height: 8),
                        Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: Colors.purple.shade50,
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: Colors.purple.shade200),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'Drugs Prescribed (pre-populated):',
                                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                              ),
                              const SizedBox(height: 4),
                              ...widget.patientRecord.pharmacyBillItems!.map((item) => Text(
                                '• ${item['drug_name']} — ₦${item['unit_price']} × ${item['quantity']}',
                                style: const TextStyle(fontSize: 13),
                              )),
                            ],
                          ),
                        ),
                      ],
                      if (widget.patientRecord.paymentContext == 'lab_billing' &&
                          widget.patientRecord.labBillItems != null) ...[
                        const SizedBox(height: 8),
                        Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: Colors.blue.shade50,
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: Colors.blue.shade200),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'Lab Tests Requested (pre-populated):',
                                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                              ),
                              const SizedBox(height: 4),
                              ...widget.patientRecord.labBillItems!.map((item) => Text(
                                '• ${item['test_name']} — ₦${item['unit_price']} × ${item['quantity']}',
                                style: const TextStyle(fontSize: 13),
                              )),
                            ],
                          ),
                        ),
                      ],
                      if (widget.patientRecord.paymentContext == 'admission_billing') ...[
                        const SizedBox(height: 8),
                        Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: Colors.green.shade50,
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: Colors.green.shade200),
                          ),
                          child: const Text(
                            'Admission Payment — please add the admission fee and any related charges.',
                            style: TextStyle(fontSize: 13),
                          ),
                        ),
                      ],
                      if (widget.patientRecord.clinicalNotes != null &&
                          widget.patientRecord.paymentContext != 'pharmacy_billing' &&
                          widget.patientRecord.paymentContext != 'lab_billing' &&
                          widget.patientRecord.paymentContext != 'admission_billing') ...[
                        const SizedBox(height: 8),
                        Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: Colors.grey.shade100,
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: Colors.grey.shade300),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                widget.patientRecord.paymentContext == 'lab_referral'
                                    ? 'Lab Tests Requested:'
                                    : 'Drugs Prescribed:',
                                style: const TextStyle(
                                  fontWeight: FontWeight.bold,
                                  fontSize: 13,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                widget.patientRecord.clinicalNotes!,
                                style: const TextStyle(fontSize: 13),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ],
                  ],
                ),
              ),
            ),

            // Invoice items list
            Expanded(
              child: ListView.builder(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                itemCount: _items.length,
                itemBuilder: (context, index) {
                  final item = _items[index];
                  return Card(
                    margin: const EdgeInsets.only(bottom: 12),
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(
                                'Item ${index + 1}',
                                style: const TextStyle(fontWeight: FontWeight.bold),
                              ),
                              if (_items.length > 1)
                                IconButton(
                                  icon: const Icon(Icons.delete, color: Colors.red),
                                  onPressed: () => _removeItem(index),
                                ),
                            ],
                          ),
                          const SizedBox(height: 8),
                          // Description with dropdown suggestions
                          Autocomplete<String>(
                            optionsBuilder: (textEditingValue) {
                              if (textEditingValue.text.isEmpty) {
                                return _commonServices;
                              }
                              return _commonServices.where((s) => s
                                  .toLowerCase()
                                  .contains(textEditingValue.text.toLowerCase()));
                            },
                            onSelected: (value) {
                              item.descriptionController.text = value;
                            },
                            fieldViewBuilder: (context, controller, focusNode, onSubmitted) {
                              // Sync with our controller
                              controller.text = item.descriptionController.text;
                              controller.addListener(() {
                                item.descriptionController.text = controller.text;
                              });
                              return TextFormField(
                                controller: controller,
                                focusNode: focusNode,
                                decoration: const InputDecoration(
                                  labelText: 'Description / Service',
                                  border: OutlineInputBorder(),
                                ),
                                validator: (_) {
                                  if (item.descriptionController.text.isEmpty) {
                                    return 'Description is required';
                                  }
                                  return null;
                                },
                              );
                            },
                          ),
                          const SizedBox(height: 8),
                          Row(
                            children: [
                              Expanded(
                                flex: 2,
                                child: TextFormField(
                                  controller: item.unitPriceController,
                                  decoration: const InputDecoration(
                                    labelText: 'Unit Price (₦)',
                                    border: OutlineInputBorder(),
                                  ),
                                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                                  validator: (value) {
                                    if (value == null || value.isEmpty) return 'Required';
                                    if (double.tryParse(value) == null) return 'Invalid';
                                    return null;
                                  },
                                  onChanged: (_) => setState(() {}),
                                ),
                              ),
                              const SizedBox(width: 8),
                              Expanded(
                                child: TextFormField(
                                  controller: item.quantityController,
                                  decoration: const InputDecoration(
                                    labelText: 'Qty',
                                    border: OutlineInputBorder(),
                                  ),
                                  keyboardType: TextInputType.number,
                                  validator: (value) {
                                    if (value == null || value.isEmpty) return 'Required';
                                    if (int.tryParse(value) == null) return 'Invalid';
                                    return null;
                                  },
                                  onChanged: (_) => setState(() {}),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 8),
                          Text(
                            'Total: ₦${_calculateItemTotal(item).toStringAsFixed(2)}',
                            style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.green),
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),

            // Add item button
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: OutlinedButton.icon(
                onPressed: _addItem,
                icon: const Icon(Icons.add),
                label: const Text('Add Item'),
              ),
            ),

            const SizedBox(height: 8),

            // Total and submit
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.grey[100],
                border: Border(top: BorderSide(color: Colors.grey[300]!)),
              ),
              child: Column(
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Total:',
                        style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                      ),
                      Text(
                        '₦${_calculateTotal().toStringAsFixed(2)}',
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                          color: Colors.green,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: invoiceCreationState.isLoading ? null : _submitInvoice,
                      style: ElevatedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        backgroundColor: Theme.of(context).colorScheme.primary,
                        foregroundColor: Colors.white,
                      ),
                      child: invoiceCreationState.isLoading
                          ? const SizedBox(
                              width: 22,
                              height: 22,
                              child: CircularProgressIndicator(
                                strokeWidth: 2.5,
                                color: Colors.white,
                              ),
                            )
                          : const Text('Create Invoice', style: TextStyle(fontSize: 16)),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _InvoiceItemForm {
  final TextEditingController descriptionController;
  final TextEditingController unitPriceController;
  final TextEditingController quantityController;

  _InvoiceItemForm({
    required this.descriptionController,
    required this.unitPriceController,
    required this.quantityController,
  });

  void dispose() {
    descriptionController.dispose();
    unitPriceController.dispose();
    quantityController.dispose();
  }
}
