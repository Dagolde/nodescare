import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/data_sources/remote_data_source.dart';
import '../../data/local/auth_storage.dart';
import '../../domain/entities/auth_user.dart';
import 'accounts_provider.dart';

// ---------------------------------------------------------------------------
// State
// ---------------------------------------------------------------------------

class AuthState {
  final AuthUser? user;
  final bool isLoading;
  final String? error;

  const AuthState({
    this.user,
    this.isLoading = false,
    this.error,
  });

  bool get isAuthenticated => user != null;

  AuthState copyWith({
    AuthUser? user,
    bool? isLoading,
    String? error,
    bool clearUser = false,
    bool clearError = false,
  }) {
    return AuthState(
      user: clearUser ? null : (user ?? this.user),
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

// ---------------------------------------------------------------------------
// Notifier
// ---------------------------------------------------------------------------

class AuthNotifier extends StateNotifier<AuthState> {
  final RemoteDataSource _remote;
  final AuthStorage _storage;
  bool _isRefreshing = false;

  AuthNotifier(this._remote, this._storage) : super(const AuthState());

  Future<void> restoreSession() async {
    final saved = await _storage.loadUser();
    if (saved == null) return;

    // Check if the access token is expired
    final isExpired = await _storage.isAccessTokenExpired();
    if (isExpired) {
      // Try to silently refresh using the stored refresh token
      _remote.setTenantId(saved.tenantId);
      try {
        final response = await _remote.post(
          '/api/v1/auth/refresh',
          data: {'refresh_token': saved.refreshToken},
          headers: {'X-Tenant-ID': saved.tenantId},
        );
        final data = response.data['data'] as Map<String, dynamic>;
        final newAccessToken = data['access_token'] as String;
        final newRefreshToken =
            (data['refresh_token'] as String?) ?? saved.refreshToken;
        final expiresIn = (data['expires_in'] as num?)?.toInt() ?? 43200;

        final updatedUser = saved.copyWith(
          accessToken: newAccessToken,
          refreshToken: newRefreshToken,
        );
        await _storage.saveUser(
          updatedUser,
          expiresAt: DateTime.now().add(Duration(seconds: expiresIn)),
        );
        _remote.setAuthToken(newAccessToken);
        state = state.copyWith(user: updatedUser);
      } catch (_) {
        // Refresh failed — session truly expired, force logout
        await _storage.clear();
        state = const AuthState();
      }
      return;
    }

    // Token still valid — restore normally
    _remote.setAuthToken(saved.accessToken);
    _remote.setTenantId(saved.tenantId);
    state = state.copyWith(user: saved);
  }

  Future<void> login(String email, String password, String tenantId) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final response = await _remote.post(
        '/api/v1/auth/login',
        data: {
          'email': email,
          'password': password,
        },
        headers: {'X-Tenant-ID': tenantId},
      );

      final data = response.data['data'] as Map<String, dynamic>;
      final userMap = data['user'] as Map<String, dynamic>;
      final accessToken = data['access_token'] as String;
      final refreshToken = data['refresh_token'] as String;
      final expiresIn = (data['expires_in'] as num?)?.toInt() ?? 43200; // default 12h

      final user = AuthUser.fromJson(userMap, accessToken, refreshToken, tenantId);

      await _storage.saveUser(
        user,
        expiresAt: DateTime.now().add(Duration(seconds: expiresIn)),
      );
      _remote.setAuthToken(accessToken);
      _remote.setTenantId(tenantId);

      state = state.copyWith(user: user, isLoading: false);
    } on DioException catch (e) {
      final errorData = e.response?.data;
      String message = 'Login failed. Please try again.';

      if (errorData is Map) {
        final code = errorData['error']?['code'];
        if (code == 'ACCOUNT_LOCKED') {
          message = 'Account is locked due to multiple failed login attempts.';
        } else if (code == 'ACCOUNT_INACTIVE') {
          message = 'Your account is inactive. Contact your administrator.';
        } else if (code == 'INVALID_CREDENTIALS') {
          message = 'Invalid email or password.';
        } else {
          message = errorData['error']?['message'] ?? message;
        }
      }

      state = state.copyWith(isLoading: false, error: message);
    } catch (_) {
      state = state.copyWith(
        isLoading: false,
        error: 'An unexpected error occurred.',
      );
    }
  }

  /// Attempt to refresh the access token using the stored refresh token.
  /// Returns true if successful, false if refresh failed (session expired).
  Future<bool> refreshToken() async {
    if (_isRefreshing) return false;
    _isRefreshing = true;

    try {
      final currentUser = state.user;
      if (currentUser == null || currentUser.refreshToken.isEmpty) {
        await _forceLogout();
        return false;
      }

      final response = await _remote.post(
        '/api/v1/auth/refresh',
        data: {'refresh_token': currentUser.refreshToken},
        headers: {'X-Tenant-ID': currentUser.tenantId},
      );

      final data = response.data['data'] as Map<String, dynamic>;
      final newAccessToken = data['access_token'] as String;
      final newRefreshToken = (data['refresh_token'] as String?) ?? currentUser.refreshToken;
      final expiresIn = (data['expires_in'] as num?)?.toInt() ?? 43200;

      final updatedUser = currentUser.copyWith(
        accessToken: newAccessToken,
        refreshToken: newRefreshToken,
      );

      await _storage.saveUser(
        updatedUser,
        expiresAt: DateTime.now().add(Duration(seconds: expiresIn)),
      );
      _remote.setAuthToken(newAccessToken);
      state = state.copyWith(user: updatedUser);
      return true;
    } catch (_) {
      await _forceLogout();
      return false;
    } finally {
      _isRefreshing = false;
    }
  }

  Future<void> _forceLogout() async {
    _remote.clearAuthToken();
    await _storage.clear();
    state = const AuthState();
  }

  Future<void> logout() async {
    final refreshToken = state.user?.refreshToken;
    try {
      if (refreshToken != null) {
        await _remote.post(
          '/api/v1/auth/logout',
          data: {'refresh_token': refreshToken},
        );
      }
    } catch (_) {
      // Ignore logout API errors — clear locally regardless
    } finally {
      _remote.clearAuthToken();
      await _storage.clear();
      state = const AuthState();
    }
  }

  void clearError() {
    state = state.copyWith(clearError: true);
  }
}

// ---------------------------------------------------------------------------
// Providers
// ---------------------------------------------------------------------------

final _authStorageProvider = Provider<AuthStorage>((ref) => AuthStorage());

final authProvider = StateNotifierProvider<AuthNotifier, AuthState>((ref) {
  final remote = ref.watch(remoteDataSourceProvider);
  final storage = ref.watch(_authStorageProvider);
  return AuthNotifier(remote, storage);
});

final isAuthenticatedProvider = Provider<bool>((ref) {
  return ref.watch(authProvider).isAuthenticated;
});
