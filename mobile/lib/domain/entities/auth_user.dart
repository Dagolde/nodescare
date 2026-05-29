class AuthUser {
  final String id;
  final String name;
  final String email;
  final String role;
  final String? departmentId;
  final String tenantId;
  final String accessToken;
  final String refreshToken;

  const AuthUser({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
    this.departmentId,
    required this.tenantId,
    required this.accessToken,
    required this.refreshToken,
  });

  factory AuthUser.fromJson(
    Map<String, dynamic> json,
    String accessToken,
    String refreshToken,
    String tenantId,
  ) {
    return AuthUser(
      id: json['id'] as String,
      name: json['name'] as String,
      email: json['email'] as String,
      role: json['role'] as String,
      departmentId: json['department_id'] as String?,
      tenantId: tenantId,
      accessToken: accessToken,
      refreshToken: refreshToken,
    );
  }

  AuthUser copyWith({
    String? id,
    String? name,
    String? email,
    String? role,
    String? departmentId,
    String? tenantId,
    String? accessToken,
    String? refreshToken,
  }) {
    return AuthUser(
      id: id ?? this.id,
      name: name ?? this.name,
      email: email ?? this.email,
      role: role ?? this.role,
      departmentId: departmentId ?? this.departmentId,
      tenantId: tenantId ?? this.tenantId,
      accessToken: accessToken ?? this.accessToken,
      refreshToken: refreshToken ?? this.refreshToken,
    );
  }
}
