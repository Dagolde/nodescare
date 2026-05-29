import '../entities/patient_record.dart';

class Appointment {
  final String id;
  final String patientId;
  final String doctorId;
  final String? departmentId;
  final DateTime scheduledAt;
  final String status;
  final String? notes;
  final DateTime createdAt;
  final Patient? patient;
  final String? doctorName;
  final String? departmentName;

  Appointment({
    required this.id,
    required this.patientId,
    required this.doctorId,
    this.departmentId,
    required this.scheduledAt,
    required this.status,
    this.notes,
    required this.createdAt,
    this.patient,
    this.doctorName,
    this.departmentName,
  });

  factory Appointment.fromJson(Map<String, dynamic> json) {
    return Appointment(
      id: json['id'] as String,
      patientId: json['patient_id'] as String,
      doctorId: json['doctor_id'] as String,
      departmentId: json['department_id'] as String?,
      scheduledAt: DateTime.parse(json['scheduled_at'] as String),
      status: json['status'] as String,
      notes: json['notes'] as String?,
      createdAt: DateTime.parse(json['created_at'] as String),
      patient: json['patient'] != null ? Patient.fromJson(json['patient']) : null,
      doctorName: json['doctor_name'] as String? ?? (json['doctor'] != null ? json['doctor']['name'] as String? : null),
      departmentName: json['department_name'] as String? ?? (json['department'] != null ? json['department']['name'] as String? : null),
    );
  }
}

/// A medication entry for dispensing
class MedicationItem {
  final String name;
  final String dosage;
  final int quantity;
  final String? instructions;

  const MedicationItem({
    required this.name,
    required this.dosage,
    required this.quantity,
    this.instructions,
  });

  Map<String, dynamic> toJson() => {
        'name': name,
        'dosage': dosage,
        'quantity': quantity,
        if (instructions != null) 'instructions': instructions,
      };
}

class DispensingRecord {
  final String id;
  final String patientRecordId;
  final String pharmacistId;
  final List<Map<String, dynamic>> medications;
  final String? notes;
  final DateTime createdAt;

  DispensingRecord({
    required this.id,
    required this.patientRecordId,
    required this.pharmacistId,
    required this.medications,
    this.notes,
    required this.createdAt,
  });

  factory DispensingRecord.fromJson(Map<String, dynamic> json) {
    return DispensingRecord(
      id: json['id'] as String,
      patientRecordId: json['patient_record_id'] as String,
      pharmacistId: json['pharmacist_id'] as String? ?? '',
      medications: json['medications'] != null
          ? List<Map<String, dynamic>>.from(json['medications'])
          : [],
      notes: json['notes'] as String?,
      createdAt: DateTime.parse(json['created_at'] as String),
    );
  }
}

class LabResult {
  final String id;
  final String patientRecordId;
  final String technicianId;
  final String testName;
  final String result;
  final String? unit;
  final String? referenceRange;
  final String status;
  final String? notes;
  final DateTime createdAt;

  LabResult({
    required this.id,
    required this.patientRecordId,
    required this.technicianId,
    required this.testName,
    required this.result,
    this.unit,
    this.referenceRange,
    required this.status,
    this.notes,
    required this.createdAt,
  });

  factory LabResult.fromJson(Map<String, dynamic> json) {
    return LabResult(
      id: json['id'] as String? ?? '',
      patientRecordId: json['patient_record_id'] as String? ?? '',
      technicianId: json['technician_id'] as String? ?? '',
      testName: json['test_name'] as String? ?? '',
      result: json['result'] as String? ?? '',
      unit: json['unit'] as String?,
      referenceRange: json['reference_range'] as String?,
      status: json['status'] as String? ?? 'completed',
      notes: json['notes'] as String?,
      createdAt: json['created_at'] != null 
          ? DateTime.parse(json['created_at'] as String)
          : DateTime.now(),
    );
  }
}

/// Result wrapper that carries an optional warning from the API
class ClinicalResult<T> {
  final T data;
  final String? warning;

  ClinicalResult({required this.data, this.warning});
}

/// Input model for a single test result in a batch submission
class LabTestResultInput {
  final String testName;
  final String result;
  final String? referenceRange;
  final String? resultDocumentKey;
  final String? resultDocumentFilename;

  LabTestResultInput({
    required this.testName,
    required this.result,
    this.referenceRange,
    this.resultDocumentKey,
    this.resultDocumentFilename,
  });

  Map<String, dynamic> toJson() => {
    'test_name': testName,
    'result': result,
    if (referenceRange != null && referenceRange!.isNotEmpty)
      'reference_range': referenceRange,
    if (resultDocumentKey != null) 'result_document_key': resultDocumentKey,
    if (resultDocumentFilename != null)
      'result_document_filename': resultDocumentFilename,
  };
}

abstract class ClinicalRepository {
  // Appointments
  Future<ClinicalResult<Appointment>> bookAppointment({
    required String patientId,
    required DateTime scheduledAt,
    String? notes,
  });

  Future<List<Appointment>> listAppointments();

  /// All appointments — used by front desk and doctor view.
  Future<List<Appointment>> listAllAppointments();

  /// Today's appointments — all appointments for today (front desk + doctor view)
  Future<List<Appointment>> listTodaysAppointments();

  Future<Appointment> updateAppointment(
    String id, {
    DateTime? scheduledAt,
    String? status,
    String? notes,
  });

  // Pharmacy — medications is a list of MedicationItem
  Future<ClinicalResult<DispensingRecord>> recordDispensing({
    required String patientRecordId,
    required List<MedicationItem> medications,
    String? notes,
  });

  // Laboratory
  Future<ClinicalResult<LabResult>> recordLabResults({
    required String patientRecordId,
    required String testName,
    required String result,
    String? unit,
    String? referenceRange,
    String? notes,
    String? resultDocumentKey,
    String? resultDocumentFilename,
  });

  /// Submit multiple test results in one batch.
  Future<String?> recordLabResultsBatch({
    required String patientRecordId,
    required List<LabTestResultInput> results,
  });
}
