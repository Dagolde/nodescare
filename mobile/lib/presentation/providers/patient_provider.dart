import 'dart:io';
import 'dart:typed_data';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:path_provider/path_provider.dart' as path_provider;
import '../../data/local/sync_queue_database.dart';
import '../../data/repositories/patient_repository_impl.dart';
import '../../domain/entities/patient_record.dart';
import '../../domain/repositories/patient_repository.dart';
import '../../services/sync_service.dart';
import 'accounts_provider.dart';

// ── Infrastructure providers ──────────────────────────────────────────────

final syncQueueDatabaseProvider = Provider<SyncQueueDatabase>((ref) {
  return SyncQueueDatabase();
});

final syncServiceProvider = Provider<SyncService>((ref) {
  final service = SyncService(
    db: ref.watch(syncQueueDatabaseProvider),
    remote: ref.watch(remoteDataSourceProvider),
  );
  service.start();
  ref.onDispose(service.dispose);
  return service;
});

final patientRepositoryProvider = Provider<PatientRepository>((ref) {
  return PatientRepositoryImpl(
    remoteDataSource: ref.watch(remoteDataSourceProvider),
    syncQueueDatabase: ref.watch(syncQueueDatabaseProvider),
    connectivity: Connectivity(),
  );
});

// ── Connectivity ──────────────────────────────────────────────────────────

final connectivityProvider = StreamProvider<ConnectivityResult>((ref) {
  return Connectivity().onConnectivityChanged;
});

final isOnlineProvider = Provider<bool>((ref) {
  // Prefer the SyncService stream for consistency
  final syncService = ref.watch(syncServiceProvider);
  final connectivity = ref.watch(connectivityProvider);
  return connectivity.when(
    data: (result) => result != ConnectivityResult.none,
    loading: () => syncService.currentIsOnline,
    error: (_, __) => true,
  );
});

// ── Registration state ────────────────────────────────────────────────────

class PatientRegistrationNotifier extends StateNotifier<AsyncValue<PatientRecord?>> {
  final PatientRepository repository;

  PatientRegistrationNotifier(this.repository) : super(const AsyncValue.data(null));

  Future<void> register(PatientRegistrationData data) async {
    state = const AsyncValue.loading();
    try {
      final record = await repository.registerPatient(data);
      state = AsyncValue.data(record);
    } catch (e, st) {
      state = AsyncValue.error(e, st);
    }
  }

  void reset() => state = const AsyncValue.data(null);
}

final patientRegistrationProvider =
    StateNotifierProvider<PatientRegistrationNotifier, AsyncValue<PatientRecord?>>((ref) {
  return PatientRegistrationNotifier(ref.watch(patientRepositoryProvider));
});

// ── Sync trigger ──────────────────────────────────────────────────────────

/// Ensures the SyncService is initialised and running.
/// Widgets can watch this provider to trigger sync on connectivity restore.
final syncTriggerProvider = Provider<void>((ref) {
  // SyncService is already started in syncServiceProvider; just watch it.
  ref.watch(syncServiceProvider);
});

// ── Patient photo URL ─────────────────────────────────────────────────────

/// Fetches the patient photo bytes — checks local disk cache first,
/// then falls back to the authenticated API endpoint.
/// Caches the result locally so subsequent loads are instant and work offline.
final patientPhotoBytesProvider =
    FutureProvider.family<Uint8List?, String>((ref, patientId) async {
  if (patientId.isEmpty) return null;

  debugPrint('[PhotoDebug] Loading photo for patient: $patientId');

  // 1. Try local cache first
  try {
    final cacheDir = await path_provider.getApplicationCacheDirectory();
    final cacheFile = File('${cacheDir.path}/patient_photos/$patientId.jpg');
    if (await cacheFile.exists()) {
      debugPrint('[PhotoDebug] Cache HIT for $patientId');
      return await cacheFile.readAsBytes();
    }
    debugPrint('[PhotoDebug] Cache MISS for $patientId, fetching from server');
  } catch (e) {
    debugPrint('[PhotoDebug] Cache read error: $e');
  }

  // 2. Fetch from server via authenticated Dio
  try {
    final remote = ref.watch(remoteDataSourceProvider);
    final bytes = await remote.getBytes('/api/v1/patients/$patientId/photo');
    if (bytes == null || bytes.isEmpty) {
      debugPrint('[PhotoDebug] Server returned null/empty for $patientId');
      return null;
    }
    debugPrint('[PhotoDebug] Server returned ${bytes.length} bytes for $patientId');

    // 3. Save to local cache for future use
    try {
      final cacheDir = await path_provider.getApplicationCacheDirectory();
      final cacheFile = File('${cacheDir.path}/patient_photos/$patientId.jpg');
      await cacheFile.parent.create(recursive: true);
      await cacheFile.writeAsBytes(bytes);
      debugPrint('[PhotoDebug] Cached photo for $patientId');
    } catch (e) {
      debugPrint('[PhotoDebug] Cache write error: $e');
    }

    return bytes;
  } catch (e) {
    debugPrint('[PhotoDebug] Server fetch error for $patientId: $e');
    return null;
  }
});
