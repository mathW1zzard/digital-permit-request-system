<?php

namespace Tests\Feature;

use App\Http\Controllers\HealthController;
use App\Models\OutboxNotification;
use App\Models\Permit;
use App\Models\PermitType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Week 16: readiness signal (GET /api/health). */
class HealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function freshHeartbeats(): void
    {
        HealthController::beat('automation');
        HealthController::beat('dispatch');
    }

    public function test_ready_when_database_scheduler_and_outbox_are_healthy(): void
    {
        $this->freshHeartbeats();

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('checks.database.status', 'ok')
            ->assertJsonPath('checks.scheduler_automation.status', 'ok')
            ->assertJsonPath('checks.outbox.status', 'ok')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_the_scheduled_commands_write_the_heartbeats(): void
    {
        Cache::flush();
        Artisan::call('notifications:dispatch');
        Artisan::call('permits:run-automation');

        $this->getJson('/api/health')->assertJsonPath('checks.scheduler_dispatch.status', 'ok')
            ->assertJsonPath('checks.scheduler_automation.status', 'ok');
    }

    public function test_demo_runs_with_a_pretend_time_do_not_count_as_heartbeats(): void
    {
        Cache::flush();
        Artisan::call('permits:run-automation', ['--now' => '2026-12-01 09:00']);

        $this->getJson('/api/health')->assertJsonPath('checks.scheduler_automation.status', 'stale');
    }

    public function test_a_stopped_scheduler_is_degraded_but_still_serving(): void
    {
        $this->freshHeartbeats();
        Carbon::setTestNow(now()->addMinutes(11));

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.scheduler_dispatch.status', 'stale');
        Carbon::setTestNow();
    }

    public function test_an_overdue_outbox_is_degraded(): void
    {
        $this->freshHeartbeats();
        $permit = $this->makePermit();
        OutboxNotification::query()->update(['status' => OutboxNotification::SENT]);
        OutboxNotification::create([
            'permit_id' => $permit->id, 'recipient_id' => $permit->requester_id, 'kind' => 'review_assigned',
            'dedupe_key' => 'health-test-1', 'status' => OutboxNotification::PENDING,
            'attempts' => 2, 'next_attempt_at' => now()->subMinutes(30),
        ]);

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.outbox.status', 'backlog')
            ->assertJsonPath('checks.outbox.overdue', 1);
    }

    public function test_database_down_is_not_ready_503_and_reveals_nothing(): void
    {
        // Point the app at a database file that does not exist, then put the default back
        // before RefreshDatabase rolls back its transaction on the real connection.
        $default = config('database.default');
        config(['database.connections.unavailable' => [
            'driver' => 'sqlite', 'database' => storage_path('framework/testing/no-such-dir/db.sqlite'), 'prefix' => '',
        ], 'database.default' => 'unavailable']);

        try {
            $response = $this->getJson('/api/health');
        } finally {
            config(['database.default' => $default]);
        }

        $response->assertStatus(503)
            ->assertJsonPath('status', 'not_ready')
            ->assertJsonPath('checks.database.status', 'fail');

        $body = $response->getContent();
        foreach (['sqlite', 'SQLSTATE', 'storage', 'PHP', 'Laravel'] as $leak) {
            $this->assertStringNotContainsString($leak, $body);
        }
    }

    private function makePermit(): Permit
    {
        $token = $this->postJson('/api/login', ['email' => 'requester@permit.test', 'password' => 'test-only-password'])->json('data.token');
        $this->app['auth']->forgetGuards();
        $reference = $this->withHeaders(['Authorization' => "Bearer {$token}"])->postJson('/api/permits', [
            'permit_type_id' => PermitType::where('code', 'FAC-USE')->value('id'),
            'purpose' => 'Basketball clinic for first-year students',
            'location' => 'Gym A',
            'requested_start' => now()->addDay()->toDateString(),
        ])->assertCreated()->json('data.reference');

        return Permit::where('reference', $reference)->firstOrFail();
    }
}
