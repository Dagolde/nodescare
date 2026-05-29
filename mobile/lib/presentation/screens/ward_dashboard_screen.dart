import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../domain/entities/patient_record.dart';
import '../providers/accounts_provider.dart';
import '../providers/auth_provider.dart';

// Provider for admitted patients
final admittedPatientsProvider = FutureProvider<List<PatientRecord>>((ref) async {
  final remote = ref.watch(remoteDataSourceProvider);
  final response = await remote.get('/api/v1/ward/admitted');
  if (response.data['success'] == true) {
    final List<dynamic> data = response.data['data'];
    return data.map((json) => PatientRecord.fromJson(json)).toList();
  }
  throw Exception(response.data['error']?['message'] ?? 'Failed to fetch admitted patients');
});

class WardDashboardScreen extends ConsumerWidget {
  const WardDashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final patientsAsync = ref.watch(admittedPatientsProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('🏥 Ward'),
        backgroundColor: Theme.of(context).colorScheme.inversePrimary,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => ref.invalidate(admittedPatientsProvider),
          ),
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: () => ref.read(authProvider.notifier).logout(),
          ),
        ],
      ),
      body: patientsAsync.when(
        data: (patients) {
          return Column(
            children: [
              // Stats banner
              Container(
                margin: const EdgeInsets.all(16),
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: [Colors.teal.shade600, Colors.teal.shade400],
                  ),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.bed, color: Colors.white, size: 36),
                    const SizedBox(width: 16),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '${patients.length}',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 32,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        const Text(
                          'Patients Admitted',
                          style: TextStyle(color: Colors.white70, fontSize: 14),
                        ),
                      ],
                    ),
                  ],
                ),
              ),

              // Patient list
              Expanded(
                child: patients.isEmpty
                    ? const Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.bed_outlined, size: 64, color: Colors.grey),
                            SizedBox(height: 16),
                            Text('No patients currently admitted',
                                style: TextStyle(fontSize: 16, color: Colors.grey)),
                          ],
                        ),
                      )
                    : RefreshIndicator(
                        onRefresh: () async => ref.invalidate(admittedPatientsProvider),
                        child: ListView.builder(
                          padding: const EdgeInsets.symmetric(horizontal: 16),
                          itemCount: patients.length,
                          itemBuilder: (context, i) => _AdmittedPatientCard(
                            record: patients[i],
                            onDischarge: () => _showDischargeDialog(context, ref, patients[i]),
                          ),
                        ),
                      ),
              ),
            ],
          );
        },
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.error_outline, size: 48, color: Colors.red),
              const SizedBox(height: 12),
              Text(e.toString().replaceFirst('Exception: ', ''),
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: Colors.red)),
              const SizedBox(height: 16),
              ElevatedButton(
                onPressed: () => ref.invalidate(admittedPatientsProvider),
                child: const Text('Retry'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _showDischargeDialog(
      BuildContext context, WidgetRef ref, PatientRecord record) async {
    final notesController = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: Text('Discharge ${record.patient?.fullName ?? 'Patient'}'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Confirm discharge of this patient from the ward?'),
            const SizedBox(height: 12),
            TextField(
              controller: notesController,
              maxLines: 3,
              decoration: const InputDecoration(
                labelText: 'Discharge Notes (optional)',
                border: OutlineInputBorder(),
                hintText: 'e.g. Patient stable, discharged on doctor\'s orders',
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
            style: ElevatedButton.styleFrom(backgroundColor: Colors.teal),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Discharge', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );

    if (confirmed != true || !context.mounted) return;

    try {
      final remote = ref.read(remoteDataSourceProvider);
      final response = await remote.post(
        '/api/v1/ward/discharge/${record.id}',
        data: {
          if (notesController.text.trim().isNotEmpty)
            'discharge_notes': notesController.text.trim(),
        },
      );

      if (response.data['success'] == true) {
        ref.invalidate(admittedPatientsProvider);
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('${record.patient?.fullName ?? 'Patient'} discharged successfully'),
              backgroundColor: Colors.green,
            ),
          );
        }
      } else {
        throw Exception(response.data['error']?['message'] ?? 'Discharge failed');
      }
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Failed: ${e.toString().replaceFirst('Exception: ', '')}'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }
}

class _AdmittedPatientCard extends StatelessWidget {
  final PatientRecord record;
  final VoidCallback onDischarge;

  const _AdmittedPatientCard({
    required this.record,
    required this.onDischarge,
  });

  @override
  Widget build(BuildContext context) {
    final patient = record.patient;
    final admittedAt = record.admittedAt;
    final duration = admittedAt != null
        ? DateTime.now().difference(admittedAt)
        : null;
    final durationStr = duration != null
        ? duration.inDays > 0
            ? '${duration.inDays}d ${duration.inHours % 24}h'
            : '${duration.inHours}h ${duration.inMinutes % 60}m'
        : 'Unknown';

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          children: [
            CircleAvatar(
              radius: 24,
              backgroundColor: Colors.teal.shade100,
              child: Text(
                patient?.fullName.isNotEmpty == true
                    ? patient!.fullName[0].toUpperCase()
                    : 'P',
                style: TextStyle(
                    color: Colors.teal.shade700,
                    fontWeight: FontWeight.bold,
                    fontSize: 18),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    patient?.fullName ?? 'Unknown',
                    style: const TextStyle(
                        fontWeight: FontWeight.bold, fontSize: 15),
                  ),
                  Text(
                    'No: ${patient?.patientNumber ?? 'N/A'}',
                    style: TextStyle(color: Colors.grey[600], fontSize: 12),
                  ),
                  if (patient?.chiefComplaint.isNotEmpty == true)
                    Text(
                      patient!.chiefComplaint,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontSize: 12),
                    ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      Icon(Icons.access_time, size: 12, color: Colors.grey[500]),
                      const SizedBox(width: 3),
                      Text(
                        'Admitted: $durationStr ago',
                        style: TextStyle(fontSize: 11, color: Colors.grey[500]),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            ElevatedButton.icon(
              onPressed: onDischarge,
              icon: const Icon(Icons.exit_to_app, size: 14),
              label: const Text('Discharge'),
              style: ElevatedButton.styleFrom(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                textStyle: const TextStyle(fontSize: 11),
                backgroundColor: Colors.teal,
                foregroundColor: Colors.white,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
