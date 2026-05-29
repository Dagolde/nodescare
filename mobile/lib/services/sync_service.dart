import 'dart:async';
import 'dart:convert';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:dio/dio.dart';
import '../data/local/sync_queue_database.dart';
import '../data/data_sources/remote_data_source.dart';

/// Monitors network connectivity and processes the offline sync queue
/// when connectivity is restored.
class SyncService {
  final SyncQueueDatabase _db;
  final RemoteDataSource _remote;
  final Connectivity _connectivity;

  final _onlineController = StreamController<bool>.broadcast();
  StreamSubscription<ConnectivityResult>? _subscription;

  bool _isOnline = false;

  SyncService({
    required SyncQueueDatabase db,
    required RemoteDataSource remote,
    Connectivity? connectivity,
  })  : _db = db,
        _remote = remote,
        _connectivity = connectivity ?? Connectivity();

  /// Stream that emits the current online/offline state.
  Stream<bool> get isOnline => _onlineController.stream;

  /// Current synchronous online state.
  bool get currentIsOnline => _isOnline;

  /// Start monitoring connectivity.
  void start() {
    _subscription = _connectivity.onConnectivityChanged.listen((result) async {
      final online = result != ConnectivityResult.none;
      if (online != _isOnline) {
        _isOnline = online;
        _onlineController.add(online);
        if (online) {
          await processSyncQueue();
        }
      }
    });

    // Emit initial state
    _connectivity.checkConnectivity().then((result) {
      _isOnline = result != ConnectivityResult.none;
      _onlineController.add(_isOnline);
    });
  }

  /// Stop monitoring connectivity.
  void stop() {
    _subscription?.cancel();
    _subscription = null;
  }

  /// Process all pending items in the sync queue in FIFO order.
  /// Retries up to 3 times; marks as 'conflict' on 409 response.
  Future<void> processSyncQueue() async {
    final pending = await _db.getPending();
    for (final row in pending) {
      final id = row['id'] as int;
      final operationType = row['operation_type'] as String;
      final payload = jsonDecode(row['payload'] as String) as Map<String, dynamic>;

      try {
        final endpoint = _endpointFor(operationType);
        final response = await _remote.post(endpoint, data: payload);
        if (response.data['success'] == true) {
          await _db.markSynced(id);
        } else {
          await _db.incrementRetry(id);
        }
      } on DioException catch (e) {
        if (e.response?.statusCode == 409) {
          // Conflict — mark directly without further retries
          await _db.incrementRetry(id);
          await _db.incrementRetry(id);
          await _db.incrementRetry(id); // 3 retries → conflict
        } else {
          await _db.incrementRetry(id);
        }
      } catch (_) {
        await _db.incrementRetry(id);
      }
    }
  }

  /// Returns the number of items currently pending in the sync queue.
  Future<int> pendingCount() async {
    final rows = await _db.getPending();
    return rows.length;
  }

  String _endpointFor(String operationType) {
    switch (operationType) {
      case 'patient_registration':
        return '/api/v1/patients';
      default:
        return '/api/v1/sync/$operationType';
    }
  }

  void dispose() {
    stop();
    _onlineController.close();
  }
}
