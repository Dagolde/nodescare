import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../domain/entities/patient_record.dart';
import '../../domain/repositories/clinical_repository.dart';
import '../providers/clinical_provider.dart';
import '../widgets/clinical_widgets.dart';

/// Pharmacy dispensing screen.
/// Allows recording multiple medications dispensed to a patient.
class DispensingScreen extends ConsumerStatefulWidget {
  final PatientRecord patientRecord;

  const DispensingScreen({super.key, required this.patientRecord});

  @override
  ConsumerState<DispensingScreen> createState() => _DispensingScreenState();
}

class _DispensingScreenState extends ConsumerState<DispensingScreen> {
  final _formKey = GlobalKey<FormState>();
  final _notesController = TextEditingController();
  final List<_MedForm> _medications = [];

  @override
  void initState() {
    super.initState();
    _addMedication();
  }

  @override
  void dispose() {
    _notesController.dispose();
    for (final med in _medications) {
      med.dispose();
    }
    super.dispose();
  }

  void _addMedication() {
    setState(() {
      _medications.add(_MedForm(
        nameController: TextEditingController(),
        dosageController: TextEditingController(),
        quantityController: TextEditingController(text: '1'),
        instructionsController: TextEditingController(),
      ));
    });
  }

  void _removeMedication(int index) {
    setState(() {
      _medications[index].dispose();
      _medications.removeAt(index);
    });
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_medications.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Add at least one medication')),
      );
      return;
    }

    final medications = _medications.map((m) => MedicationItem(
          name: m.nameController.text.trim(),
          dosage: m.dosageController.text.trim(),
          quantity: int.tryParse(m.quantityController.text.trim()) ?? 1,
          instructions: m.instructionsController.text.trim().isNotEmpty
              ? m.instructionsController.text.trim()
              : null,
        )).toList();

    await ref.read(dispensingProvider.notifier).recordDispensing(
          patientRecordId: widget.patientRecord.id,
          medications: medications,
          notes: _notesController.text.trim().isNotEmpty
              ? _notesController.text.trim()
              : null,
        );
  }

  @override
  Widget build(BuildContext context) {
    final dispensingState = ref.watch(dispensingProvider);

    ref.listen<AsyncValue>(dispensingProvider, (_, next) {
      next.whenOrNull(
        data: (result) {
          if (result != null) {
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(
                content: Text('Dispensing recorded successfully'),
                backgroundColor: Colors.green,
              ),
            );
            ref.read(dispensingProvider.notifier).reset();
            Navigator.of(context).pop(true);
          }
        },
        error: (e, _) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(e.toString().replaceFirst('Exception: ', '')),
              backgroundColor: Colors.red,
            ),
          );
        },
      );
    });

    final isLoading = dispensingState is AsyncLoading;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Record Dispensing'),
        backgroundColor: Theme.of(context).colorScheme.inversePrimary,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              PatientInfoCard(patientRecord: widget.patientRecord),
              const SizedBox(height: 16),

              // Medications list
              ...List.generate(_medications.length, (index) {
                final med = _medications[index];
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
                              'Medication ${index + 1}',
                              style: const TextStyle(fontWeight: FontWeight.bold),
                            ),
                            if (_medications.length > 1)
                              IconButton(
                                icon: const Icon(Icons.delete, color: Colors.red),
                                onPressed: () => _removeMedication(index),
                              ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        TextFormField(
                          controller: med.nameController,
                          decoration: const InputDecoration(
                            labelText: 'Drug Name *',
                            border: OutlineInputBorder(),
                            prefixIcon: Icon(Icons.medication),
                          ),
                          validator: (v) =>
                              (v == null || v.trim().isEmpty) ? 'Required' : null,
                        ),
                        const SizedBox(height: 8),
                        Row(
                          children: [
                            Expanded(
                              flex: 2,
                              child: TextFormField(
                                controller: med.dosageController,
                                decoration: const InputDecoration(
                                  labelText: 'Dosage *',
                                  hintText: 'e.g. 500mg',
                                  border: OutlineInputBorder(),
                                ),
                                validator: (v) =>
                                    (v == null || v.trim().isEmpty) ? 'Required' : null,
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: TextFormField(
                                controller: med.quantityController,
                                decoration: const InputDecoration(
                                  labelText: 'Qty *',
                                  border: OutlineInputBorder(),
                                ),
                                keyboardType: TextInputType.number,
                                validator: (v) {
                                  if (v == null || v.trim().isEmpty) return 'Required';
                                  if (int.tryParse(v) == null) return 'Invalid';
                                  return null;
                                },
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        TextFormField(
                          controller: med.instructionsController,
                          decoration: const InputDecoration(
                            labelText: 'Instructions (optional)',
                            hintText: 'e.g. Take twice daily after meals',
                            border: OutlineInputBorder(),
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              }),

              OutlinedButton.icon(
                onPressed: _addMedication,
                icon: const Icon(Icons.add),
                label: const Text('Add Another Medication'),
              ),

              const SizedBox(height: 16),

              TextFormField(
                controller: _notesController,
                decoration: const InputDecoration(
                  labelText: 'General Notes (optional)',
                  border: OutlineInputBorder(),
                ),
                maxLines: 3,
              ),

              const SizedBox(height: 24),

              ElevatedButton(
                onPressed: isLoading ? null : _submit,
                style: ElevatedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  backgroundColor: Theme.of(context).colorScheme.primary,
                  foregroundColor: Colors.white,
                ),
                child: isLoading
                    ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : const Text('Record Dispensing', style: TextStyle(fontSize: 16)),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MedForm {
  final TextEditingController nameController;
  final TextEditingController dosageController;
  final TextEditingController quantityController;
  final TextEditingController instructionsController;

  _MedForm({
    required this.nameController,
    required this.dosageController,
    required this.quantityController,
    required this.instructionsController,
  });

  void dispose() {
    nameController.dispose();
    dosageController.dispose();
    quantityController.dispose();
    instructionsController.dispose();
  }
}
