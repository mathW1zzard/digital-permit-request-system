<?php

namespace Tests\Feature;

use App\Models\Permit;
use App\Models\PermitType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermitApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function asUser(string $email): static
    {
        return $this->withHeaders(['X-Demo-User' => $email, 'Accept' => 'application/json']);
    }

    private function validBody(): array
    {
        return [
            'permit_type_id' => PermitType::where('code', 'FAC-USE')->value('id'),
            'purpose' => 'Basketball clinic for first-year students',
            'location' => 'Gym A',
            'requested_start' => now()->addDay()->toDateString(),
        ];
    }

    public function test_requester_can_submit(): void
    {
        $this->asUser('requester@permit.test')->postJson('/api/permits', $this->validBody())
            ->assertCreated()
            ->assertJsonPath('data.status', 'under_review');
    }

    public function test_invalid_input_is_refused_with_field_errors(): void
    {
        $this->asUser('requester@permit.test')->postJson('/api/permits', ['purpose' => 'short'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['details' => ['purpose', 'location', 'requested_start', 'permit_type_id']]]);
        $this->assertDatabaseCount('permits', 0);
    }

    public function test_unknown_user_gets_401_and_wrong_role_gets_403(): void
    {
        $this->withHeaders(['Accept' => 'application/json'])->getJson('/api/permits')->assertStatus(401);
        $this->asUser('reviewer1@permit.test')->postJson('/api/permits', $this->validBody())->assertStatus(403);
    }

    public function test_wrong_state_action_gets_409(): void
    {
        $reference = $this->asUser('requester@permit.test')->postJson('/api/permits', $this->validBody())->json('data.reference');
        $reviewer = Permit::where('reference', $reference)->first()->reviewer;

        $this->asUser($reviewer->email)->postJson("/api/permits/{$reference}/endorse")->assertOk();
        $this->asUser($reviewer->email)->postJson("/api/permits/{$reference}/endorse")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'invalid_transition');
    }
}
