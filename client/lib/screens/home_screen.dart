import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../app_state.dart';
import '../widgets/status_view.dart';
import 'exceptions_view.dart';
import 'permit_detail.dart';
import 'permit_form_screen.dart';

class HomeScreen extends StatelessWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    final user = state.user;
    if (user == null) return const SizedBox.shrink(); // signing out
    final isAdmin = user.role == 'admin';
    final showName = MediaQuery.sizeOf(context).width >= 600;

    return DefaultTabController(
      length: isAdmin ? 2 : 1,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Permits'),
          actions: [
            if (showName) Center(child: Text('${user.name} · ${user.role}')),
            IconButton(tooltip: 'Refresh', icon: const Icon(Icons.refresh), onPressed: state.loadPermits),
            IconButton(tooltip: 'Sign out', icon: const Icon(Icons.logout), onPressed: state.signOut),
          ],
          bottom: isAdmin ? const TabBar(tabs: [Tab(text: 'Permits'), Tab(text: 'Needs attention')]) : null,
        ),
        floatingActionButton: user.role == 'requester'
            ? FloatingActionButton.extended(
                onPressed: () => Navigator.of(context)
                    .push(MaterialPageRoute<void>(builder: (_) => const PermitFormScreen())),
                icon: const Icon(Icons.add),
                label: const Text('New permit'),
              )
            : null,
        body: isAdmin ? const TabBarView(children: [PermitsPane(), ExceptionsView()]) : const PermitsPane(),
      ),
    );
  }
}

class PermitsPane extends StatelessWidget {
  const PermitsPane({super.key});

  static const double wideBreakpoint = 900;

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    return LayoutBuilder(builder: (context, constraints) {
      final wide = constraints.maxWidth >= wideBreakpoint;
      final list = _PermitList(onOpen: (reference) {
        state.select(reference);
        if (!wide) {
          Navigator.of(context).push(MaterialPageRoute<void>(
            builder: (_) => Scaffold(appBar: AppBar(title: Text(reference)), body: PermitDetail(reference: reference)),
          ));
        }
      });
      if (!wide) return list;

      final selected = state.selectedReference;
      return Row(children: [
        SizedBox(width: 380, child: list),
        const VerticalDivider(width: 1),
        Expanded(
          child: selected == null
              ? const EmptyPanel(message: 'Select a permit to see its details.', icon: Icons.touch_app_outlined)
              : PermitDetail(key: ValueKey(selected), reference: selected),
        ),
      ]);
    });
  }
}

class _PermitList extends StatelessWidget {
  const _PermitList({required this.onOpen});

  final void Function(String reference) onOpen;

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    return switch (state.listStatus) {
      LoadStatus.idle || LoadStatus.loading => const LoadingPanel(label: 'Loading permits'),
      LoadStatus.error => ErrorPanel(error: state.listError!, onRetry: state.loadPermits),
      LoadStatus.empty => const EmptyPanel(message: 'No permits to show yet.'),
      LoadStatus.ready => ListView.separated(
          itemCount: state.permits.length,
          separatorBuilder: (context, index) => const Divider(height: 1),
          itemBuilder: (context, index) {
            final p = state.permits[index];
            return ListTile(
              selected: p.reference == state.selectedReference,
              title: Text(p.purpose, maxLines: 1, overflow: TextOverflow.ellipsis),
              subtitle: Text('${p.reference} · ${p.typeName}'),
              trailing: StatusChip(status: p.status, flagged: p.needsManualAction),
              onTap: () => onOpen(p.reference),
            );
          },
        ),
    };
  }
}
