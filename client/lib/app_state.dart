import 'package:flutter/foundation.dart';

import 'api_client.dart';
import 'models.dart';

enum LoadStatus { idle, loading, ready, empty, error }

class AppState extends ChangeNotifier {
  AppState(this.api) {
    api.onSessionExpired = _expireSession;
  }

  final ApiClient api;

  AppUser? user;
  List<PermitType> types = [];
  List<Permit> permits = [];
  LoadStatus listStatus = LoadStatus.idle;
  ApiError? listError;
  String? selectedReference;

  /// True after the server rejected our token, so the sign-in screen can say why.
  bool sessionExpired = false;

  bool get isSignedIn => user != null;

  Future<void> signIn(String email, String password) async {
    try {
      final body = await api.post('/login', {'email': email.trim(), 'password': password});
      final data = body['data'] as Map<String, dynamic>;
      api.token = data['token'] as String;
      user = AppUser.fromJson(data['user'] as Map<String, dynamic>);
      sessionExpired = false;
      final t = await api.get('/permit-types');
      types = (t['data'] as List).map((e) => PermitType.fromJson(e as Map<String, dynamic>)).toList();
      notifyListeners();
      await loadPermits();
    } catch (_) {
      api.token = null;
      user = null;
      rethrow;
    }
  }

  Future<void> signOut() async {
    try {
      await api.post('/logout'); // revoke the token on the server, not just forget it here
    } on ApiError {
      // already expired or the server is down: forgetting it locally is still correct
    }
    _clear();
    notifyListeners();
  }

  void _expireSession() {
    if (user == null) return;
    _clear();
    sessionExpired = true;
    notifyListeners();
  }

  void _clear() {
    api.token = null;
    user = null;
    permits = [];
    selectedReference = null;
    listStatus = LoadStatus.idle;
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
      listStatus = LoadStatus.error; // a 401 already sent the user back to sign-in
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
