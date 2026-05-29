import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../domain/entities/patient_record.dart';
import '../../domain/repositories/department_repository.dart';
import '../providers/auth_provider.dart';
import '../providers/department_provider.dart';
import '../providers/patient_provider.dart';
import '../providers/websocket_provider.dart';
import 'patient_detail_screen.dart';
import 'patient_registration_screen.dart';

class DepartmentQueueScreen extends ConsumerStatefulWidget {
  const DepartmentQueueScreen({super.key});

  @override
  ConsumerState<DepartmentQueueScreen> createState() =>
      _DepartmentQueueScreenState();
}

class _DepartmentQueueScreenState extends ConsumerState<DepartmentQueueScreen> with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    // Refresh queue when screen is first loaded
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final user = ref.read(authProvider).user;
      final isFrontDesk = user?.role == 'front_desk_officer';
      if (!isFrontDesk) {
        ref.read(departmentQueueNotifierProvider.notifier).refresh();
      }
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // Refresh queue when app comes back to foreground
    if (state == AppLifecycleState.resumed) {
      final user = ref.read(authProvider).user;
      final isFrontDesk = user?.role == 'front_desk_officer';
      if (!isFrontDesk) {
        ref.read(departmentQueueNotifierProvider.notifier).refresh();
      } else {
        ref.invalidate(todayRegistrationsProvider);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(authProvider).user;
    final role = user?.role ?? '';
    final isFrontDesk = role == 'front_desk_officer';

    return Scaffold(
      appBar: AppBar(
        title: Text(_titleForRole(role)),
        backgroundColor: Theme.of(context).colorScheme.inversePrimary,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () {
              if (isFrontDesk) {
                ref.invalidate(todayRegistrationsProvider);
              } else {
                ref.read(departmentQueueNotifierProvider.notifier).refresh();
              }
            },
          ),
          IconButton(
            icon: const Icon(Icons.logout),
            tooltip: 'Logout',
            onPressed: () async {
              await ref.read(authProvider.notifier).logout();
            },
          ),
        ],
      ),
      floatingActionButton: isFrontDesk
          ? FloatingActionButton.extended(
              onPressed: () async {
                await Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => const PatientRegistrationScreen(),
                  ),
                );
                // Always refresh after returning — whether registered or cancelled
                if (mounted) {
                  ref.invalidate(todayRegistrationsProvider);
                }
              },
              icon: const Icon(Icons.person_add),
              label: const Text('Register Patient'),
              backgroundColor: Theme.of(context).colorScheme.primary,
            )
          : null,
      body: isFrontDesk
          ? const _FrontDeskView()
          : _DepartmentQueueView(role: role),
    );
  }

  String _titleForRole(String role) {
    switch (role) {
      case 'front_desk_officer':
        return 'Front Desk';
      case 'doctor':
        return 'Doctor Queue';
      case 'pharmacist':
        return 'Pharmacy Queue';
      case 'lab_technician':
        return 'Lab Queue';
      case 'accountant':
        return 'Accounts Queue';
      case 'nurse':
        return 'Nurse Queue';
      case 'triage':
        return 'Triage Queue';
      default:
        return 'Department Queue';
    }
  }
}

// ── Front Desk: Today's Registrations ────────────────────────────────────────

class _FrontDeskView extends ConsumerWidget {
  const _FrontDeskView();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final todayAsync = ref.watch(todayRegistrationsProvider);

    // Listen for invoice settlement events and refresh the list
    ref.listen(webSocketEventsProvider, (previous, next) {
      next.whenData((event) {
        if (event.eventName == 'invoice.settled') {
          ref.invalidate(todayRegistrationsProvider);
        }
      });
    });

    return todayAsync.when(
      data: (registrations) {
        debugPrint('[FrontDeskDebug] Got ${registrations.length} registrations');
        for (final r in registrations) {
          debugPrint('[FrontDeskDebug] Patient: ${r.fullName}, id=${r.id}, photoKey=${r.photoObjectKey}');
        }
        if (registrations.isEmpty) {
          return Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.person_add, size: 64, color: Colors.grey),
                const SizedBox(height: 16),
                const Text(
                  'No patients registered today\nTap + to register a new patient',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 18, color: Colors.grey),
                ),
                const SizedBox(height: 24),
                ElevatedButton.icon(
                  onPressed: () async {
                    await Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => const PatientRegistrationScreen(),
                      ),
                    );
                    if (context.mounted) {
                      ref.invalidate(todayRegistrationsProvider);
                    }
                  },
                  icon: const Icon(Icons.person_add),
                  label: const Text('Register New Patient'),
                  style: ElevatedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 24, vertical: 12),
                  ),
                ),
              ],
            ),
          );
        }

        return Column(
          children: [
            Container(
              width: double.infinity,
              color: Theme.of(context).colorScheme.primaryContainer,
              padding:
                  const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              child: Text(
                'Today\'s Registrations (${registrations.length})',
                style: TextStyle(
                  fontWeight: FontWeight.bold,
                  color: Theme.of(context).colorScheme.onPrimaryContainer,
                ),
              ),
            ),
            Expanded(
              child: RefreshIndicator(
                onRefresh: () async =>
                    ref.invalidate(todayRegistrationsProvider),
                child: ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: registrations.length,
                  itemBuilder: (context, index) =>
                      _TodayRegistrationCard(reg: registrations[index]),
                ),
              ),
            ),
          ],
        );
      },
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (e, stack) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.error_outline, size: 48, color: Colors.red),
              const SizedBox(height: 12),
              const Text(
                'Could not load today\'s registrations',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 8),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.red.shade50,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: Colors.red.shade200),
                ),
                child: Text(
                  e.toString(),
                  textAlign: TextAlign.center,
                  style: TextStyle(color: Colors.red.shade800, fontSize: 13),
                ),
              ),
              const SizedBox(height: 16),
              ElevatedButton(
                onPressed: () => ref.invalidate(todayRegistrationsProvider),
                child: const Text('Retry'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _TodayRegistrationCard extends StatelessWidget {
  final TodayRegistration reg;

  const _TodayRegistrationCard({required this.reg});

  @override
  Widget build(BuildContext context) {
    final statusColor = _statusColor(reg.recordStatus);

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            _PatientAvatar(
              patientId: reg.id,
              name: reg.fullName,
              hasPhoto: reg.photoObjectKey != null,
              radius: 24,
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    reg.fullName,
                    style: const TextStyle(
                        fontWeight: FontWeight.bold, fontSize: 15),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    'No: ${reg.patientNumber}',
                    style:
                        TextStyle(color: Colors.grey[600], fontSize: 13),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    reg.chiefComplaint,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 13),
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 8, vertical: 2),
                        decoration: BoxDecoration(
                          color: statusColor.withValues(alpha: 0.15),
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
                      Text(
                        reg.currentDepartment,
                        style: TextStyle(
                            fontSize: 11, color: Colors.grey[600]),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            Text(
              _timeAgo(reg.createdAt),
              style: TextStyle(fontSize: 11, color: Colors.grey[500]),
            ),
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

  String _timeAgo(DateTime dt) {
    final diff = DateTime.now().difference(dt);
    if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
    return '${diff.inHours}h ago';
  }
}

// ── Other Departments: Standard Queue ────────────────────────────────────────

class _DepartmentQueueView extends ConsumerWidget {
  final String role;

  const _DepartmentQueueView({required this.role});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final queueAsync = ref.watch(departmentQueueNotifierProvider);

    return queueAsync.when(
      data: (records) => _QueueList(records: records, role: role),
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (error, _) => _ErrorView(
        message: error.toString(),
        onRetry: () =>
            ref.read(departmentQueueNotifierProvider.notifier).refresh(),
      ),
    );
  }
}

class _QueueList extends ConsumerWidget {
  final List<PatientRecord> records;
  final String role;

  const _QueueList({required this.records, required this.role});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (records.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              role == 'front_desk_officer' ? Icons.person_add : Icons.inbox,
              size: 64,
              color: Colors.grey,
            ),
            const SizedBox(height: 16),
            Text(
              role == 'front_desk_officer'
                  ? 'No patients yet\nTap + to register a new patient'
                  : 'No patients in queue',
              textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 18, color: Colors.grey),
            ),
            if (role == 'front_desk_officer') ...[
              const SizedBox(height: 24),
              ElevatedButton.icon(
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => const PatientRegistrationScreen(),
                    ),
                  );
                },
                icon: const Icon(Icons.person_add),
                label: const Text('Register New Patient'),
                style: ElevatedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 24,
                    vertical: 12,
                  ),
                ),
              ),
            ],
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () async {
        ref.read(departmentQueueNotifierProvider.notifier).refresh();
      },
      child: ListView.builder(
        itemCount: records.length,
        padding: const EdgeInsets.all(16),
        itemBuilder: (context, index) =>
            _PatientQueueCard(record: records[index]),
      ),
    );
  }
}

class _PatientQueueCard extends ConsumerWidget {
  final PatientRecord record;

  const _PatientQueueCard({required this.record});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final patient = record.patient;
    final timeInQueue = _timeInQueue(record.createdAt);

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () async {
          await Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => PatientDetailScreen(patientRecord: record),
            ),
          );
          // Refresh queue when returning from patient detail screen
          if (context.mounted) {
            ref.read(departmentQueueNotifierProvider.notifier).refresh();
          }
        },
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              _PatientAvatar(
                patientId: patient?.id ?? '',
                name: patient?.fullName ?? 'P',
                hasPhoto: patient?.photoObjectKey != null,
                radius: 24,
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      patient?.fullName ?? 'Unknown Patient',
                      style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'No: ${patient?.patientNumber ?? 'N/A'}',
                      style: TextStyle(color: Colors.grey[600], fontSize: 13),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      patient?.chiefComplaint ?? 'No complaint recorded',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontSize: 13),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    timeInQueue,
                    style: TextStyle(
                      color: _queueTimeColor(record.createdAt),
                      fontWeight: FontWeight.w600,
                      fontSize: 12,
                    ),
                  ),
                  const SizedBox(height: 4),
                  const Icon(Icons.arrow_forward_ios, size: 14, color: Colors.grey),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  String _timeInQueue(DateTime createdAt) {
    final diff = DateTime.now().difference(createdAt);
    if (diff.inMinutes < 60) return '${diff.inMinutes}m';
    if (diff.inHours < 24) return '${diff.inHours}h ${diff.inMinutes % 60}m';
    return '${diff.inDays}d';
  }

  Color _queueTimeColor(DateTime createdAt) {
    final minutes = DateTime.now().difference(createdAt).inMinutes;
    if (minutes < 30) return Colors.green;
    if (minutes < 60) return Colors.orange;
    return Colors.red;
  }
}

class _ErrorView extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _ErrorView({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 64, color: Colors.red),
            const SizedBox(height: 16),
            Text(
              message,
              textAlign: TextAlign.center,
              style: const TextStyle(color: Colors.red),
            ),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: onRetry,
              child: const Text('Retry'),
            ),
          ],
        ),
      ),
    );
  }
}

// ── Patient Avatar with photo support ────────────────────────────────────────

/// Shows the patient's photo if available, otherwise falls back to initials.
/// Fetches image bytes via the authenticated Dio client so the JWT header
/// is included — [NetworkImage] cannot do this without a custom [HttpClient].
class _PatientAvatar extends ConsumerWidget {
  final String patientId;
  final String name;
  final bool hasPhoto;
  final double radius;

  const _PatientAvatar({
    required this.patientId,
    required this.name,
    required this.hasPhoto,
    this.radius = 24,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final initial = name.isNotEmpty ? name.substring(0, 1).toUpperCase() : 'P';
    final primaryColor = Theme.of(context).colorScheme.primary;

    Widget fallback = CircleAvatar(
      radius: radius,
      backgroundColor: primaryColor,
      child: Text(initial,
          style: const TextStyle(
              color: Colors.white, fontWeight: FontWeight.bold)),
    );

    if (!hasPhoto || patientId.isEmpty) {
      debugPrint('[AvatarDebug] No photo for $patientId (hasPhoto=$hasPhoto)');
      return fallback;
    }

    debugPrint('[AvatarDebug] Watching photo for $patientId');
    final photoAsync = ref.watch(patientPhotoBytesProvider(patientId));

    return photoAsync.when(
      data: (bytes) {
        if (bytes == null || bytes.isEmpty) return fallback;
        return CircleAvatar(
          radius: radius,
          backgroundImage: MemoryImage(bytes),
        );
      },
      loading: () => CircleAvatar(
        radius: radius,
        backgroundColor: primaryColor.withValues(alpha: 0.4),
        child: Text(initial,
            style: const TextStyle(
                color: Colors.white, fontWeight: FontWeight.bold)),
      ),
      error: (_, __) => fallback,
    );
  }
}
