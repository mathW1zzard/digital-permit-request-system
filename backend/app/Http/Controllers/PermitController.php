<?php

namespace App\Http\Controllers;

use App\Enums\PermitStatus;
use App\Http\Resources\PermitResource;
use App\Models\Permit;
use App\Models\PermitType;
use App\Services\PermitWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PermitController extends Controller
{
    /** Keep in step with docs/05-validation-rules.md and the Flutter form. */
    private const PERMIT_RULES = [
        'permit_type_id' => ['required', 'integer', 'exists:permit_types,id'],
        'purpose' => ['required', 'string', 'min:10', 'max:255'],
        'location' => ['required', 'string', 'max:120'],
        'details' => ['nullable', 'string', 'max:2000'],
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
        $data = $request->validate(self::PERMIT_RULES);
        $permit = $this->workflow->submit($request->user(), PermitType::findOrFail($data['permit_type_id']), $data);

        return PermitResource::make($this->withRelations($permit))->response()->setStatusCode(201);
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
