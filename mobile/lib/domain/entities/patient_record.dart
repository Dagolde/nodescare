class PatientRecord {
  final String id;
  final String patientId;
  final String? workflowTemplateId;
  final String status;
  final String? currentDepartmentId;
  final int? currentStepOrder;
  final String registeredBy;
  final DateTime? admittedAt;
  final DateTime? dischargedAt;
  final DateTime createdAt;
  final Patient? patient;
  final Department? currentDepartment;
  final String? invoiceStatus; // null = no invoice, 'issued'/'partially_paid'/'paid'
  final String? paymentContext; // 'lab_referral', 'pharmacy_referral', etc.
  final String? paymentReason; // Human-readable: 'Lab Test Payment', 'Pharmacy/Drugs Payment'
  final String? returnToDepartmentId; // Department to route to after payment
  final String? clinicalNotes; // Prescription notes or investigation notes
  final List<Map<String, dynamic>>? pharmacyBillItems; // Parsed from pharmacy_bill_items
  final List<Map<String, dynamic>>? labBillItems; // Parsed from lab_bill_items
  final String? wardDepartmentId; // Parsed from ward_department_id
  final String? investigationNotes; // Doctor's requested tests
  final String? prescriptionNotes; // Doctor's prescription
  final List<Map<String, dynamic>>? latestLabResults; // Latest lab results for doctor
  final String? complaintHistory; // Patient's complaint & history
  final String? examinationFindings; // Doctor's examination findings
  final String? visitType; // Visit type slug (e.g. 'pediatric')
  final String? assignedDoctorId; // Assigned doctor UUID
  final String? assignedDoctorName; // Assigned doctor name
  final String? assignedDoctorDepartmentId; // Assigned doctor's department UUID

  PatientRecord({
    required this.id,
    required this.patientId,
    this.workflowTemplateId,
    required this.status,
    this.currentDepartmentId,
    this.currentStepOrder,
    required this.registeredBy,
    this.admittedAt,
    this.dischargedAt,
    required this.createdAt,
    this.patient,
    this.currentDepartment,
    this.invoiceStatus,
    this.paymentContext,
    this.paymentReason,
    this.returnToDepartmentId,
    this.clinicalNotes,
    this.pharmacyBillItems,
    this.labBillItems,
    this.wardDepartmentId,
    this.investigationNotes,
    this.prescriptionNotes,
    this.latestLabResults,
    this.complaintHistory,
    this.examinationFindings,
    this.visitType,
    this.assignedDoctorId,
    this.assignedDoctorName,
    this.assignedDoctorDepartmentId,
  });

  bool get hasInvoice => invoiceStatus != null;
  bool get isPaid => invoiceStatus == 'paid';

  factory PatientRecord.fromJson(Map<String, dynamic> json) {
    // Extract invoice status from the first invoice if present
    String? invoiceStatus;
    final invoices = json['invoices'];
    if (invoices is List && invoices.isNotEmpty) {
      invoiceStatus = invoices.first['status'] as String?;
    }

    return PatientRecord(
      id: json['id'],
      patientId: json['patient_id'],
      workflowTemplateId: json['workflow_template_id'],
      status: json['status'],
      currentDepartmentId: json['current_department_id'],
      currentStepOrder: json['current_step_order'],
      registeredBy: json['registered_by'],
      admittedAt: json['admitted_at'] != null ? DateTime.parse(json['admitted_at']) : null,
      dischargedAt: json['discharged_at'] != null ? DateTime.parse(json['discharged_at']) : null,
      createdAt: DateTime.parse(json['created_at']),
      patient: json['patient'] != null ? Patient.fromJson(json['patient']) : null,
      currentDepartment: json['current_department'] != null
          ? Department.fromJson(json['current_department'])
          : null,
      invoiceStatus: invoiceStatus,
      paymentContext: json['payment_context'],
      paymentReason: json['payment_reason'],
      returnToDepartmentId: json['return_to_department_id'],
      clinicalNotes: json['clinical_notes'],
      pharmacyBillItems: json['pharmacy_bill_items'] != null
          ? List<Map<String, dynamic>>.from(
              (json['pharmacy_bill_items'] as List).map((e) => Map<String, dynamic>.from(e as Map)))
          : null,
      labBillItems: json['lab_bill_items'] != null
          ? List<Map<String, dynamic>>.from(
              (json['lab_bill_items'] as List).map((e) => Map<String, dynamic>.from(e as Map)))
          : null,
      wardDepartmentId: json['ward_department_id'],
      investigationNotes: json['investigation_notes'] as String?,
      prescriptionNotes: json['prescription_notes'] as String?,
      latestLabResults: json['latest_lab_results'] != null
          ? List<Map<String, dynamic>>.from(
              (json['latest_lab_results'] as List).map((e) => Map<String, dynamic>.from(e as Map)))
          : null,
      complaintHistory: json['complaint_history'] as String?,
      examinationFindings: json['examination_findings'] as String?,
      visitType: json['visit_type'] as String?,
      assignedDoctorId: json['assigned_doctor_id'] as String?,
      assignedDoctorName: json['assigned_doctor_name'] as String?,
      assignedDoctorDepartmentId: json['assigned_doctor_department_id'] as String?,
    );
  }
}

class Patient {
  final String id;
  final String patientNumber;
  final String fullName;
  final String dateOfBirth;
  final String gender;
  final String phone;
  final String? address;
  final String? nextOfKinName;
  final String? nextOfKinPhone;
  final String chiefComplaint;
  final String? hmoProviderName;
  final String? hmoPolicyNumber;
  final String? photoObjectKey;
  final DateTime createdAt;

  Patient({
    required this.id,
    required this.patientNumber,
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
    this.photoObjectKey,
    required this.createdAt,
  });

  factory Patient.fromJson(Map<String, dynamic> json) {
    return Patient(
      id: json['id'],
      patientNumber: json['patient_number'],
      fullName: json['full_name'],
      dateOfBirth: json['date_of_birth'],
      gender: json['gender'],
      phone: json['phone'],
      address: json['address'],
      nextOfKinName: json['next_of_kin_name'],
      nextOfKinPhone: json['next_of_kin_phone'],
      chiefComplaint: json['chief_complaint'],
      hmoProviderName: json['hmo_provider_name'],
      hmoPolicyNumber: json['hmo_policy_number'],
      photoObjectKey: json['photo_object_key'],
      createdAt: DateTime.parse(json['created_at']),
    );
  }
}

class Department {
  final String id;
  final String name;
  final String code;
  final String type;
  final bool isActive;

  Department({
    required this.id,
    required this.name,
    required this.code,
    required this.type,
    required this.isActive,
  });

  factory Department.fromJson(Map<String, dynamic> json) {
    return Department(
      id: json['id'],
      name: json['name'],
      code: json['code'],
      type: json['type'],
      isActive: json['is_active'],
    );
  }
}
