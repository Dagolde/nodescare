import 'dart:io';
import 'dart:typed_data';

import 'package:crypto/crypto.dart';
import 'package:dio/dio.dart';
import 'package:dio/io.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/data_sources/remote_data_source.dart';
import '../../data/local/auth_storage.dart';
import '../../data/repositories/accounts_repository_impl.dart';
import '../../domain/entities/invoice.dart';
import '../../domain/entities/patient_record.dart';
import '../../domain/repositories/accounts_repository.dart';

/// Computes the SHA-256 fingerprint of DER-encoded bytes.
/// Returns a lowercase hex string with no colons.
String _computeSha256Fingerprint(Uint8List derBytes) {
  final digest = sha256.convert(derBytes);
  return digest.bytes.map((b) => b.toRadixString(16).padLeft(2, '0')).join();
}

// Dio provider
final dioProvider = Provider<Dio>((ref) {
  const baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://nodescare.nodestech.cloud',
  );

  const pinnedSha256_1 = String.fromEnvironment('PINNED_CERT_SHA256_1', defaultValue: '');
  const pinnedSha256_2 = String.fromEnvironment('PINNED_CERT_SHA256_2', defaultValue: '');
  const pinningEnabled = String.fromEnvironment('CERTIFICATE_PINNING_ENABLED', defaultValue: 'true') != 'false';

  final dio = Dio(BaseOptions(
    baseUrl: baseUrl,
    connectTimeout: const Duration(seconds: 30),
    receiveTimeout: const Duration(seconds: 30),
    headers: {
      'Accept': 'application/json',
      // Do NOT set Content-Type here — Dio sets it automatically:
      // 'application/json' for Map/String data, 'multipart/form-data' for FormData.
      // A hardcoded value here would break multipart file uploads.
    },
  ));

  // Auto-refresh interceptor: on 401, try to refresh token once
  dio.interceptors.add(
    InterceptorsWrapper(
      onError: (DioException error, ErrorInterceptorHandler handler) async {
        if (error.response?.statusCode == 401) {
          // Try to refresh the token
          try {
            final storage = AuthStorage();
            final saved = await storage.loadUser();
            if (saved != null && saved.refreshToken.isNotEmpty) {
              final refreshDio = Dio(BaseOptions(
                baseUrl: baseUrl,
                headers: {
                  'Accept': 'application/json',
                  'X-Tenant-ID': saved.tenantId,
                },
              ));

              final refreshResponse = await refreshDio.post(
                '/api/v1/auth/refresh',
                data: {'refresh_token': saved.refreshToken},
              );

              if (refreshResponse.statusCode == 200) {
                final data = refreshResponse.data['data'] as Map<String, dynamic>;
                final newToken = data['access_token'] as String;
                final newRefresh = (data['refresh_token'] as String?) ?? saved.refreshToken;
                final expiresIn = (data['expires_in'] as num?)?.toInt() ?? 43200;

                final updatedUser = saved.copyWith(
                  accessToken: newToken,
                  refreshToken: newRefresh,
                );
                await storage.saveUser(
                  updatedUser,
                  expiresAt: DateTime.now().add(Duration(seconds: expiresIn)),
                );

                // Update the dio headers
                dio.options.headers['Authorization'] = 'Bearer $newToken';

                // Retry the original request with new token
                final opts = error.requestOptions;
                opts.headers['Authorization'] = 'Bearer $newToken';
                final retryResponse = await dio.fetch(opts);
                return handler.resolve(retryResponse);
              }
            }
          } catch (_) {
            // Refresh failed — clear storage so app redirects to login
            final storage = AuthStorage();
            await storage.clear();
            dio.options.headers.remove('Authorization');
          }
        }
        return handler.next(error);
      },
    ),
  );

  // Certificate pinning via custom HttpClient
  (dio.httpClientAdapter as IOHttpClientAdapter).createHttpClient = () {
    final client = HttpClient();
    client.badCertificateCallback = (cert, host, port) {
      // If pinning is disabled OR no pins are configured, accept all certificates
      if (!pinningEnabled || (pinnedSha256_1.isEmpty && pinnedSha256_2.isEmpty)) {
        return true;
      }
      final fingerprint = _computeSha256Fingerprint(cert.der);
      return fingerprint == pinnedSha256_1 || fingerprint == pinnedSha256_2;
    };
    return client;
  };

  return dio;
});

// Remote data source provider
final remoteDataSourceProvider = Provider<RemoteDataSource>((ref) {
  final dio = ref.watch(dioProvider);
  return RemoteDataSource(dio: dio);
});

// Accounts repository provider
final accountsRepositoryProvider = Provider<AccountsRepository>((ref) {
  final remoteDataSource = ref.watch(remoteDataSourceProvider);
  return AccountsRepositoryImpl(remoteDataSource: remoteDataSource);
});

// Accounts queue provider
final accountsQueueProvider = FutureProvider<List<PatientRecord>>((ref) async {
  final repository = ref.watch(accountsRepositoryProvider);
  return await repository.getAccountsQueue();
});

// Invoice list provider for a specific patient record
final invoiceListProvider = FutureProvider.family<List<Invoice>, String>((ref, patientRecordId) async {
  final repository = ref.watch(accountsRepositoryProvider);
  return await repository.getInvoices(patientRecordId);
});

// State notifier for invoice creation
class InvoiceCreationNotifier extends StateNotifier<AsyncValue<Invoice?>> {
  final AccountsRepository repository;

  InvoiceCreationNotifier(this.repository) : super(const AsyncValue.data(null));

  Future<void> createInvoice(
    String patientRecordId,
    List<InvoiceItem> items,
  ) async {
    state = const AsyncValue.loading();
    try {
      final invoice = await repository.createInvoice(patientRecordId, items);
      state = AsyncValue.data(invoice);
    } catch (e, stack) {
      state = AsyncValue.error(e, stack);
    }
  }

  void reset() {
    state = const AsyncValue.data(null);
  }
}

final invoiceCreationProvider = StateNotifierProvider<InvoiceCreationNotifier, AsyncValue<Invoice?>>((ref) {
  final repository = ref.watch(accountsRepositoryProvider);
  return InvoiceCreationNotifier(repository);
});

// State notifier for payment recording
class PaymentRecordingNotifier extends StateNotifier<AsyncValue<Invoice?>> {
  final AccountsRepository repository;

  PaymentRecordingNotifier(this.repository) : super(const AsyncValue.data(null));

  Future<void> recordPayment(String invoiceId, Map<String, dynamic> paymentData) async {
    state = const AsyncValue.loading();
    try {
      final invoice = await repository.recordPayment(invoiceId, paymentData);
      state = AsyncValue.data(invoice);
    } catch (e, stack) {
      state = AsyncValue.error(e, stack);
    }
  }

  void reset() {
    state = const AsyncValue.data(null);
  }
}

final paymentRecordingProvider = StateNotifierProvider<PaymentRecordingNotifier, AsyncValue<Invoice?>>((ref) {
  final repository = ref.watch(accountsRepositoryProvider);
  return PaymentRecordingNotifier(repository);
});

// State notifier for invoice settlement
class InvoiceSettlementNotifier extends StateNotifier<AsyncValue<Invoice?>> {
  final AccountsRepository repository;

  InvoiceSettlementNotifier(this.repository) : super(const AsyncValue.data(null));

  Future<void> settleInvoice(String invoiceId) async {
    state = const AsyncValue.loading();
    try {
      final invoice = await repository.settleInvoice(invoiceId);
      state = AsyncValue.data(invoice);
    } catch (e, stack) {
      state = AsyncValue.error(e, stack);
    }
  }

  void reset() {
    state = const AsyncValue.data(null);
  }
}

final invoiceSettlementProvider = StateNotifierProvider<InvoiceSettlementNotifier, AsyncValue<Invoice?>>((ref) {
  final repository = ref.watch(accountsRepositoryProvider);
  return InvoiceSettlementNotifier(repository);
});
