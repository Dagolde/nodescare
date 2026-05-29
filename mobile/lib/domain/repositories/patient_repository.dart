import 'dart:io';
import '../entities/patient_record.dart';

/// Domain-layer contract for patient registration operations.
abstract class PatientRepository {
  /// Register a new patient. When offline the implementation queues the
  /// registration locally and returns a provisional [PatientRecord] with
  /// status 'pending'.
  Future<PatientRecord> registerPatient(PatientRegistrationData data);

  /// Request a pre-signed upload URL from the server.
  Future<String> getPresignedUploadUrl({
    required String fileName,
    required String contentType,
    int? fileSize,
  });

  /// Upload a file directly to R2 using a pre-signed URL.
  Future<void> uploadFileToPresignedUrl({
    required String presignedUrl,
    required File file,
    required String contentType,
  });

  /// Process any pending items in the local sync queue.
  Future<void> syncPendingRegistrations();

  /// Get a 15-minute signed URL for the patient's photograph.
  /// Returns null if the patient has no photo on file.
  Future<String?> getPatientPhotoUrl(String patientId);
}

/// Value object carrying all fields collected on the registration form.
class PatientRegistrationData {
  final String fullName;
  final DateTime dateOfBirth;
  final String gender; // 'male' | 'female' | 'other'
  final String phone;
  final String? address;
  final String? nextOfKinName;
  final String? nextOfKinPhone;
  final String chiefComplaint;
  final String? hmoProviderName;
  final String? hmoPolicyNumber;
  final File? photo;
  final List<File> documents;
  final String? visitType;
  final String? assignedDoctorId;

  const PatientRegistrationData({
    required this.fullName,
    required this.dateOfBirth,
    required this.gender,
    required this.phone,
    this.address,
    this.nextOfKinName,
    this.nextOfKinPhone,
    required this.chiefComplaint,
    this.hmoProviderName,
    this.hmoPolicyNumber,
    this.photo,
    this.documents = const [],
    this.visitType,
    this.assignedDoctorId,
  });

  Map<String, dynamic> toJson() => {
        'full_name': fullName,
        'date_of_birth':
            '${dateOfBirth.year}-${dateOfBirth.month.toString().padLeft(2, '0')}-${dateOfBirth.day.toString().padLeft(2, '0')}',
        'gender': gender,
        'phone': phone,
        if (address != null) 'address': address,
        if (nextOfKinName != null) 'next_of_kin_name': nextOfKinName,
        if (nextOfKinPhone != null) 'next_of_kin_phone': nextOfKinPhone,
        'chief_complaint': chiefComplaint,
        if (hmoProviderName != null) 'hmo_provider_name': hmoProviderName,
        if (hmoPolicyNumber != null) 'hmo_policy_number': hmoPolicyNumber,
        if (visitType != null) 'visit_type': visitType,
        if (assignedDoctorId != null) 'assigned_doctor_id': assignedDoctorId,
      };
}
