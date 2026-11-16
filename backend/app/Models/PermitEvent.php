<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermitEvent extends Model
{
    const UPDATED_AT = null; // audit rows are never edited

    protected $guarded = [];

    protected function casts(): array
    {
        return ['meta' => 'encrypted:array']; // reviewer comments and rejection reasons
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * The note a person wrote for this step, if the step has one (Week 17, UAT findings F-02, F-03):
     * the reviewer's comment when asking for a revision, the reason when rejecting.
     */
    public function note(): ?string
    {
        return match ($this->type) {
            'revision_requested' => $this->meta['comment'] ?? null,
            'rejected' => $this->meta['reason'] ?? null,
            default => null,
        };
    }
}
