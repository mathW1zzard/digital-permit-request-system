<?php
// Mock notification gateway for Week 13 failure injection. Local use only, no dependencies.
// Start: php -S 127.0.0.1:9000 mock-gateway/router.php
declare(strict_types=1);

ignore_user_abort(true); // keep running when the caller times out, like a real server would

const STATE_FILE = __DIR__.'/state.json';
const LOG_FILE = __DIR__.'/received.jsonl';

function loadState(): array
{
    $saved = is_file(STATE_FILE) ? json_decode((string) file_get_contents(STATE_FILE), true) : null;

    return (is_array($saved) ? $saved : []) + ['mode' => 'ok', 'remaining' => 0, 'seconds' => 5, 'seen' => []];
}

function saveState(array $state): void
{
    file_put_contents(STATE_FILE, json_encode($state, JSON_PRETTY_PRINT), LOCK_EX);
}

function record(array $row): void
{
    file_put_contents(LOG_FILE, json_encode(['at' => gmdate('c')] + $row).PHP_EOL, FILE_APPEND | LOCK_EX);
}

function reply(int $status, array $body): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($body);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$state = loadState();

// GET /health - is the gateway up, and what failure mode is armed?
if ($method === 'GET' && $path === '/health') {
    reply(200, ['status' => 'up', 'mode' => $state['mode'], 'remaining' => $state['remaining']]);
}

// POST /control - arm a failure: {"mode":"fail_n","count":2} | {"mode":"slow_n","count":1,"seconds":5} | {"mode":"ok"}
if ($method === 'POST' && $path === '/control') {
    $in = json_decode((string) file_get_contents('php://input'), true) ?: [];
    $state['mode'] = in_array($in['mode'] ?? '', ['ok', 'fail_n', 'slow_n'], true) ? $in['mode'] : 'ok';
    $state['remaining'] = max(0, (int) ($in['count'] ?? 0));
    $state['seconds'] = max(1, (int) ($in['seconds'] ?? 5));
    saveState($state);
    record(['event' => 'control', 'mode' => $state['mode'], 'count' => $state['remaining']]);
    reply(200, ['mode' => $state['mode'], 'remaining' => $state['remaining']]);
}

// POST /send - deliver one notification
if ($method === 'POST' && $path === '/send') {
    $key = getenv('GATEWAY_API_KEY') ?: '';
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $idempotencyKey = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '';
    $in = json_decode((string) file_get_contents('php://input'), true) ?: [];
    $to = (string) ($in['to'] ?? '');
    $base = ['idempotency_key' => $idempotencyKey, 'template' => $in['template'] ?? null, 'reference' => $in['reference'] ?? null];

    if ($key !== '' && ! hash_equals("Bearer {$key}", $auth)) {
        record($base + ['result' => 401]);
        reply(401, ['error' => 'bad_credentials']);
    }
    if (! filter_var($to, FILTER_VALIDATE_EMAIL) || str_ends_with($to, '@invalid.test')) {
        record($base + ['result' => 422]);
        reply(422, ['error' => 'recipient_rejected']); // permanent: retrying will never help
    }
    if ($idempotencyKey !== '' && isset($state['seen'][$idempotencyKey])) {
        record($base + ['result' => 'duplicate_ignored']);
        reply(200, ['status' => 'duplicate_ignored']); // already delivered: do not deliver twice
    }
    if ($state['mode'] === 'fail_n' && $state['remaining'] > 0) {
        $state['remaining']--;
        saveState($state);
        record($base + ['result' => 503]);
        reply(503, ['error' => 'temporarily_unavailable']); // transient
    }
    if ($state['mode'] === 'slow_n' && $state['remaining'] > 0) {
        $state['remaining']--;
        saveState($state);
        sleep($state['seconds']); // longer than the client timeout: the caller gives up, but we still deliver
    }

    $state = loadState();
    $state['seen'][$idempotencyKey ?: bin2hex(random_bytes(8))] = true;
    saveState($state);
    record($base + ['result' => 'accepted']);
    reply(200, ['status' => 'accepted']);
}

reply(404, ['error' => 'not_found']);
