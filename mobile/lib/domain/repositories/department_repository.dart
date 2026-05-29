import '../entities/patient_record.dart';

abstract class DepartmentRepository {
  Future<List<PatientRecord>> getDepartmentQueue();
  Future<PatientRecord> getPatientRecord(String patientRecordId);
  Future<List<JourneyEvent>> getJourneyHistory(String patientRecordId);
  Future<List<PatientVitals>> getVitals(String patientRecordId);
  Future<List<LabResult>> getLabResults(String patientRecordId);
  Future<InvestigationNotes?> getInvestigation(String patientRecordId);
  Future<void> addNote(
    String patientRecordId,
    String note, {
    String? complaintHistory,
    String? examinationFindings,
  });
  Future<void> completeStep(
    String patientRecordId, {
    String? complaintHistory,
    String? examinationFindings,
  });
  Future<void> routeToPharmacy(
    String patientRecordId,
    String prescriptionNotes, {
    String? complaintHistory,
    String? examinationFindings,
  });
  Future<void> routeToLab(
    String patientRecordId,
    String investigationNotes, {
    String? complaintHistory,
    String? examinationFindings,
  });
  Future<void> admitPatient(String patientRecordId);
  Future<List<TodayRegistration>> getRegisteredToday();
}

/// Lightweight summary of a patient registered today — used on the front desk dashboard.
class TodayRegistration {
  final String id;
  final String patientNumber;
  final String fullName;
  final String gender;
  final String phone;
  final String chiefComplaint;
  final String? photoObjectKey;
  final DateTime createdAt;
  final String recordStatus;
  final String currentDepartment;
  final String? patientRecordId;
  final String? visitType;
  final String? assignedDoctorId;
  final String? assignedDoctorName;
  final String? assignedDoctorDepartmentId;

  TodayRegistration({
    required this.id,
    required this.patientNumber,
    required this.fullName,
    required this.gender,
    required this.phone,
    required this.chiefComplaint,
    this.photoObjectKey,
    required this.createdAt,
    required this.recordStatus,
    required this.currentDepartment,
    this.patientRecordId,
    this.visitType,
    this.assignedDoctorId,
    this.assignedDoctorName,
    this.assignedDoctorDepartmentId,
  });

  factory TodayRegistration.fromJson(Map<String, dynamic> json) {
    return TodayRegistration(
      id: json['id'] as String,
      patientNumber: json['patient_number'] as String,
      fullName: json['full_name'] as String,
      gender: json['gender'] as String,
      phone: json['phone'] as String,
      chiefComplaint: json['chief_complaint'] as String,
      photoObjectKey: json['photo_object_key'] as String?,
      createdAt: DateTime.parse(json['created_at'] as String),
      recordStatus: json['record_status'] as String? ?? 'unknown',
      currentDepartment: json['current_department'] as String? ?? 'N/A',
      patientRecordId: json['patient_record_id'] as String?,
      visitType: json['visit_type'] as String?,
      assignedDoctorId: json['assigned_doctor_id'] as String?,
      assignedDoctorName: json['assigned_doctor_name'] as String?,
      assignedDoctorDepartmentId: json['assigned_doctor_department_id'] as String?,
    );
  }
}

class JourneyEvent {
  final String id;
  final String patientRecordId;
  final String? fromDepartmentId;
  final String? toDepartmentId;
  final String? fromDepartmentName;
  final String? toDepartmentName;
  final String eventType;
  final String performedBy;
  final String? performedByName;
  final String? notes;
  final Map<String, dynamic>? metadata;
  final DateTime createdAt;

  JourneyEvent({
    required this.id,
    required this.patientRecordId,
    this.fromDepartmentId,
    this.toDepartmentId,
    this.fromDepartmentName,
    this.toDepartmentName,
    required this.eventType,
    required this.performedBy,
    this.performedByName,
    this.notes,
    this.metadata,
    required this.createdAt,
  });

  factory JourneyEvent.fromJson(Map<String, dynamic> json) {
    return JourneyEvent(
      id: json['id'],
      patientRecordId: json['patient_record_id'],
      fromDepartmentId: json['from_department_id'],
      toDepartmentId: json['to_department_id'],
      fromDepartmentName: json['from_department']?['name'],
      toDepartmentName: json['to_department']?['name'],
      eventType: json['event_type'],
      performedBy: json['performed_by'],
      performedByName: json['performed_by_user']?['name'],
      notes: json['notes'],
      metadata: json['metadata'] != null && json['metadata'] is Map
          ? Map<String, dynamic>.from(json['metadata'] as Map)
          : null,
      createdAt: DateTime.parse(json['created_at']),
    );
  }
}


class PatientVitals {
  final String id;
  final String patientRecordId;
  final String? bloodPressure;
  final String? heartRate;
  final String? temperature;
  final String? respiratoryRate;
  final String? oxygenSaturation;
  final String? weight;
  final String? height;
  final String? notes;
  final String recordedBy;
  final String? recordedByName;
  final DateTime createdAt;

  PatientVitals({
    required this.id,
    required this.patientRecordId,
    this.bloodPressure,
    this.heartRate,
    this.temperature,
    this.respiratoryRate,
    this.oxygenSaturation,
    this.weight,
    this.height,
    this.notes,
    required this.recordedBy,
    this.recordedByName,
    required this.createdAt,
  });

  factory PatientVitals.fromJson(Map<String, dynamic> json) {
    // Helper to safely convert any value (int, double, String, null) to String?
    String? toStr(dynamic v) => v != null ? v.toString() : null;

    return PatientVitals(
      id: json['id'] as String? ?? '',
      patientRecordId: json['patient_record_id'] as String? ?? '',
      bloodPressure: toStr(json['blood_pressure']),
      heartRate: toStr(json['heart_rate']),
      temperature: toStr(json['temperature']),
      respiratoryRate: toStr(json['respiratory_rate']),
      oxygenSaturation: toStr(json['oxygen_saturation']),
      weight: toStr(json['weight']),
      height: toStr(json['height']),
      notes: json['notes'] as String?,
      recordedBy: json['recorded_by'] as String? ?? '',
      recordedByName: json['recorded_by_user']?['name'] as String?,
      createdAt: json['created_at'] != null
          ? DateTime.parse(json['created_at'] as String)
          : DateTime.now(),
    );
  }
}

class LabResult {
  final String id;
  final String patientRecordId;
  final String technicianId;
  final String? technicianName;
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
    this.technicianName,
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
      technicianName: json['technician']?['name'] as String?,
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

class InvestigationNotes {
  final String patientRecordId;
  final String? patientName;
  final String? patientNumber;
  final String investigationNotes;
  final String? requestedBy;
  final String? requestedAt;

  InvestigationNotes({
    required this.patientRecordId,
    this.patientName,
    this.patientNumber,
    required this.investigationNotes,
    this.requestedBy,
    this.requestedAt,
  });

  factory InvestigationNotes.fromJson(Map<String, dynamic> json) {
    return InvestigationNotes(
      patientRecordId: json['patient_record_id'] as String? ?? '',
      patientName: json['patient_name'] as String?,
      patientNumber: json['patient_number'] as String?,
      investigationNotes: json['investigation_notes'] as String? ?? '',
      requestedBy: json['requested_by'] as String?,
      requestedAt: json['requested_at'] as String?,
    );
  }
}
