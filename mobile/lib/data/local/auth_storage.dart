import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../../domain/entities/auth_user.dart';

class AuthStorage {
  static const _storage = FlutterSecureStorage();

  static const _keyAccessToken = 'auth_access_token';
  static const _keyRefreshToken = 'auth_refresh_token';
  static const _keyUser = 'auth_user';
  static const _keyTokenExpiresAt = 'auth_token_expires_at'; // ISO-8601 string

  Future<void> saveUser(AuthUser user, {DateTime? expiresAt}) async {
    await Future.wait([
      _storage.write(key: _keyAccessToken, value: user.accessToken),
      _storage.write(key: _keyRefreshToken, value: user.refreshToken),
      _storage.write(
        key: _keyUser,
        value: jsonEncode({
          'id': user.id,
          'name': user.name,
          'email': user.email,
          'role': user.role,
          'department_id': user.departmentId,
          'tenant_id': user.tenantId,
        }),
      ),
      if (expiresAt != null)
        _storage.write(
          key: _keyTokenExpiresAt,
          value: expiresAt.toIso8601String(),
        ),
    ]);
  }

  Future<AuthUser?> loadUser() async {
    final accessToken = await _storage.read(key: _keyAccessToken);
    final refreshToken = await _storage.read(key: _keyRefreshToken);
    final userJson = await _storage.read(key: _keyUser);

    if (accessToken == null || refreshToken == null || userJson == null) {
      return null;
    }

    try {
      final userMap = jsonDecode(userJson) as Map<String, dynamic>;
      final tenantId = userMap['tenant_id'] as String? ?? '';
      return AuthUser.fromJson(userMap, accessToken, refreshToken, tenantId);
    } catch (_) {
      return null;
    }
  }

  /// Returns true if the stored access token is expired (or expiry unknown).
  Future<bool> isAccessTokenExpired() async {
    final expiresAtStr = await _storage.read(key: _keyTokenExpiresAt);
    if (expiresAtStr == null) return true; // unknown — treat as expired
    try {
      final expiresAt = DateTime.parse(expiresAtStr);
      // Add a 60-second buffer so we refresh slightly before actual expiry
      return DateTime.now().isAfter(expiresAt.subtract(const Duration(seconds: 60)));
    } catch (_) {
      return true;
    }
  }

  Future<void> clear() async {
    await Future.wait([
      _storage.delete(key: _keyAccessToken),
      _storage.delete(key: _keyRefreshToken),
      _storage.delete(key: _keyUser),
      _storage.delete(key: _keyTokenExpiresAt),
    ]);
  }
}
