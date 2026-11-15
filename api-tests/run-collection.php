<?php
// Runs project32.postman_collection.json without Postman, in the same order, with the same checks.
// It understands the check lines that build-collection.php writes; anything else is reported as "unsupported".
// Usage (repository root, API and proxy running, database freshly seeded):
//   php api-tests/run-collection.php [output-folder]
// The demo password is read from backend/.env and never printed; tokens are redacted from saved responses.
declare(strict_types=1);

$root = dirname(__DIR__);
$out = $argv[1] ?? $root.'/api-tests/results';
if (! is_dir($out)) {
    mkdir($out, 0777, true);
}
$env = [];
foreach (file("$root/backend/.env", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if ($line[0] !== '#' && str_contains($line, '=')) {
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v, " \"'");
    }
}
$collection = json_decode(file_get_contents(__DIR__.'/project32.postman_collection.json'), true, flags: JSON_THROW_ON_ERROR);
$day = fn (int $n) => gmdate('Y-m-d', time() + $n * 86400);
$vars = ['baseUrl' => getenv('BASE_URL') ?: 'https://localhost:8443/api', 'password' => $env['DEMO_PASSWORD'] ?? '',
    'yesterday' => $day(-1), 'today' => $day(0), 'tomorrow' => $day(1), 'plus366' => $day(366), 'runId' => (string) round(microtime(true) * 1000)];
foreach ($collection['variable'] ?? [] as $v) {
    $vars[$v['key']] = $v['value'];
}
$secrets = array_filter([$vars['password']]);
// By reference: variables saved by earlier requests (tokens, references) must be visible to later ones.
$sub = function (string $s) use (&$vars): string {
    return preg_replace_callback('/\{\{(\w+)\}\}/', fn ($m) => (string) ($vars[$m[1]] ?? $m[0]), $s);
};
$getPath = function ($data, string $chain) {
    preg_match_all('/\.(\w+)|\[(\d+)\]/', $chain, $parts, PREG_SET_ORDER);
    foreach ($parts as $p) {
        $key = ($p[2] ?? '') !== '' ? (int) $p[2] : $p[1];
        if (! is_array($data) || ! array_key_exists($key, $data)) {
            return [false, null];
        }
        $data = $data[$key];
    }

    return [true, $data];
};
$redact = function (string $text) use (&$secrets): string {
    $text = preg_replace('/"token":"[^"]+"/', '"token":"[redacted]"', $text);
    foreach ($secrets as $s) {
        $text = str_replace($s, '[redacted]', $text);
    }

    return $text;
};

$results = [];
$started = microtime(true);
foreach ($collection['item'] as $folder) {
    foreach ($folder['item'] as $item) {
        $req = $item['request'];
        $url = $sub($req['url']['raw']);
        $headers = [];
        foreach ($req['header'] as $h) {
            $headers[] = $h['key'].': '.$sub($h['value']);
        }
        $body = isset($req['body']['raw']) ? $sub($req['body']['raw']) : null;
        $respHeaders = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $req['method'], CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CAINFO => "$root/certs/dev-ca.pem", CURLOPT_TIMEOUT => 15,
            CURLOPT_HEADERFUNCTION => function ($c, $h) use (&$respHeaders) {
                if (str_contains($h, ':')) {
                    [$k, $v] = explode(':', $h, 2);
                    $respHeaders[strtolower(trim($k))] = trim($v);
                }

                return strlen($h);
            },
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $t0 = microtime(true);
        $text = curl_exec($ch);
        $ms = (int) round((microtime(true) - $t0) * 1000);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        $text = is_string($text) ? $text : '';
        $json = json_decode($text, true);

        $checks = [];
        foreach ($item['event'][0]['script']['exec'] as $line) {
            $name = preg_match('/^pm\.test\("((?:[^"\\\\]|\\\\.)*)"/', $line, $m) ? stripcslashes($m[1]) : $line;
            if (preg_match('/^pm\.collectionVariables\.set\("(\w+)", pm\.response\.json\(\)((?:\.\w+|\[\d+\])+)\);$/', $line, $m)) {
                [$found, $value] = $getPath($json, $m[2]);
                if ($found) {
                    $vars[$m[1]] = (string) $value;
                    if (str_starts_with($m[1], 'tok')) {
                        $secrets[] = (string) $value;
                    }
                }
                continue;
            }
            if (preg_match('/to\.have\.status\((\d+)\)/', $line, $m)) {
                $ok = $status === (int) $m[1];
                $detail = "got $status";
            } elseif (preg_match('/pm\.expect\(pm\.response\.json\(\)((?:\.\w+|\[\d+\])+)\)\.to\.eql\(pm\.collectionVariables\.get\("(\w+)"\)\)/', $line, $m)) {
                [, $v] = $getPath($json, $m[1]);
                $ok = (string) $v === ($vars[$m[2]] ?? null);
                $detail = 'got '.json_encode($v);
            } elseif (preg_match('/pm\.expect\(pm\.response\.json\(\)((?:\.\w+|\[\d+\])+)\)\.to\.eql\((.+)\)\);$/', $line, $m)) {
                [, $v] = $getPath($json, $m[1]);
                $ok = $v === json_decode($m[2], true);
                $detail = 'got '.json_encode($v);
            } elseif (preg_match('/pm\.expect\(pm\.response\.json\(\)((?:\.\w+|\[\d+\])+)\)\.to\.exist/', $line, $m)) {
                [$found, $v] = $getPath($json, $m[1]);
                $ok = $found && $v !== null;
                $detail = $found ? 'present' : 'missing';
            } elseif (preg_match('/headers\.get\("([^"]+)"\)\)\.to\.eql\("([^"]*)"\)/', $line, $m)) {
                $v = $respHeaders[strtolower($m[1])] ?? null;
                $ok = $v === $m[2];
                $detail = 'got '.json_encode($v);
            } elseif (preg_match('/headers\.get\("([^"]+)"\)\)\.to\.exist/', $line, $m)) {
                $ok = isset($respHeaders[strtolower($m[1])]);
                $detail = $ok ? 'present' : 'missing';
            } elseif (preg_match('/headers\.has\("([^"]+)"\)\)\.to\.be\.false/', $line, $m)) {
                $ok = ! isset($respHeaders[strtolower($m[1])]);
                $detail = $ok ? 'absent' : 'present: '.$respHeaders[strtolower($m[1])];
            } elseif (preg_match('/for \(const s of (\[.*\]\)) pm\.expect/', $line, $m)) {
                $words = json_decode(rtrim($m[1], ')'), true);
                $hits = array_values(array_filter($words, fn ($w) => str_contains($text, $w)));
                $ok = $hits === [];
                $detail = $ok ? 'clean' : 'found '.implode(', ', $hits);
            } elseif (preg_match('/pm\.response\.text\(\)\)\.to\.not\.include\("([^"]+)"\)/', $line, $m)) {
                $ok = ! str_contains($text, $m[1]);
                $detail = $ok ? 'absent' : 'present';
            } else {
                $ok = false;
                $detail = 'unsupported check';
            }
            $checks[] = ['check' => $name, 'pass' => $ok, 'detail' => $detail];
        }
        $pass = $checks !== [] && ! in_array(false, array_column($checks, 'pass'), true);
        $results[] = ['id' => strtok($item['name'], ' '), 'folder' => $folder['name'], 'name' => $item['name'], 'method' => $req['method'],
            'path' => str_replace($vars['baseUrl'], '', $url), 'status' => $status, 'ms' => $ms, 'pass' => $pass, 'checks' => $checks,
            'correlation_id' => $respHeaders['x-correlation-id'] ?? null, 'transport_error' => $error ?: null,
            'response' => $redact(mb_substr($text, 0, 600))];
    }
}

$passed = count(array_filter($results, fn ($r) => $r['pass']));
$summary = sprintf("Run %s UTC, %d requests, %d passed, %d failed, %.1f s, base %s\n", gmdate('Y-m-d H:i:s'), count($results), $passed, count($results) - $passed, microtime(true) - $started, $vars['baseUrl']);
$lines = [$summary];
foreach ($results as $r) {
    $failed = array_filter($r['checks'], fn ($c) => ! $c['pass']);
    $lines[] = sprintf("%-4s %-4s %-6s %-40s %3d %5dms  %s", $r['pass'] ? 'PASS' : 'FAIL', $r['id'], $r['method'], mb_strimwidth($r['path'], 0, 40, '…'), $r['status'], $r['ms'],
        $failed ? implode('; ', array_map(fn ($c) => "{$c['check']} ({$c['detail']})", $failed)) : '');
}
file_put_contents("$out/results.json", json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
file_put_contents("$out/summary.txt", implode("\n", $lines)."\n");
echo implode("\n", $lines), "\n";
exit($passed === count($results) ? 0 : 1);
