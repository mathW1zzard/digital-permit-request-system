class AppUser {
  AppUser({required this.id, required this.name, required this.role});

  final int id;
  final String name;
  final String role;

  factory AppUser.fromJson(Map<String, dynamic> json) =>
      AppUser(id: json['id'] as int, name: json['name'] as String, role: json['role'] as String);
}

class PermitType {
  PermitType({required this.id, required this.name, required this.validityDays});

  final int id;
  final String name;
  final int validityDays;

  factory PermitType.fromJson(Map<String, dynamic> json) => PermitType(
        id: json['id'] as int,
        name: json['name'] as String,
        validityDays: json['validity_days'] as int,
      );
}

class TimelineEvent {
  TimelineEvent({required this.type, this.from, this.to, this.at});

  final String type;
  final String? from;
  final String? to;
  final String? at;

  factory TimelineEvent.fromJson(Map<String, dynamic> json) => TimelineEvent(
        type: json['type'] as String,
        from: json['from'] as String?,
        to: json['to'] as String?,
        at: json['at'] as String?,
      );
}

class Delivery {
  Delivery({required this.kind, required this.status, required this.attempts});

  final String kind;
  final String status;
  final int attempts;

  factory Delivery.fromJson(Map<String, dynamic> json) => Delivery(
        kind: json['kind'] as String,
        status: json['status'] as String,
        attempts: json['attempts'] as int,
      );

  /// Safe, human words for the automation state. No raw gateway errors reach the browser.
  String get label => switch (status) {
        'sent' => 'Delivered',
        'pending' => attempts == 0 ? 'Queued' : 'Retrying (attempt $attempts so far)',
        'manual_action' => 'Needs staff attention',
        _ => status,
      };
}

class Permit {
  Permit({
    required this.reference,
    required this.typeName,
    required this.purpose,
    required this.location,
    required this.status,
    this.details,
    this.reviewer,
    this.approver,
    this.validUntil,
    this.needsManualAction = false,
    this.manualReason,
    this.events = const [],
    this.deliveries = const [],
  });

  final String reference;
  final String typeName;
  final String purpose;
  final String location;
  final String status;
  final String? details;
  final String? reviewer;
  final String? approver;
  final String? validUntil;
  final bool needsManualAction;
  final String? manualReason;
  final List<TimelineEvent> events;
  final List<Delivery> deliveries;

  factory Permit.fromJson(Map<String, dynamic> json) => Permit(
        reference: json['reference'] as String,
        typeName: (json['type'] as Map<String, dynamic>?)?['name'] as String? ?? '',
        purpose: json['purpose'] as String,
        location: json['location'] as String,
        status: json['status'] as String,
        details: json['details'] as String?,
        reviewer: json['reviewer'] as String?,
        approver: json['approver'] as String?,
        validUntil: json['valid_until'] as String?,
        needsManualAction: json['needs_manual_action'] as bool? ?? false,
        manualReason: json['manual_action_reason'] as String?,
        events: ((json['events'] as List?) ?? const [])
            .map((e) => TimelineEvent.fromJson(e as Map<String, dynamic>))
            .toList(),
        deliveries: ((json['notifications'] as List?) ?? const [])
            .map((e) => Delivery.fromJson(e as Map<String, dynamic>))
            .toList(),
      );
}

/// Server times are UTC; show them in the viewer's local time (Asia/Manila on your laptop).
String formatLocal(String? iso) {
  if (iso == null) return '–';
  final d = DateTime.parse(iso).toLocal();
  String two(int n) => n.toString().padLeft(2, '0');
  return '${d.year}-${two(d.month)}-${two(d.day)} ${two(d.hour)}:${two(d.minute)}';
}
