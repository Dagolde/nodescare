import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../domain/entities/patient_record.dart';
import '../../domain/repositories/department_repository.dart';
import '../providers/accounts_provider.dart';
import '../providers/auth_provider.dart';
import '../providers/billing_provider.dart';
import '../providers/department_provider.dart';
import '../providers/patient_provider.dart';
import 'appointment_booking_screen.dart';
import 'appointments_list_screen.dart';
import 'billing_form_screen.dart';
import 'doctor_consultation_screen.dart';
import 'invoice_creation_screen.dart';
import 'lab_results_screen.dart';
import 'patient_detail_screen.dart';
import 'patient_registration_screen.dart';
import 'patient_search_screen.dart';
import 'ward_dashboard_screen.dart';
import 'vitals_screen.dart';

// Provider for accounts queue used in the accountant dashboard
final _accountsQueueProvider = FutureProvider<List<PatientRecord>>((ref) async {
  final repo = ref.watch(accountsRepositoryProvider);
  return repo.getAccountsQueue();
});

/// Master role-based dashboard — routes each role to their specific UI.
class RoleDashboardScreen extends ConsumerWidget {
  const RoleDashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authProvider).user;
    final role = user?.role ?? '';

    switch (role) {
      case 'front_desk_officer':
        return const _FrontDeskDashboard();
      case 'doctor':
        return const _DoctorDashboard();
      case 'pharmacist':
        return const _PharmacistDashboard();
      case 'lab_technician':
        return const _LabDashboard();
      case 'accountant':
        return const _AccountantDashboard();
      case 'ward_staff':
      case 'ward_nurse':
        return const WardDashboardScreen();
      default:
        return const _NurseDashboard();
    }
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Shared AppBar
// ─────────────────────────────────────────────────────────────────────────────

AppBar _buildAppBar(
  BuildContext context,
  WidgetRef ref,
  String title, {
  List<Widget>? extraActions,
}) {
  return AppBar(
    title: Text(title),
    backgroundColor: Theme.of(context).colorScheme.inversePrimary,
    actions: [
      ...?extraActions,
      IconButton(
        icon: const Icon(Icons.refresh),
        tooltip: 'Refresh',
        onPressed: () =>
            ref.read(departmentQueueNotifierProvider.notifier).refresh(),
      ),
      IconButton(
        icon: const Icon(Icons.logout),
        tooltip: 'Logout',
        onPressed: () => ref.read(authProvider.notifier).logout(),
      ),
    ],
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Shared queue body
// ─────────────────────────────────────────────────────────────────────────────

class _QueueBody extends ConsumerWidget {
  final Widget? Function(PatientRecord)? trailingBuilder;

  const _QueueBody({this.trailingBuilder});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final queueAsync = ref.watch(departmentQueueNotifierProvider);

    return queueAsync.when(
      data: (records) {
        if (records.isEmpty) {
          return const Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.inbox, size: 64, color: Colors.grey),
                SizedBox(height: 16),
                Text('No patients in queue',
                    style: TextStyle(fontSize: 18, color: Colors.grey)),
              ],
            ),
          );
        }
        return RefreshIndicator(
          onRefresh: () async =>
              ref.read(departmentQueueNotifierProvider.notifier).refresh(),
          child: ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: records.length,
            itemBuilder: (context, i) => _PatientCard(
              record: records[i],
              trailing: trailingBuilder?.call(records[i]),
            ),
          ),
        );
      },
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (e, _) => Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 64, color: Colors.red),
            const SizedBox(height: 16),
            Text(e.toString(),
                textAlign: TextAlign.center,
                style: const TextStyle(color: Colors.red)),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: () =>
                  ref.read(departmentQueueNotifierProvider.notifier).refresh(),
              child: const Text('Retry'),
            ),
          ],
        ),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Patient card
// ─────────────────────────────────────────────────────────────────────────────

class _PatientCard extends StatelessWidget {
  final PatientRecord record;
  final Widget? trailing;

  const _PatientCard({required this.record, this.trailing});

  @override
  Widget build(BuildContext context) {
    final patient = record.patient;
    final diff = DateTime.now().difference(record.createdAt);
    final timeStr = diff.inMinutes < 60
        ? '${diff.inMinutes}m'
        : '${diff.inHours}h ${diff.inMinutes % 60}m';
    final timeColor = diff.inMinutes < 30
        ? Colors.green
        : diff.inMinutes < 60
            ? Colors.orange
            : Colors.red;

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () => Navigator.push(
          context,
          MaterialPageRoute(
              builder: (_) => PatientDetailScreen(patientRecord: record)),
        ),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              _RoleDashPatientAvatar(
                patientId: patient?.id ?? '',
                name: patient?.fullName ?? 'P',
                hasPhoto: patient?.photoObjectKey != null,
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
                    if (patient?.chiefComplaint != null)
                      Text(
                        patient!.chiefComplaint,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontSize: 12),
                      ),
                    // Visit type badge
                    if (record.visitType != null) ...[
                      const SizedBox(height: 3),
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(
                          color: Colors.blue.shade50,
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: Colors.blue.shade300),
                        ),
                        child: Text(
                          record.visitType!.replaceAll('_', ' ').toUpperCase(),
                          style: TextStyle(
                              fontSize: 9,
                              color: Colors.blue.shade700,
                              fontWeight: FontWeight.w600),
                        ),
                      ),
                    ],
                    // Assigned doctor name
                    if (record.assignedDoctorName != null) ...[
                      const SizedBox(height: 2),
                      Row(
                        children: [
                          Icon(Icons.person, size: 11, color: Colors.teal.shade600),
                          const SizedBox(width: 3),
                          Expanded(
                            child: Text(
                              'Dr: ${record.assignedDoctorName}',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                  fontSize: 11,
                                  color: Colors.teal.shade700,
                                  fontWeight: FontWeight.w500),
                            ),
                          ),
                        ],
                      ),
                    ],
                    if (record.isPaid &&
                        ((record.labBillItems != null && record.labBillItems!.isNotEmpty) ||
                         (record.pharmacyBillItems != null && record.pharmacyBillItems!.isNotEmpty))) ...[
                      const SizedBox(height: 3),
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(
                          color: Colors.green.shade50,
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: Colors.green.shade400),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(Icons.check_circle,
                                size: 10, color: Colors.green.shade700),
                            const SizedBox(width: 3),
                            Text(
                              'Bill Paid ✓',
                              style: TextStyle(
                                  fontSize: 10,
                                  color: Colors.green.shade700,
                                  fontWeight: FontWeight.w600),
                            ),
                          ],
                        ),
                      ),
                    ] else if (record.paymentReason != null) ...[
                      const SizedBox(height: 3),
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(
                          color: _billingColor(record.paymentContext)
                              .withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(
                              color: _billingColor(record.paymentContext)),
                        ),
                        child: Text(
                          record.paymentReason!,
                          style: TextStyle(
                              fontSize: 10,
                              color: _billingColor(record.paymentContext),
                              fontWeight: FontWeight.w600),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(width: 8),
              trailing ??
                  Column(
                    children: [
                      Text(timeStr,
                          style: TextStyle(
                              color: timeColor,
                              fontWeight: FontWeight.bold,
                              fontSize: 12)),
                      const Icon(Icons.arrow_forward_ios,
                          size: 14, color: Colors.grey),
                    ],
                  ),
            ],
          ),
        ),
      ),
    );
  }

  Color _billingColor(String? context) {
    switch (context) {
      case 'pharmacy_billing':
        return Colors.purple;
      case 'lab_billing':
        return Colors.blue;
      case 'admission_billing':
        return Colors.green;
      default:
        return Colors.orange;
    }
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// FRONT DESK
// ─────────────────────────────────────────────────────────────────────────────

class _FrontDeskDashboard extends ConsumerStatefulWidget {
  const _FrontDeskDashboard();

  @override
  ConsumerState<_FrontDeskDashboard> createState() =>
      _FrontDeskDashboardState();
}

class _FrontDeskDashboardState extends ConsumerState<_FrontDeskDashboard> {
  Timer? _refreshTimer;

  @override
  void initState() {
    super.initState();
    // Auto-refresh every 15 seconds so status changes (like drugs_given) appear promptly
    _refreshTimer = Timer.periodic(const Duration(seconds: 15), (_) {
      if (mounted) ref.invalidate(todayRegistrationsProvider);
    });
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final todayAsync = ref.watch(todayRegistrationsProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('🏥 Front Desk'),
        backgroundColor: Theme.of(context).colorScheme.inversePrimary,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: 'Refresh',
            onPressed: () => ref.invalidate(todayRegistrationsProvider),
          ),
          IconButton(
            icon: const Icon(Icons.logout),
            tooltip: 'Logout',
            onPressed: () => ref.read(authProvider.notifier).logout(),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _goRegister(context),
        icon: const Icon(Icons.person_add),
        label: const Text('Register Patient'),
      ),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.all(12),
            child: Row(
              children: [
                Expanded(
                  child: _ActionCard(
                    icon: Icons.person_add,
                    label: 'New\nPatient',
                    color: Colors.blue,
                    onTap: () => _goRegister(context),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _ActionCard(
                    icon: Icons.person_search,
                    label: 'Returning\nPatient',
                    color: Colors.teal,
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                          builder: (_) => const PatientSearchScreen()),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _ActionCard(
                    icon: Icons.calendar_month,
                    label: 'Check\nAppointments',
                    color: Colors.indigo,
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                          builder: (_) => const AppointmentsListScreen()),
                    ),
                  ),
                ),
              ],
            ),
          ),
          todayAsync.when(
            data: (list) => Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
              child: Text(
                'Today\'s Registrations (${list.length})',
                style: const TextStyle(
                    fontWeight: FontWeight.bold, fontSize: 16),
              ),
            ),
            loading: () => const SizedBox.shrink(),
            error: (_, __) => const SizedBox.shrink(),
          ),
          Expanded(
            child: todayAsync.when(
              data: (registrations) {
                if (registrations.isEmpty) {
                  return Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.people_outline,
                            size: 64, color: Colors.grey),
                        const SizedBox(height: 16),
                        const Text('No patients registered today',
                            style:
                                TextStyle(fontSize: 16, color: Colors.grey)),
                        const SizedBox(height: 24),
                        ElevatedButton.icon(
                          onPressed: () => _goRegister(context),
                          icon: const Icon(Icons.person_add),
                          label: const Text('Register First Patient'),
                        ),
                      ],
                    ),
                  );
                }
                return RefreshIndicator(
                  onRefresh: () async =>
                      ref.invalidate(todayRegistrationsProvider),
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: registrations.length,
                    itemBuilder: (context, i) =>
                        _TodayPatientCard(reg: registrations[i]),
                  ),
                );
              },
              loading: () =>
                  const Center(child: CircularProgressIndicator()),
              error: (e, _) => Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.error_outline,
                          size: 48, color: Colors.red),
                      const SizedBox(height: 12),
                      Text(e.toString(),
                          textAlign: TextAlign.center,
                          style: const TextStyle(color: Colors.red)),
                      const SizedBox(height: 16),
                      ElevatedButton(
                        onPressed: () =>
                            ref.invalidate(todayRegistrationsProvider),
                        child: const Text('Retry'),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _goRegister(BuildContext context) async {
    await Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => const PatientRegistrationScreen()),
    );
    if (mounted) {
      ref.invalidate(todayRegistrationsProvider);
    }
  }
}

class _TodayPatientCard extends StatelessWidget {
  final TodayRegistration reg;

  const _TodayPatientCard({required this.reg});

  @override
  Widget build(BuildContext context) {
    final statusColor = _statusColor(reg.recordStatus);
    final diff = DateTime.now().difference(reg.createdAt);
    final timeStr = diff.inMinutes < 60
        ? '${diff.inMinutes}m ago'
        : '${diff.inHours}h ago';

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          children: [
            _RoleDashPatientAvatar(
              patientId: reg.id,
              name: reg.fullName,
              hasPhoto: reg.photoObjectKey != null,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(reg.fullName,
                      style: const TextStyle(
                          fontWeight: FontWeight.bold, fontSize: 15)),
                  Text('No: ${reg.patientNumber}',
                      style: TextStyle(
                          color: Colors.grey[600], fontSize: 12)),
                  Text(reg.chiefComplaint,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontSize: 12)),
                  const SizedBox(height: 4),
                  // Visit type badge
                  if (reg.visitType != null) ...[
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(
                        color: Colors.blue.shade50,
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: Colors.blue.shade300),
                      ),
                      child: Text(
                        reg.visitType!.replaceAll('_', ' ').toUpperCase(),
                        style: TextStyle(fontSize: 9, color: Colors.blue.shade700, fontWeight: FontWeight.w600),
                      ),
                    ),
                    const SizedBox(height: 2),
                  ],
                  // Assigned doctor
                  if (reg.assignedDoctorName != null)
                    Row(
                      children: [
                        Icon(Icons.person, size: 11, color: Colors.teal.shade600),
                        const SizedBox(width: 3),
                        Expanded(
                          child: Text(
                            'Dr: ${reg.assignedDoctorName}',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(fontSize: 11, color: Colors.teal.shade700, fontWeight: FontWeight.w500),
                          ),
                        ),
                      ],
                    ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 8, vertical: 2),
                        decoration: BoxDecoration(
                          color: statusColor.withAlpha(30),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: statusColor),
                        ),
                        child: Text(
                          _statusLabel(reg.recordStatus),
                          style: TextStyle(
                              fontSize: 11,
                              color: statusColor,
                              fontWeight: FontWeight.w600),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Text(reg.currentDepartment,
                          style: TextStyle(
                              fontSize: 11, color: Colors.grey[600])),
                    ],
                  ),
                ],
              ),
            ),
            Text(timeStr,
                style:
                    TextStyle(fontSize: 11, color: Colors.grey[500])),
          ],
        ),
      ),
    );
  }

  Color _statusColor(String status) {
    switch (status) {
      case 'pending_payment':
        return Colors.orange;
      case 'active':
        return Colors.blue;
      case 'admitted':
        return Colors.purple;
      case 'drugs_given':
        return Colors.green;
      case 'discharged':
      case 'completed':
        return Colors.green;
      default:
        return Colors.grey;
    }
  }

  String _statusLabel(String status) {
    switch (status) {
      case 'pending_payment':
        return 'Pending Payment';
      case 'active':
        return 'Active';
      case 'in_transit':
        return 'In Transit';
      case 'admitted':
        return 'Admitted';
      case 'drugs_given':
        return '💊 Drugs Given';
      case 'discharged':
        return 'Discharged';
      case 'completed':
        return 'Completed';
      default:
        return status.toUpperCase();
    }
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// NURSE / TRIAGE
// ─────────────────────────────────────────────────────────────────────────────

class _NurseDashboard extends ConsumerStatefulWidget {
  const _NurseDashboard();

  @override
  ConsumerState<_NurseDashboard> createState() => _NurseDashboardState();
}

class _NurseDashboardState extends ConsumerState<_NurseDashboard> {
  final Set<String> _routingIds = {};

  Future<void> _routeToDoctor(PatientRecord record) async {
    if (_routingIds.contains(record.id)) return;
    setState(() => _routingIds.add(record.id));
    try {
      final ds = ref.read(remoteDataSourceProvider);
      await ds.post('/api/v1/patient-records/${record.id}/route', data: {
        'department_id': record.assignedDoctorDepartmentId,
        'notes': 'Routed to assigned doctor by nurse/triage.',
      });
      ref.read(departmentQueueNotifierProvider.notifier).refresh();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Patient routed to Dr. ${record.assignedDoctorName ?? ""}'),
            backgroundColor: Colors.green,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Failed to route patient: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _routingIds.remove(record.id));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: _buildAppBar(context, ref, '🩺 Triage / Nursing'),
      body: Column(
        children: [
          _InfoBanner(
            message: 'Assess patients, record vitals, then route to the doctor.',
            color: Colors.teal.shade50,
            icon: Icons.monitor_heart,
          ),
          Expanded(
            child: _QueueBody(
              trailingBuilder: (record) => Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  ElevatedButton.icon(
                    onPressed: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => VitalsScreen(patientRecord: record),
                      ),
                    ).then((_) => ref.read(departmentQueueNotifierProvider.notifier).refresh()),
                    icon: const Icon(Icons.monitor_heart, size: 16),
                    label: const Text('Vitals'),
                    style: ElevatedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                      textStyle: const TextStyle(fontSize: 12),
                      backgroundColor: Colors.teal,
                      foregroundColor: Colors.white,
                    ),
                  ),
                  if (record.assignedDoctorDepartmentId != null) ...[
                    const SizedBox(height: 6),
                    ElevatedButton.icon(
                      onPressed: _routingIds.contains(record.id)
                          ? null
                          : () => _routeToDoctor(record),
                      icon: _routingIds.contains(record.id)
                          ? const SizedBox(
                              width: 12,
                              height: 12,
                              child: CircularProgressIndicator(
                                  strokeWidth: 2, color: Colors.white))
                          : const Icon(Icons.send, size: 14),
                      label: Text(
                        'Dr. ${record.assignedDoctorName ?? ""}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      style: ElevatedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 8, vertical: 6),
                        textStyle: const TextStyle(fontSize: 11),
                        backgroundColor: Colors.indigo,
                        foregroundColor: Colors.white,
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// DOCTOR
// ─────────────────────────────────────────────────────────────────────────────

class _DoctorDashboard extends ConsumerWidget {
  const _DoctorDashboard();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      appBar: _buildAppBar(
        context,
        ref,
        '👨‍⚕️ Doctor',
        extraActions: [
          IconButton(
            icon: const Icon(Icons.calendar_today),
            tooltip: 'Book Appointment',
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(
                  builder: (_) => const AppointmentBookingScreen()),
            ),
          ),
          IconButton(
            icon: const Icon(Icons.calendar_month),
            tooltip: 'Check Appointments',
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(
                  builder: (_) => const AppointmentsListScreen()),
            ),
          ),
        ],
      ),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.all(12),
            child: Row(
              children: [
                Expanded(
                  child: _ActionCard(
                    icon: Icons.calendar_today,
                    label: 'Book\nAppointment',
                    color: Colors.indigo,
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                          builder: (_) => const AppointmentBookingScreen()),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _ActionCard(
                    icon: Icons.calendar_month,
                    label: 'Check\nAppointments',
                    color: Colors.teal,
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                          builder: (_) => const AppointmentsListScreen()),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _ActionCard(
                    icon: Icons.people,
                    label: 'My\nQueue',
                    color: Colors.blue,
                    onTap: () {},
                  ),
                ),
              ],
            ),
          ),
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 16, vertical: 4),
            child: Text('Patient Queue',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
          ),
          Expanded(
            child: _QueueBody(
              trailingBuilder: (record) => ElevatedButton.icon(
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) =>
                        DoctorConsultationScreen(patientRecord: record),
                  ),
                ).then((_) => ref
                    .read(departmentQueueNotifierProvider.notifier)
                    .refresh()),
                icon: const Icon(Icons.medical_information, size: 16),
                label: const Text('Consult'),
                style: ElevatedButton.styleFrom(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                  textStyle: const TextStyle(fontSize: 12),
                  backgroundColor: Colors.indigo,
                  foregroundColor: Colors.white,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// PHARMACIST
// ─────────────────────────────────────────────────────────────────────────────

class _PharmacistDashboard extends ConsumerStatefulWidget {
  const _PharmacistDashboard();

  @override
  ConsumerState<_PharmacistDashboard> createState() => _PharmacistDashboardState();
}

class _PharmacistDashboardState extends ConsumerState<_PharmacistDashboard> {
  // Track which records have been dispensed locally so the badge persists across queue refreshes
  final Set<String> _dispensedIds = {};

  void _markDispensed(String recordId) {
    setState(() => _dispensedIds.add(recordId));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: _buildAppBar(context, ref, '💊 Pharmacy'),
      body: Column(
        children: [
          _InfoBanner(
            message:
                'Bill drugs for patients, then confirm dispense after payment.',
            color: Colors.green.shade50,
            icon: Icons.medication,
          ),
          Expanded(
            child: _QueueBody(
              trailingBuilder: (record) => _PharmacistActions(
                record: record,
                ref: ref,
                isDispensed: _dispensedIds.contains(record.id),
                onDispensed: () => _markDispensed(record.id),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _PharmacistActions extends ConsumerStatefulWidget {
  final PatientRecord record;
  final WidgetRef ref;
  final bool isDispensed;
  final VoidCallback onDispensed;

  const _PharmacistActions({
    required this.record,
    required this.ref,
    required this.isDispensed,
    required this.onDispensed,
  });

  @override
  ConsumerState<_PharmacistActions> createState() => _PharmacistActionsState();
}

class _PharmacistActionsState extends ConsumerState<_PharmacistActions> {
  bool _dispensing = false;

  PatientRecord get record => widget.record;

  /// Patient has returned from Accounts after pharmacy payment specifically.
  /// Must have pharmacy bill items (meaning pharmacy billed drugs and payment was made).
  bool get _needsDispenseConfirmation =>
      record.status == 'active' &&
      record.isPaid &&
      (record.pharmacyBillItems != null && record.pharmacyBillItems!.isNotEmpty);

  /// Patient is pending payment — they're at Accounts, not in pharmacy queue.
  bool get _pendingPayment => record.status == 'pending_payment';

  @override
  Widget build(BuildContext context) {
    // Show "Drugs Given" badge — persists across queue refreshes via parent state
    if (widget.isDispensed) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
        decoration: BoxDecoration(
          color: Colors.green.shade50,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: Colors.green.shade400),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.check_circle, size: 14, color: Colors.green.shade700),
            const SizedBox(width: 4),
            Text('💊 Drugs Given',
                style: TextStyle(
                    fontSize: 11,
                    color: Colors.green.shade700,
                    fontWeight: FontWeight.w600)),
          ],
        ),
      );
    }

    if (_pendingPayment) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
        decoration: BoxDecoration(
          color: Colors.orange.shade50,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: Colors.orange.shade300),
        ),
        child: Text(
          'Awaiting\nPayment',
          textAlign: TextAlign.center,
          style: TextStyle(
              fontSize: 10,
              color: Colors.orange.shade700,
              fontWeight: FontWeight.w600),
        ),
      );
    }

    if (_needsDispenseConfirmation) {
      return Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
            decoration: BoxDecoration(
              color: Colors.green.shade50,
              borderRadius: BorderRadius.circular(6),
              border: Border.all(color: Colors.green.shade400),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.check_circle, size: 10, color: Colors.green.shade700),
                const SizedBox(width: 3),
                Text('Bill Paid ✓',
                    style: TextStyle(
                        fontSize: 10,
                        color: Colors.green.shade700,
                        fontWeight: FontWeight.w600)),
              ],
            ),
          ),
          const SizedBox(height: 4),
          ElevatedButton.icon(
            onPressed: _dispensing ? null : () => _confirmDispense(context),
            icon: _dispensing
                ? const SizedBox(
                    width: 12,
                    height: 12,
                    child: CircularProgressIndicator(
                        strokeWidth: 2, color: Colors.white))
                : const Icon(Icons.check_circle, size: 14),
            label: const Text('Confirm\nDispense'),
            style: ElevatedButton.styleFrom(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
              textStyle: const TextStyle(fontSize: 11),
              backgroundColor: Colors.green,
              foregroundColor: Colors.white,
            ),
          ),
        ],
      );
    }

    // Default: show Bill Drugs button only — dispense locked until bill paid
    return ElevatedButton.icon(
      onPressed: () => Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => BillingFormScreen(
            patientRecordId: record.id,
            billingType: BillingType.pharmacy,
            patientRecord: record,
          ),
        ),
      ).then((_) =>
          ref.read(departmentQueueNotifierProvider.notifier).refresh()),
      icon: const Icon(Icons.receipt_long, size: 14),
      label: const Text('Bill Drugs'),
      style: ElevatedButton.styleFrom(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
        textStyle: const TextStyle(fontSize: 11),
        backgroundColor: Colors.purple,
        foregroundColor: Colors.white,
      ),
    );
  }

  Future<void> _confirmDispense(BuildContext context) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Confirm Dispense'),
        content: const Text(
            'Confirm that all drugs have been dispensed to the patient?'),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Cancel')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.green),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Confirm',
                style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );

    if (confirmed != true) return;

    setState(() => _dispensing = true);
    try {
      await ref
          .read(confirmDispenseProvider.notifier)
          .confirmDispense(record.id);
      // Notify parent to mark this record as dispensed (persists across queue refreshes)
      widget.onDispensed();
      if (mounted) setState(() => _dispensing = false);
      // Refresh queue after cache expires (5 seconds)
      await Future.delayed(const Duration(seconds: 6));
      ref.read(departmentQueueNotifierProvider.notifier).refresh();
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
              content: Text('Dispense confirmed — patient routed to front desk'),
              backgroundColor: Colors.green),
        );
      }
    } catch (e) {
      if (mounted) setState(() => _dispensing = false);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
              content: Text('Failed: $e'), backgroundColor: Colors.red),
        );
      }
    }
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// LAB TECHNICIAN
// ─────────────────────────────────────────────────────────────────────────────

class _LabDashboard extends ConsumerWidget {
  const _LabDashboard();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      appBar: _buildAppBar(context, ref, '🔬 Laboratory'),
      body: Column(
        children: [
          _InfoBanner(
            message:
                'Bill investigations, then enter results after patient returns from payment.',
            color: Colors.purple.shade50,
            icon: Icons.biotech,
          ),
          Expanded(
            child: _QueueBody(
              trailingBuilder: (record) =>
                  _LabActions(record: record, ref: ref),
            ),
          ),
        ],
      ),
    );
  }
}

class _LabActions extends ConsumerWidget {
  final PatientRecord record;
  final WidgetRef ref;

  const _LabActions({required this.record, required this.ref});

  bool get _pendingPayment => record.status == 'pending_payment';

  /// Patient has returned from Accounts after lab payment specifically.
  /// Must have lab bill items (meaning lab billed tests and payment was made).
  bool get _readyForResults =>
      record.status == 'active' &&
      record.isPaid &&
      (record.labBillItems != null && record.labBillItems!.isNotEmpty);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (_pendingPayment) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
        decoration: BoxDecoration(
          color: Colors.orange.shade50,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: Colors.orange.shade300),
        ),
        child: Text(
          'Awaiting\nPayment',
          textAlign: TextAlign.center,
          style: TextStyle(
              fontSize: 10,
              color: Colors.orange.shade700,
              fontWeight: FontWeight.w600),
        ),
      );
    }

    if (_readyForResults) {
      return Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
            decoration: BoxDecoration(
              color: Colors.green.shade50,
              borderRadius: BorderRadius.circular(6),
              border: Border.all(color: Colors.green.shade400),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.check_circle, size: 10, color: Colors.green.shade700),
                const SizedBox(width: 3),
                Text('Bill Paid ✓',
                    style: TextStyle(
                        fontSize: 10,
                        color: Colors.green.shade700,
                        fontWeight: FontWeight.w600)),
              ],
            ),
          ),
          const SizedBox(height: 4),
          ElevatedButton.icon(
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(
                  builder: (_) => LabResultsScreen(patientRecord: record)),
            ).then((_) =>
                ref.read(departmentQueueNotifierProvider.notifier).refresh()),
            icon: const Icon(Icons.science, size: 14),
            label: const Text('Enter\nResults'),
            style: ElevatedButton.styleFrom(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
              textStyle: const TextStyle(fontSize: 11),
              backgroundColor: Colors.purple,
              foregroundColor: Colors.white,
            ),
          ),
        ],
      );
    }

    // Default: show Bill Investigations button only — results locked until bill paid
    return ElevatedButton.icon(
      onPressed: () => Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => BillingFormScreen(
            patientRecordId: record.id,
            billingType: BillingType.lab,
            patientRecord: record,
          ),
        ),
      ).then((_) =>
          ref.read(departmentQueueNotifierProvider.notifier).refresh()),
      icon: const Icon(Icons.receipt_long, size: 14),
      label: const Text('Bill Tests'),
      style: ElevatedButton.styleFrom(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
        textStyle: const TextStyle(fontSize: 11),
        backgroundColor: Colors.blue,
        foregroundColor: Colors.white,
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// ACCOUNTANT
// ─────────────────────────────────────────────────────────────────────────────

class _AccountantDashboard extends ConsumerWidget {
  const _AccountantDashboard();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final queueAsync = ref.watch(_accountsQueueProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('💰 Accounts'),
        backgroundColor: Theme.of(context).colorScheme.inversePrimary,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: 'Refresh',
            onPressed: () => ref.invalidate(_accountsQueueProvider),
          ),
          IconButton(
            icon: const Icon(Icons.logout),
            tooltip: 'Logout',
            onPressed: () => ref.read(authProvider.notifier).logout(),
          ),
        ],
      ),
      body: Column(
        children: [
          _InfoBanner(
            message:
                'Process payments for patients awaiting billing. Tap a patient to create an invoice.',
            color: Colors.amber.shade50,
            icon: Icons.receipt_long,
          ),
          Expanded(
            child: queueAsync.when(
              data: (records) {
                if (records.isEmpty) {
                  return const Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.check_circle_outline,
                            size: 64, color: Colors.green),
                        SizedBox(height: 16),
                        Text('No pending payments',
                            style:
                                TextStyle(fontSize: 18, color: Colors.grey)),
                      ],
                    ),
                  );
                }
                return RefreshIndicator(
                  onRefresh: () async =>
                      ref.invalidate(_accountsQueueProvider),
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: records.length,
                    itemBuilder: (context, i) {
                      final record = records[i];
                      final patient = record.patient;
                      final diff = DateTime.now().difference(record.createdAt);
                      final timeStr = diff.inMinutes < 60
                          ? '${diff.inMinutes}m'
                          : '${diff.inHours}h';
                      final hasInvoice = record.hasInvoice;
                      final isPaid = record.isPaid;

                      return Card(
                        margin: const EdgeInsets.only(bottom: 12),
                        child: InkWell(
                          borderRadius: BorderRadius.circular(12),
                          onTap: () => Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) => InvoiceCreationScreen(
                                patientRecord: record,
                              ),
                            ),
                          ).then((_) => ref.invalidate(_accountsQueueProvider)),
                          child: Padding(
                            padding: const EdgeInsets.all(14),
                            child: Row(
                              children: [
                                _RoleDashPatientAvatar(
                                  patientId: patient?.id ?? '',
                                  name: patient?.fullName ?? 'P',
                                  hasPhoto: patient?.photoObjectKey != null,
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        patient?.fullName ?? 'Unknown',
                                        style: const TextStyle(
                                            fontWeight: FontWeight.bold,
                                            fontSize: 15),
                                      ),
                                      Text(
                                        'No: ${patient?.patientNumber ?? 'N/A'}',
                                        style: TextStyle(
                                            color: Colors.grey[600],
                                            fontSize: 12),
                                      ),
                                      if (record.paymentReason != null)
                                        Container(
                                          margin: const EdgeInsets.only(top: 4),
                                          padding: const EdgeInsets.symmetric(
                                              horizontal: 8, vertical: 2),
                                          decoration: BoxDecoration(
                                            color: record.paymentContext == 'lab_referral'
                                                ? Colors.blue.shade50
                                                : Colors.purple.shade50,
                                            borderRadius: BorderRadius.circular(12),
                                            border: Border.all(
                                              color: record.paymentContext == 'lab_referral'
                                                  ? Colors.blue.shade300
                                                  : Colors.purple.shade300,
                                            ),
                                          ),
                                          child: Text(
                                            record.paymentReason!,
                                            style: TextStyle(
                                              fontSize: 11,
                                              fontWeight: FontWeight.w600,
                                              color: record.paymentContext == 'lab_referral'
                                                  ? Colors.blue.shade700
                                                  : Colors.purple.shade700,
                                            ),
                                          ),
                                        ),
                                      if (patient?.chiefComplaint != null && record.paymentReason == null)
                                        Text(
                                          patient!.chiefComplaint,
                                          maxLines: 1,
                                          overflow: TextOverflow.ellipsis,
                                          style:
                                              const TextStyle(fontSize: 12),
                                        ),
                                    ],
                                  ),
                                ),
                                Column(
                                  crossAxisAlignment: CrossAxisAlignment.end,
                                  children: [
                                    Text(timeStr,
                                        style: TextStyle(
                                            color: Colors.grey[500],
                                            fontSize: 11)),
                                    const SizedBox(height: 4),
                                    _InvoiceBadge(
                                      hasInvoice: hasInvoice,
                                      isPaid: isPaid,
                                      invoiceStatus: record.invoiceStatus,
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                        ),
                      );
                    },
                  ),
                );
              },
              loading: () =>
                  const Center(child: CircularProgressIndicator()),
              error: (e, _) => Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(Icons.error_outline,
                        size: 48, color: Colors.red),
                    const SizedBox(height: 12),
                    Text(e.toString(),
                        textAlign: TextAlign.center,
                        style: const TextStyle(color: Colors.red)),
                    const SizedBox(height: 16),
                    ElevatedButton(
                      onPressed: () => ref.invalidate(_accountsQueueProvider),
                      child: const Text('Retry'),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Shared widgets
// ─────────────────────────────────────────────────────────────────────────────

class _ActionCard extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;

  const _ActionCard({
    required this.icon,
    required this.label,
    required this.color,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 2,
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 12),
          child: Column(
            children: [
              Icon(icon, size: 36, color: color),
              const SizedBox(height: 8),
              Text(
                label,
                textAlign: TextAlign.center,
                style: TextStyle(
                    fontWeight: FontWeight.w600, fontSize: 13, color: color),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _InvoiceBadge extends StatelessWidget {
  final bool hasInvoice;
  final bool isPaid;
  final String? invoiceStatus;

  const _InvoiceBadge({
    required this.hasInvoice,
    required this.isPaid,
    this.invoiceStatus,
  });

  @override
  Widget build(BuildContext context) {
    if (isPaid) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
        decoration: BoxDecoration(
          color: Colors.green.shade50,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: Colors.green.shade400),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.check_circle, size: 11, color: Colors.green.shade700),
            const SizedBox(width: 3),
            Text(
              'Paid',
              style: TextStyle(
                  fontSize: 11,
                  color: Colors.green.shade700,
                  fontWeight: FontWeight.w600),
            ),
          ],
        ),
      );
    }
    if (hasInvoice) {
      final label = invoiceStatus == 'partially_paid'
          ? 'Part Paid'
          : 'Invoice Created';
      final color = invoiceStatus == 'partially_paid'
          ? Colors.blue.shade700
          : Colors.blue.shade600;
      final bg = invoiceStatus == 'partially_paid'
          ? Colors.blue.shade50
          : Colors.blue.shade50;
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
        decoration: BoxDecoration(
          color: bg,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: color),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.receipt, size: 11, color: color),
            const SizedBox(width: 3),
            Text(
              label,
              style: TextStyle(
                  fontSize: 11,
                  color: color,
                  fontWeight: FontWeight.w600),
            ),
          ],
        ),
      );
    }
    // No invoice yet
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: Colors.orange.shade50,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.orange.shade300),
      ),
      child: const Text(
        'Create Invoice',
        style: TextStyle(
            fontSize: 11,
            color: Colors.orange,
            fontWeight: FontWeight.w600),
      ),
    );
  }
}

class _InfoBanner extends StatelessWidget {
  final String message;
  final Color color;
  final IconData icon;

  const _InfoBanner({
    required this.message,
    required this.color,
    required this.icon,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.all(12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        children: [
          Icon(icon, color: Colors.grey[700]),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              message,
              style: TextStyle(color: Colors.grey[800], fontSize: 13),
            ),
          ),
        ],
      ),
    );
  }
}

// ── Patient Avatar with photo support (role dashboard) ───────────────────────

class _RoleDashPatientAvatar extends ConsumerWidget {
  final String patientId;
  final String name;
  final bool hasPhoto;

  const _RoleDashPatientAvatar({
    required this.patientId,
    required this.name,
    required this.hasPhoto,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final initial = name.isNotEmpty ? name.substring(0, 1).toUpperCase() : 'P';
    final primaryColor = Theme.of(context).colorScheme.primary;

    Widget fallback = CircleAvatar(
      radius: 22,
      backgroundColor: primaryColor,
      child: Text(initial,
          style: const TextStyle(
              color: Colors.white, fontWeight: FontWeight.bold)),
    );

    if (!hasPhoto || patientId.isEmpty) return fallback;

    final photoAsync = ref.watch(patientPhotoBytesProvider(patientId));

    return photoAsync.when(
      data: (bytes) {
        if (bytes == null || bytes.isEmpty) return fallback;
        return CircleAvatar(
          radius: 22,
          backgroundImage: MemoryImage(bytes),
        );
      },
      loading: () => CircleAvatar(
        radius: 22,
        backgroundColor: primaryColor.withValues(alpha: 0.4),
        child: Text(initial,
            style: const TextStyle(
                color: Colors.white, fontWeight: FontWeight.bold)),
      ),
      error: (_, __) => fallback,
    );
  }
}
