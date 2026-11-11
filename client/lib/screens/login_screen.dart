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
  // Fictional seeded accounts (Step 4). The chips fill the email only; the password is DEMO_PASSWORD from backend\.env.
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
  final _password = TextEditingController();
  final _passwordFocus = FocusNode();
  bool _busy = false;
  bool _showPassword = false;
  ApiError? _error;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    _passwordFocus.dispose();
    super.dispose();
  }

  Future<void> _signIn() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await context.read<AppState>().signIn(_email.text, _password.text);
    } on ApiError catch (e) {
      if (mounted) setState(() => _error = e);
    } finally {
      if (mounted) {
        _password.clear(); // never keep a typed password around after an attempt
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final sessionExpired = context.select<AppState, bool>((s) => s.sessionExpired);
    final errorText = _error?.message;
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
                const Text('Sign in with a demo account. All accounts and data are fictional.'),
                if (sessionExpired) ...[
                  const SizedBox(height: 16),
                  Semantics(
                    liveRegion: true,
                    child: Card(
                      color: theme.colorScheme.secondaryContainer,
                      child: const ListTile(
                        leading: Icon(Icons.timer_off_outlined),
                        title: Text('Your session ended'),
                        subtitle: Text('It expired or was signed out. Please sign in again.'),
                      ),
                    ),
                  ),
                ],
                const SizedBox(height: 24),
                TextFormField(
                  controller: _email,
                  autofocus: true,
                  keyboardType: TextInputType.emailAddress,
                  autofillHints: const [AutofillHints.username],
                  decoration: const InputDecoration(labelText: 'Email', border: OutlineInputBorder()),
                  validator: (v) => (v == null || !v.contains('@')) ? 'Enter an email address' : null,
                  onFieldSubmitted: (_) => _passwordFocus.requestFocus(),
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _password,
                  focusNode: _passwordFocus,
                  obscureText: !_showPassword,
                  autofillHints: const [AutofillHints.password],
                  decoration: InputDecoration(
                    labelText: 'Password',
                    border: const OutlineInputBorder(),
                    suffixIcon: IconButton(
                      tooltip: _showPassword ? 'Hide password' : 'Show password',
                      icon: Icon(_showPassword ? Icons.visibility_off : Icons.visibility),
                      onPressed: () => setState(() => _showPassword = !_showPassword),
                    ),
                  ),
                  validator: (v) => (v == null || v.isEmpty) ? 'Enter your password' : null,
                  onFieldSubmitted: (_) => _signIn(),
                ),
                const SizedBox(height: 12),
                Wrap(spacing: 8, runSpacing: 8, children: [
                  for (final entry in demoAccounts.entries)
                    ActionChip(
                      label: Text(entry.value),
                      onPressed: () {
                        setState(() => _email.text = entry.key);
                        _passwordFocus.requestFocus();
                      },
                    ),
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
