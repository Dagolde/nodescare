import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:dio/dio.dart';
import '../../domain/entities/patient_record.dart';
import '../providers/accounts_provider.dart';
import '../providers/department_provider.dart';

class DoctorConsultationScreen extends ConsumerStatefulWidget {
  final PatientRecord patientRecord;

  const DoctorConsultationScreen({super.key, required this.patientRecord});

  @override
  ConsumerState<DoctorConsultationScreen> createState() =>
      _DoctorConsultationScreenState();
}

class _DoctorConsultationScreenState
    extends ConsumerState<DoctorConsultationScreen> {
  final _formKey = GlobalKey<FormState>();
  bool _isLoading = false;

  final _complaintHistoryController = TextEditingController();
  final _examinationFindingsController = TextEditingController();
  final _diagnosisController = TextEditingController();
  final _prescriptionController = TextEditingController();
  final _investigationController = TextEditingController();
  final _notesController = TextEditingController();

  // Routing choice
  String _routingAction = 'next'; // 'next', 'lab', 'pharmacy', 'admit'

  @override
  void dispose() {
    _complaintHistoryController.dispose();
    _examinationFindingsController.dispose();
    _diagnosisController.dispose();
    _prescriptionController.dispose();
    _investigationController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isLoading = true);
    try {
      final ds = ref.read(remoteDataSourceProvider);
      final recordId = widget.patientRecord.id;

      // Save consultation notes first
      await ds.post('/api/v1/patient-records/$recordId/notes', data: {
        'notes': _buildConsultationNote(),
        if (_complaintHistoryController.text.isNotEmpty)
          'complaint_history': _complaintHistoryController.text.trim(),
        if (_examinationFindingsController.text.isNotEmpty)
          'examination_findings': _examinationFindingsController.text.trim(),
      });

      // Route based on doctor's decision
      if (_routingAction == 'admit') {
        await ds.post('/api/v1/patient-records/$recordId/admit', data: {
          'notes': _notesController.text.trim().isNotEmpty
              ? _notesController.text.trim()
              : 'Patient admitted by doctor.',
        });
      } else if (_routingAction == 'lab' || _routingAction == 'pharmacy') {
        // Route to specific department by type using the public by-type endpoint
        final targetType = _routingAction == 'lab' ? 'laboratory' : 'pharmacy';
        final deptResponse = await ds.get('/api/v1/departments/by-type/$targetType');
        final deptList = deptResponse.data['data'] as List<dynamic>;

        if (deptList.isEmpty) {
          throw Exception(
              '${_routingAction == 'lab' ? 'Laboratory' : 'Pharmacy'} department not found. Please ensure it is configured.');
        }

        final dept = deptList.first as Map<String, dynamic>;

        await ds.post('/api/v1/patient-records/$recordId/route', data: {
          'department_id': dept['id'],
          'notes': _notesController.text.trim().isNotEmpty
              ? _notesController.text.trim()
              : 'Routed by doctor.',
          if (_routingAction == 'pharmacy' &&
              _prescriptionController.text.isNotEmpty)
            'prescription_notes': _prescriptionController.text.trim(),
          if (_routingAction == 'lab' &&
              _investigationController.text.isNotEmpty)
            'investigation_notes': _investigationController.text.trim(),
        });
      } else {
        // Route to next in workflow
        await ds.post('/api/v1/patient-records/$recordId/complete', data: {
          'notes': _notesController.text.trim().isNotEmpty
              ? _notesController.text.trim()
              : 'Consultation complete.',
          if (_prescriptionController.text.isNotEmpty)
            'prescription_notes': _prescriptionController.text.trim(),
          if (_investigationController.text.isNotEmpty)
            'investigation_notes': _investigationController.text.trim(),
        });
      }

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(_successMessage()),
            backgroundColor: Colors.green,
            duration: const Duration(seconds: 3),
          ),
        );
        ref.read(departmentQueueNotifierProvider.notifier).refresh();
        Navigator.pop(context);
      }
    } catch (e) {
      if (mounted) {
        String errorMessage = 'Failed: ';
        if (e is DioException) {
          if (e.response?.data != null && e.response?.data is Map) {
            final errorData = e.response?.data as Map;
            if (errorData['error'] != null && errorData['error']['message'] != null) {
              errorMessage += errorData['error']['message'];
            } else {
              errorMessage += e.message ?? e.toString();
            }
          } else {
            errorMessage += 'Status ${e.response?.statusCode}: ${e.message}';
          }
        } else {
          errorMessage += e.toString();
        }
        
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(errorMessage),
            backgroundColor: Colors.red,
            duration: const Duration(seconds: 5),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  String _buildConsultationNote() {
    final parts = <String>[];
    if (_complaintHistoryController.text.isNotEmpty) {
      parts.add('Complaint & History: ${_complaintHistoryController.text.trim()}');
    }
    if (_examinationFindingsController.text.isNotEmpty) {
      parts.add('Examination Findings: ${_examinationFindingsController.text.trim()}');
    }
    if (_diagnosisController.text.isNotEmpty) {
      parts.add('Diagnosis: ${_diagnosisController.text.trim()}');
    }
    if (_prescriptionController.text.isNotEmpty) {
      parts.add('Prescription: ${_prescriptionController.text.trim()}');
    }
    if (_investigationController.text.isNotEmpty) {
      parts.add('Investigations: ${_investigationController.text.trim()}');
    }
    if (_notesController.text.isNotEmpty) {
      parts.add('Notes: ${_notesController.text.trim()}');
    }
    return parts.join('\n');
  }

  String _successMessage() {
    switch (_routingAction) {
      case 'lab':
        return 'Patient sent to Laboratory';
      case 'pharmacy':
        return 'Patient sent to Pharmacy';
      case 'admit':
        return 'Patient admitted';
      default:
        return 'Consultation complete. Patient routed.';
    }
  }

  Widget _buildClinicalSummary() {
    final record = widget.patientRecord;
    final hasComplaint = record.complaintHistory != null ||
        record.examinationFindings != null ||
        record.investigationNotes != null ||
        record.prescriptionNotes != null;
    final hasLabResults = record.latestLabResults != null &&
        record.latestLabResults!.isNotEmpty;

    if (!hasComplaint && !hasLabResults) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (record.complaintHistory != null) ...[
          _SummaryCard(
            icon: Icons.history,
            color: Colors.teal,
            title: 'Complaint & History',
            content: record.complaintHistory!,
          ),
          const SizedBox(height: 8),
        ],
        if (record.examinationFindings != null) ...[
          _SummaryCard(
            icon: Icons.search,
            color: Colors.orange,
            title: 'Examination Findings',
            content: record.examinationFindings!,
          ),
          const SizedBox(height: 8),
        ],
        if (record.investigationNotes != null) ...[
          _SummaryCard(
            icon: Icons.science,
            color: Colors.blue,
            title: 'Investigations Requested',
            content: record.investigationNotes!,
          ),
          const SizedBox(height: 8),
        ],
        if (record.prescriptionNotes != null) ...[
          _SummaryCard(
            icon: Icons.medication,
            color: Colors.green,
            title: 'Previous Prescription',
            content: record.prescriptionNotes!,
          ),
          const SizedBox(height: 8),
        ],
        // Lab results
        if (hasLabResults) ...[
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Colors.purple.shade50,
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: Colors.purple.shade300),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Icon(Icons.biotech, color: Colors.purple.shade700, size: 18),
                    const SizedBox(width: 6),
                    Text(
                      'Lab Results',
                      style: TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 13,
                        color: Colors.purple.shade800,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                ...record.latestLabResults!.map((r) => Padding(
                  padding: const EdgeInsets.only(bottom: 6),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        width: 6,
                        height: 6,
                        margin: const EdgeInsets.only(top: 5, right: 6),
                        decoration: BoxDecoration(
                          color: Colors.purple.shade400,
                          shape: BoxShape.circle,
                        ),
                      ),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              r['test_name'] as String? ?? '',
                              style: const TextStyle(
                                  fontWeight: FontWeight.w600, fontSize: 13),
                            ),
                            Text(
                              r['result'] as String? ?? '',
                              style: TextStyle(
                                  fontSize: 12, color: Colors.grey[700]),
                            ),
                            if (r['reference_range'] != null)
                              Text(
                                'Ref: ${r['reference_range']}',
                                style: TextStyle(
                                    fontSize: 11, color: Colors.grey[500]),
                              ),
                          ],
                        ),
                      ),
                    ],
                  ),
                )),
              ],
            ),
          ),
          const SizedBox(height: 16),
        ],
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    final patient = widget.patientRecord.patient;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Doctor Consultation'),
        backgroundColor: Colors.indigo,
        foregroundColor: Colors.white,
      ),
      body: Form(
        key: _formKey,
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Patient info
              Card(
                color: Colors.indigo.shade50,
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Row(
                    children: [
                      CircleAvatar(
                        backgroundColor: Colors.indigo,
                        child: Text(
                          patient?.fullName.isNotEmpty == true
                              ? patient!.fullName[0].toUpperCase()
                              : 'P',
                          style: const TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.bold),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(patient?.fullName ?? 'Unknown',
                                style: const TextStyle(
                                    fontWeight: FontWeight.bold,
                                    fontSize: 16)),
                            Text(
                                'No: ${patient?.patientNumber ?? 'N/A'}',
                                style: TextStyle(
                                    color: Colors.grey[600], fontSize: 13)),
                            if (patient?.chiefComplaint.isNotEmpty == true)
                              Text('CC: ${patient!.chiefComplaint}',
                                  style: const TextStyle(fontSize: 13)),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 20),

              // ── Clinical Summary (from previous consultation + lab results) ──
              _buildClinicalSummary(),

              // ── Vitals ──────────────────────────────────────────────────────
              _VitalsSection(patientRecordId: widget.patientRecord.id),

              const Text('Consultation',
                  style: TextStyle(
                      fontSize: 18, fontWeight: FontWeight.bold)),
              const SizedBox(height: 12),

              // Complaint & History
              TextFormField(
                controller: _complaintHistoryController,
                decoration: const InputDecoration(
                  labelText: 'Complaint & History',
                  hintText: 'e.g. 3-day history of fever and cough...',
                  border: OutlineInputBorder(),
                  prefixIcon: Icon(Icons.history, color: Colors.teal),
                ),
                maxLines: 3,
                maxLength: 5000,
              ),
              const SizedBox(height: 12),

              // Examination Findings
              TextFormField(
                controller: _examinationFindingsController,
                decoration: const InputDecoration(
                  labelText: 'Examination Findings',
                  hintText: 'e.g. Temperature 38.5°C, bilateral crepitations...',
                  border: OutlineInputBorder(),
                  prefixIcon: Icon(Icons.search, color: Colors.orange),
                ),
                maxLines: 3,
                maxLength: 5000,
              ),
              const SizedBox(height: 12),

              // Diagnosis
              TextFormField(
                controller: _diagnosisController,
                decoration: const InputDecoration(
                  labelText: 'Diagnosis *',
                  hintText: 'Enter diagnosis',
                  border: OutlineInputBorder(),
                  prefixIcon:
                      Icon(Icons.medical_information, color: Colors.indigo),
                ),
                maxLines: 2,
                validator: (v) =>
                    (v == null || v.trim().isEmpty) ? 'Required' : null,
              ),
              const SizedBox(height: 12),

              // Prescription
              TextFormField(
                controller: _prescriptionController,
                decoration: const InputDecoration(
                  labelText: 'Prescription / Medications',
                  hintText: 'e.g. Amoxicillin 500mg TDS x 5 days',
                  border: OutlineInputBorder(),
                  prefixIcon:
                      Icon(Icons.medication, color: Colors.green),
                ),
                maxLines: 3,
              ),
              const SizedBox(height: 12),

              // Investigations
              TextFormField(
                controller: _investigationController,
                decoration: const InputDecoration(
                  labelText: 'Investigations Requested',
                  hintText: 'e.g. FBC, LFT, Chest X-ray',
                  border: OutlineInputBorder(),
                  prefixIcon: Icon(Icons.biotech, color: Colors.purple),
                ),
                maxLines: 2,
              ),
              const SizedBox(height: 12),

              // Additional notes
              TextFormField(
                controller: _notesController,
                decoration: const InputDecoration(
                  labelText: 'Additional Notes',
                  border: OutlineInputBorder(),
                  prefixIcon: Icon(Icons.notes, color: Colors.grey),
                ),
                maxLines: 2,
              ),
              const SizedBox(height: 24),

              // Routing decision
              const Text('Route Patient To',
                  style: TextStyle(
                      fontSize: 16, fontWeight: FontWeight.bold)),
              const SizedBox(height: 8),

              _RoutingOption(
                value: 'next',
                groupValue: _routingAction,
                label: 'Next Step (Workflow)',
                subtitle: 'Follow standard workflow order',
                icon: Icons.arrow_forward,
                color: Colors.blue,
                onChanged: (v) => setState(() => _routingAction = v!),
              ),
              _RoutingOption(
                value: 'lab',
                groupValue: _routingAction,
                label: 'Laboratory',
                subtitle: 'Send for investigations',
                icon: Icons.biotech,
                color: Colors.purple,
                onChanged: (v) => setState(() => _routingAction = v!),
              ),
              _RoutingOption(
                value: 'pharmacy',
                groupValue: _routingAction,
                label: 'Pharmacy',
                subtitle: 'Send for medication dispensing',
                icon: Icons.medication,
                color: Colors.green,
                onChanged: (v) => setState(() => _routingAction = v!),
              ),
              _RoutingOption(
                value: 'admit',
                groupValue: _routingAction,
                label: 'Admit Patient',
                subtitle: 'Admit for inpatient care',
                icon: Icons.hotel,
                color: Colors.orange,
                onChanged: (v) => setState(() => _routingAction = v!),
              ),

              const SizedBox(height: 24),

              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: _isLoading ? null : _submit,
                  icon: _isLoading
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                              strokeWidth: 2, color: Colors.white))
                      : const Icon(Icons.check_circle),
                  label: Text(
                    _isLoading ? 'Saving...' : 'Complete & Route Patient',
                    style: const TextStyle(fontSize: 16),
                  ),
                  style: ElevatedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    backgroundColor: Colors.indigo,
                    foregroundColor: Colors.white,
                  ),
                ),
              ),
              const SizedBox(height: 16),
            ],
          ),
        ),
      ),
    );
  }
}

class _RoutingOption extends StatelessWidget {
  final String value;
  final String groupValue;
  final String label;
  final String subtitle;
  final IconData icon;
  final Color color;
  final ValueChanged<String?> onChanged;

  const _RoutingOption({
    required this.value,
    required this.groupValue,
    required this.label,
    required this.subtitle,
    required this.icon,
    required this.color,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    final selected = value == groupValue;
    return GestureDetector(
      onTap: () => onChanged(value),
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: selected ? color.withAlpha(25) : Colors.grey.shade50,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(
              color: selected ? color : Colors.grey.shade300, width: 1.5),
        ),
        child: Row(
          children: [
            Icon(icon, color: selected ? color : Colors.grey, size: 22),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(label,
                      style: TextStyle(
                          fontWeight: FontWeight.w600,
                          color: selected ? color : Colors.black87)),
                  Text(subtitle,
                      style: TextStyle(
                          fontSize: 12, color: Colors.grey[600])),
                ],
              ),
            ),
            Radio<String>(
              value: value,
              groupValue: groupValue,
              onChanged: onChanged,
              activeColor: color,
            ),
          ],
        ),
      ),
    );
  }
}


class _SummaryCard extends StatelessWidget {
  final IconData icon;
  final Color color;
  final String title;
  final String content;

  const _SummaryCard({
    required this.icon,
    required this.color,
    required this.title,
    required this.content,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.07),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: color.withValues(alpha: 0.4)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: color, size: 16),
              const SizedBox(width: 6),
              Text(
                title,
                style: TextStyle(
                  fontWeight: FontWeight.bold,
                  fontSize: 12,
                  color: color,
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text(content, style: const TextStyle(fontSize: 13)),
        ],
      ),
    );
  }
}

// ── Vitals Section ────────────────────────────────────────────────────────────

class _VitalsSection extends ConsumerWidget {
  final String patientRecordId;

  const _VitalsSection({required this.patientRecordId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final vitalsAsync = ref.watch(patientVitalsProvider(patientRecordId));

    return vitalsAsync.when(
      data: (vitalsList) {
        if (vitalsList.isEmpty) return const SizedBox.shrink();

        // Show the most recent vitals entry
        final v = vitalsList.first;

        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.red.shade50,
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: Colors.red.shade300),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Icon(Icons.favorite, color: Colors.red.shade700, size: 18),
                      const SizedBox(width: 6),
                      Text(
                        'Latest Vitals',
                        style: TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 13,
                          color: Colors.red.shade800,
                        ),
                      ),
                      const Spacer(),
                      Text(
                        _formatTime(v.createdAt),
                        style: TextStyle(fontSize: 11, color: Colors.grey[600]),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 12,
                    runSpacing: 6,
                    children: [
                      if (v.bloodPressure != null)
                        _VitalChip(label: 'BP', value: '${v.bloodPressure} mmHg'),
                      if (v.heartRate != null)
                        _VitalChip(label: 'HR', value: '${v.heartRate} bpm'),
                      if (v.temperature != null)
                        _VitalChip(label: 'Temp', value: '${v.temperature}°C'),
                      if (v.oxygenSaturation != null)
                        _VitalChip(label: 'SpO₂', value: '${v.oxygenSaturation}%'),
                      if (v.respiratoryRate != null)
                        _VitalChip(label: 'RR', value: '${v.respiratoryRate}/min'),
                      if (v.weight != null)
                        _VitalChip(label: 'Wt', value: '${v.weight} kg'),
                      if (v.height != null)
                        _VitalChip(label: 'Ht', value: '${v.height} cm'),
                    ],
                  ),
                  if (v.notes != null && v.notes!.isNotEmpty) ...[
                    const SizedBox(height: 6),
                    Text(
                      'Notes: ${v.notes}',
                      style: TextStyle(fontSize: 12, color: Colors.grey[700]),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 16),
          ],
        );
      },
      loading: () => const SizedBox.shrink(),
      error: (_, __) => const SizedBox.shrink(),
    );
  }

  String _formatTime(DateTime dt) {
    final diff = DateTime.now().difference(dt);
    if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
    if (diff.inHours < 24) return '${diff.inHours}h ago';
    return '${dt.day}/${dt.month}/${dt.year}';
  }
}

class _VitalChip extends StatelessWidget {
  final String label;
  final String value;

  const _VitalChip({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(6),
        border: Border.all(color: Colors.red.shade200),
      ),
      child: RichText(
        text: TextSpan(
          children: [
            TextSpan(
              text: '$label: ',
              style: TextStyle(
                fontSize: 11,
                color: Colors.grey[600],
                fontWeight: FontWeight.w500,
              ),
            ),
            TextSpan(
              text: value,
              style: const TextStyle(
                fontSize: 12,
                color: Colors.black87,
                fontWeight: FontWeight.bold,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
