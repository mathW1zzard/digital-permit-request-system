import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:http/http.dart' as http;

import 'config.dart';

enum ErrorKind { validation, unauthenticated, forbidden, notFound, conflict, server, network }

class ApiError implements Exception {
  ApiError(this.kind, this.message, {this.fieldErrors = const {}, this.correlationId});

  final ErrorKind kind;
  final String message;
  final Map<String, String> fieldErrors;
  final String? correlationId;

  @override
  String toString() => message;
}

class ApiClient {
  ApiClient({http.Client? client}) : _http = client ?? http.Client();

  final http.Client _http;
  final Random _random = Random();

  /// Week 13 demo identity, sent as X-Demo-User. Week 14 replaces it with a token.
  String? userEmail;

  Future<dynamic> get(String path) => _send('GET', path);

  Future<dynamic> post(String path, [Map<String, dynamic>? body]) => _send('POST', path, body);

  Future<dynamic> _send(String method, String path, [Map<String, dynamic>? body]) async {
    final uri = Uri.parse('$apiBaseUrl$path');
    final headers = {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'X-Correlation-Id': 'web-${DateTime.now().millisecondsSinceEpoch}-${_random.nextInt(1 << 30)}',
      'X-Demo-User': ?userEmail,
    };

    http.Response response;
    try {
      final request = method == 'GET'
          ? _http.get(uri, headers: headers)
          : _http.post(uri, headers: headers, body: jsonEncode(body ?? {}));
      response = await request.timeout(const Duration(seconds: 10));
    } on TimeoutException {
      throw ApiError(ErrorKind.network, 'The server took too long to answer. Check your connection and try again.');
    } on http.ClientException {
      throw ApiError(ErrorKind.network, 'Cannot reach the server. Check your connection and try again.');
    }

    dynamic decoded;
    try {
      decoded = response.body.isEmpty ? null : jsonDecode(response.body);
    } on FormatException {
      decoded = null; // e.g. an HTML error page from a proxy
    }

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return decoded;
    }
    throw _toError(response.statusCode, decoded);
  }

  ApiError _toError(int status, dynamic body) {
    final Map error = (body is Map && body['error'] is Map) ? body['error'] as Map : const {};
    final message = (error['message'] as String?) ?? 'Unexpected response from the server ($status).';
    final fields = <String, String>{};
    final details = error['details'];
    if (details is Map) {
      details.forEach((key, value) {
        if (value is List && value.isNotEmpty) fields['$key'] = '${value.first}';
      });
    }
    final kind = switch (status) {
      401 => ErrorKind.unauthenticated,
      403 => ErrorKind.forbidden,
      404 => ErrorKind.notFound,
      409 => ErrorKind.conflict,
      422 => ErrorKind.validation,
      _ => ErrorKind.server,
    };
    return ApiError(kind, message, fieldErrors: fields, correlationId: error['correlation_id'] as String?);
  }
}
