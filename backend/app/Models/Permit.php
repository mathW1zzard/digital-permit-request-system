<?php

namespace App\Models;

use App\Enums\PermitStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Permit extends Model
{
    // Only PermitWorkflow writes permits, always with explicit arrays (never $request->all()).
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => PermitStatus::class,
            'requester_id' => 'integer',
            'reviewer_id' => 'integer',
            'approver_id' => 'integer',
            'requested_start' => 'date',
            'submitted_at' => 'datetime',
            'stage_entered_at' => 'datetime',
            'issued_at' => 'datetime',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'needs_manual_action' => 'boolean',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(PermitType::class, 'permit_type_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PermitEvent::class)->orderBy('id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(OutboxNotification::class)->orderBy('id');
    }
}
