import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/repositories/clinical_repository_impl.dart';
import '../../domain/repositories/clinical_repository.dart';
import 'accounts_provider.dart';

// Clinical repository provider
final clinicalRepositoryProvider = Provider<ClinicalRepository>((ref) {
  final remoteDataSource = ref.watch(remoteDataSourceProvider);
  return ClinicalRepositoryImpl(remoteDataSource: remoteDataSource);
});

// Appointments list provider (doctor's own appointments)
final appointmentsProvider = FutureProvider<List<Appointment>>((ref) async {
  final repository = ref.watch(clinicalRepositoryProvider);
  return await repository.listAppointments();
});

// Today's appointments — all appointments for today (front desk + doctor view)
final todaysAppointmentsListProvider = FutureProvider<List<Appointment>>((ref) async {
  final repository = ref.watch(clinicalRepositoryProvider);
  return await repository.listTodaysAppointments();
});

// All appointments — full list with dates for doctor and front desk
final allAppointmentsProvider = FutureProvider<List<Appointment>>((ref) async {
  final repository = ref.watch(clinicalRepositoryProvider);
  return await repository.listAllAppointments();
});

// ─── Appointment Booking ───────────────────────────────────────────────────

class AppointmentBookingNotifier
    extends StateNotifier<AsyncValue<ClinicalResult<Appointment>?>> {
  final ClinicalRepository repository;

  AppointmentBookingNotifier(this.repository)
      : super(const AsyncValue.data(null));

  Future<void> bookAppointment({
    required String patientId,
    required DateTime scheduledAt,
    String? notes,
  }) async {
    state = const AsyncValue.loading();
    try {
      final result = await repository.bookAppointment(
        patientId: patientId,
        scheduledAt: scheduledAt,
        notes: notes,
      );
      state = AsyncValue.data(result);
    } catch (e, stack) {
      state = AsyncValue.error(e, stack);
    }
  }

  void reset() => state = const AsyncValue.data(null);
}

final appointmentBookingProvider = StateNotifierProvider<
    AppointmentBookingNotifier,
    AsyncValue<ClinicalResult<Appointment>?>>((ref) {
  final repository = ref.watch(clinicalRepositoryProvider);
  return AppointmentBookingNotifier(repository);
});

// ─── Dispensing ────────────────────────────────────────────────────────────

class DispensingNotifier
    extends StateNotifier<AsyncValue<ClinicalResult<DispensingRecord>?>> {
  final ClinicalRepository repository;

  DispensingNotifier(this.repository) : super(const AsyncValue.data(null));

  Future<void> recordDispensing({
    required String patientRecordId,
    required List<MedicationItem> medications,
    String? notes,
  }) async {
    state = const AsyncValue.loading();
    try {
      final result = await repository.recordDispensing(
        patientRecordId: patientRecordId,
        medications: medications,
        notes: notes,
      );
      state = AsyncValue.data(result);
    } catch (e, stack) {
      state = AsyncValue.error(e, stack);
    }
  }

  void reset() => state = const AsyncValue.data(null);
}

final dispensingProvider = StateNotifierProvider<DispensingNotifier,
    AsyncValue<ClinicalResult<DispensingRecord>?>>((ref) {
  final repository = ref.watch(clinicalRepositoryProvider);
  return DispensingNotifier(repository);
});

// ─── Lab Results ───────────────────────────────────────────────────────────

class LabResultsNotifier
    extends StateNotifier<AsyncValue<ClinicalResult<LabResult>?>> {
  final ClinicalRepository repository;

  LabResultsNotifier(this.repository) : super(const AsyncValue.data(null));

  Future<void> recordLabResults({
    required String patientRecordId,
    required String testName,
    required String result,
    String? unit,
    String? referenceRange,
    String? notes,
    String? resultDocumentKey,
    String? resultDocumentFilename,
  }) async {
    state = const AsyncValue.loading();
    try {
      final labResult = await repository.recordLabResults(
        patientRecordId: patientRecordId,
        testName: testName,
        result: result,
        unit: unit,
        referenceRange: referenceRange,
        notes: notes,
        resultDocumentKey: resultDocumentKey,
        resultDocumentFilename: resultDocumentFilename,
      );
      state = AsyncValue.data(labResult);
    } catch (e, stack) {
      state = AsyncValue.error(e, stack);
    }
  }

  void reset() => state = const AsyncValue.data(null);
}

final labResultsProvider = StateNotifierProvider<LabResultsNotifier,
    AsyncValue<ClinicalResult<LabResult>?>>((ref) {
  final repository = ref.watch(clinicalRepositoryProvider);
  return LabResultsNotifier(repository);
});

// ─── Lab Results Batch ─────────────────────────────────────────────────────

class LabResultsBatchNotifier extends StateNotifier<AsyncValue<String?>> {
  final ClinicalRepository repository;

  LabResultsBatchNotifier(this.repository) : super(const AsyncValue.data(null));

  Future<void> submitBatch({
    required String patientRecordId,
    required List<LabTestResultInput> results,
  }) async {
    state = const AsyncValue.loading();
    try {
      final warning = await repository.recordLabResultsBatch(
        patientRecordId: patientRecordId,
        results: results,
      );
      state = AsyncValue.data(warning);
    } catch (e, stack) {
      state = AsyncValue.error(e, stack);
    }
  }

  void reset() => state = const AsyncValue.data(null);
}

final labResultsBatchProvider =
    StateNotifierProvider<LabResultsBatchNotifier, AsyncValue<String?>>((ref) {
  final repository = ref.watch(clinicalRepositoryProvider);
  return LabResultsBatchNotifier(repository);
});
