<?php

namespace Tests\Feature;

use App\Enums\PermitStatus;
use App\Models\OutboxNotification;
use App\Models\Permit;
use App\Models\PermitType;
use App\Models\User;
use App\Services\NotificationDispatcher;
use App\Services\PermitAutomation;
use App\Services\PermitWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config([
            'permit.gateway.url' => 'http://gateway.test/send',
            'permit.gateway.max_attempts' => 3,
            'permit.gateway.backoff_base' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function submit(string $as = 'requester@permit.test'): Permit
    {
        return app(PermitWorkflow::class)->submit($this->user($as), PermitType::where('code', 'FAC-USE')->firstOrFail(), [
            'purpose' => 'Basketball clinic for first-year students',
            'location' => 'Gym A',
            'requested_start' => now()->addDay()->toDateString(),
        ]);
    }

    private function issuedPermit(Carbon $validUntil): Permit
    {
        return Permit::create([
            'reference' => 'PRM-TEST-'.Str::upper(Str::random(5)),
            'requester_id' => $this->user('requester@permit.test')->id,
            'permit_type_id' => PermitType::where('code', 'FAC-USE')->value('id'),
            'purpose' => 'Basketball clinic for first-year students',
            'location' => 'Gym A',
            'requested_start' => now()->toDateString(),
            'status' => PermitStatus::Issued,
            'stage_entered_at' => now(),
            'valid_from' => now()->subDays(20),
            'valid_until' => $validUntil,
        ]);
    }

    private function dispatchOnce(): array
    {
        Carbon::setTestNow(now()->addMinutes(1)); // move past any backoff or lease

        return app(NotificationDispatcher::class)->dispatchDue();
    }

    public function test_submission_goes_to_the_least_loaded_reviewer(): void
    {
        $first = $this->submit();
        $second = $this->submit();

        $this->assertSame(PermitStatus::UnderReview, $first->fresh()->status);
        $this->assertNotSame($first->fresh()->reviewer_id, $second->fresh()->reviewer_id);
        $this->assertSame(2, OutboxNotification::where('kind', 'review_assigned')->count());
    }

    public function test_missing_reviewer_is_escalated_then_recovered(): void
    {
        User::where('role', 'reviewer')->update(['role' => 'on_leave']);
        $permit = $this->submit();
        $this->assertTrue($permit->fresh()->needs_manual_action);
        $this->assertSame('no_reviewer_available', $permit->fresh()->manual_action_reason);

        User::where('role', 'on_leave')->update(['role' => 'reviewer']);
        $result = app(PermitAutomation::class)->run();

        $this->assertSame(1, $result['rerouted']);
        $this->assertFalse($permit->fresh()->needs_manual_action);
        $this->assertNotNull($permit->fresh()->reviewer_id);
    }

    public function test_transient_failure_is_retried_and_recovers(): void
    {
        Http::fake(['gateway.test/*' => Http::sequence()->push(['error' => 'busy'], 503)->push(['status' => 'accepted'], 200)]);
        $this->submit();

        $this->assertSame(1, $this->dispatchOnce()['retry_scheduled']);
        $this->assertSame(1, $this->dispatchOnce()['sent']);

        $row = OutboxNotification::firstOrFail();
        $this->assertSame(OutboxNotification::SENT, $row->status);
        $this->assertSame(2, $row->attempts);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->header('Idempotency-Key')[0] === $row->dedupe_key);
    }

    public function test_permanent_failure_goes_to_a_person_without_retry(): void
    {
        Http::fake(['gateway.test/*' => Http::response(['error' => 'recipient_rejected'], 422)]);
        $this->submit();

        $this->assertSame(1, $this->dispatchOnce()['manual_action']);
        $this->dispatchOnce();
        $this->dispatchOnce();

        $row = OutboxNotification::firstOrFail();
        $this->assertSame(OutboxNotification::MANUAL, $row->status);
        $this->assertSame('permanent', $row->last_error_class);
        Http::assertSentCount(1);
    }

    public function test_retries_stop_at_the_limit(): void
    {
        Http::fake(['gateway.test/*' => Http::response(['error' => 'down'], 503)]);
        $this->submit();

        foreach (range(1, 5) as $ignored) {
            $this->dispatchOnce();
        }

        $row = OutboxNotification::firstOrFail();
        $this->assertSame(OutboxNotification::MANUAL, $row->status);
        $this->assertSame('transient_exhausted', $row->last_error_class);
        $this->assertSame(3, $row->attempts);
        Http::assertSentCount(3);
    }

    public function test_expiry_is_automatic_and_idempotent(): void
    {
        $permit = $this->issuedPermit(now()->subMinute());

        $this->assertSame(1, app(PermitAutomation::class)->run()['expired']);
        $this->assertSame(0, app(PermitAutomation::class)->run()['expired']);
        $this->assertSame(PermitStatus::Expired, $permit->fresh()->status);
        $this->assertSame(1, OutboxNotification::where('kind', 'permit_expired')->count());
    }

    public function test_expiry_reminder_is_queued_once(): void
    {
        $this->issuedPermit(now()->addDays(6)); // FAC-USE reminds 7 days before

        $this->assertSame(1, app(PermitAutomation::class)->run()['reminders']);
        $this->assertSame(0, app(PermitAutomation::class)->run()['reminders']);
    }

    public function test_stalled_review_is_escalated_once(): void
    {
        $permit = $this->submit();
        Carbon::setTestNow(now()->addHours(25)); // FAC-USE review SLA is 24 h

        $this->assertSame(1, app(PermitAutomation::class)->run()['escalated']);
        $this->assertSame(0, app(PermitAutomation::class)->run()['escalated']);
        $this->assertSame('review_sla_breached', $permit->fresh()->manual_action_reason);
        $this->assertSame(1, OutboxNotification::where('kind', 'manual_action_needed')->count());
    }
}
