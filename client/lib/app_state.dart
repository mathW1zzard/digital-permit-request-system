import 'package:flutter/foundation.dart';

import 'api_client.dart';
import 'models.dart';

enum LoadStatus { idle, loading, ready, empty, error }

class AppState extends ChangeNotifier {
  AppState(this.api);

  final ApiClient api;

  AppUser? user;
  List<PermitType> types = [];
  List<Permit> permits = [];
  LoadStatus listStatus = LoadStatus.idle;
  ApiError? listError;
  String? selectedReference;

  bool get isSignedIn => user != null;

  Future<void> signIn(String email) async {
    api.userEmail = email.trim();
    try {
      final me = await api.get('/me');
      user = AppUser.fromJson(me['data'] as Map<String, dynamic>);
      final t = await api.get('/permit-types');
      types = (t['data'] as List).map((e) => PermitType.fromJson(e as Map<String, dynamic>)).toList();
      notifyListeners();
      await loadPermits();
    } catch (_) {
      api.userEmail = null;
      user = null;
      rethrow;
    }
  }

  void signOut() {
    api.userEmail = null;
    user = null;
    permits = [];
    selectedReference = null;
    listStatus = LoadStatus.idle;
    notifyListeners();
  }

  Future<void> loadPermits() async {
    listStatus = LoadStatus.loading;
    listError = null;
    notifyListeners();
    try {
      final body = await api.get('/permits');
      permits = (body['data'] as List).map((e) => Permit.fromJson(e as Map<String, dynamic>)).toList();
      listStatus = permits.isEmpty ? LoadStatus.empty : LoadStatus.ready;
    } on ApiError catch (e) {
      listError = e;
      listStatus = LoadStatus.error;
      if (e.kind == ErrorKind.unauthenticated) user = null; // back to sign-in
    }
    notifyListeners();
  }

  void select(String reference) {
    selectedReference = reference;
    notifyListeners();
  }

  Future<Permit> fetchPermit(String reference) async {
    final body = await api.get('/permits/$reference');
    return Permit.fromJson(body['data'] as Map<String, dynamic>);
  }

  Future<Permit> submitPermit(Map<String, dynamic> form) async {
    final body = await api.post('/permits', form);
    final permit = Permit.fromJson(body['data'] as Map<String, dynamic>);
    await loadPermits();
    select(permit.reference);
    return permit;
  }

  Future<void> act(String reference, String action, [Map<String, dynamic>? body]) async {
    await api.post('/permits/$reference/$action', body);
    await loadPermits();
  }

  Future<Map<String, dynamic>> loadExceptions() async =>
      (await api.get('/admin/exceptions'))['data'] as Map<String, dynamic>;

  Future<void> requeue(int id) => api.post('/admin/notifications/$id/requeue');
}
