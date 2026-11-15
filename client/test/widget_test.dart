import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';

import 'package:permit_web/api_client.dart';
import 'package:permit_web/app_state.dart';
import 'package:permit_web/main.dart';
import 'package:permit_web/models.dart';
import 'package:permit_web/screens/permit_form_screen.dart';
import 'package:flutter/material.dart';

void main() {
  testWidgets('Sign-in validates both fields and shows a wrong-password error', (tester) async {
    final requests = <http.Request>[];
    final client = MockClient((request) async {
      requests.add(request);
      return http.Response(
        '{"error":{"code":"invalid_credentials","message":"Email or password is incorrect."}}',
        401,
      );
    });
    await tester.pumpWidget(ChangeNotifierProvider(
      create: (_) => AppState(ApiClient(client: client)),
      child: const PermitApp(),
    ));

    await tester.ensureVisible(find.text('Sign in'));
    await tester.tap(find.text('Sign in'));
    await tester.pump();
    expect(find.text('Enter an email address'), findsOneWidget);
    expect(find.text('Enter your password'), findsOneWidget);

    await tester.enterText(find.bySemanticsLabel('Email'), 'requester@permit.test');
    await tester.enterText(find.bySemanticsLabel('Password'), 'not-the-password');
    await tester.ensureVisible(find.text('Sign in'));
    await tester.tap(find.text('Sign in'));
    await tester.pumpAndSettle();

    expect(find.text('Email or password is incorrect.'), findsOneWidget);
    expect(requests.single.url.path, endsWith('/login'));
    expect(requests.single.headers.containsKey('Authorization'), isFalse);
  });

  test('A 401 on a signed-in request ends the session', () async {
    final client = MockClient((request) async {
      if (request.url.path.endsWith('/login')) {
        return http.Response(jsonEncode({
          'data': {'token': '1|abc', 'expires_at': null, 'user': {'id': 2, 'name': 'Rico Requester', 'role': 'requester'}},
        }), 200);
      }
      if (request.url.path.endsWith('/permit-types')) return http.Response('{"data":[]}', 200);
      return http.Response('{"error":{"code":"unauthenticated","message":"Sign in to continue."}}', 401);
    });
    final state = AppState(ApiClient(client: client));

    await state.signIn('requester@permit.test', 'test-only-password');

    expect(state.isSignedIn, isFalse);
    expect(state.sessionExpired, isTrue);
    expect(state.api.token, isNull);
  });

  test('A resent submission carries the same Idempotency-Key', () async {
    final keys = <String?>[];
    final client = MockClient((request) async {
      keys.add(request.headers['Idempotency-Key']);
      return http.Response('{"data":{}}', 201);
    });
    final api = ApiClient(client: client);
    final key = api.newIdempotencyKey();

    await api.post('/permits', {'purpose': 'Same form'}, key);
    await api.post('/permits', {'purpose': 'Same form'}, key);
    await api.post('/permit-types');

    expect(keys, [key, key, null]);
    expect(api.newIdempotencyKey(), isNot(key));
  });

  testWidgets('U1: a failed submission keeps a visible error on the form', (tester) async {
    final client = MockClient((request) async => throw http.ClientException('blocked'));
    final state = AppState(ApiClient(client: client))
      ..types = [PermitType.fromJson({'id': 1, 'code': 'FAC-USE', 'name': 'Facility Use Permit', 'validity_days': 30})];
    await tester.binding.setSurfaceSize(const Size(800, 1400));
    await tester.pumpWidget(ChangeNotifierProvider.value(
      value: state,
      child: const MaterialApp(home: PermitFormScreen()),
    ));

    await tester.tap(find.textContaining('Facility Use Permit'));
    await tester.enterText(find.bySemanticsLabel('Purpose'), 'Basketball clinic for first-year students');
    await tester.enterText(find.bySemanticsLabel('Location'), 'Gym A');
    await tester.tap(find.text('Choose'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('OK'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Submit for review'));
    await tester.pump(const Duration(seconds: 1));

    expect(find.textContaining('Cannot reach the server'), findsWidgets);

    // A SnackBar alone disappears after 4 seconds; the user may be looking elsewhere.
    for (var i = 0; i < 60; i++) {
      await tester.pump(const Duration(milliseconds: 200));
    }
    await tester.pumpAndSettle();
    expect(find.textContaining('Cannot reach the server'), findsOneWidget);
    expect(find.textContaining('Your entries are kept'), findsOneWidget);
    expect(find.text('Submit for review'), findsOneWidget); // the form stays open so the user can retry
    await tester.binding.setSurfaceSize(null);
  });
}
