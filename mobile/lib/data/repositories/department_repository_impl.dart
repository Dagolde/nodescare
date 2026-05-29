import 'package:dio/dio.dart';
import '../../domain/entities/patient_record.dart';
import '../../domain/repositories/department_repository.dart';
import '../data_sources/remote_data_source.dart';

class DepartmentRepositoryImpl implements DepartmentRepository {
  final RemoteDataSource remoteDataSource;

  DepartmentRepositoryImpl({required this.remoteDataSource});

  @override
  Future<List<PatientRecord>> getDepartmentQueue() async {
    try {
      final response = await remoteDataSource.get('/api/v1/department/queue');
      if (response.data['success'] == true) {
        final List<dynamic> data = response.data['data'];
        return data.map((json) => PatientRecord.fromJson(json)).toList();
      } else {
        throw Exception('Failed to fetch department queue');
      }
    } catch (e) {
      throw Exception('Error fetching department queue: $e');
    }
  }

  @override
  Future<PatientRecord> getPatientRecord(String patientRecordId) async {
    try {
      final response = await remoteDataSource.get(
        '/api/v1/patients/$patientRecordId',
      );
      if (response.data['success'] == true) {
        return PatientRecord.fromJson(response.data['data']);
      } else {
        throw Exception('Failed to fetch patient record');
      }
    } catch (e) {
      throw Exception('Error fetching patient record: $e');
    }
  }

  @override
  Future<List<JourneyEvent>> getJourneyHistory(String patientRecordId) async {
    try {
      final response = await remoteDataSource.get(
        '/api/v1/patient-records/$patientRecordId/journey',
      );
      if (response.data['success'] == true) {
        final List<dynamic> data = response.data['data'];
        return data.map((json) => JourneyEvent.fromJson(json)).toList();
      } else {
        throw Exception('Failed to fetch journey history');
      }
    } catch (e) {
      throw Exception('Error fetching journey history: $e');
    }
  }

  @override
  Future<void> addNote(
    String patientRecordId,
    String note, {
    String? complaintHistory,
    String? examinationFindings,
  }) async {
    try {
      final body = <String, dynamic>{'notes': note};
      if (complaintHistory != null && complaintHistory.isNotEmpty) {
        body['complaint_history'] = complaintHistory;
      }
      if (examinationFindings != null && examinationFindings.isNotEmpty) {
        body['examination_findings'] = examinationFindings;
      }
      final response = await remoteDataSource.post(
        '/api/v1/patient-records/$patientRecordId/notes',
        data: body,
      );
      if (response.data['success'] != true) {
        throw Exception(
          response.data['error']?['message'] ?? 'Failed to add note',
        );
      }
    } catch (e) {
      if (e is DioException && e.response != null) {
        final msg = e.response?.data['error']?['message'] ?? 'Failed to add note';
        throw Exception(msg);
      }
      throw Exception('Error adding note: $e');
    }
  }

  @override
  Future<void> completeStep(
    String patientRecordId, {
    String? complaintHistory,
    String? examinationFindings,
  }) async {
    try {
      final body = <String, dynamic>{};
      if (complaintHistory != null && complaintHistory.isNotEmpty) {
        body['complaint_history'] = complaintHistory;
      }
      if (examinationFindings != null && examinationFindings.isNotEmpty) {
        body['examination_findings'] = examinationFindings;
      }
      final response = await remoteDataSource.post(
        '/api/v1/patient-records/$patientRecordId/complete',
        data: body.isNotEmpty ? body : null,
      );
      if (response.data['success'] != true) {
        throw Exception(
          response.data['error']?['message'] ?? 'Failed to complete step',
        );
      }
    } catch (e) {
      if (e is DioException && e.response != null) {
        final msg =
            e.response?.data['error']?['message'] ?? 'Failed to complete step';
        throw Exception(msg);
      }
      throw Exception('Error completing step: $e');
    }
  }

  @override
  Future<void> routeToPharmacy(
    String patientRecordId,
    String prescriptionNotes, {
    String? complaintHistory,
    String? examinationFindings,
  }) async {
    try {
      final body = <String, dynamic>{
        'destination': 'pharmacy',
        'notes': prescriptionNotes,
      };
      if (complaintHistory != null && complaintHistory.isNotEmpty) {
        body['complaint_history'] = complaintHistory;
      }
      if (examinationFindings != null && examinationFindings.isNotEmpty) {
        body['examination_findings'] = examinationFindings;
      }
      final response = await remoteDataSource.post(
        '/api/v1/patient-records/$patientRecordId/route',
        data: body,
      );
      if (response.data['success'] != true) {
        throw Exception(
          response.data['error']?['message'] ?? 'Failed to route to pharmacy',
        );
      }
    } catch (e) {
      if (e is DioException && e.response != null) {
        final msg =
            e.response?.data['error']?['message'] ?? 'Failed to route to pharmacy';
        throw Exception(msg);
      }
      throw Exception('Error routing to pharmacy: $e');
    }
  }

  @override
  Future<void> routeToLab(
    String patientRecordId,
    String investigationNotes, {
    String? complaintHistory,
    String? examinationFindings,
  }) async {
    try {
      final body = <String, dynamic>{
        'destination': 'laboratory',
        'notes': investigationNotes,
      };
      if (complaintHistory != null && complaintHistory.isNotEmpty) {
        body['complaint_history'] = complaintHistory;
      }
      if (examinationFindings != null && examinationFindings.isNotEmpty) {
        body['examination_findings'] = examinationFindings;
      }
      final response = await remoteDataSource.post(
        '/api/v1/patient-records/$patientRecordId/route',
        data: body,
      );
      if (response.data['success'] != true) {
        throw Exception(
          response.data['error']?['message'] ?? 'Failed to route to lab',
        );
      }
    } catch (e) {
      if (e is DioException && e.response != null) {
        final msg =
            e.response?.data['error']?['message'] ?? 'Failed to route to lab';
        throw Exception(msg);
      }
      throw Exception('Error routing to lab: $e');
    }
  }

  @override
  Future<void> admitPatient(String patientRecordId) async {
    try {
      final response = await remoteDataSource.post(
        '/api/v1/patient-records/$patientRecordId/admit',
      );
      if (response.data['success'] != true) {
        throw Exception(
          response.data['error']?['message'] ?? 'Failed to admit patient',
        );
      }
    } catch (e) {
      if (e is DioException && e.response != null) {
        final msg =
            e.response?.data['error']?['message'] ?? 'Failed to admit patient';
        throw Exception(msg);
      }
      throw Exception('Error admitting patient: $e');
    }
  }

  @override
  Future<List<PatientVitals>> getVitals(String patientRecordId) async {
    try {
      final response = await remoteDataSource.get(
        '/api/v1/patient-records/$patientRecordId/vitals',
      );
      if (response.data['success'] == true) {
        final List<dynamic> data = response.data['data'];
        return data.map((json) => PatientVitals.fromJson(json)).toList();
      }
      throw Exception(
        response.data['error']?['message'] ?? 'Failed to fetch vitals',
      );
    } on DioException catch (e) {
      final msg = e.response?.data?['error']?['message'] ?? 'Failed to fetch vitals';
      throw Exception(msg);
    } catch (e) {
      throw Exception('Error fetching vitals: $e');
    }
  }

  @override
  Future<List<LabResult>> getLabResults(String patientRecordId) async {
    try {
      final response = await remoteDataSource.get(
        '/api/v1/patient-records/$patientRecordId/lab-results',
      );
      if (response.data['success'] == true) {
        final List<dynamic> data = response.data['data'];
        return data.map((json) => LabResult.fromJson(json)).toList();
      }
      throw Exception(
        response.data['error']?['message'] ?? 'Failed to fetch lab results',
      );
    } on DioException catch (e) {
      final msg = e.response?.data?['error']?['message'] ?? 'Failed to fetch lab results';
      throw Exception(msg);
    } catch (e) {
      throw Exception('Error fetching lab results: $e');
    }
  }

  @override
  Future<List<TodayRegistration>> getRegisteredToday() async {
    try {
      final response = await remoteDataSource.get(
        '/api/v1/patients/registered-today',
      );
      if (response.data['success'] == true) {
        final List<dynamic> data = response.data['data'];
        return data.map((json) => TodayRegistration.fromJson(json)).toList();
      }
      throw Exception(
        response.data['error']?['message'] ?? 'Server returned failure',
      );
    } on DioException catch (e) {
      final status = e.response?.statusCode;
      final msg = e.response?.data?['error']?['message']
          ?? e.response?.data?['message']
          ?? e.message
          ?? 'Network error';
      throw Exception('[$status] $msg');
    } catch (e) {
      rethrow;
    }
  }

  @override
  Future<InvestigationNotes?> getInvestigation(String patientRecordId) async {
    try {
      final response = await remoteDataSource.get(
        '/api/v1/patient-records/$patientRecordId/investigation',
      );
      
      // Log the full response for debugging
      print('Investigation API Response: ${response.data}');
      
      if (response.data['success'] == true) {
        final data = response.data['data'];
        if (data == null) {
          print('Investigation notes: No data found (null)');
          return null;
        }
        print('Investigation notes found: ${data['investigation_notes']}');
        return InvestigationNotes.fromJson(data);
      }
      
      // API returned success: false
      final errorMsg = response.data['error']?['message'] ?? 'API returned failure';
      print('Investigation API error: $errorMsg');
      throw Exception(errorMsg);
      
    } on DioException catch (e) {
      final status = e.response?.statusCode;
      final errorData = e.response?.data;
      final msg = e.response?.data?['error']?['message'] 
          ?? e.response?.data?['message']
          ?? e.message
          ?? 'Network error';
      
      print('Investigation DioException: Status=$status, Error=$errorData');
      throw Exception('[$status] $msg');
      
    } catch (e) {
      print('Investigation unexpected error: $e');
      throw Exception('Error fetching investigation notes: $e');
    }
  }
}
