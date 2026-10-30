import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api_client.dart';
import '../app_state.dart';
import '../widgets/status_view.dart';

class ExceptionsView extends StatefulWidget {
  const ExceptionsView({super.key});

  @override
  State<ExceptionsView> createState() => _ExceptionsViewState();
}

class _ExceptionsViewState extends State<ExceptionsView> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<AppState>().loadExceptions();
  }

  void _reload() {
    setState(() {
      _future = context.read<AppState>().loadExceptions();
    });
  }

  Future<void> _requeue(int id) async {
    final state = context.read<AppState>();
    final messenger = ScaffoldMessenger.of(context);
    try {
      await state.requeue(id);
      messenger.showSnackBar(const SnackBar(content: Text('Requeued. The next dispatch run will try again.')));
    } on ApiError catch (e) {
      messenger.showSnackBar(SnackBar(content: Text(e.message)));
    }
    if (mounted) _reload();
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, dynamic>>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const LoadingPanel(label: 'Loading items that need attention');
        }
        if (snapshot.hasError) {
          final error = snapshot.error;
          return ErrorPanel(
            error: error is ApiError ? error : ApiError(ErrorKind.server, 'The list could not be loaded.'),
            onRetry: _reload,
          );
        }
        final notifications = (snapshot.data!['notifications'] as List).cast<Map<String, dynamic>>();
        final permits = (snapshot.data!['permits'] as List).cast<Map<String, dynamic>>();
        if (notifications.isEmpty && permits.isEmpty) {
          return const EmptyPanel(message: 'Nothing needs attention.', icon: Icons.task_alt);
        }
        final theme = Theme.of(context);
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Align(
              alignment: Alignment.centerRight,
              child: TextButton.icon(onPressed: _reload, icon: const Icon(Icons.refresh), label: const Text('Refresh')),
            ),
            Text('Undelivered notifications (${notifications.length})', style: theme.textTheme.titleMedium),
            for (final n in notifications)
              ListTile(
                leading: const Icon(Icons.mark_email_unread_outlined),
                title: Text('${(n['kind'] as String).replaceAll('_', ' ')} · ${n['permit']}'),
                subtitle: Text('${_failureText(n['failure'] as String?)} after ${n['attempts']} attempt(s) · ${n['detail'] ?? ''}'),
                trailing: OutlinedButton(onPressed: () => _requeue(n['id'] as int), child: const Text('Requeue')),
              ),
            const SizedBox(height: 16),
            Text('Permits waiting for staff (${permits.length})', style: theme.textTheme.titleMedium),
            for (final p in permits)
              ListTile(
                leading: const Icon(Icons.support_agent),
                title: Text('${p['reference']} · ${(p['status'] as String).replaceAll('_', ' ')}'),
                subtitle: Text(((p['reason'] as String?) ?? '').replaceAll('_', ' ')),
              ),
          ],
        );
      },
    );
  }
}

String _failureText(String? failure) => switch (failure) {
      'permanent' => 'Rejected by the gateway, will not retry',
      'transient_exhausted' => 'Gateway unreachable, retries used up',
      _ => 'Failed',
    };
