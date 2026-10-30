import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api_client.dart';
import '../app_state.dart';

class PermitFormScreen extends StatefulWidget {
  const PermitFormScreen({super.key});

  @override
  State<PermitFormScreen> createState() => _PermitFormScreenState();
}

class _PermitFormScreenState extends State<PermitFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _purpose = TextEditingController();
  final _location = TextEditingController();
  final _details = TextEditingController();
  int? _typeId;
  DateTime? _start;
  Map<String, String> _serverErrors = {};
  bool _busy = false;

  DateTime get _today {
    final now = DateTime.now();
    return DateTime(now.year, now.month, now.day);
  }

  String _iso(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  @override
  void dispose() {
    _purpose.dispose();
    _location.dispose();
    _details.dispose();
    super.dispose();
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _start ?? _today,
      firstDate: _today,
      lastDate: _today.add(const Duration(days: 365)),
    );
    if (picked != null) setState(() => _start = picked);
  }

  Future<void> _submit() async {
    setState(() => _serverErrors = {});
    if (!_formKey.currentState!.validate()) return;
    final state = context.read<AppState>();
    final navigator = Navigator.of(context);
    final messenger = ScaffoldMessenger.of(context);
    setState(() => _busy = true);
    try {
      final permit = await state.submitPermit({
        'permit_type_id': _typeId,
        'purpose': _purpose.text.trim(),
        'location': _location.text.trim(),
        if (_details.text.trim().isNotEmpty) 'details': _details.text.trim(),
        'requested_start': _iso(_start!),
      });
      messenger.showSnackBar(SnackBar(content: Text('Submitted ${permit.reference}. It is now under review.')));
      navigator.pop();
    } on ApiError catch (e) {
      if (!mounted) return;
      if (e.kind == ErrorKind.validation) {
        setState(() => _serverErrors = e.fieldErrors);
        _formKey.currentState!.validate(); // show the server's messages under the fields
      } else {
        messenger.showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final types = context.watch<AppState>().types;
    return Scaffold(
      appBar: AppBar(title: const Text('New permit')),
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 640),
          child: Form(
            key: _formKey,
            child: ListView(padding: const EdgeInsets.all(16), children: [
              FormField<int>(
                validator: (_) => _typeId == null ? 'Choose a permit type' : _serverErrors['permit_type_id'],
                builder: (field) => InputDecorator(
                  decoration: InputDecoration(
                    labelText: 'Permit type',
                    errorText: field.errorText,
                    border: const OutlineInputBorder(),
                  ),
                  child: Wrap(spacing: 8, runSpacing: 8, children: [
                    for (final t in types)
                      ChoiceChip(
                        label: Text('${t.name} (${t.validityDays} days)'),
                        selected: _typeId == t.id,
                        onSelected: (_) {
                          setState(() => _typeId = t.id);
                          field.didChange(t.id);
                        },
                      ),
                  ]),
                ),
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _purpose,
                maxLength: 255,
                decoration: const InputDecoration(
                  labelText: 'Purpose',
                  helperText: '10 to 255 characters',
                  border: OutlineInputBorder(),
                ),
                validator: (v) => (v ?? '').trim().length < 10 ? 'Write at least 10 characters' : _serverErrors['purpose'],
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _location,
                maxLength: 120,
                decoration: const InputDecoration(labelText: 'Location', border: OutlineInputBorder()),
                validator: (v) => (v ?? '').trim().isEmpty ? 'Enter the location' : _serverErrors['location'],
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _details,
                maxLength: 2000,
                maxLines: 4,
                decoration: const InputDecoration(labelText: 'Details (optional)', border: OutlineInputBorder()),
                validator: (_) => _serverErrors['details'],
              ),
              const SizedBox(height: 8),
              FormField<DateTime>(
                validator: (_) {
                  if (_start == null) return 'Choose a start date';
                  if (_start!.isBefore(_today)) return 'The start date cannot be in the past';
                  return _serverErrors['requested_start'];
                },
                builder: (field) => InputDecorator(
                  decoration: InputDecoration(
                    labelText: 'Requested start date',
                    errorText: field.errorText,
                    border: const OutlineInputBorder(),
                  ),
                  child: Row(children: [
                    Expanded(child: Text(_start == null ? 'Not chosen' : _iso(_start!))),
                    TextButton.icon(
                      onPressed: () async {
                        await _pickDate();
                        field.didChange(_start);
                      },
                      icon: const Icon(Icons.calendar_today),
                      label: const Text('Choose'),
                    ),
                  ]),
                ),
              ),
              const SizedBox(height: 24),
              FilledButton(
                onPressed: _busy ? null : _submit,
                child: _busy
                    ? const SizedBox.square(dimension: 20, child: CircularProgressIndicator(strokeWidth: 2))
                    : const Text('Submit for review'),
              ),
            ]),
          ),
        ),
      ),
    );
  }
}
