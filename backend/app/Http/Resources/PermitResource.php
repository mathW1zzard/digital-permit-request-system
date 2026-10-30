<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** What the browser may see. No emails, no comments, no raw gateway errors. */
class PermitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'type' => $this->whenLoaded('type', fn () => ['id' => $this->type->id, 'code' => $this->type->code, 'name' => $this->type->name]),
            'purpose' => $this->purpose,
            'location' => $this->location,
            'details' => $this->details,
            'requested_start' => $this->requested_start?->toDateString(),
            'status' => $this->status->value,
            'revision_count' => $this->revision_count,
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer?->name),
            'approver' => $this->whenLoaded('approver', fn () => $this->approver?->name),
            'valid_from' => $this->valid_from?->toIso8601String(),
            'valid_until' => $this->valid_until?->toIso8601String(),
            'needs_manual_action' => $this->needs_manual_action,
            'manual_action_reason' => $this->manual_action_reason,
            'events' => $this->whenLoaded('events', fn () => $this->events->map(fn ($e) => [
                'type' => $e->type,
                'from' => $e->from_status,
                'to' => $e->to_status,
                'at' => $e->created_at?->toIso8601String(),
            ])),
            'notifications' => $this->whenLoaded('notifications', fn () => $this->notifications->map(fn ($n) => [
                'kind' => $n->kind,
                'status' => $n->status,
                'attempts' => $n->attempts,
                'next_attempt_at' => $n->next_attempt_at?->toIso8601String(),
            ])),
        ];
    }
}
