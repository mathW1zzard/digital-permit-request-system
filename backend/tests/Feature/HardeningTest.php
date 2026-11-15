<?php

namespace Tests\Feature;

use App\Models\Permit;
use App\Models\PermitType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Week 15: one test per hardening fix (D1-D7), so each fix stays fixed. */
class HardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function asRequester(): static
    {
        $token = $this->postJson('/api/login', ['email' => 'requester@permit.test', 'password' => 'test-only-password'])->json('data.token');
        $this->app['auth']->forgetGuards();

        return $this->withHeaders(['Authorization' => "Bearer {$token}", 'Accept' => 'application/json']);
    }

    private function body(array $changes = []): array
    {
        return array_merge([
            'permit_type_id' => PermitType::where('code', 'FAC-USE')->value('id'),
            'purpose' => 'Basketball clinic for first-year students',
            'location' => 'Gym A',
            'requested_start' => now()->addDay()->toDateString(),
        ], $changes);
    }

    public function test_d1_start_date_is_limited_to_365_days_like_the_form(): void
    {
        $this->asRequester()->postJson('/api/permits', $this->body(['requested_start' => now()->addDays(365)->toDateString()]))->assertCreated();
        $this->asRequester()->postJson('/api/permits', $this->body(['requested_start' => now()->addDays(366)->toDateString()]))
            ->assertStatus(422)->assertJsonStructure(['error' => ['details' => ['requested_start']]]);
    }

    public function test_d2_unknown_paths_and_methods_still_get_a_correlation_id(): void
    {
        $this->getJson('/api/no-such-endpoint')->assertStatus(404)->assertJsonStructure(['error' => ['correlation_id']])->assertHeader('X-Correlation-Id');
        $this->deleteJson('/api/permit-types')->assertStatus(405)->assertJsonStructure(['error' => ['correlation_id']])->assertHeader('Allow');
    }

    public function test_d3_resending_a_submission_returns_the_same_permit(): void
    {
        $first = $this->asRequester()->withHeader('Idempotency-Key', 'form-abc-1')->postJson('/api/permits', $this->body())->assertCreated();
        $again = $this->asRequester()->withHeader('Idempotency-Key', 'form-abc-1')->postJson('/api/permits', $this->body())->assertOk()->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame($first->json('data.reference'), $again->json('data.reference'));
        $this->assertSame(1, Permit::count());

        $this->asRequester()->withHeader('Idempotency-Key', 'form-abc-1')->postJson('/api/permits', $this->body(['location' => 'Gym B']))
            ->assertStatus(422)->assertJsonPath('error.code', 'idempotency_key_reused');
        $this->assertSame(1, Permit::count());
    }

    public function test_d4_d5_security_headers_and_no_version_header(): void
    {
        $response = $this->asRequester()->getJson('/api/me')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Content-Security-Policy');
        $this->assertFalse($response->headers->has('X-Powered-By'));
        $this->getJson('/api/no-such-endpoint')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_d6_signed_in_calls_are_rate_limited_with_retry_after(): void
    {
        config(['permit.api_rate_per_minute' => 3]);
        $client = $this->asRequester();
        foreach (range(1, 3) as $ignored) {
            $client->getJson('/api/me')->assertOk()->assertHeader('X-RateLimit-Limit', '3');
        }
        $client->getJson('/api/me')->assertStatus(429)->assertJsonPath('error.code', 'http_429')->assertHeader('Retry-After');
    }
    public function test_d7_oversized_bodies_are_refused_with_413_and_nothing_is_saved(): void
    {
        config(["permit.max_body_kb" => 4]);
        $before = Permit::count();

        $this->asRequester()->postJson("/api/permits", $this->body(["purpose" => str_repeat("x", 5000)]))
            ->assertStatus(413)
            ->assertJsonPath("error.code", "http_413")
            ->assertJsonStructure(["error" => ["correlation_id"]]);

        $this->assertSame($before, Permit::count());
        // A normal request under the limit still works.
        $this->asRequester()->postJson("/api/permits", $this->body())->assertCreated();
    }
}
