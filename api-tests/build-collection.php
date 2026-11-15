<?php
// Builds project32.postman_collection.json (Postman v2.1) from the case list below.
// The same JSON file is what Postman imports and what run-collection.php executes,
// so both always run identical requests and checks.
// Run from the repository root: php api-tests/build-collection.php
declare(strict_types=1);

const LEAK_WORDS = ['SQLSTATE', 'vendor/', 'Stack trace', '.php:', 'Illuminate\\\\'];

$validBody = ['permit_type_id' => 1, 'purpose' => 'Basketball clinic for first-year students', 'location' => 'Gym A', 'requested_start' => '{{tomorrow}}'];
$with = fn (array $changes) => array_merge($validBody, $changes);
$login = fn (string $email) => ['email' => $email, 'password' => '{{password}}'];
$noLeak = ['noleak'];

// [id, folder, name, method, path, token var or null, body (array|string|null), extra headers, checks, sets]
// checks: ['status', n] | ['json', path, value] | ['exists', path] | ['header', name, value|null] | ['noheader', name] | ['noleak'] | ['samevar', path, var] | ['notvar', path, var]
$cases = [
    // ---- Authentication ----
    ['T01', 'Authentication', 'Requester signs in', 'POST', '/login', null, $login('requester@permit.test'), [], [['status', 200], ['exists', 'data.token'], ['exists', 'data.expires_at']], ['tokRequester' => 'data.token']],
    ['T02', 'Authentication', 'Reviewer 1 signs in', 'POST', '/login', null, $login('reviewer1@permit.test'), [], [['status', 200]], ['tokReviewer1' => 'data.token']],
    ['T03', 'Authentication', 'Reviewer 2 signs in', 'POST', '/login', null, $login('reviewer2@permit.test'), [], [['status', 200]], ['tokReviewer2' => 'data.token']],
    ['T04', 'Authentication', 'Approver signs in', 'POST', '/login', null, $login('approver@permit.test'), [], [['status', 200]], ['tokApprover' => 'data.token']],
    ['T05', 'Authentication', 'Admin signs in', 'POST', '/login', null, $login('admin@permit.test'), [], [['status', 200]], ['tokAdmin' => 'data.token']],
    ['T06', 'Authentication', 'Inspector signs in', 'POST', '/login', null, $login('inspector@permit.test'), [], [['status', 200]], ['tokInspector' => 'data.token']],
    ['T07', 'Authentication', 'Second requester signs in', 'POST', '/login', null, $login('bounce@invalid.test'), [], [['status', 200]], ['tokRequester2' => 'data.token']],
    ['T08', 'Authentication', 'Wrong password is refused without saying which part was wrong', 'POST', '/login', null, ['email' => 'inspector@permit.test', 'password' => 'not-the-password'], [], [['status', 401], ['json', 'error.code', 'invalid_credentials']], []],
    ['T09', 'Authentication', 'Missing login fields', 'POST', '/login', null, [], [], [['status', 422], ['exists', 'error.details.email'], ['exists', 'error.details.password']], []],
    ['T10', 'Authentication', 'No token', 'GET', '/permits', null, null, [], [['status', 401], ['json', 'error.code', 'unauthenticated'], ['exists', 'error.correlation_id']], []],
    ['T11', 'Authentication', 'Made-up token', 'GET', '/permits', 'fakeToken', null, [], [['status', 401]], []],

    // ---- Contract ----
    ['T12', 'Contract', 'Permit types list', 'GET', '/permit-types', 'tokRequester', null, [], [['status', 200], ['exists', 'data.0.id'], ['exists', 'data.0.validity_days']], []],
    ['T13', 'Contract', 'Current user, without email', 'GET', '/me', 'tokRequester', null, [], [['status', 200], ['json', 'data.role', 'requester'], ['noleak'], ['nobody', '@']], []],
    ['T14', 'Contract', 'Submit a valid permit', 'POST', '/permits', 'tokRequester', $validBody, [], [['status', 201], ['json', 'data.status', 'under_review'], ['exists', 'data.reference'], ['exists', 'data.reviewer']], ['ref1' => 'data.reference']],
    ['T15', 'Contract', 'Read the permit with timeline and deliveries', 'GET', '/permits/{{ref1}}', 'tokRequester', null, [], [['status', 200], ['exists', 'data.events.0.type'], ['exists', 'data.notifications.0.status']], []],
    ['T16', 'Contract', 'Permit list', 'GET', '/permits', 'tokRequester', null, [], [['status', 200], ['exists', 'data.0.reference']], []],

    // ---- Validation and boundaries ----
    ['T17', 'Validation', 'Purpose 9 characters (below minimum)', 'POST', '/permits', 'tokRequester', $with(['purpose' => str_repeat('a', 9)]), [], [['status', 422], ['exists', 'error.details.purpose']], []],
    ['T18', 'Validation', 'Purpose 10 characters (minimum)', 'POST', '/permits', 'tokRequester', $with(['purpose' => str_repeat('b', 10)]), [], [['status', 201]], []],
    ['T19', 'Validation', 'Purpose 256 characters (above maximum)', 'POST', '/permits', 'tokRequester', $with(['purpose' => str_repeat('c', 256)]), [], [['status', 422], ['exists', 'error.details.purpose']], []],
    ['T20', 'Validation', 'Location 121 characters (above maximum)', 'POST', '/permits', 'tokRequester', $with(['location' => str_repeat('d', 121)]), [], [['status', 422], ['exists', 'error.details.location']], []],
    ['T21', 'Validation', 'Start date yesterday', 'POST', '/permits', 'tokRequester', $with(['requested_start' => '{{yesterday}}']), [], [['status', 422], ['exists', 'error.details.requested_start']], []],
    ['T22', 'Validation', 'Start date today (boundary, allowed)', 'POST', '/permits', 'tokRequester', $with(['requested_start' => '{{today}}']), [], [['status', 201]], []],
    ['T23', 'Validation', 'Start date 366 days ahead (the form allows at most 365)', 'POST', '/permits', 'tokRequester', $with(['requested_start' => '{{plus366}}']), [], [['status', 422], ['exists', 'error.details.requested_start']], []],
    ['T24', 'Validation', 'Permit type that does not exist', 'POST', '/permits', 'tokRequester', $with(['permit_type_id' => 999]), [], [['status', 422], ['exists', 'error.details.permit_type_id'], ['noleak']], []],
    ['T25', 'Validation', 'Wrong data type for permit type', 'POST', '/permits', 'tokRequester', $with(['permit_type_id' => 'abc']), [], [['status', 422], ['exists', 'error.details.permit_type_id']], []],
    ['T26', 'Validation', 'Malformed JSON body', 'POST', '/permits', 'tokRequester', '{"purpose": "unterminated', [], [['status', 422], ['noleak']], []],
    ['T27', 'Validation', 'Very large purpose (100,000 characters)', 'POST', '/permits', 'tokRequester', $with(['purpose' => str_repeat('e', 100000)]), [], [['status', 422], ['noleak']], []],

    // ---- Authorization ----
    ['T28', 'Authorization', 'Reviewer tries to submit a permit', 'POST', '/permits', 'tokReviewer1', $validBody, [], [['status', 403]], []],
    ['T29', 'Authorization', 'Requester tries to approve', 'POST', '/permits/{{ref1}}/approve', 'tokRequester', null, [], [['status', 403]], []],
    ['T30', 'Authorization', "Another requester reads someone else's permit", 'GET', '/permits/{{ref1}}', 'tokRequester2', null, [], [['status', 403]], []],
    ['T31', 'Authorization', 'Reviewer who is not assigned tries to endorse', 'POST', '/permits/{{ref1}}/endorse', 'tokReviewer2', null, [], [['status', 403]], []],
    ['T32', 'Authorization', 'Requester opens the admin list', 'GET', '/admin/exceptions', 'tokRequester', null, [], [['status', 403]], []],
    ['T33', 'Authorization', 'Requester calls the inspector check', 'GET', '/permits/{{ref1}}/verify', 'tokRequester', null, [], [['status', 403]], []],

    // ---- Not found and method ----
    ['T34', 'Not found', 'Unknown permit reference', 'GET', '/permits/PRM-NOPE', 'tokRequester', null, [], [['status', 404], ['json', 'error.code', 'http_404'], ['noleak']], []],
    ['T35', 'Not found', 'Unknown API path', 'GET', '/no-such-endpoint', 'tokRequester', null, [], [['status', 404], ['exists', 'error.correlation_id'], ['noleak']], []],
    ['T36', 'Not found', 'Method not allowed', 'DELETE', '/permits/{{ref1}}', 'tokRequester', null, [], [['status', 405], ['exists', 'error.correlation_id'], ['noleak']], []],

    // ---- State and duplicates ----
    ['T37', 'Duplicates', 'Assigned reviewer endorses', 'POST', '/permits/{{ref1}}/endorse', 'tokReviewer1', null, [], [['status', 200], ['json', 'data.status', 'pending_approval']], []],
    ['T38', 'Duplicates', 'Same endorsement again', 'POST', '/permits/{{ref1}}/endorse', 'tokReviewer1', null, [], [['status', 409], ['json', 'error.code', 'invalid_transition']], []],
    ['T39', 'Duplicates', 'Submit with an Idempotency-Key', 'POST', '/permits', 'tokRequester', $with(['purpose' => 'Idempotency check {{runId}}']), ['Idempotency-Key' => 'submit-{{runId}}'], [['status', 201]], ['dupRef' => 'data.reference']],
    ['T40', 'Duplicates', 'Same submission resent (double click or retry)', 'POST', '/permits', 'tokRequester', $with(['purpose' => 'Idempotency check {{runId}}']), ['Idempotency-Key' => 'submit-{{runId}}'], [['status', 200], ['samevar', 'data.reference', 'dupRef']], []],
    ['T41', 'Duplicates', 'Same key reused for a different submission', 'POST', '/permits', 'tokRequester', $with(['purpose' => 'A different permit {{runId}}']), ['Idempotency-Key' => 'submit-{{runId}}'], [['status', 422], ['json', 'error.code', 'idempotency_key_reused']], []],

    // ---- Headers and information leakage ----
    ['T42', 'Hardening', 'Security headers on API responses', 'GET', '/me', 'tokRequester', null, [], [['status', 200], ['header', 'X-Content-Type-Options', 'nosniff'], ['header', 'X-Frame-Options', 'DENY'], ['header', 'Referrer-Policy', 'no-referrer'], ['header', 'Content-Security-Policy', null]], []],
    ['T43', 'Hardening', 'No server version disclosed', 'GET', '/me', 'tokRequester', null, [], [['status', 200], ['noheader', 'X-Powered-By']], []],
    ['T44', 'Hardening', 'Error responses carry a correlation ID and no internals', 'POST', '/permits/PRM-NOPE/approve', 'tokApprover', null, [], [['status', 404], ['exists', 'error.correlation_id'], ['noleak']], []],
    ['T45', 'Hardening', 'Rate-limit headers on authenticated calls', 'GET', '/me', 'tokRequester', null, [], [['status', 200], ['header', 'X-RateLimit-Limit', null]], []],

    // ---- Recovery path ----
    ['T46', 'Recovery', 'Approver issues the endorsed permit', 'POST', '/permits/{{ref1}}/approve', 'tokApprover', null, [], [['status', 200], ['json', 'data.status', 'issued']], []],
    ['T47', 'Recovery', 'Inspector verifies it', 'GET', '/permits/{{ref1}}/verify', 'tokInspector', null, [], [['status', 200], ['json', 'data.valid', true]], []],
    ['T48', 'Recovery', 'Admin exception list loads', 'GET', '/admin/exceptions', 'tokAdmin', null, [], [['status', 200], ['exists', 'data.notifications'], ['exists', 'data.permits']], []],
];

// ---------- emit Postman v2.1 ----------
$js = fn (string $s) => json_encode($s, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$jsPath = fn (string $path) => 'pm.response.json()'.implode('', array_map(fn ($p) => ctype_digit($p) ? "[{$p}]" : ".{$p}", explode('.', $path)));
$folders = [];
foreach ($cases as [$id, $folder, $name, $method, $path, $tokenVar, $body, $headers, $checks, $sets]) {
    $exec = [];
    foreach ($checks as $c) {
        $exec[] = match ($c[0]) {
            'status' => "pm.test(\"status {$c[1]}\", () => pm.response.to.have.status({$c[1]}));",
            'json' => 'pm.test('.$js("{$c[1]} is ".json_encode($c[2])).', () => pm.expect('.$jsPath($c[1]).').to.eql('.json_encode($c[2]).'));',
            'exists' => 'pm.test('.$js("has {$c[1]}").', () => pm.expect('.$jsPath($c[1]).').to.exist);',
            'header' => $c[2] === null
                ? 'pm.test('.$js("header {$c[1]} present").', () => pm.expect(pm.response.headers.get('.$js($c[1]).')).to.exist);'
                : 'pm.test('.$js("header {$c[1]} is {$c[2]}").', () => pm.expect(pm.response.headers.get('.$js($c[1]).')).to.eql('.$js($c[2]).'));',
            'noheader' => 'pm.test('.$js("no {$c[1]} header").', () => pm.expect(pm.response.headers.has('.$js($c[1]).')).to.be.false);',
            'noleak' => 'pm.test("no internals leaked", () => { for (const s of '.json_encode(LEAK_WORDS).') pm.expect(pm.response.text()).to.not.include(s); });',
            'nobody' => 'pm.test('.$js("body does not contain {$c[1]}").', () => pm.expect(pm.response.text()).to.not.include('.$js($c[1]).'));',
            'samevar' => 'pm.test('.$js("{$c[1]} equals {$c[2]}").', () => pm.expect('.$jsPath($c[1]).').to.eql(pm.collectionVariables.get('.$js($c[2]).')));',
        };
    }
    foreach ($sets as $var => $jsonPath) {
        $exec[] = "pm.collectionVariables.set(\"{$var}\", {$jsPath($jsonPath)});";
    }
    $hdr = [['key' => 'Accept', 'value' => 'application/json'], ['key' => 'Content-Type', 'value' => 'application/json']];
    foreach ($headers as $k => $v) {
        $hdr[] = ['key' => $k, 'value' => $v];
    }
    if ($tokenVar !== null) {
        $hdr[] = ['key' => 'Authorization', 'value' => "Bearer {{{$tokenVar}}}"];
    }
    $request = ['method' => $method, 'header' => $hdr, 'url' => ['raw' => '{{baseUrl}}'.$path, 'host' => ['{{baseUrl}}'], 'path' => array_values(array_filter(explode('/', $path)))]];
    if ($body !== null) {
        $raw = is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $request['body'] = ['mode' => 'raw', 'raw' => $raw, 'options' => ['raw' => ['language' => 'json']]];
    }
    $folders[$folder][] = ['name' => "{$id} {$name}", 'event' => [['listen' => 'test', 'script' => ['type' => 'text/javascript', 'exec' => $exec]]], 'request' => $request];
}

$prerequest = [
    '// Dates in UTC, the same "today" the API uses; runId makes each run\'s Idempotency-Key unique.',
    'const day = n => new Date(Date.now() + n * 864e5).toISOString().slice(0, 10);',
    'pm.collectionVariables.set("yesterday", day(-1));',
    'pm.collectionVariables.set("today", day(0));',
    'pm.collectionVariables.set("tomorrow", day(1));',
    'pm.collectionVariables.set("plus366", day(366));',
    'if (pm.info.requestName.startsWith("T01")) pm.collectionVariables.set("runId", String(Date.now()));',
];
$collection = [
    'info' => [
        'name' => 'Project 32 - Digital Permit Request System API (Week 15)',
        'description' => "Integration test collection for Week 15. Set baseUrl and password in the environment (see project32.postman_environment.example.json), run the folders in order against a freshly seeded database. The same file is executed by api-tests/run-collection.php.",
        'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
    ],
    'event' => [['listen' => 'prerequest', 'script' => ['type' => 'text/javascript', 'exec' => $prerequest]]],
    'variable' => [['key' => 'fakeToken', 'value' => '999|this-token-was-never-issued']],
    'item' => array_map(fn ($name, $items) => ['name' => $name, 'item' => $items], array_keys($folders), $folders),
];
file_put_contents(__DIR__.'/project32.postman_collection.json', json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
file_put_contents(__DIR__.'/project32.postman_environment.example.json', json_encode([
    'name' => 'Project 32 local (copy, then set password)',
    'values' => [
        ['key' => 'baseUrl', 'value' => 'https://localhost:8443/api', 'enabled' => true],
        ['key' => 'password', 'value' => 'DEMO_PASSWORD from backend/.env', 'type' => 'secret', 'enabled' => true],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
echo 'Wrote '.count($cases)." requests in ".count($folders)." folders.\n";
