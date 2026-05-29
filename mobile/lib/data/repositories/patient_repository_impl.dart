import 'dart:convert';
import 'dart:io';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:dio/dio.dart';
import 'package:http_parser/http_parser.dart';
import 'package:path_provider/path_provider.dart' as path_provider;
import '../../domain/entities/patient_record.dart';
import '../../domain/repositories/patient_repository.dart';
import '../data_sources/remote_data_source.dart';
import '../local/sync_queue_database.dart';

class PatientRepositoryImpl implements PatientRepository {
  final RemoteDataSource remoteDataSource;
  final SyncQueueDatabase syncQueueDatabase;
  final Connectivity connectivity;

  PatientRepositoryImpl({
    required this.remoteDataSource,
    required this.syncQueueDatabase,
    Connectivity? connectivity,
  }) : connectivity = connectivity ?? Connectivity();

  // ── Registration ──────────────────────────────────────────────────────────

  @override
  Future<PatientRecord> registerPatient(PatientRegistrationData data) async {
    final isOnline = await _isOnline();

    if (!isOnline) {
      // Queue locally and return a provisional record
      await syncQueueDatabase.enqueue(
        operationType: 'patient_registration',
        payload: data.toJson(),
      );
      return _provisionalRecord(data);
    }

    // Build multipart form data so the photo is uploaded directly to the server
    // (no S3/presigned URL needed — the backend stores it on local disk)
    final formFields = {
      'full_name': data.fullName,
      'date_of_birth':
          '${data.dateOfBirth.year}-${data.dateOfBirth.month.toString().padLeft(2, '0')}-${data.dateOfBirth.day.toString().padLeft(2, '0')}',
      'gender': data.gender,
      'phone': data.phone,
      if (data.address != null) 'address': data.address!,
      if (data.nextOfKinName != null) 'next_of_kin_name': data.nextOfKinName!,
      if (data.nextOfKinPhone != null) 'next_of_kin_phone': data.nextOfKinPhone!,
      'chief_complaint': data.chiefComplaint,
      if (data.hmoProviderName != null) 'hmo_provider_name': data.hmoProviderName!,
      if (data.hmoPolicyNumber != null) 'hmo_policy_number': data.hmoPolicyNumber!,
      if (data.visitType != null) 'visit_type': data.visitType!,
      if (data.assignedDoctorId != null) 'assigned_doctor_id': data.assignedDoctorId!,
    };

    final formData = FormData.fromMap(formFields);

    // Attach photo as multipart file if present
    if (data.photo != null) {
      final fileName = data.photo!.path.split('/').last;
      formData.files.add(MapEntry(
        'photo',
        await MultipartFile.fromFile(
          data.photo!.path,
          filename: fileName,
          contentType: MediaType.parse(_mimeFromFile(data.photo!)),
        ),
      ));
    }

    try {
      final response = await remoteDataSource.post(
        '/api/v1/patients',
        data: formData,
      );
      if (response.data['success'] == true) {
        final record = PatientRecord.fromJson(
            response.data['data']['patient_record'] ?? response.data['data']);

        // Cache the photo locally so it shows immediately on this device
        // without needing a round-trip to the server
        if (data.photo != null && record.patient?.id != null) {
          try {
            final cacheDir = await path_provider.getApplicationCacheDirectory();
            final cacheFile = File(
                '${cacheDir.path}/patient_photos/${record.patient!.id}.jpg');
            await cacheFile.parent.create(recursive: true);
            await cacheFile.writeAsBytes(await data.photo!.readAsBytes());
          } catch (_) {
            // Cache write failed — non-fatal
          }
        }

        return record;
      }
      throw Exception(response.data['error']?['message'] ?? 'Registration failed');
    } on DioException catch (e) {
      final msg = e.response?.data?['error']?['message'] ??
          e.response?.data?['message'] ??
          'Registration failed (${e.response?.statusCode ?? 'no response'})';
      throw Exception(msg);
    }
  }

  // ── Presigned URL ─────────────────────────────────────────────────────────

  @override
  Future<String> getPresignedUploadUrl({
    required String fileName,
    required String contentType,
    int? fileSize,
  }) async {
    final data = await _getPresignedUrlData(
      fileName: fileName,
      contentType: contentType,
      fileSize: fileSize,
    );
    return data['upload_url'] as String;
  }

  /// Returns the full presigned URL response data including `upload_url` and `object_key`.
  Future<Map<String, dynamic>> _getPresignedUrlData({
    required String fileName,
    required String contentType,
    int? fileSize,
  }) async {
    final response = await remoteDataSource.get(
      '/api/v1/upload/presigned-url',
      queryParameters: {
        'filename': fileName,
        'content_type': contentType,
        'file_size': fileSize ?? 1,
      },
    );
    if (response.data['success'] == true) {
      return response.data['data'] as Map<String, dynamic>;
    }
    throw Exception('Failed to get presigned URL');
  }

  @override
  Future<void> uploadFileToPresignedUrl({
    required String presignedUrl,
    required File file,
    required String contentType,
  }) async {
    final dio = Dio();
    final bytes = await file.readAsBytes();
    await dio.put(
      presignedUrl,
      data: Stream.fromIterable([bytes]),
      options: Options(
        headers: {
          'Content-Type': contentType,
          'Content-Length': bytes.length,
        },
      ),
    );
  }

  // ── Photo URL ─────────────────────────────────────────────────────────────

  @override
  Future<String?> getPatientPhotoUrl(String patientId) async {
    // Use the permanent photo endpoint — no expiring signed URLs.
    // The backend serves the file directly from local storage.
    // We still call the /photo/url endpoint to confirm a photo exists
    // (returns 404 if none), then return the permanent /photo URL.
    try {
      final response = await remoteDataSource.get(
        '/api/v1/patients/$patientId/photo/url',
      );
      if (response.data['success'] == true) {
        final url = response.data['data']['url'] as String?;
        // If backend returned a permanent URL (no expires_at), use it directly.
        // Otherwise fall back to the permanent endpoint URL.
        if (url != null && response.data['data']['expires_at'] == null) {
          return url;
        }
        // Legacy S3 signed URL — still usable but will expire
        return url;
      }
      return null;
    } on DioException catch (e) {
      if (e.response?.statusCode == 404) return null;
      rethrow;
    }
  }

  // ── Sync ───────────────────────────────────────────────────────────────────

  @override
  Future<void> syncPendingRegistrations() async {
    if (!await _isOnline()) return;

    final pending = await syncQueueDatabase.getPending();
    for (final row in pending) {
      final id = row['id'] as int;
      try {
        final payload = jsonDecode(row['payload'] as String) as Map<String, dynamic>;
        final response = await remoteDataSource.post('/api/v1/patients', data: payload);
        if (response.data['success'] == true) {
          await syncQueueDatabase.markSynced(id);
        } else {
          await syncQueueDatabase.incrementRetry(id);
        }
      } catch (_) {
        await syncQueueDatabase.incrementRetry(id);
      }
    }
  }

  // ── Helpers ───────────────────────────────────────────────────────────────

  Future<bool> _isOnline() async {
    final result = await connectivity.checkConnectivity();
    return result != ConnectivityResult.none;
  }

  String _mimeFromFile(File file) {
    final ext = file.path.split('.').last.toLowerCase();
    switch (ext) {
      case 'jpg':
      case 'jpeg':
        return 'image/jpeg';
      case 'png':
        return 'image/png';
      case 'pdf':
        return 'application/pdf';
      default:
        return 'application/octet-stream';
    }
  }

  PatientRecord _provisionalRecord(PatientRegistrationData data) {
    return PatientRecord(
      id: 'local_${DateTime.now().millisecondsSinceEpoch}',
      patientId: '',
      status: 'pending',
      registeredBy: '',
      createdAt: DateTime.now(),
      patient: Patient(
        id: '',
        patientNumber: 'PENDING',
        fullName: data.fullName,
        dateOfBirth:
            '${data.dateOfBirth.year}-${data.dateOfBirth.month.toString().padLeft(2, '0')}-${data.dateOfBirth.day.toString().padLeft(2, '0')}',
        gender: data.gender,
        phone: data.phone,
        address: data.address,
        nextOfKinName: data.nextOfKinName,
        nextOfKinPhone: data.nextOfKinPhone,
        chiefComplaint: data.chiefComplaint,
        hmoProviderName: data.hmoProviderName,
        hmoPolicyNumber: data.hmoPolicyNumber,
        createdAt: DateTime.now(),
      ),
    );
  }
}
