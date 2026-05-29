import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../services/websocket_service.dart';

/// Exposes the singleton [WebSocketService] instance.
final webSocketServiceProvider = Provider<WebSocketService>((ref) {
  final service = WebSocketService.instance;
  ref.onDispose(service.disconnect);
  return service;
});

/// Exposes the broadcast stream of [WebSocketEvent] objects.
final webSocketEventsProvider = StreamProvider<WebSocketEvent>((ref) {
  final service = ref.watch(webSocketServiceProvider);
  return service.events;
});

/// Notifier that manages connecting/subscribing on login and
/// unsubscribing/disconnecting on logout.
class WebSocketSessionNotifier extends StateNotifier<bool> {
  final WebSocketService _service;

  WebSocketSessionNotifier(this._service) : super(false);

  /// Call after a successful login to connect and subscribe to the
  /// user's department, personal, and tenant channels.
  Future<void> onLogin({
    required String authEndpoint,
    required String token,
    required String tenantId,
    required String departmentId,
    required String userId,
  }) async {
    try {
      await _service.connect(authEndpoint, token);
      await _service.subscribeToChannel('private-tenant.$tenantId');
      await _service.subscribeToChannel('private-department.$departmentId');
      await _service.subscribeToChannel('private-user.$userId');
      state = true;
    } catch (_) {
      // WebSocket failure is non-fatal; FCM fallback handles missed events
      state = false;
    }
  }

  /// Call on logout to clean up channels and the connection.
  Future<void> onLogout({
    required String tenantId,
    required String departmentId,
    required String userId,
  }) async {
    try {
      await _service.unsubscribeFromChannel('private-tenant.$tenantId');
      await _service.unsubscribeFromChannel('private-department.$departmentId');
      await _service.unsubscribeFromChannel('private-user.$userId');
      await _service.disconnect();
    } finally {
      state = false;
    }
  }
}

/// Provider for [WebSocketSessionNotifier].
/// `state` is `true` when the WebSocket session is active.
final webSocketSessionProvider =
    StateNotifierProvider<WebSocketSessionNotifier, bool>((ref) {
  final service = ref.watch(webSocketServiceProvider);
  return WebSocketSessionNotifier(service);
});
