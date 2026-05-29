import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../domain/entities/patient_record.dart';
import '../../domain/repositories/department_repository.dart';
import '../providers/billing_provider.dart';
import '../providers/department_provider.dart';
import '../providers/patient_provider.dart';
import '../utils/consultation_utils.dart';
import 'billing_form_screen.dart';

// Hardcoded role for demo; in production this comes from the auth token/provider.
// Replace with actual role from auth state when auth provider is wired up.
const _kDemoRole = 'doctor'; // 'doctor' | 'department_staff' | etc.

class PatientDetailScreen extends ConsumerStatefulWidget {
  final PatientRecord patientRecord;

  const PatientDetailScreen({super.key, required this.patientRecord});

  @override
  ConsumerState<PatientDetailScreen> createState() => _PatientDetailScreenState();
}

class _PatientDetailScreenState extends ConsumerState<PatientDetailScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 4, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  PatientRecord get record => widget.patientRecord;
  Patient? get patient => record.patient;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(patient?.fullName ?? 'Patient Record'),
        backgroundColor: Theme.of(context).colorScheme.inversePrimary,
        bottom: TabBar(
          controller: _tabController,
          tabs: const [
            Tab(text: 'Details'),
            Tab(text: 'Vitals'),
            Tab(text: 'Lab Results'),
            Tab(text: 'Journey'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          _DetailsTab(record: record),
          _VitalsTab(patientRecordId: record.id),
          _LabResultsTab(patientRecordId: record.id),
          _JourneyTab(patientRecordId: record.id),
        ],
      ),
      bottomNavigationBar: _ActionBar(record: record),
    );
  }
}

// ─── Details Tab ─────────────────────────────────────────────────────────────

class _DetailsTab extends ConsumerWidget {
  final PatientRecord record;

  const _DetailsTab({required this.record});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final patient = record.patient;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Patient photo section
          if (patient?.photoObjectKey != null) ...[
            _PatientPhotoSection(patientId: patient!.id),
            const SizedBox(height: 16),
          ],
          _SectionCard(
            title: 'Patient Information',
            children: [
              _InfoRow('Patient No', patient?.patientNumber ?? 'N/A'),
              _InfoRow('Full Name', patient?.fullName ?? 'N/A'),
              _InfoRow('Date of Birth', patient?.dateOfBirth ?? 'N/A'),
              _InfoRow('Gender', patient?.gender ?? 'N/A'),
              _InfoRow('Phone', patient?.phone ?? 'N/A'),
              if (patient?.address != null)
                _InfoRow('Address', patient!.address!),
            ],
          ),
          const SizedBox(height: 12),
          _SectionCard(
            title: 'Chief Complaint',
            children: [
              Text(
                patient?.chiefComplaint ?? 'Not recorded',
                style: const TextStyle(fontSize: 15),
              ),
            ],
          ),
          if (patient?.hmoProviderName != null) ...[
            const SizedBox(height: 12),
            _SectionCard(
              title: 'Insurance / HMO',
              children: [
                _InfoRow('Provider', patient!.hmoProviderName!),
                if (patient.hmoPolicyNumber != null)
                  _InfoRow('Policy No', patient.hmoPolicyNumber!),
              ],
            ),
          ],
          if (patient?.nextOfKinName != null) ...[
            const SizedBox(height: 12),
            _SectionCard(
              title: 'Next of Kin',
              children: [
                _InfoRow('Name', patient!.nextOfKinName!),
                if (patient.nextOfKinPhone != null)
                  _InfoRow('Phone', patient.nextOfKinPhone!),
              ],
            ),
          ],
          const SizedBox(height: 12),
          _SectionCard(
            title: 'Record Status',
            children: [
              _InfoRow('Status', record.status.toUpperCase()),
              _InfoRow(
                'Department',
                record.currentDepartment?.name ?? 'N/A',
              ),
              _InfoRow(
                'Registered',
                _formatDateTime(record.createdAt),
              ),
            ],
          ),
        ],
      ),
    );
  }

  String _formatDateTime(DateTime dt) =>
      '${dt.day}/${dt.month}/${dt.year} ${dt.hour}:${dt.minute.toString().padLeft(2, '0')}';
}

// ─── Patient Photo Section ───────────────────────────────────────────────────

class _PatientPhotoSection extends ConsumerWidget {
  final String patientId;

  const _PatientPhotoSection({required this.patientId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final photoBytesAsync = ref.watch(patientPhotoBytesProvider(patientId));

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Patient Photograph',
              style: TextStyle(
                fontWeight: FontWeight.bold,
                fontSize: 14,
                color: Colors.blueGrey,
              ),
            ),
            const Divider(height: 16),
            Center(
              child: photoBytesAsync.when(
                data: (bytes) {
                  if (bytes == null || bytes.isEmpty) {
                    return const _NoPhotoPlaceholder();
                  }
                  return ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: Image.memory(
                      bytes,
                      width: 200,
                      height: 200,
                      fit: BoxFit.cover,
                      errorBuilder: (context, error, stackTrace) {
                        return const _NoPhotoPlaceholder();
                      },
                    ),
                  );
                },
                loading: () => const SizedBox(
                  width: 200,
                  height: 200,
                  child: Center(child: CircularProgressIndicator()),
                ),
                error: (_, __) => const _NoPhotoPlaceholder(),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _NoPhotoPlaceholder extends StatelessWidget {
  const _NoPhotoPlaceholder();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 200,
      height: 200,
      decoration: BoxDecoration(
        color: Colors.grey[200],
        borderRadius: BorderRadius.circular(8),
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.person, size: 64, color: Colors.grey[400]),
          const SizedBox(height: 8),
          Text(
            'No photo available',
            style: TextStyle(color: Colors.grey[600], fontSize: 12),
          ),
        ],
      ),
    );
  }
}

// ─── Journey Tab ─────────────────────────────────────────────────────────────

class _JourneyTab extends ConsumerWidget {
  final String patientRecordId;

  const _JourneyTab({required this.patientRecordId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final journeyAsync = ref.watch(journeyHistoryProvider(patientRecordId));

    return journeyAsync.when(
      data: (events) {
        if (events.isEmpty) {
          return const Center(
            child: Text('No journey events yet.', style: TextStyle(color: Colors.grey)),
          );
        }
        return ListView.separated(
          padding: const EdgeInsets.all(16),
          itemCount: events.length,
          separatorBuilder: (_, __) => const SizedBox(height: 8),
          itemBuilder: (_, i) => _JourneyEventCard(event: events[i]),
        );
      },
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (e, _) => Center(
        child: Text('Error: $e', style: const TextStyle(color: Colors.red)),
      ),
    );
  }
}

/// Public alias for testing — wraps the private [_JourneyEventCard].
@visibleForTesting
class JourneyEventCardTestable extends StatelessWidget {
  final JourneyEvent event;
  const JourneyEventCardTestable({super.key, required this.event});

  @override
  Widget build(BuildContext context) => _JourneyEventCard(event: event);
}

class _JourneyEventCard extends StatelessWidget {
  final JourneyEvent event;

  const _JourneyEventCard({required this.event});

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(_eventIcon(event.eventType), size: 18, color: Colors.blueGrey),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    _eventLabel(event.eventType),
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                ),
                Text(
                  _formatDateTime(event.createdAt),
                  style: TextStyle(fontSize: 11, color: Colors.grey[600]),
                ),
              ],
            ),
            if (event.fromDepartmentName != null || event.toDepartmentName != null) ...[
              const SizedBox(height: 6),
              Text(
                '${event.fromDepartmentName ?? '—'} → ${event.toDepartmentName ?? '—'}',
                style: TextStyle(fontSize: 13, color: Colors.grey[700]),
              ),
            ],
            if (event.notes != null && event.notes!.isNotEmpty) ...[
              const SizedBox(height: 6),
              Text(event.notes!, style: const TextStyle(fontSize: 13)),
            ],
            if (event.metadata?['complaint_history'] != null &&
                (event.metadata!['complaint_history'] as String).isNotEmpty) ...[
              const SizedBox(height: 6),
              _ClinicalSection(
                label: 'Complaint & History',
                text: event.metadata!['complaint_history'] as String,
              ),
            ],
            if (event.metadata?['examination_findings'] != null &&
                (event.metadata!['examination_findings'] as String).isNotEmpty) ...[
              const SizedBox(height: 6),
              _ClinicalSection(
                label: 'Examination Findings',
                text: event.metadata!['examination_findings'] as String,
              ),
            ],
            if (event.performedByName != null) ...[
              const SizedBox(height: 4),
              Text(
                'By: ${event.performedByName}',
                style: TextStyle(fontSize: 11, color: Colors.grey[500]),
              ),
            ],
          ],
        ),
      ),
    );
  }

  IconData _eventIcon(String type) {
    switch (type) {
      case 'registered':
        return Icons.person_add;
      case 'routed':
        return Icons.arrow_forward;
      case 'admitted':
        return Icons.local_hospital;
      case 'discharged':
        return Icons.exit_to_app;
      case 'note_added':
        return Icons.note_add;
      case 'document_attached':
        return Icons.attach_file;
      default:
        return Icons.info_outline;
    }
  }

  String _eventLabel(String type) {
    switch (type) {
      case 'registered':
        return 'Registered';
      case 'routed':
        return 'Routed';
      case 'admitted':
        return 'Admitted';
      case 'discharged':
        return 'Discharged';
      case 'note_added':
        return 'Note Added';
      case 'document_attached':
        return 'Document Attached';
      case 'status_changed':
        return 'Status Changed';
      default:
        return type.replaceAll('_', ' ').toUpperCase();
    }
  }

  String _formatDateTime(DateTime dt) =>
      '${dt.day}/${dt.month}/${dt.year} ${dt.hour}:${dt.minute.toString().padLeft(2, '0')}';
}

// ─── Vitals Tab ──────────────────────────────────────────────────────────────

class _VitalsTab extends ConsumerWidget {
  final String patientRecordId;

  const _VitalsTab({required this.patientRecordId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final vitalsAsync = ref.watch(patientVitalsProvider(patientRecordId));

    return vitalsAsync.when(
      data: (vitals) {
        if (vitals.isEmpty) {
          return const Center(
            child: Text('No vitals recorded yet.', style: TextStyle(color: Colors.grey)),
          );
        }
        return ListView.separated(
          padding: const EdgeInsets.all(16),
          itemCount: vitals.length,
          separatorBuilder: (_, __) => const SizedBox(height: 8),
          itemBuilder: (_, i) => _VitalsCard(vitals: vitals[i]),
        );
      },
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (e, _) => Center(
        child: Text('Error: $e', style: const TextStyle(color: Colors.red)),
      ),
    );
  }
}

class _VitalsCard extends StatelessWidget {
  final PatientVitals vitals;

  const _VitalsCard({required this.vitals});

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.favorite, size: 18, color: Colors.red),
                const SizedBox(width: 8),
                const Text(
                  'Vital Signs',
                  style: TextStyle(fontWeight: FontWeight.bold),
                ),
                const Spacer(),
                Text(
                  _formatDateTime(vitals.createdAt),
                  style: TextStyle(fontSize: 11, color: Colors.grey[600]),
                ),
              ],
            ),
            const Divider(height: 16),
            if (vitals.bloodPressure != null)
              _VitalRow('Blood Pressure', vitals.bloodPressure!, 'mmHg'),
            if (vitals.heartRate != null)
              _VitalRow('Heart Rate', vitals.heartRate!, 'bpm'),
            if (vitals.temperature != null)
              _VitalRow('Temperature', vitals.temperature!, '°C'),
            if (vitals.respiratoryRate != null)
              _VitalRow('Respiratory Rate', vitals.respiratoryRate!, '/min'),
            if (vitals.oxygenSaturation != null)
              _VitalRow('Oxygen Saturation', vitals.oxygenSaturation!, '%'),
            if (vitals.weight != null)
              _VitalRow('Weight', vitals.weight!, 'kg'),
            if (vitals.height != null)
              _VitalRow('Height', vitals.height!, 'cm'),
            if (vitals.notes != null && vitals.notes!.isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(
                'Notes: ${vitals.notes}',
                style: TextStyle(fontSize: 13, color: Colors.grey[700]),
              ),
            ],
            if (vitals.recordedByName != null) ...[
              const SizedBox(height: 8),
              Text(
                'Recorded by: ${vitals.recordedByName}',
                style: TextStyle(fontSize: 11, color: Colors.grey[500]),
              ),
            ],
          ],
        ),
      ),
    );
  }

  String _formatDateTime(DateTime dt) =>
      '${dt.day}/${dt.month}/${dt.year} ${dt.hour}:${dt.minute.toString().padLeft(2, '0')}';
}

class _VitalRow extends StatelessWidget {
  final String label;
  final String value;
  final String unit;

  const _VitalRow(this.label, this.value, this.unit);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          SizedBox(
            width: 140,
            child: Text(
              label,
              style: TextStyle(color: Colors.grey[600], fontSize: 13),
            ),
          ),
          Expanded(
            child: Text(
              '$value $unit',
              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500),
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Lab Results Tab ─────────────────────────────────────────────────────────

class _LabResultsTab extends ConsumerWidget {
  final String patientRecordId;

  const _LabResultsTab({required this.patientRecordId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final labResultsAsync = ref.watch(patientLabResultsProvider(patientRecordId));

    return labResultsAsync.when(
      data: (results) {
        if (results.isEmpty) {
          return const Center(
            child: Text('No lab results yet.', style: TextStyle(color: Colors.grey)),
          );
        }
        return ListView.separated(
          padding: const EdgeInsets.all(16),
          itemCount: results.length,
          separatorBuilder: (_, __) => const SizedBox(height: 8),
          itemBuilder: (_, i) => _LabResultCard(result: results[i]),
        );
      },
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (e, _) => Center(
        child: Text('Error: $e', style: const TextStyle(color: Colors.red)),
      ),
    );
  }
}

class _LabResultCard extends StatelessWidget {
  final LabResult result;

  const _LabResultCard({required this.result});

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.science, size: 18, color: Colors.blue),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    result.testName,
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                  ),
                ),
                _StatusBadge(status: result.status),
              ],
            ),
            const Divider(height: 16),
            _LabResultRow('Result', result.result, result.unit),
            if (result.referenceRange != null)
              _LabResultRow('Reference Range', result.referenceRange!, ''),
            if (result.notes != null && result.notes!.isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(
                'Notes: ${result.notes}',
                style: TextStyle(fontSize: 13, color: Colors.grey[700]),
              ),
            ],
            const SizedBox(height: 8),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                if (result.technicianName != null)
                  Text(
                    'By: ${result.technicianName}',
                    style: TextStyle(fontSize: 11, color: Colors.grey[500]),
                  ),
                Text(
                  _formatDateTime(result.createdAt),
                  style: TextStyle(fontSize: 11, color: Colors.grey[600]),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  String _formatDateTime(DateTime dt) =>
      '${dt.day}/${dt.month}/${dt.year} ${dt.hour}:${dt.minute.toString().padLeft(2, '0')}';
}

class _LabResultRow extends StatelessWidget {
  final String label;
  final String value;
  final String? unit;

  const _LabResultRow(this.label, this.value, this.unit);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          SizedBox(
            width: 120,
            child: Text(
              label,
              style: TextStyle(color: Colors.grey[600], fontSize: 13),
            ),
          ),
          Expanded(
            child: Text(
              unit != null && unit!.isNotEmpty ? '$value $unit' : value,
              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500),
            ),
          ),
        ],
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  final String status;

  const _StatusBadge({required this.status});

  @override
  Widget build(BuildContext context) {
    Color color;
    switch (status.toLowerCase()) {
      case 'completed':
        color = Colors.green;
        break;
      case 'pending':
        color = Colors.orange;
        break;
      case 'abnormal':
        color = Colors.red;
        break;
      default:
        color = Colors.grey;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color, width: 1),
      ),
      child: Text(
        status.toUpperCase(),
        style: TextStyle(
          color: color,
          fontSize: 10,
          fontWeight: FontWeight.bold,
        ),
      ),
    );
  }
}

// ─── Action Bar ──────────────────────────────────────────────────────────────

class _ActionBar extends ConsumerWidget {
  final PatientRecord record;

  const _ActionBar({required this.record});

  bool get _isDoctor => _kDemoRole == 'doctor';
  bool get _isPharmacist => _kDemoRole == 'pharmacist';
  bool get _isLabTechnician => _kDemoRole == 'lab_technician';

  /// Returns true if the patient has returned from Accounts after pharmacy payment.
  bool get _pharmacyPaymentSettled =>
      record.paymentContext == 'pharmacy_billing' && record.isPaid;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    icon: const Icon(Icons.note_add),
                    label: const Text('Add Note'),
                    onPressed: () => _showAddNoteDialog(context, ref),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: ElevatedButton.icon(
                    icon: const Icon(Icons.check_circle_outline),
                    label: const Text('Complete'),
                    onPressed: () => _showCompleteDialog(context, ref),
                  ),
                ),
              ],
            ),
            if (_isDoctor) ...[
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      icon: const Icon(Icons.medication),
                      label: const Text('Pharmacy'),
                      onPressed: () => _showRouteDialog(
                        context,
                        ref,
                        destination: 'pharmacy',
                        label: 'Prescription Notes',
                        hint: 'Enter prescription details...',
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: OutlinedButton.icon(
                      icon: const Icon(Icons.science),
                      label: const Text('Lab'),
                      onPressed: () => _showRouteDialog(
                        context,
                        ref,
                        destination: 'laboratory',
                        label: 'Investigation Notes',
                        hint: 'Enter investigation request...',
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: OutlinedButton.icon(
                      icon: const Icon(Icons.hotel),
                      label: const Text('Admit'),
                      onPressed: () => _showAdmitDialog(context, ref),
                    ),
                  ),
                ],
              ),
            ],
            if (_isPharmacist) ...[
              const SizedBox(height: 8),
              SizedBox(
                width: double.infinity,
                child: _pharmacyPaymentSettled
                    ? ElevatedButton.icon(
                        icon: const Icon(Icons.check_circle),
                        label: const Text('Confirm Dispense'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.green,
                          foregroundColor: Colors.white,
                        ),
                        onPressed: () => _confirmDispense(context, ref),
                      )
                    : ElevatedButton.icon(
                        icon: const Icon(Icons.medication),
                        label: const Text('Bill Drugs'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.purple,
                          foregroundColor: Colors.white,
                        ),
                        onPressed: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => BillingFormScreen(
                              patientRecordId: record.id,
                              billingType: BillingType.pharmacy,
                            ),
                          ),
                        ),
                      ),
              ),
            ],
            if (_isLabTechnician) ...[
              const SizedBox(height: 8),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  icon: const Icon(Icons.science),
                  label: const Text('Bill Investigations'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.blue,
                    foregroundColor: Colors.white,
                  ),
                  onPressed: () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => BillingFormScreen(
                        patientRecordId: record.id,
                        billingType: BillingType.lab,
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Future<void> _confirmDispense(BuildContext context, WidgetRef ref) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Confirm Dispense'),
        content: const Text('Confirm that all drugs have been dispensed to the patient?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.green),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Confirm'),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      await _executeAction(
        context,
        ref,
        action: () => ref.read(confirmDispenseProvider.notifier).confirmDispense(record.id),
        successMessage: 'Dispense confirmed',
        popOnSuccess: true,
      );
    }
  }

  Future<void> _showAddNoteDialog(BuildContext context, WidgetRef ref) async {
    final formKey = GlobalKey<FormState>();
    final complaintHistoryController = TextEditingController();
    final examinationFindingsController = TextEditingController();
    final notesController = TextEditingController();

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Add Note'),
        content: SingleChildScrollView(
          child: Form(
            key: formKey,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                _ConsultationFormFields(
                  complaintHistoryController: complaintHistoryController,
                  examinationFindingsController: examinationFindingsController,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: notesController,
                  maxLines: 4,
                  maxLength: 5000,
                  decoration: const InputDecoration(
                    labelText: 'Additional Notes',
                    hintText: 'Enter additional notes...',
                    border: OutlineInputBorder(),
                  ),
                ),
              ],
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Save'),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      final complaintHistory = complaintHistoryController.text.trim();
      final examinationFindings = examinationFindingsController.text.trim();
      final additionalNotes = notesController.text.trim();

      final noteText = assembleConsultationNote(
        complaintHistory: complaintHistory.isNotEmpty ? complaintHistory : null,
        examinationFindings:
            examinationFindings.isNotEmpty ? examinationFindings : null,
        additionalNotes: additionalNotes.isNotEmpty ? additionalNotes : null,
      );

      if (noteText.isNotEmpty) {
        await _executeAction(
          context,
          ref,
          action: () => ref.read(addNoteProvider.notifier).addNote(
                record.id,
                noteText,
                complaintHistory:
                    complaintHistory.isNotEmpty ? complaintHistory : null,
                examinationFindings:
                    examinationFindings.isNotEmpty ? examinationFindings : null,
              ),
          successMessage: 'Note added successfully',
        );
      }
    }
    complaintHistoryController.dispose();
    examinationFindingsController.dispose();
    notesController.dispose();
  }

  Future<void> _showCompleteDialog(BuildContext context, WidgetRef ref) async {
    final complaintHistoryController = TextEditingController();
    final examinationFindingsController = TextEditingController();

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Complete Step'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Mark this patient as complete for your department? '
                'They will be routed to the next step.',
              ),
              const SizedBox(height: 16),
              _ConsultationFormFields(
                complaintHistoryController: complaintHistoryController,
                examinationFindingsController: examinationFindingsController,
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Confirm'),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      final complaintHistory = complaintHistoryController.text.trim();
      final examinationFindings = examinationFindingsController.text.trim();
      await _executeAction(
        context,
        ref,
        action: () => ref.read(completeStepProvider.notifier).completeStep(
              record.id,
              complaintHistory:
                  complaintHistory.isNotEmpty ? complaintHistory : null,
              examinationFindings:
                  examinationFindings.isNotEmpty ? examinationFindings : null,
            ),
        successMessage: 'Step completed',
        popOnSuccess: true,
      );
    }
    complaintHistoryController.dispose();
    examinationFindingsController.dispose();
  }

  Future<void> _showRouteDialog(
    BuildContext context,
    WidgetRef ref, {
    required String destination,
    required String label,
    required String hint,
  }) async {
    final complaintHistoryController = TextEditingController();
    final examinationFindingsController = TextEditingController();
    final destinationController = TextEditingController();

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: Text('Route to ${destination == 'pharmacy' ? 'Pharmacy' : 'Laboratory'}'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              _ConsultationFormFields(
                complaintHistoryController: complaintHistoryController,
                examinationFindingsController: examinationFindingsController,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: destinationController,
                maxLines: 4,
                maxLength: 5000,
                decoration: InputDecoration(
                  labelText: label,
                  hintText: hint,
                  border: const OutlineInputBorder(),
                ),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Route'),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      final complaintHistory = complaintHistoryController.text.trim();
      final examinationFindings = examinationFindingsController.text.trim();
      final notifier = ref.read(doctorActionProvider.notifier);
      await _executeAction(
        context,
        ref,
        action: () => destination == 'pharmacy'
            ? notifier.routeToPharmacy(
                record.id,
                destinationController.text.trim(),
                complaintHistory:
                    complaintHistory.isNotEmpty ? complaintHistory : null,
                examinationFindings:
                    examinationFindings.isNotEmpty ? examinationFindings : null,
              )
            : notifier.routeToLab(
                record.id,
                destinationController.text.trim(),
                complaintHistory:
                    complaintHistory.isNotEmpty ? complaintHistory : null,
                examinationFindings:
                    examinationFindings.isNotEmpty ? examinationFindings : null,
              ),
        successMessage: 'Patient routed to ${destination == 'pharmacy' ? 'Pharmacy' : 'Lab'}',
        popOnSuccess: true,
      );
    }
    complaintHistoryController.dispose();
    examinationFindingsController.dispose();
    destinationController.dispose();
  }

  Future<void> _showAdmitDialog(BuildContext context, WidgetRef ref) async {
    final wardController = TextEditingController();

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Admit Patient'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Enter the ward department ID to admit this patient. '
              'The patient will be routed to Accounts for admission billing.',
            ),
            const SizedBox(height: 16),
            TextField(
              controller: wardController,
              decoration: const InputDecoration(
                labelText: 'Ward Department ID',
                hintText: 'Enter ward department UUID...',
                border: OutlineInputBorder(),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.orange),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Admit'),
          ),
        ],
      ),
    );

    if (confirmed == true && wardController.text.trim().isNotEmpty) {
      await _executeAction(
        context,
        ref,
        action: () => ref
            .read(admissionBillingProvider.notifier)
            .submitAdmission(record.id, wardController.text.trim()),
        successMessage: 'Patient admitted — routed to Accounts',
        popOnSuccess: true,
      );
    }
    wardController.dispose();
  }

  Future<void> _executeAction(
    BuildContext context,
    WidgetRef ref, {
    required Future<void> Function() action,
    required String successMessage,
    bool popOnSuccess = false,
  }) async {
    try {
      await action();
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(successMessage),
          backgroundColor: Colors.green,
        ),
      );
      // Refresh the queue after any action
      ref.read(departmentQueueNotifierProvider.notifier).refresh();
      if (popOnSuccess) Navigator.pop(context);
    } catch (e) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error: $e'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }
}

// ─── Shared Widgets ───────────────────────────────────────────────────────────

/// A reusable form widget that encapsulates the "Complaint & History" and
/// "Examination Findings" fields used across all consultation dialogs.
class _ConsultationFormFields extends StatelessWidget {
  final TextEditingController complaintHistoryController;
  final TextEditingController examinationFindingsController;

  const _ConsultationFormFields({
    required this.complaintHistoryController,
    required this.examinationFindingsController,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        TextFormField(
          controller: complaintHistoryController,
          maxLines: 4,
          maxLength: 5000,
          decoration: const InputDecoration(
            labelText: 'Complaint & History',
            hintText: 'Enter complaint and history...',
            border: OutlineInputBorder(),
          ),
        ),
        const SizedBox(height: 12),
        TextFormField(
          controller: examinationFindingsController,
          maxLines: 4,
          maxLength: 5000,
          decoration: const InputDecoration(
            labelText: 'Examination Findings',
            hintText: 'Enter examination findings...',
            border: OutlineInputBorder(),
          ),
        ),
      ],
    );
  }
}

class _SectionCard extends StatelessWidget {
  final String title;
  final List<Widget> children;

  const _SectionCard({required this.title, required this.children});

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: const TextStyle(
                fontWeight: FontWeight.bold,
                fontSize: 14,
                color: Colors.blueGrey,
              ),
            ),
            const Divider(height: 16),
            ...children,
          ],
        ),
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  final String label;
  final String value;

  const _InfoRow(this.label, this.value);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 110,
            child: Text(
              label,
              style: TextStyle(color: Colors.grey[600], fontSize: 13),
            ),
          ),
          Expanded(
            child: Text(value, style: const TextStyle(fontSize: 13)),
          ),
        ],
      ),
    );
  }
}

class _ClinicalSection extends StatelessWidget {
  final String label;
  final String text;

  const _ClinicalSection({required this.label, required this.text});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(
            fontWeight: FontWeight.bold,
            fontSize: 13,
            color: Colors.blueGrey,
          ),
        ),
        const SizedBox(height: 2),
        Text(text, style: const TextStyle(fontSize: 13)),
      ],
    );
  }
}
