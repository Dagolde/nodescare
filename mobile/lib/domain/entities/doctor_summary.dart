class DoctorSummary {
  final String id;
  final String name;
  final String? departmentId;
  final String? departmentName;
  final List<String> specialties;

  const DoctorSummary({
    required this.id,
    required this.name,
    this.departmentId,
    this.departmentName,
    required this.specialties,
  });

  factory DoctorSummary.fromJson(Map<String, dynamic> json) => DoctorSummary(
        id: json['id'] as String,
        name: json['name'] as String,
        departmentId: json['department_id'] as String?,
        departmentName: json['department'] != null
            ? (json['department'] as Map<String, dynamic>)['name'] as String?
            : null,
        specialties: json['specialties'] != null
            ? List<String>.from(json['specialties'] as List)
            : [],
      );
}
