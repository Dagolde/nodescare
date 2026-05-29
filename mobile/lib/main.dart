import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:firebase_core/firebase_core.dart';
import 'presentation/providers/auth_provider.dart';
import 'presentation/providers/websocket_provider.dart';
import 'presentation/screens/login_screen.dart';
import 'presentation/screens/role_dashboard_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  try {
    await Firebase.initializeApp();
  } catch (_) {
    // Firebase may not be configured — non-fatal
  }

  runApp(const ProviderScope(child: HHMSApp()));
}

class HHMSApp extends StatelessWidget {
  const HHMSApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'HHMS Mobile',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF1565C0)),
        useMaterial3: true,
        appBarTheme: const AppBarTheme(centerTitle: false, elevation: 0),
      ),
      home: const _AppRoot(),
    );
  }
}

class _AppRoot extends ConsumerStatefulWidget {
  const _AppRoot();

  @override
  ConsumerState<_AppRoot> createState() => _AppRootState();
}

class _AppRootState extends ConsumerState<_AppRoot> {
  bool _sessionRestored = false;

  @override
  void initState() {
    super.initState();
    _restoreSession();
  }

  Future<void> _restoreSession() async {
    await ref.read(authProvider.notifier).restoreSession();
    if (mounted) setState(() => _sessionRestored = true);
  }

  @override
  Widget build(BuildContext context) {
    if (!_sessionRestored) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    final authState = ref.watch(authProvider);

    // Initialize WebSocket connection when user is authenticated
    ref.listen<AuthState>(authProvider, (previous, next) {
      if (next.isAuthenticated && next.user != null) {
        final user = next.user!;
        // Only initialize if transitioning from unauthenticated to authenticated
        if (previous?.isAuthenticated != true) {
          // Get the API base URL from the remote data source
          final apiBaseUrl = 'https://your-api-url.com'; // TODO: Get this from config
          ref.read(webSocketSessionProvider.notifier).onLogin(
                authEndpoint: '$apiBaseUrl/broadcasting/auth',
                token: user.accessToken,
                tenantId: user.tenantId,
                departmentId: user.departmentId ?? '',
                userId: user.id,
              );
        }
      } else if (!next.isAuthenticated && previous?.isAuthenticated == true) {
        // User logged out
        final prevUser = previous?.user;
        if (prevUser != null) {
          ref.read(webSocketSessionProvider.notifier).onLogout(
                tenantId: prevUser.tenantId,
                departmentId: prevUser.departmentId ?? '',
                userId: prevUser.id,
              );
        }
      }
    });

    if (!authState.isAuthenticated) {
      return const LoginScreen();
    }

    // RoleDashboardScreen handles routing by role
    return const RoleDashboardScreen();
  }
}
