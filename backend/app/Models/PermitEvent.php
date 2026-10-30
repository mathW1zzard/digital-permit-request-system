<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermitEvent extends Model
{
    const UPDATED_AT = null; // audit rows are never edited

    protected $guarded = [];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }
}
