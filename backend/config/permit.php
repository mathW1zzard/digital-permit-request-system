<?php

return [
    // Week 14: shared password for the fictional demo accounts (hashed by the seeder).
    'demo_password' => env('DEMO_PASSWORD', ''),

    // Week 14: refuse API calls that did not arrive over HTTPS (set false only in tests).
    'require_https' => (bool) env('REQUIRE_HTTPS', true),

    // Week 15: requests per minute for each signed-in user (sign-in has its own stricter limit).
    'api_rate_per_minute' => (int) env('API_RATE_PER_MINUTE', 60),

    // Week 15: the furthest ahead a permit may start; the Flutter form uses the same limit.
    'max_days_ahead' => 365,

    // Week 15: largest request body accepted, in KB (defect D7). A permit request is under 2 KB.
    'max_body_kb' => (int) env('MAX_BODY_KB', 1024),

    // Week 16: readiness check (GET /api/health). The scheduler runs dispatch every minute and
    // automation every 5 minutes, so a heartbeat older than 10 minutes means it has stopped.
    'health' => [
        'heartbeat_max_age_seconds' => (int) env('HEALTH_HEARTBEAT_MAX_AGE', 600),
        'outbox_overdue_seconds' => (int) env('HEALTH_OUTBOX_OVERDUE', 600),
    ],

    'gateway' => [
        'url' => env('GATEWAY_URL', 'https://127.0.0.1:9443/send'),
        'api_key' => env('GATEWAY_API_KEY', ''),
        // Certificate authority that signed the gateway's TLS certificate. Empty means the system CA store.
        'ca_file' => env('GATEWAY_CA_FILE', ''),
        'timeout' => (int) env('GATEWAY_TIMEOUT_SECONDS', 3),
        'max_attempts' => (int) env('GATEWAY_MAX_ATTEMPTS', 4),
        // Week 16: notifications sent per dispatch run (one run a minute). 50 a minute fell behind the
        // W2 peak, which created about 180 a minute.
        'dispatch_batch' => (int) env('GATEWAY_DISPATCH_BATCH', 50),
        'backoff_base' => (int) env('GATEWAY_BACKOFF_BASE_SECONDS', 5),
        'backoff_cap' => (int) env('GATEWAY_BACKOFF_CAP_SECONDS', 300),
        // Week 14: key for the encrypted message payload (XChaCha20-Poly1305), 32 bytes, base64.
        'payload_key_id' => env('GATEWAY_PAYLOAD_KEY_ID', 'k1'),
        'payload_key' => env('GATEWAY_PAYLOAD_KEY', ''),
    ],
];
