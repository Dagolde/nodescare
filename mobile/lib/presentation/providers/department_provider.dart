import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/repositories/department_repository_impl.dart';
import '../../domain/entities/patient_record.dart';
import '../../domain/repositories/department_repository.dart';
import '../../services/websocket_service.dart';
import 'accounts_provider.dart';

// Department repository provider
final departmentRepositoryProvider = Provider<DepartmentRepository>((ref) {
  final remoteDataSource = ref.watch(remoteDataSourceProvider);
  return DepartmentRepositoryImpl(remoteDataSource: remoteDataSource);
});

// Department queue provider (initial fetch)
final departmentQueueProvider = FutureProvider<List<PatientRecord>>((ref) async {
  final repository = ref.watch(departmentRepositoryProvider);
  return await repository.getDepartmentQueue();
});

// Journey history provider for a specific patient record
final journeyHistoryProvider =
    FutureProvider.family<List<JourneyEvent>, String>((ref, patientRecordId) async {
  final repository = ref.watch(departmentRepositoryProvider);
  return await repository.getJourneyHistory(patientRecordId);
});

// Today's registrations provider — for front desk officers
final todayRegistrationsProvider =
    FutureProvider<List<TodayRegistration>>((ref) async {
  final repository = ref.watch(departmentRepositoryProvider);
  return await repository.getRegisteredToday();
});

// State notifier for the live department queue (supports real-time updates)
class DepartmentQueueNotifier extends StateNotifier<AsyncValue<List<PatientRecord>>> {
  final DepartmentRepository repository;
  final WebSocketService _wsService;
  String? _subscribedDepartmentId;

  DepartmentQueueNotifier(this.repository, this._wsService)
      : super(const AsyncValue.loading()) {
    _loadQueue();
    // Listen to the centralised WebSocket event stream
    _wsService.events.listen(_onWebSocketEvent);
  }

  Future<void> _loadQueue() async {
    state = const AsyncValue.loading();
    try {
      final records = await repository.getDepartmentQueue();
      state = AsyncValue.data(records);
    } catch (e, stack) {
      state = AsyncValue.error(e, stack);
    }
  }

  Future<void> refresh() => _loadQueue();

  /// Subscribe to the department channel via the centralised [WebSocketService].
  Future<void> subscribeToChannel(
      String departmentId, String authEndpoint, String token) async {
    if (_subscribedDepartmentId == departmentId) return;
    _subscribedDepartmentId = departmentId;

    try {
      await _wsService.connect(authEndpoint, token);
      await _wsService.subscribeToChannel('private-department.$departmentId');
    } catch (_) {
      // WebSocket failure is non-fatal; polling still works via refresh
    }
  }

  void _onWebSocketEvent(WebSocketEvent event) {
    if (event.eventName == 'PatientRouted' ||
        event.eventName == 'PatientStatusChanged') {
      _loadQueue();
    }
  }

  @override
  void dispose() {
    if (_subscribedDepartmentId != null) {
      _wsService.unsubscribeFromChannel(
          'private-department.$_subscribedDepartmentId');
    }
    super.dispose();
  }
}

final departmentQueueNotifierProvider =
    StateNotifierProvider<DepartmentQueueNotifier, AsyncValue<List<PatientRecord>>>((ref) {
  final repository = ref.watch(departmentRepositoryProvider);
  final wsService = WebSocketService.instance;
  return DepartmentQueueNotifier(repository, wsService);
});

// Action notifiers

class AddNoteNotifier extends StateNotifier<AsyncValue<void>> {
  final DepartmentRepository repository;

  AddNoteNotifier(this.repository) : super(const AsyncValue.data(null));

  Future<void> addNote(
    String patientRecordId,
    String note, {
    String? complaintHistory,
    String? examinationFindings,
  }) async {
    state = const AsyncValue.loading();
    try {
      await repository.addNote(
        patientRecordId,
        note,
        complaintHistory: complaintHistory,
        examinationFindings: examinationFindings,
      );
      state = const AsyncValue.data(null);
    } catch (e, stack) {
      state = AsyncValue.error(e, stack);
    }
  }

  void reset() => state = const AsyncValue.data(null);
}

final addNoteProvider =
    StateNotifierProvider<AddNoteNotifier, AsyncValue<void>>((ref) {
  final repository = ref.watch(departmentRepositoryProvider);
  return AddNoteNotifier(repository);
});

class CompleteStepNotifier extends StateNotifier<AsyncValue<void>> {
  final DepartmentRepository repository;

  CompleteStepNotifier(this.repository) : super(const AsyncValue.data(null));

  Future<void> completeStep(
    String patientRecordId, {
    String? complaintHistory,
    String? examinationFindings,
  }) async {
    state = const AsyncValue.loading();
    try {
      await repository.completeStep(
        patientRecordId,
        complaintHistory: complaintHistory,
        examinationFindings: examinationFindings,
      );
      state = const AsyncValue.data(null);
    } catch (e, stack) {
      state = AsyncValue.error(e, stack);
    }
  }

  void reset() => state = const AsyncValue.data(null);
}

final completeStepProvider =
    StateNotifierProvider<CompleteStepNotifier, AsyncValue<void>>((ref) {
  final repository = ref.watch(departmentRepositoryProvider);
  return CompleteStepNotifier(repository);
});

class DoctorActionNotifier extends StateNotifier<AsyncValue<void>> {
  final DepartmentRepository repository;

  DoctorActionNotifier(this.repository) : super(const AsyncValue.data(null));

  Future<void> routeToPharmacy(
    String patientRecordId,
    String notes, {
    String? complaintHistory,
    String? examinationFindings,
  }) async {
    state = const AsyncValue.loading();
    try {
      await repository.routeToPharmacy(
        patientRecordId,
        notes,
        complaintHistory: complaintHistory,
        examinationFindings: examinationFindings,
      );
      state = const AsyncValue.data(null);
    } catch (e, stack) {
      state = AsyncValue.error(e, stack);
    }
  }

  Future<void> routeToLab(
    String patientRecordId,
    String notes, {
    String? complaintHistory,
    String? examinationFindings,
  }) async {
    state = const AsyncValue.loading();
    try {
      await repository.routeToLab(
        patientRecordId,
        notes,
        complaintHistory: complaintHistory,
        examinationFindings: examinationFindings,
      );
      state = const AsyncValue.data(null);
    } catch (e, stack) {
      state = AsyncValue.error(e, stack);
    }
  }

  Future<void> admitPatient(String patientRecordId) async {
    state = const AsyncValue.loading();
    try {
      await repository.admitPatient(patientRecordId);
      state = const AsyncValue.data(null);
    } catch (e, stack) {
      state = AsyncValue.error(e, stack);
    }
  }

  void reset() => state = const AsyncValue.data(null);
}

final doctorActionProvider =
    StateNotifierProvider<DoctorActionNotifier, AsyncValue<void>>((ref) {
  final repository = ref.watch(departmentRepositoryProvider);
  return DoctorActionNotifier(repository);
});


// ─── Patient Vitals Provider ────────────────────────────────────────────────

final patientVitalsProvider = FutureProvider.family<List<PatientVitals>, String>(
  (ref, patientRecordId) async {
    final repository = ref.read(departmentRepositoryProvider);
    return repository.getVitals(patientRecordId);
  },
);

// ─── Patient Lab Results Provider ───────────────────────────────────────────

final patientLabResultsProvider = FutureProvider.family<List<LabResult>, String>(
  (ref, patientRecordId) async {
    final repository = ref.read(departmentRepositoryProvider);
    return repository.getLabResults(patientRecordId);
  },
);
