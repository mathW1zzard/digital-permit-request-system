<?php

namespace Tests\Feature;

use App\Models\OutboxNotification;
use App\Models\Permit;
use App\Models\PermitEvent;
use App\Models\PermitType;
use App\Models\User;
use App\Services\NotificationDispatcher;
use App\Services\PermitWorkflow;
use App\Support\SecureEnvelope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/** Week 14: login, token expiry, HTTPS, message protection and encryption at rest. */
class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'dGVzdC1vbmx5LWtleS1ub3QtYS1zZWNyZXQtMzJieXQ=';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function login(string $email = 'requester@permit.test', string $password = 'test-only-password')
    {
        return $this->postJson('/api/login', ['email' => $email, 'password' => $password]);
    }

    public function test_login_returns_an_expiring_token_and_passwords_are_hashed(): void
    {
        $this->login()->assertOk()->assertJsonStructure(['data' => ['token', 'expires_at', 'user' => ['id', 'name', 'role']]]);

        $stored = User::where('email', 'requester@permit.test')->value('password');
        $this->assertStringStartsWith('$2y$', $stored);
        $this->assertNotNull(DB::table('personal_access_tokens')->value('expires_at'));
        $this->assertNotSame(explode('|', $this->login()->json('data.token'))[1], DB::table('personal_access_tokens')->value('token'));
    }

    public function test_wrong_password_and_unknown_email_get_the_same_answer(): void
    {
        $wrong = $this->login(password: 'not-the-password')->assertStatus(401)->json('error');
        $unknown = $this->login(email: 'nobody@permit.test')->assertStatus(401)->json('error');

        $this->assertSame('invalid_credentials', $wrong['code']);
        $this->assertSame($wrong['message'], $unknown['message']);
    }

    public function test_repeated_failed_logins_are_throttled(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->login(password: 'not-the-password')->assertStatus(401);
        }
        $this->login()->assertStatus(429); // even the right password waits once the limit is hit
    }

    public function test_expired_altered_and_revoked_tokens_are_refused(): void
    {
        $token = $this->login()->json('data.token');
        $this->app['auth']->forgetGuards();

        $this->withToken($token.'x')->getJson('/api/me')->assertStatus(401);
        $this->app['auth']->forgetGuards();

        DB::table('personal_access_tokens')->update(['expires_at' => now()->subMinute()]);
        $this->withToken($token)->getJson('/api/me')->assertStatus(401)->assertJsonPath('error.code', 'unauthenticated');
        $this->app['auth']->forgetGuards();

        DB::table('personal_access_tokens')->update(['expires_at' => now()->addHour()]);
        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertStatus(401);
    }

    public function test_plain_http_is_refused_when_https_is_required(): void
    {
        config(['permit.require_https' => true]);

        $this->postJson('http://localhost/api/login', ['email' => 'requester@permit.test', 'password' => 'test-only-password'])
            ->assertStatus(426)->assertJsonPath('error.code', 'http_426');
        $this->postJson('https://localhost/api/login', ['email' => 'requester@permit.test', 'password' => 'test-only-password'])
            ->assertOk()->assertHeader('Strict-Transport-Security');
    }

    public function test_envelope_detects_altered_data_and_wrong_keys(): void
    {
        $context = ['idempotency_key' => 'k', 'reference' => 'PRM-1', 'template' => 'review_assigned'];
        $envelope = SecureEnvelope::seal(['to' => 'a@permit.test'], $context, 't1', self::KEY);

        $this->assertSame(['to' => 'a@permit.test'], SecureEnvelope::open($envelope, $context, self::KEY));
        $this->assertStringNotContainsString('a@permit.test', json_encode($envelope));

        $failures = 0;
        foreach ([
            [$envelope, ['reference' => 'PRM-2'] + $context, self::KEY],
            [['ct' => 'A'.substr($envelope['ct'], 1)] + $envelope, $context, self::KEY],
            [['ts' => $envelope['ts'] + 1] + $envelope, $context, self::KEY],
            [$envelope, $context, base64_encode(random_bytes(32))],
        ] as [$env, $ctx, $key]) {
            try {
                SecureEnvelope::open($env, $ctx, $key);
            } catch (RuntimeException) {
                $failures++;
            }
        }
        $this->assertSame(4, $failures);
    }

    public function test_gateway_receives_only_the_encrypted_recipient(): void
    {
        config(['permit.gateway.url' => 'https://gateway.test/send']);
        Http::fake(['gateway.test/*' => Http::response(['status' => 'accepted'], 200)]);
        app(PermitWorkflow::class)->submit(User::where('email', 'requester@permit.test')->first(), PermitType::first(), [
            'purpose' => 'Basketball clinic for first-year students', 'location' => 'Gym A', 'requested_start' => now()->addDay()->toDateString(),
        ]);

        app(NotificationDispatcher::class)->dispatchDue();

        Http::assertSent(function ($request) {
            $row = OutboxNotification::first();
            $body = $request->data();

            return ! array_key_exists('to', $body)
                && ! str_contains($request->body(), '@permit.test')
                && SecureEnvelope::open($body['envelope'], ['idempotency_key' => $row->dedupe_key, 'reference' => $body['reference'], 'template' => $body['template']], self::KEY)['to'] === $row->recipient->email;
        });
    }

    public function test_untrusted_gateway_certificate_goes_to_a_person_without_retry(): void
    {
        config(['permit.gateway.url' => 'https://gateway.test/send']);
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 60: SSL certificate problem: unable to get local issuer certificate'));
        app(PermitWorkflow::class)->submit(User::where('email', 'requester@permit.test')->first(), PermitType::first(), [
            'purpose' => 'Basketball clinic for first-year students', 'location' => 'Gym A', 'requested_start' => now()->addDay()->toDateString(),
        ]);

        $this->assertSame(1, app(NotificationDispatcher::class)->dispatchDue()['manual_action']);
        $this->assertSame('gateway_tls_failed', OutboxNotification::first()->last_error_detail);
    }

    public function test_free_text_is_encrypted_in_the_database(): void
    {
        $permit = app(PermitWorkflow::class)->submit(User::where('email', 'requester@permit.test')->first(), PermitType::first(), [
            'purpose' => 'Basketball clinic for first-year students', 'location' => 'Gym A',
            'details' => 'Contact the coach on 0917 000 0000', 'requested_start' => now()->addDay()->toDateString(),
        ]);
        app(PermitWorkflow::class)->requestRevision($permit->fresh(), $permit->fresh()->reviewer, 'Please attach the event schedule');

        $this->assertStringNotContainsString('0917', DB::table('permits')->value('details'));
        $this->assertSame('Contact the coach on 0917 000 0000', Permit::first()->details);
        $this->assertStringNotContainsString('event schedule', DB::table('permit_events')->whereNotNull('meta')->value('meta'));
        $this->assertSame('Please attach the event schedule', PermitEvent::where('type', 'revision_requested')->first()->meta['comment']);
    }
}
