import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';

import 'package:permit_web/api_client.dart';
import 'package:permit_web/app_state.dart';
import 'package:permit_web/main.dart';

void main() {
  testWidgets('Sign-in validates the email and reports an unknown user', (tester) async {
    final client = MockClient((_) async => http.Response(
          '{"error":{"code":"unauthenticated","message":"Sign in to continue."}}',
          401,
        ));
    await tester.pumpWidget(ChangeNotifierProvider(
      create: (_) => AppState(ApiClient(client: client)),
      child: const PermitApp(),
    ));

    await tester.tap(find.text('Sign in'));
    await tester.pump();
    expect(find.text('Enter an email address'), findsOneWidget);

    await tester.enterText(find.bySemanticsLabel('Email'), 'nobody@permit.test');
    await tester.tap(find.text('Sign in'));
    await tester.pumpAndSettle();
    expect(find.text('No demo account uses that email.'), findsOneWidget);
  });
}
