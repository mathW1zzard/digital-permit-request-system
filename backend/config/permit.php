<?php

return [
    'gateway' => [
        'url' => env('GATEWAY_URL', 'http://127.0.0.1:9000/send'),
        'api_key' => env('GATEWAY_API_KEY', ''),
        'timeout' => (int) env('GATEWAY_TIMEOUT_SECONDS', 3),
        'max_attempts' => (int) env('GATEWAY_MAX_ATTEMPTS', 4),
        'backoff_base' => (int) env('GATEWAY_BACKOFF_BASE_SECONDS', 5),
        'backoff_cap' => (int) env('GATEWAY_BACKOFF_CAP_SECONDS', 300),
    ],
];
