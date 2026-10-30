/// Set when running: --dart-define=API_BASE_URL=http://localhost:8000/api
/// Never put a secret here: everything in a web build can be read by anyone who opens it.
const String apiBaseUrl =
    String.fromEnvironment('API_BASE_URL', defaultValue: 'http://localhost:8000/api');
