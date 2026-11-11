<?php
// Starts the mock gateway with the keys from backend/.env, so they never have to be typed or shown.
// Run from anywhere, in any terminal (CMD, PowerShell, Git Bash):  php mock-gateway/start.php
// Local development only.
declare(strict_types=1);

$envFile = __DIR__.'/../backend/.env';
if (! is_file($envFile)) {
    exit("backend/.env not found. Create it first (see README).\n");
}

$env = [];
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if ($line[0] === '#' || ! str_contains($line, '=')) {
        continue;
    }
    [$name, $value] = explode('=', $line, 2); // split at the first "=" only: base64 keys end with "="
    $env[trim($name)] = trim($value, " \t\"'");
}

foreach (['GATEWAY_API_KEY', 'GATEWAY_PAYLOAD_KEY_ID', 'GATEWAY_PAYLOAD_KEY'] as $required) {
    if (($env[$required] ?? '') === '') {
        exit("{$required} is missing in backend/.env.\n");
    }
}

putenv('GATEWAY_API_KEY='.$env['GATEWAY_API_KEY']);
putenv('GATEWAY_PAYLOAD_KEYS='.$env['GATEWAY_PAYLOAD_KEY_ID'].':'.$env['GATEWAY_PAYLOAD_KEY']);

echo "Mock gateway on http://127.0.0.1:9000 (HTTPS through the proxy at https://127.0.0.1:9443). Ctrl+C to stop.\n";
$router = escapeshellarg(__DIR__.'/router.php');
passthru(escapeshellarg(PHP_BINARY)." -S 127.0.0.1:9000 {$router}", $exit);
exit($exit);
