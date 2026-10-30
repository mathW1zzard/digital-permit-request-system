<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermitType extends Model
{
    protected $guarded = []; // only the seeder writes this table

    protected function casts(): array
    {
        return ['requires_senior_approval' => 'boolean'];
    }
}
