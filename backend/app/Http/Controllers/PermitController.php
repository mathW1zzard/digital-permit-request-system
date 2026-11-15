<?php

namespace App\Http\Controllers;

use App\Enums\PermitStatus;
use App\Http\Resources\PermitResource;
use App\Models\IdempotencyKey;
use App\Models\Permit;
use App\Models\PermitType;
use App\Services\PermitWorkflow;
use App\Support\StructuredLog;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PermitController extends Controller
{
    /** Keep in step with docs/05-validation-rules.md and the Flutter form. */
    private const PERMIT_RULES = [
        'permit_type_id' => ['required', 'integer', 'exists:permit_types,id'],
        'purpose' => ['required', 'string', 'min:10', 'max:255'],
        'location' => ['required', 'string', 'max:120'],
        'details' => ['nullable', 'string', 'max:2000'],
        // The upper limit is added in permitRules(): it depends on today's date (defect D1).
        'requested_start' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
    ];

    private const NOTE_RULES = ['required', 'string', 'min:5', 'max:500'];

    public function __construct(private readonly PermitWorkflow $workflow) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $query = Permit::with(['type', 'reviewer', 'approver'])->latest('id')->limit(100);

        if ($user->role === 'requester') {
            $query->where('requester_id', $user->id);
        } elseif ($user->role === 'reviewer') {
            $query->where('reviewer_id', $user->id);
        } elseif ($user->role === 'approver') {
            $query->where('approver_id', $user->id);
        } // admin and inspector see all

        return PermitResource::collection($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->permitRules());
        $key = $request->header('Idempotency-Key');

        if ($key === null) {
            $permit = $this->workflow->submit($request->user(), PermitType::findOrFail($data['permit_type_id']), $data);

            return PermitResource::make($this->withRelations($permit))->response()->setStatusCode(201);
        }

        // Defect D3: the same key from the same user returns the permit it already created,
        // so a double click or a retry after a lost response never makes a second permit.
        if (strlen($key) > 100 || preg_match('/^[A-Za-z0-9._:-]+$/', $key) !== 1) {
            throw ValidationException::withMessages(['idempotency_key' => 'Use up to 100 letters, digits, dots, colons, dashes or underscores.']);
        }
        ksort($data);
        $hash = hash('sha256', json_encode($data));

        try {
            [$record, $created] = DB::transaction(function () use ($request, $data, $key, $hash) {
                $existing = IdempotencyKey::where('user_id', $request->user()->id)->where('key', $key)->first();
                if ($existing) {
                    return [$existing, false];
                }
                $permit = $this->workflow->submit($request->user(), PermitType::findOrFail($data['permit_type_id']), $data);
                $record = IdempotencyKey::create(['user_id' => $request->user()->id, 'key' => $key, 'request_hash' => $hash, 'permit_id' => $permit->id, 'created_at' => now()]);

                return [$record, true];
            });
        } catch (UniqueConstraintViolationException) {
            // Two identical requests raced: the other one won and its transaction (with its permit) stands.
            [$record, $created] = [IdempotencyKey::where('user_id', $request->user()->id)->where('key', $key)->firstOrFail(), false];
        }

        if (! $created && ! hash_equals($record->request_hash, $hash)) {
            return response()->json(['error' => [
                'code' => 'idempotency_key_reused',
                'message' => 'This request key was already used for a different submission.',
                'correlation_id' => StructuredLog::correlationId(),
            ]], 422);
        }

        $permit = $record->permit;
        StructuredLog::event($created ? 'permit.submitted_with_key' : 'permit.duplicate_submission_replayed', ['permit' => $permit->reference]);

        return PermitResource::make($this->withRelations($permit))->response()
            ->setStatusCode($created ? 201 : 200)
            ->header('Idempotent-Replayed', $created ? 'false' : 'true');
    }

    /** Validation for a new permit. The start date may be at most permit.max_days_ahead days away (defect D1). */
    private function permitRules(): array
    {
        $rules = self::PERMIT_RULES;
        $rules['requested_start'][] = 'before_or_equal:'.now()->addDays((int) config('permit.max_days_ahead'))->toDateString();

        return $rules;
    }

    public function show(Permit $permit): PermitResource
    {
        Gate::authorize('view', $permit);

        return PermitResource::make($this->withRelations($permit)->load(['events', 'notifications']));
    }

    public function requestRevision(Request $request, Permit $permit): PermitResource
    {
        Gate::authorize('review', $permit);
        $data = $request->validate(['comment' => self::NOTE_RULES]);
        $this->workflow->requestRevision($permit, $request->user(), $data['comment']);

        return $this->fresh($permit);
    }

    public function resubmit(Request $request, Permit $permit): PermitResource
    {
        Gate::authorize('resubmit', $permit);
        $data = $request->validate([
            'purpose' => ['sometimes', 'string', 'min:10', 'max:255'],
            'details' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);
        $this->workflow->resubmit($permit, $request->user(), $data);

        return $this->fresh($permit);
    }

    public function endorse(Request $request, Permit $permit): PermitResource
    {
        Gate::authorize('review', $permit);
        $this->workflow->endorse($permit, $request->user());

        return $this->fresh($permit);
    }

    public function approve(Request $request, Permit $permit): PermitResource
    {
        Gate::authorize('approve', $permit);
        $this->workflow->approve($permit, $request->user());

        return $this->fresh($permit);
    }

    public function reject(Request $request, Permit $permit): PermitResource
    {
        Gate::authorize('reject', $permit);
        $data = $request->validate(['reason' => self::NOTE_RULES]);
        $this->workflow->reject($permit, $request->user(), $data['reason']);

        return $this->fresh($permit);
    }

    /** Validity status for inspectors. Checks the clock too, so a lapsed permit is never "valid". */
    public function verify(Permit $permit): JsonResponse
    {
        $valid = $permit->status === PermitStatus::Issued && $permit->valid_until?->isFuture() === true;

        return response()->json(['data' => [
            'reference' => $permit->reference,
            'status' => $permit->status->value,
            'valid' => $valid,
            'valid_until' => $permit->valid_until?->toIso8601String(),
        ]]);
    }

    private function withRelations(Permit $permit): Permit
    {
        return $permit->load(['type', 'reviewer', 'approver']);
    }

    private function fresh(Permit $permit): PermitResource
    {
        return PermitResource::make($this->withRelations($permit->refresh()));
    }
}
