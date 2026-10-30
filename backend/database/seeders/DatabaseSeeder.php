<?php

namespace Database\Seeders;

use App\Models\PermitType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Fictional accounts only. bounce@invalid.test is rejected by the mock gateway on purpose.
        $accounts = [
            ['Ana Admin', 'admin@permit.test', 'admin', false],
            ['Rico Requester', 'requester@permit.test', 'requester', false],
            ['Bea Bounce', 'bounce@invalid.test', 'requester', false],
            ['Rita Reviewer', 'reviewer1@permit.test', 'reviewer', false],
            ['Rey Reviewer', 'reviewer2@permit.test', 'reviewer', false],
            ['Alma Approver', 'approver@permit.test', 'approver', false],
            ['Sol Senior', 'senior@permit.test', 'approver', true],
            ['Ines Inspector', 'inspector@permit.test', 'inspector', false],
        ];

        foreach ($accounts as [$name, $email, $role, $senior]) {
            User::forceCreate([
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'is_senior' => $senior,
                'password' => Hash::make(Str::random(40)), // unusable random password; no login in Week 13
            ]);
        }

        $types = [
            // code, name, validity days, reminder days, senior approval, review SLA h, approval SLA h
            ['FAC-USE', 'Facility Use Permit', 30, 7, false, 24, 48],
            ['HOT-WORK', 'Hot Work Permit', 7, 2, true, 8, 24],
            ['MINOR-CONST', 'Minor Construction Permit', 60, 14, true, 48, 72],
        ];

        foreach ($types as [$code, $name, $validity, $reminder, $senior, $reviewSla, $approvalSla]) {
            PermitType::create([
                'code' => $code,
                'name' => $name,
                'validity_days' => $validity,
                'reminder_days' => $reminder,
                'requires_senior_approval' => $senior,
                'review_sla_hours' => $reviewSla,
                'approval_sla_hours' => $approvalSla,
            ]);
        }
    }
}
