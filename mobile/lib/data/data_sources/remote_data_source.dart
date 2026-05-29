import 'dart:typed_data';

import 'package:dio/dio.dart';

/// Remote data source for API communication
class RemoteDataSource {
  final Dio _dio;

  RemoteDataSource({required Dio dio}) : _dio = dio;

  /// Attach a Bearer token to all subsequent requests.
  void setAuthToken(String token) {
    _dio.options.headers['Authorization'] = 'Bearer $token';
  }

  /// Remove the Authorization header (e.g. on logout).
  void clearAuthToken() {
    _dio.options.headers.remove('Authorization');
    _dio.options.headers.remove('X-Tenant-ID');
  }

  /// Set the tenant ID header for all subsequent requests.
  void setTenantId(String tenantId) {
    _dio.options.headers['X-Tenant-ID'] = tenantId;
  }

  Future<Response> get(
    String path, {
    Map<String, dynamic>? queryParameters,
    Map<String, dynamic>? headers,
  }) async {
    return await _dio.get(
      path,
      queryParameters: queryParameters,
      options: headers != null ? Options(headers: headers) : null,
    );
  }

  Future<Response> post(
    String path, {
    dynamic data,
    Map<String, dynamic>? headers,
  }) async {
    return await _dio.post(
      path,
      data: data,
      options: headers != null ? Options(headers: headers) : null,
    );
  }

  Future<Response> put(String path, {dynamic data}) async {
    return await _dio.put(path, data: data);
  }

  Future<Response> patch(String path, {dynamic data}) async {
    return await _dio.patch(path, data: data);
  }

  Future<Response> delete(String path) async {
    return await _dio.delete(path);
  }

  /// Fetch binary data (e.g. images) with the auth header included.
  /// Returns null on 404, throws on other errors.
  Future<Uint8List?> getBytes(String path) async {
    try {
      final response = await _dio.get<List<int>>(
        path,
        options: Options(responseType: ResponseType.bytes),
      );
      if (response.data == null) return null;
      return Uint8List.fromList(response.data!);
    } on DioException catch (e) {
      if (e.response?.statusCode == 404) return null;
      rethrow;
    }
  }
}
