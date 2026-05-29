import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../domain/entities/patient_record.dart';
import '../../domain/repositories/clinical_repository.dart';
import '../../domain/repositories/department_repository.dart';
import '../providers/clinical_provider.dart';
import '../providers/department_provider.dart';
import '../widgets/clinical_widgets.dart';

/// Laboratory results screen — per-test individual result entry.
class LabResultsScreen extends ConsumerStatefulWidget {
  final PatientRecord patientRecord;

  const LabResultsScreen({super.key, required this.patientRecord});

  @override
  ConsumerState<LabResultsScreen> createState() => _LabResultsScreenState();
}

class _LabResultsScreenState extends ConsumerState<LabResultsScreen> {
  // Investigation notes from doctor
  InvestigationNotes? _investigationNotes;
  bool _isLoadingInvestigation = true;

  // Per-test result controllers — one entry per test
  final List<_TestResultEntry> _entries = [];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadInvestigationNotes());
  }

  Future<void> _loadInvestigationNotes() async {
    if (!mounted) return;
    try {
      final repository = ref.read(departmentRepositoryProvider);
      final notes = await repository.getInvestigation(widget.patientRecord.id);
      if (mounted) {
        setState(() {
          _investigationNotes = notes;
          _isLoadingInvestigation = false;
          _buildEntriesFromNotes(notes);
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _isLoadingInvestigation = false;
          // Start with one blank entry if no notes
          if (_entries.isEmpty) _entries.add(_TestResultEntry());
        });
      }
    }
  }

  /// Parse investigation notes into individual test entries.
  /// Supports comma-separated, newline-separated, or numbered lists.
  void _buildEntriesFromNotes(InvestigationNotes? notes) {
    if (notes == null || notes.investigationNotes.trim().isEmpty) {
      _entries.add(_TestResultEntry());
      return;
    }

    final raw = notes.investigationNotes;
    // Split by comma, newline, or numbered list patterns like "1. FBC"
    final parts = raw
        .split(RegExp(r'[,\n]|\d+\.\s*'))
        .map((s) => s.trim())
        .where((s) => s.isNotEmpty)
        .toList();

    if (parts.isEmpty) {
      _entries.add(_TestResultEntry(testName: raw.trim()));
    } else {
      for (final part in parts) {
        _entries.add(_TestResultEntry(testName: part));
      }
    }
  }

  void _addEntry() {
    setState(() => _entries.add(_TestResultEntry()));
  }

  void _removeEntry(int index) {
    if (_entries.length <= 1) return;
    setState(() => _entries.removeAt(index));
  }

  bool get _allValid => _entries.every((e) => e.isValid);

  bool _submitted = false;

  Future<void> _submit() async {
    if (!_allValid) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please fill in all test names and results.'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    final inputs = _entries
        .map((e) => LabTestResultInput(
              testName: e.testNameController.text.trim(),
              result: e.resultController.text.trim(),
              referenceRange: e.referenceRangeController.text.trim().isEmpty
                  ? null
                  : e.referenceRangeController.text.trim(),
            ))
        .toList();

    setState(() => _submitted = true);
    await ref.read(labResultsBatchProvider.notifier).submitBatch(
          patientRecordId: widget.patientRecord.id,
          results: inputs,
        );
  }

  @override
  void dispose() {
    for (final e in _entries) {
      e.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final batchState = ref.watch(labResultsBatchProvider);

    ref.listen<AsyncValue<String?>>(labResultsBatchProvider, (previous, next) {
      if (!_submitted) return; // ignore initial state changes
      next.whenOrNull(
        data: (warning) {
          // Only act when transitioning FROM loading (i.e. a real submission completed)
          if (previous is AsyncLoading) {
            final msg = warning != null
                ? 'Results saved. Note: $warning'
                : 'Lab results recorded successfully';
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text(msg), backgroundColor: Colors.green),
            );
            ref.read(labResultsBatchProvider.notifier).reset();
            ref.read(departmentQueueNotifierProvider.notifier).refresh();
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

    final isLoading = batchState is AsyncLoading;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Record Lab Results'),
        backgroundColor: Theme.of(context).colorScheme.inversePrimary,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            PatientInfoCard(patientRecord: widget.patientRecord),
            const SizedBox(height: 16),

            // Investigation notes banner
            if (_isLoadingInvestigation)
              const Card(
                child: Padding(
                  padding: EdgeInsets.all(16),
                  child: Row(
                    children: [
                      SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2)),
                      SizedBox(width: 12),
                      Text('Loading requested tests...'),
                    ],
                  ),
                ),
              )
            else if (_investigationNotes != null)
              Card(
                color: Colors.blue.shade50,
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Icon(Icons.science, color: Colors.blue.shade700, size: 18),
                          const SizedBox(width: 6),
                          Text(
                            'Tests Requested by Doctor',
                            style: TextStyle(
                                fontWeight: FontWeight.bold,
                                color: Colors.blue.shade800),
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(
                        _investigationNotes!.investigationNotes,
                        style: TextStyle(color: Colors.blue.shade900),
                      ),
                      if (_investigationNotes!.requestedBy != null)
                        Text(
                          'By: ${_investigationNotes!.requestedBy}',
                          style: TextStyle(
                              fontSize: 12,
                              color: Colors.grey[600],
                              fontStyle: FontStyle.italic),
                        ),
                    ],
                  ),
                ),
              ),

            const SizedBox(height: 16),

            // Per-test result cards
            const Text(
              'Enter Results for Each Test',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
            ),
            const SizedBox(height: 8),

            ...List.generate(_entries.length, (i) {
              return _TestResultCard(
                index: i,
                entry: _entries[i],
                canRemove: _entries.length > 1,
                onRemove: () => _removeEntry(i),
                onChanged: () => setState(() {}),
              );
            }),

            const SizedBox(height: 8),
            OutlinedButton.icon(
              onPressed: _addEntry,
              icon: const Icon(Icons.add),
              label: const Text('Add Another Test'),
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
                          strokeWidth: 2, color: Colors.white))
                  : Text(
                      'Submit ${_entries.length} Result${_entries.length > 1 ? 's' : ''}',
                      style: const TextStyle(fontSize: 16),
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

// ─── Per-test data holder ────────────────────────────────────────────────────

class _TestResultEntry {
  final TextEditingController testNameController;
  final TextEditingController resultController;
  final TextEditingController referenceRangeController;

  _TestResultEntry({String? testName})
      : testNameController = TextEditingController(text: testName ?? ''),
        resultController = TextEditingController(),
        referenceRangeController = TextEditingController();

  bool get isValid =>
      testNameController.text.trim().isNotEmpty &&
      resultController.text.trim().isNotEmpty;

  void dispose() {
    testNameController.dispose();
    resultController.dispose();
    referenceRangeController.dispose();
  }
}

// ─── Per-test card widget ────────────────────────────────────────────────────

class _TestResultCard extends StatelessWidget {
  final int index;
  final _TestResultEntry entry;
  final bool canRemove;
  final VoidCallback onRemove;
  final VoidCallback onChanged;

  const _TestResultCard({
    required this.index,
    required this.entry,
    required this.canRemove,
    required this.onRemove,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                CircleAvatar(
                  radius: 14,
                  backgroundColor: Theme.of(context).colorScheme.primary,
                  child: Text(
                    '${index + 1}',
                    style: const TextStyle(
                        color: Colors.white,
                        fontSize: 12,
                        fontWeight: FontWeight.bold),
                  ),
                ),
                const SizedBox(width: 8),
                const Text('Test Result',
                    style: TextStyle(fontWeight: FontWeight.bold)),
                const Spacer(),
                if (canRemove)
                  IconButton(
                    icon: const Icon(Icons.remove_circle_outline,
                        color: Colors.red, size: 20),
                    onPressed: onRemove,
                    tooltip: 'Remove',
                    padding: EdgeInsets.zero,
                    constraints: const BoxConstraints(),
                  ),
              ],
            ),
            const SizedBox(height: 12),
            TextField(
              controller: entry.testNameController,
              onChanged: (_) => onChanged(),
              decoration: const InputDecoration(
                labelText: 'Test Name *',
                hintText: 'e.g. FBC, X-Ray, Malaria RDT',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.biotech),
                isDense: true,
              ),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: entry.resultController,
              onChanged: (_) => onChanged(),
              maxLines: 3,
              decoration: const InputDecoration(
                labelText: 'Result *',
                hintText: 'e.g. Haemoglobin: 12.5 g/dL, WBC: 6.2 x10³/µL',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.analytics),
                isDense: true,
              ),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: entry.referenceRangeController,
              decoration: const InputDecoration(
                labelText: 'Reference Range (optional)',
                hintText: 'e.g. 12.0–16.0 g/dL',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.straighten),
                isDense: true,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
