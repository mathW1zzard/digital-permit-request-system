import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api_client.dart';
import '../app_state.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  // Fictional seeded accounts (Step 4). Week 14 replaces this screen with real login.
  static const demoAccounts = {
    'requester@permit.test': 'Requester',
    'bounce@invalid.test': 'Requester (bad address)',
    'reviewer1@permit.test': 'Reviewer 1',
    'reviewer2@permit.test': 'Reviewer 2',
    'approver@permit.test': 'Approver',
    'senior@permit.test': 'Senior approver',
    'inspector@permit.test': 'Inspector',
    'admin@permit.test': 'Admin',
  };

  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();
  bool _busy = false;
  ApiError? _error;

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  Future<void> _signIn() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await context.read<AppState>().signIn(_email.text);
    } on ApiError catch (e) {
      if (mounted) setState(() => _error = e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final errorText = _error == null
        ? null
        : _error!.kind == ErrorKind.unauthenticated
            ? 'No demo account uses that email.'
            : _error!.message;
    return Scaffold(
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 420),
            child: Form(
              key: _formKey,
              child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                Text('Digital Permit Request System', style: theme.textTheme.headlineSmall),
                const SizedBox(height: 4),
                const Text('Week 13 demo sign-in. Real login arrives in Week 14.'),
                const SizedBox(height: 24),
                TextFormField(
                  controller: _email,
                  autofocus: true,
                  keyboardType: TextInputType.emailAddress,
                  decoration: const InputDecoration(labelText: 'Email', border: OutlineInputBorder()),
                  validator: (v) => (v == null || !v.contains('@')) ? 'Enter an email address' : null,
                  onFieldSubmitted: (_) => _signIn(),
                ),
                const SizedBox(height: 12),
                Wrap(spacing: 8, runSpacing: 8, children: [
                  for (final entry in demoAccounts.entries)
                    ActionChip(label: Text(entry.value), onPressed: () => setState(() => _email.text = entry.key)),
                ]),
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: _busy ? null : _signIn,
                  child: _busy
                      ? const SizedBox.square(dimension: 20, child: CircularProgressIndicator(strokeWidth: 2))
                      : const Text('Sign in'),
                ),
                if (errorText != null) ...[
                  const SizedBox(height: 12),
                  Semantics(
                    liveRegion: true,
                    child: Text(errorText, style: TextStyle(color: theme.colorScheme.error)),
                  ),
                ],
              ]),
            ),
          ),
        ),
      ),
    );
  }
}
