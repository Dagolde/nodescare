import 'package:dio/dio.dart';
import '../../domain/repositories/clinical_repository.dart';
import '../data_sources/remote_data_source.dart';

class ClinicalRepositoryImpl implements ClinicalRepository {
  final RemoteDataSource remoteDataSource;

  ClinicalRepositoryImpl({required this.remoteDataSource});

  @override
  Future<ClinicalResult<Appointment>> bookAppointment({
    required String patientId,
    required DateTime scheduledAt,
    String? notes,
  }) async {
    try {
      final response = await remoteDataSource.post(
        '/api/v1/appointments',
        data: {
          'patient_id': patientId,
          'scheduled_at': scheduledAt.toIso8601String(),
          if (notes != null && notes.isNotEmpty) 'notes': notes,
        },
      );

      if (response.data['success'] == true) {
        return ClinicalResult(
          data: Appointment.fromJson(response.data['data']),
          warning: response.data['warning'] as String?,
        );
      }
      throw Exception(response.data['error']?['message'] ?? 'Failed to book appointment');
    } on DioException catch (e) {
      final msg = e.response?.data?['error']?['message'] ?? 'Failed to book appointment';
      throw Exception(msg);
    }
  }

  @override
  Future<List<Appointment>> listAppointments() async {
    try {
      final response = await remoteDataSource.get('/api/v1/appointments');
      if (response.data['success'] == true) {
        final List<dynamic> data = response.data['data'];
        return data.map((json) => Appointment.fromJson(json)).toList();
      }
      throw Exception('Failed to fetch appointments');
    } on DioException catch (e) {
      final msg = e.response?.data?['error']?['message'] ?? 'Failed to fetch appointments';
      throw Exception(msg);
    }
  }

  @override
  Future<List<Appointment>> listTodaysAppointments() async {
    try {
      final response = await remoteDataSource.get('/api/v1/appointments/today');
      if (response.data['success'] == true) {
        final List<dynamic> data = response.data['data'];
        return data.map((json) => Appointment.fromJson(json)).toList();
      }
      throw Exception('Failed to fetch today\'s appointments');
    } on DioException catch (e) {
      final msg = e.response?.data?['error']?['message'] ?? 'Failed to fetch appointments';
      throw Exception(msg);
    }
  }

  @override
  Future<List<Appointment>> listAllAppointments() async {
    try {
      final response = await remoteDataSource.get('/api/v1/appointments/all');
      if (response.data['success'] == true) {
        final List<dynamic> data = response.data['data'];
        return data.map((json) => Appointment.fromJson(json)).toList();
      }
      throw Exception('Failed to fetch appointments');
    } on DioException catch (e) {
      final msg = e.response?.data?['error']?['message'] ?? 'Failed to fetch appointments';
      throw Exception(msg);
    }
  }

  @override
  Future<Appointment> updateAppointment(
    String id, {
    DateTime? scheduledAt,
    String? status,
    String? notes,
  }) async {
    try {
      final response = await remoteDataSource.patch(
        '/api/v1/appointments/$id',
        data: {
          if (scheduledAt != null) 'scheduled_at': scheduledAt.toIso8601String(),
          if (status != null) 'status': status,
          if (notes != null) 'notes': notes,
        },
      );
      if (response.data['success'] == true) {
        return Appointment.fromJson(response.data['data']);
      }
      throw Exception(response.data['error']?['message'] ?? 'Failed to update appointment');
    } on DioException catch (e) {
      final msg = e.response?.data?['error']?['message'] ?? 'Failed to update appointment';
      throw Exception(msg);
    }
  }

  @override
  Future<ClinicalResult<DispensingRecord>> recordDispensing({
    required String patientRecordId,
    required List<MedicationItem> medications,
    String? notes,
  }) async {
    try {
      final response = await remoteDataSource.post(
        '/api/v1/patient-records/$patientRecordId/dispensing',
        data: {
          'medications': medications.map((m) => m.toJson()).toList(),
          if (notes != null && notes.isNotEmpty) 'notes': notes,
        },
      );

      if (response.data['success'] == true) {
        return ClinicalResult(
          data: DispensingRecord.fromJson(response.data['data']),
          warning: response.data['warning'] as String?,
        );
      }
      throw Exception(response.data['error']?['message'] ?? 'Failed to record dispensing');
    } on DioException catch (e) {
      final msg = e.response?.data?['error']?['message'] ?? 'Failed to record dispensing';
      throw Exception(msg);
    }
  }

  @override
  Future<ClinicalResult<LabResult>> recordLabResults({
    required String patientRecordId,
    required String testName,
    required String result,
    String? unit,
    String? referenceRange,
    String? notes,
    String? resultDocumentKey,
    String? resultDocumentFilename,
  }) async {
    try {
      final response = await remoteDataSource.post(
        '/api/v1/patient-records/$patientRecordId/lab-results',
        data: {
          'test_name': testName,
          'result': result,
          if (unit != null && unit.isNotEmpty) 'unit': unit,
          if (referenceRange != null && referenceRange.isNotEmpty)
            'reference_range': referenceRange,
          if (notes != null && notes.isNotEmpty) 'notes': notes,
          'status': 'completed',
          if (resultDocumentKey != null) 'result_document_key': resultDocumentKey,
          if (resultDocumentFilename != null)
            'result_document_filename': resultDocumentFilename,
        },
      );

      if (response.data['success'] == true) {
        final labResultData = response.data['data']['lab_result'];
        return ClinicalResult(
          data: LabResult.fromJson(labResultData),
          warning: response.data['warning'] as String?,
        );
      }
      throw Exception(response.data['error']?['message'] ?? 'Failed to record lab results');
    } on DioException catch (e) {
      final msg = e.response?.data?['error']?['message'] ?? 'Failed to record lab results';
      throw Exception(msg);
    }
  }

  @override
  Future<String?> recordLabResultsBatch({
    required String patientRecordId,
    required List<LabTestResultInput> results,
  }) async {
    try {
      final response = await remoteDataSource.post(
        '/api/v1/patient-records/$patientRecordId/lab-results',
        data: {
          'results': results.map((r) => r.toJson()).toList(),
        },
      );
      if (response.data['success'] == true) {
        return response.data['warning'] as String?;
      }
      throw Exception(response.data['error']?['message'] ?? 'Failed to record lab results');
    } on DioException catch (e) {
      final msg = e.response?.data?['error']?['message'] ?? 'Failed to record lab results';
      throw Exception(msg);
    }
  }
}
