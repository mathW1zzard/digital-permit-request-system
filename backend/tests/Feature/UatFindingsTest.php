<?php

namespace Tests\Feature;

use App\Enums\PermitStatus;
use App\Exceptions\InvalidTransition;
use App\Models\Permit;
use App\Models\PermitType;
use App\Models\User;
use App\Services\PermitWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Week 17: fixes for the findings of UAT round 1 (docs/week17/43-uat-report.md). */
class UatFindingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function as(string $email): static
    {
        $token = $this->postJson('/api/login', ['email' => $email, 'password' => 'test-only-password'])->json('data.token');
        $this->app['auth']->forgetGuards();

        return $this->withHeaders(['Authorization' => "Bearer {$token}", 'Accept' => 'application/json']);
    }

    private function submit(): Permit
    {
        $ref = $this->as('requester@permit.test')->postJson('/api/permits', [
            'permit_type_id' => PermitType::where('code', 'FAC-USE')->value('id'),
            'purpose' => 'Zumba session for senior citizens',
            'location' => 'Covered court',
            'requested_start' => now()->addDays(3)->toDateString(),
        ])->assertCreated()->json('data.reference');

        return Permit::where('reference', $ref)->firstOrFail();
    }

    private function reviewerEmail(Permit $permit): string
    {
        return User::findOrFail($permit->reviewer_id)->email;
    }

    public function test_f02_requester_reads_the_revision_note_and_f01_who_asked(): void
    {
        $permit = $this->submit();
        $reviewer = User::findOrFail($permit->reviewer_id);
        $this->as($reviewer->email)->postJson("/api/permits/{$permit->reference}/request-revision", ['comment' => 'Please give the exact start and end time.'])->assertOk();

        $events = $this->as('requester@permit.test')->getJson("/api/permits/{$permit->reference}")->assertOk()->json('data.events');
        $asked = collect($events)->firstWhere('type', 'revision_requested');

        $this->assertSame('Please give the exact start and end time.', $asked['note']);
        $this->assertSame($reviewer->name, $asked['by']);
        $this->assertNull(collect($events)->firstWhere('type', 'submitted')['note']); // other steps carry no note
    }

    public function test_f03_requester_reads_the_rejection_reason(): void
    {
        $permit = $this->submit();
        $this->as($this->reviewerEmail($permit))->postJson("/api/permits/{$permit->reference}/reject", ['reason' => 'Venue already booked that evening.'])->assertOk();

        $events = $this->as('requester@permit.test')->getJson("/api/permits/{$permit->reference}")->json('data.events');
        $this->assertSame('Venue already booked that evening.', collect($events)->firstWhere('type', 'rejected')['note']);
    }

    public function test_notes_stay_out_of_the_list_and_away_from_other_requesters(): void
    {
        $permit = $this->submit();
        $this->as($this->reviewerEmail($permit))->postJson("/api/permits/{$permit->reference}/reject", ['reason' => 'Venue already booked that evening.'])->assertOk();

        $list = $this->as('requester@permit.test')->getJson('/api/permits')->assertOk()->getContent();
        $this->assertStringNotContainsString('Venue already booked', $list);
        $this->as('bounce@invalid.test')->getJson("/api/permits/{$permit->reference}")->assertForbidden();
    }

    public function test_f04_losing_a_race_says_someone_else_acted_and_the_current_status(): void
    {
        $permit = $this->submit();
        $reviewer = User::findOrFail($permit->reviewer_id);
        $stale = $permit->fresh();
        // The other reviewer's endorsement lands between our read and our write.
        app(PermitWorkflow::class)->endorse($permit->fresh(), $reviewer);

        try {
            app(PermitWorkflow::class)->endorse($stale, $reviewer);
            $this->fail('expected InvalidTransition');
        } catch (InvalidTransition $e) {
            $this->assertTrue($e->race);
            $this->assertSame(PermitStatus::PendingApproval, $e->current);
            $this->assertSame('Someone else acted on this permit first. It is now pending approval.', $e->userMessage());
        }
    }

    public function test_f04_a_plain_wrong_state_keeps_its_message(): void
    {
        $permit = $this->submit();
        $this->as($this->reviewerEmail($permit))->postJson("/api/permits/{$permit->reference}/endorse")->assertOk();

        $this->as($this->reviewerEmail($permit))->postJson("/api/permits/{$permit->reference}/endorse")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'invalid_transition')
            ->assertJsonPath('error.message', 'This action is not available while the permit is pending approval.');
    }
}
