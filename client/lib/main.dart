import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'api_client.dart';
import 'app_state.dart';
import 'screens/home_screen.dart';
import 'screens/login_screen.dart';

void main() {
  runApp(ChangeNotifierProvider(create: (_) => AppState(ApiClient()), child: const PermitApp()));
}

class PermitApp extends StatelessWidget {
  const PermitApp({super.key});

  @override
  Widget build(BuildContext context) {
    final signedIn = context.select<AppState, bool>((s) => s.isSignedIn);
    return MaterialApp(
      title: 'Digital Permit Request System',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(colorSchemeSeed: const Color(0xFF1F5FAA), useMaterial3: true),
      home: signedIn ? const HomeScreen() : const LoginScreen(),
    );
  }
}
