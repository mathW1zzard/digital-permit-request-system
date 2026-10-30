import 'package:flutter/material.dart';

import '../api_client.dart';

class LoadingPanel extends StatelessWidget {
  const LoadingPanel({super.key, this.label = 'Loading'});

  final String label;

  @override
  Widget build(BuildContext context) => Center(
        child: Semantics(label: label, liveRegion: true, child: const CircularProgressIndicator()),
      );
}

class EmptyPanel extends StatelessWidget {
  const EmptyPanel({super.key, required this.message, this.icon = Icons.inbox_outlined});

  final String message;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Icon(icon, size: 40),
            const SizedBox(height: 8),
            Text(message, textAlign: TextAlign.center),
          ]),
        ),
      );
}

class ErrorPanel extends StatelessWidget {
  const ErrorPanel({super.key, required this.error, this.onRetry});

  final ApiError error;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    final (icon, title) = switch (error.kind) {
      ErrorKind.network => (Icons.wifi_off, 'Connection problem'),
      ErrorKind.unauthenticated => (Icons.lock_outline, 'Please sign in again'),
      ErrorKind.forbidden => (Icons.block, 'Not allowed'),
      ErrorKind.notFound => (Icons.search_off, 'Not found'),
      ErrorKind.conflict => (Icons.sync_problem, 'Already changed'),
      ErrorKind.validation => (Icons.edit_note, 'Check the form'),
      ErrorKind.server => (Icons.error_outline, 'Server problem'),
    };
    final canRetry = onRetry != null && (error.kind == ErrorKind.network || error.kind == ErrorKind.server);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Semantics(
          liveRegion: true,
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Icon(icon, size: 40, color: Theme.of(context).colorScheme.error),
            const SizedBox(height: 8),
            Text(title, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 4),
            Text(error.message, textAlign: TextAlign.center),
            if (error.correlationId != null)
              SelectableText('Reference: ${error.correlationId}', style: Theme.of(context).textTheme.bodySmall),
            if (canRetry) ...[
              const SizedBox(height: 12),
              FilledButton.icon(onPressed: onRetry, icon: const Icon(Icons.refresh), label: const Text('Try again')),
            ],
          ]),
        ),
      ),
    );
  }
}

/// Status as text, not colour alone, so it stays readable for colourblind users and screen readers.
class StatusChip extends StatelessWidget {
  const StatusChip({super.key, required this.status, this.flagged = false});

  final String status;
  final bool flagged;

  @override
  Widget build(BuildContext context) => Row(mainAxisSize: MainAxisSize.min, children: [
        if (flagged)
          Tooltip(
            message: 'Needs staff attention',
            child: Icon(Icons.warning_amber,
                color: Theme.of(context).colorScheme.error, semanticLabel: 'Needs staff attention'),
          ),
        const SizedBox(width: 4),
        Chip(label: Text(status.replaceAll('_', ' ')), visualDensity: VisualDensity.compact),
      ]);
}
