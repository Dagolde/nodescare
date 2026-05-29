import 'dart:async';
import 'dart:convert';
import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';

/// Model representing a real-time event received over WebSocket.
class WebSocketEvent {
  final String channelName;
  final String eventName;
  final Map<String, dynamic> data;
  final DateTime receivedAt;

  const WebSocketEvent({
    required this.channelName,
    required this.eventName,
    required this.data,
    required this.receivedAt,
  });
}

/// Singleton service that manages the Pusher/Soketi WebSocket connection,
/// channel subscriptions, and event deduplication.
class WebSocketService {
  WebSocketService._();
  static final WebSocketService instance = WebSocketService._();

  // Pusher app key supplied at build time via --dart-define=PUSHER_APP_KEY=...
  // Defaults to empty string — WebSocket is disabled when not configured.
  static const _pusherAppKey = String.fromEnvironment('PUSHER_APP_KEY', defaultValue: '');

  PusherChannelsFlutter? _pusher;
  bool _connected = false;

  final _controller = StreamController<WebSocketEvent>.broadcast();

  /// Stream of deduplicated WebSocket events.
  Stream<WebSocketEvent> get events => _controller.stream;

  // Deduplication: maps "eventName|timestamp" -> expiry DateTime
  final Map<String, DateTime> _seenEvents = {};
  static const _deduplicationTtl = Duration(seconds: 30);

  /// Initialize and connect to Soketi/Pusher.
  ///
  /// [authEndpoint] — the Laravel Pusher auth endpoint URL.
  /// [token] — the JWT bearer token for channel authorization.
  Future<void> connect(String authEndpoint, String token) async {
    if (_connected) return;

    if (_pusherAppKey.isEmpty) {
      // ignore: avoid_print
      print('[WebSocketService] PUSHER_APP_KEY not configured — WebSocket disabled.');
      return;
    }

    _pusher = PusherChannelsFlutter.getInstance();

    await _pusher!.init(
      apiKey: _pusherAppKey,
      cluster: 'mt1',
      authEndpoint: authEndpoint,
      onAuthorizer: (channelName, socketId, options) async {
        return {'Authorization': 'Bearer $token'};
      },
      onConnectionStateChange: (currentState, previousState) {
        _connected = currentState == 'CONNECTED';
      },
      onError: (message, code, error) {
        // Non-fatal; FCM fallback handles missed events
      },
    );

    await _pusher!.connect();
    _connected = true;
  }

  /// Subscribe to a private Pusher channel.
  Future<void> subscribeToChannel(String channelName) async {
    if (_pusher == null) return;
    await _pusher!.subscribe(
      channelName: channelName,
      onEvent: _handleEvent,
    );
  }

  /// Unsubscribe from a private Pusher channel.
  Future<void> unsubscribeFromChannel(String channelName) async {
    if (_pusher == null) return;
    await _pusher!.unsubscribe(channelName: channelName);
  }

  /// Disconnect and clean up all resources.
  Future<void> disconnect() async {
    if (_pusher == null) return;
    await _pusher!.disconnect();
    _connected = false;
    _seenEvents.clear();
  }

  void _handleEvent(PusherEvent event) {
    final now = DateTime.now();
    _pruneSeenEvents(now);

    // Build a deduplication key from eventName + truncated timestamp (1-second bucket)
    final bucket = (now.millisecondsSinceEpoch ~/ 1000).toString();
    final dedupeKey = '${event.eventName}|$bucket';

    if (_seenEvents.containsKey(dedupeKey)) return; // duplicate — drop it
    _seenEvents[dedupeKey] = now.add(_deduplicationTtl);

    Map<String, dynamic> parsedData = {};
    if (event.data != null && event.data!.isNotEmpty) {
      try {
        parsedData = Map<String, dynamic>.from(
          jsonDecode(event.data!) as Map,
        );
      } catch (_) {
        parsedData = {'raw': event.data};
      }
    }

    _controller.add(WebSocketEvent(
      channelName: event.channelName,
      eventName: event.eventName,
      data: parsedData,
      receivedAt: now,
    ));
  }

  /// Remove expired entries from the seen-event set.
  void _pruneSeenEvents(DateTime now) {
    _seenEvents.removeWhere((_, expiry) => now.isAfter(expiry));
  }

  /// Mark an event ID as seen (called by FCM handler to deduplicate
  /// events that arrive via both WebSocket and push notification).
  void markEventSeen(String eventName) {
    final now = DateTime.now();
    final bucket = (now.millisecondsSinceEpoch ~/ 1000).toString();
    final dedupeKey = '$eventName|$bucket';
    _seenEvents[dedupeKey] = now.add(_deduplicationTtl);
  }
}
