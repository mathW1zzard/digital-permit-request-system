<?php

namespace App\Console\Commands;

use App\Support\SecureEnvelope;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Week 14 verification: sends one genuine message and five altered ones to the mock gateway
 * and prints expected versus actual results. Uses a fictional recipient only.
 */
class GatewayTamperTest extends Command
{
    protected $signature = 'gateway:tamper-test';

    protected $description = 'Send genuine, altered, wrong-key, stale and replayed messages to the gateway (demo only)';

    public function handle(): int
    {
        $cfg = config('permit.gateway');
        $context = ['idempotency_key' => 'tamper-test:'.Str::lower(Str::random(8)), 'reference' => 'PRM-TAMPER-TEST', 'template' => 'review_assigned'];
        $payload = ['to' => 'tamper-test@permit.test'];
        $seal = fn (array $ctx, ?string $key = null, ?int $ts = null) => SecureEnvelope::seal($payload, $ctx, $cfg['payload_key_id'], $key ?? $cfg['payload_key'], $ts);

        $genuine = $seal($context);
        $flipped = $seal($context);
        $flipped['ct'] = $this->flipFirstCharacter($flipped['ct']);

        $cases = [
            ['genuine message', $context, $genuine, '200 accepted'],
            ['replay of the genuine message', $context, $genuine, '409 replayed'],
            ['altered ciphertext', $context + ['idempotency_key' => $context['idempotency_key'].'-b'], $flipped, '400 invalid_envelope'],
            ['altered reference (outside the ciphertext)', ['reference' => 'PRM-SOMEONE-ELSE'] + $context, $seal($context), '400 invalid_envelope'],
            ['sealed with the wrong key', $context, $seal($context, base64_encode(random_bytes(32))), '400 invalid_envelope'],
            ['timestamp 10 minutes old', $context, $seal($context, null, time() - 600), '400 stale_timestamp'],
        ];

        $rows = [];
        foreach ($cases as [$name, $ctx, $envelope, $expected]) {
            $actual = $this->send($cfg, $ctx, $envelope);
            $rows[] = [$name, $expected, $actual, str_starts_with($actual, substr($expected, 0, 3)) ? 'PASS' : 'FAIL'];
        }
        $this->table(['case', 'expected', 'actual', 'result'], $rows);

        return in_array('FAIL', array_column($rows, 3), true) ? self::FAILURE : self::SUCCESS;
    }

    private function send(array $cfg, array $ctx, array $envelope): string
    {
        try {
            $response = Http::withToken($cfg['api_key'])
                ->withOptions(['verify' => $cfg['ca_file'] !== '' ? base_path($cfg['ca_file']) : true])
                ->withHeaders(['Idempotency-Key' => $ctx['idempotency_key']])
                ->acceptJson()
                ->timeout($cfg['timeout'])
                ->post($cfg['url'], ['template' => $ctx['template'], 'reference' => $ctx['reference'], 'envelope' => $envelope]);
        } catch (ConnectionException) {
            return 'no connection';
        }

        return $response->status().' '.($response->json('error') ?? $response->json('status') ?? '');
    }

    private function flipFirstCharacter(string $text): string
    {
        return ($text[0] === 'A' ? 'B' : 'A').substr($text, 1);
    }
}
