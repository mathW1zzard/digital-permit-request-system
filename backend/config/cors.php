<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST'],
    'allowed_origins' => explode(',', env('FRONTEND_ORIGIN', 'http://localhost:5000')),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Content-Type', 'X-Demo-User', 'X-Correlation-Id'],
    'exposed_headers' => ['X-Correlation-Id'],
    'max_age' => 600,
    'supports_credentials' => false,
];
