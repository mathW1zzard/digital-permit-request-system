/// Set when running: --dart-define=API_BASE_URL=https://localhost:8443/api
/// Never put a secret here: everything in a web build can be read by anyone who opens it.
const String apiBaseUrl =
    String.fromEnvironment('API_BASE_URL', defaultValue: 'https://localhost:8443/api');
