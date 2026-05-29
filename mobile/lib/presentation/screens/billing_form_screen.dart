import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../domain/entities/patient_record.dart';
import '../providers/billing_provider.dart';

enum BillingType { pharmacy, lab }

class BillingFormScreen extends ConsumerStatefulWidget {
  final String patientRecordId;
  final BillingType billingType;
  final PatientRecord? patientRecord; // optional — for showing notes

  const BillingFormScreen({
    super.key,
    required this.patientRecordId,
    required this.billingType,
    this.patientRecord,
  });

  @override
  ConsumerState<BillingFormScreen> createState() => _BillingFormScreenState();
}

class _BillingFormScreenState extends ConsumerState<BillingFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final List<_BillingLineItem> _items = [];

  bool get _isPharmacy => widget.billingType == BillingType.pharmacy;

  String get _nameLabel => _isPharmacy ? 'Drug Name' : 'Test Name';
  String get _nameKey => _isPharmacy ? 'drug_name' : 'test_name';
  String get _title => _isPharmacy ? 'Bill Drugs' : 'Bill Investigations';

  @override
  void initState() {
    super.initState();
    _addItem();
  }

  void _addItem() {
    setState(() {
      _items.add(_BillingLineItem(
        nameController: TextEditingController(),
        unitPriceController: TextEditingController(),
        quantity: 1,
      ));
    });
  }

  void _removeItem(int index) {
    setState(() {
      _items[index].dispose();
      _items.removeAt(index);
    });
  }

  void _incrementQuantity(int index) {
    setState(() => _items[index].quantity++);
  }

  void _decrementQuantity(int index) {
    if (_items[index].quantity > 1) {
      setState(() => _items[index].quantity--);
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    if (_items.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please add at least one item')),
      );
      return;
    }

    final items = _items.map((item) => {
      _nameKey: item.nameController.text.trim(),
      'unit_price': double.parse(item.unitPriceController.text.trim()),
      'quantity': item.quantity,
    }).toList();

    if (_isPharmacy) {
      await ref.read(pharmacyBillingProvider.notifier).submitPharmacyBilling(
        widget.patientRecordId,
        items,
      );
    } else {
      await ref.read(labBillingProvider.notifier).submitLabBilling(
        widget.patientRecordId,
        items,
      );
    }
  }

  @override
  void dispose() {
    for (final item in _items) {
      item.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final billingState = _isPharmacy
        ? ref.watch(pharmacyBillingProvider)
        : ref.watch(labBillingProvider);

    ref.listen<AsyncValue<void>>(
      _isPharmacy ? pharmacyBillingProvider : labBillingProvider,
      (_, next) {
        next.whenOrNull(
          data: (_) {
            if (!mounted) return;
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(_isPharmacy
                    ? 'Pharmacy billing submitted successfully'
                    : 'Lab billing submitted successfully'),
                backgroundColor: Colors.green,
              ),
            );
            Navigator.pop(context);
          },
          error: (error, _) {
            if (!mounted) return;
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text('Error: $error'),
                backgroundColor: Colors.red,
              ),
            );
          },
        );
      },
    );

    return Scaffold(
      appBar: AppBar(
        title: Text(_title),
        backgroundColor: Theme.of(context).colorScheme.inversePrimary,
      ),
      body: Form(
        key: _formKey,
        child: Column(
          children: [
            // ── Doctor's Notes Banner ──────────────────────────────────
            if (!_isPharmacy && widget.patientRecord?.investigationNotes != null)
              Container(
                margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.blue.shade50,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: Colors.blue.shade300),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(Icons.science, color: Colors.blue.shade700, size: 18),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Tests Requested by Doctor',
                            style: TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 13,
                              color: Colors.blue.shade800,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            widget.patientRecord!.investigationNotes!,
                            style: TextStyle(
                              fontSize: 13,
                              color: Colors.blue.shade900,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            if (_isPharmacy && widget.patientRecord?.prescriptionNotes != null)
              Container(
                margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.purple.shade50,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: Colors.purple.shade300),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(Icons.medication, color: Colors.purple.shade700, size: 18),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Prescription from Doctor',
                            style: TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 13,
                              color: Colors.purple.shade800,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            widget.patientRecord!.prescriptionNotes!,
                            style: TextStyle(
                              fontSize: 13,
                              color: Colors.purple.shade900,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            Expanded(
              child: ListView.builder(
                padding: const EdgeInsets.all(16),
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
                          TextFormField(
                            controller: item.nameController,
                            decoration: InputDecoration(
                              labelText: _nameLabel,
                              border: const OutlineInputBorder(),
                            ),
                            validator: (value) {
                              if (value == null || value.trim().isEmpty) {
                                return '$_nameLabel is required';
                              }
                              return null;
                            },
                          ),
                          const SizedBox(height: 8),
                          TextFormField(
                            controller: item.unitPriceController,
                            decoration: const InputDecoration(
                              labelText: 'Unit Price (₦)',
                              border: OutlineInputBorder(),
                            ),
                            keyboardType: const TextInputType.numberWithOptions(decimal: true),
                            validator: (value) {
                              if (value == null || value.trim().isEmpty) return 'Required';
                              final price = double.tryParse(value.trim());
                              if (price == null) return 'Invalid price';
                              if (price <= 0) return 'Price must be greater than 0';
                              return null;
                            },
                          ),
                          const SizedBox(height: 8),
                          Row(
                            children: [
                              const Text('Quantity:', style: TextStyle(fontSize: 14)),
                              const SizedBox(width: 12),
                              IconButton(
                                icon: const Icon(Icons.remove_circle_outline),
                                onPressed: () => _decrementQuantity(index),
                                color: Colors.red,
                              ),
                              Text(
                                '${item.quantity}',
                                style: const TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                              IconButton(
                                icon: const Icon(Icons.add_circle_outline),
                                onPressed: () => _incrementQuantity(index),
                                color: Colors.green,
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: OutlinedButton.icon(
                onPressed: _addItem,
                icon: const Icon(Icons.add),
                label: const Text('Add Item'),
              ),
            ),
            const SizedBox(height: 8),
            Padding(
              padding: const EdgeInsets.all(16),
              child: SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: billingState.isLoading ? null : _submit,
                  style: ElevatedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    backgroundColor: Theme.of(context).colorScheme.primary,
                    foregroundColor: Colors.white,
                  ),
                  child: billingState.isLoading
                      ? const SizedBox(
                          width: 22,
                          height: 22,
                          child: CircularProgressIndicator(
                            strokeWidth: 2.5,
                            color: Colors.white,
                          ),
                        )
                      : Text(
                          _isPharmacy ? 'Submit Drug Bill' : 'Submit Lab Bill',
                          style: const TextStyle(fontSize: 16),
                        ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _BillingLineItem {
  final TextEditingController nameController;
  final TextEditingController unitPriceController;
  int quantity;

  _BillingLineItem({
    required this.nameController,
    required this.unitPriceController,
    required this.quantity,
  });

  void dispose() {
    nameController.dispose();
    unitPriceController.dispose();
  }
}
