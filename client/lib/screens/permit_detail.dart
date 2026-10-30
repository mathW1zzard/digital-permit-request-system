import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api_client.dart';
import '../app_state.dart';
import '../models.dart';
import '../widgets/status_view.dart';

class PermitDetail extends StatefulWidget {
  const PermitDetail({super.key, required this.reference});

  final String reference;

  @override
  State<PermitDetail> createState() => _PermitDetailState();
}

class _PermitDetailState extends State<PermitDetail> {
  late Future<Permit> _future;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _future = context.read<AppState>().fetchPermit(widget.reference);
  }

  void _reload() {
    setState(() {
      _future = context.read<AppState>().fetchPermit(widget.reference);
    });
  }

  Future<void> _run(String action, {String? noteField, String? noteTitle}) async {
    final state = context.read<AppState>();
    final messenger = ScaffoldMessenger.of(context);
    Map<String, dynamic>? body;
    if (noteField != null) {
      final note = await _askForNote(noteTitle ?? 'Add a note');
      if (note == null) return; // cancelled
      body = {noteField: note};
    }
    if (!mounted) return;
    setState(() => _busy = true);
    try {
      await state.act(widget.reference, action, body);
      messenger.showSnackBar(SnackBar(content: Text('Done: ${action.replaceAll('-', ' ')}.')));
    } on ApiError catch (e) {
      messenger.showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) {
        setState(() => _busy = false);
        _reload(); // always show the server's current state, also after a 403 or 409
      }
    }
  }

  Future<void> _verify() async {
    final state = context.read<AppState>();
    final messenger = ScaffoldMessenger.of(context);
    try {
      final body = await state.api.get('/permits/${widget.reference}/verify');
      final data = body['data'] as Map<String, dynamic>;
      final text = data['valid'] == true
          ? 'VALID until ${formatLocal(data['valid_until'] as String?)}'
          : 'NOT VALID (status: ${(data['status'] as String).replaceAll('_', ' ')})';
      messenger.showSnackBar(SnackBar(content: Text(text)));
    } on ApiError catch (e) {
      messenger.showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<String?> _askForNote(String title) {
    final controller = TextEditingController();
    final formKey = GlobalKey<FormState>();
    return showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(title),
        content: Form(
          key: formKey,
          child: TextFormField(
            controller: controller,
            autofocus: true,
            maxLines: 3,
            maxLength: 500,
            decoration: const InputDecoration(labelText: 'Note', border: OutlineInputBorder()),
            validator: (v) => (v == null || v.trim().length < 5) ? 'Write at least 5 characters' : null,
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext), child: const Text('Cancel')),
          FilledButton(
            onPressed: () {
              if (formKey.currentState!.validate()) Navigator.pop(dialogContext, controller.text.trim());
            },
            child: const Text('Send'),
          ),
        ],
      ),
    );
  }

  Widget _actions(String? role, Permit p) {
    VoidCallback? go(VoidCallback f) => _busy ? null : f;
    final buttons = <Widget>[
      if (role == 'reviewer' && p.status == 'under_review') ...[
        FilledButton(onPressed: go(() => _run('endorse')), child: const Text('Endorse')),
        OutlinedButton(
          onPressed: go(() => _run('request-revision', noteField: 'comment', noteTitle: 'What needs to change?')),
          child: const Text('Request revision'),
        ),
        OutlinedButton(
          onPressed: go(() => _run('reject', noteField: 'reason', noteTitle: 'Reason for rejection')),
          child: const Text('Reject'),
        ),
      ],
      if (role == 'approver' && p.status == 'pending_approval') ...[
        FilledButton(onPressed: go(() => _run('approve')), child: const Text('Approve and issue')),
        OutlinedButton(
          onPressed: go(() => _run('reject', noteField: 'reason', noteTitle: 'Reason for rejection')),
          child: const Text('Reject'),
        ),
      ],
      if (role == 'requester' && p.status == 'revision_requested')
        FilledButton(
          onPressed: go(() => _run('resubmit', noteField: 'details', noteTitle: 'What did you change?')),
          child: const Text('Resubmit'),
        ),
      if ((role == 'inspector' || role == 'admin') && (p.status == 'issued' || p.status == 'expired'))
        OutlinedButton.icon(
          onPressed: go(_verify),
          icon: const Icon(Icons.verified_outlined),
          label: const Text('Check validity'),
        ),
    ];
    if (buttons.isEmpty) return const SizedBox.shrink();
    return Wrap(spacing: 8, runSpacing: 8, children: buttons);
  }

  @override
  Widget build(BuildContext context) {
    final role = context.watch<AppState>().user?.role;
    return FutureBuilder<Permit>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const LoadingPanel(label: 'Loading permit');
        }
        if (snapshot.hasError) {
          final error = snapshot.error;
          return ErrorPanel(
            error: error is ApiError ? error : ApiError(ErrorKind.server, 'This permit could not be shown.'),
            onRetry: _reload,
          );
        }
        final p = snapshot.data!;
        final theme = Theme.of(context);
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Wrap(spacing: 8, crossAxisAlignment: WrapCrossAlignment.center, children: [
              Text(p.reference, style: theme.textTheme.labelLarge),
              StatusChip(status: p.status, flagged: p.needsManualAction),
            ]),
            const SizedBox(height: 8),
            Text(p.purpose, style: theme.textTheme.titleLarge),
            const SizedBox(height: 8),
            _Field('Type', p.typeName),
            _Field('Location', p.location),
            if (p.details != null) _Field('Details', p.details!),
            _Field('Reviewer', p.reviewer ?? 'Not assigned yet'),
            _Field('Approver', p.approver ?? 'Not assigned yet'),
            _Field('Valid until', formatLocal(p.validUntil)),
            if (p.needsManualAction)
              Card(
                color: theme.colorScheme.errorContainer,
                child: ListTile(
                  leading: const Icon(Icons.support_agent),
                  title: const Text('Waiting for staff'),
                  subtitle: Text(_reasonText(p.manualReason)),
                ),
              ),
            const SizedBox(height: 12),
            _actions(role, p),
            const SizedBox(height: 16),
            Text('Notifications', style: theme.textTheme.titleMedium),
            if (p.deliveries.isEmpty) const Text('None yet.'),
            for (final d in p.deliveries)
              ListTile(
                dense: true,
                leading: Icon(switch (d.status) {
                  'sent' => Icons.check_circle_outline,
                  'manual_action' => Icons.report_problem_outlined,
                  _ => Icons.schedule,
                }),
                title: Text(d.kind.replaceAll('_', ' ')),
                subtitle: Text(d.label),
              ),
            const SizedBox(height: 16),
            Text('Timeline', style: theme.textTheme.titleMedium),
            for (final e in p.events)
              ListTile(
                dense: true,
                title: Text(e.type.replaceAll('_', ' ')),
                subtitle: Text([
                  if (e.from != null || e.to != null) '${e.from ?? '–'} → ${e.to ?? '–'}',
                  formatLocal(e.at),
                ].join(' · ')),
              ),
          ],
        );
      },
    );
  }
}

class _Field extends StatelessWidget {
  const _Field(this.label, this.value);

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          SizedBox(width: 110, child: Text(label, style: Theme.of(context).textTheme.labelMedium)),
          Expanded(child: SelectableText(value)),
        ]),
      );
}

String _reasonText(String? reason) => switch (reason) {
      'no_reviewer_available' => 'No reviewer is available. Staff have been alerted.',
      'no_approver_available' => 'No approver is available. Staff have been alerted.',
      'review_sla_breached' => 'The review is taking longer than expected. Staff have been alerted.',
      'approval_sla_breached' => 'The approval is taking longer than expected. Staff have been alerted.',
      _ => 'Staff have been alerted.',
    };
