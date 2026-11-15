<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Week 16: fictional accounts for the performance runs only.
 *
 *   php artisan db:seed --class=LoadTestUsersSeeder --force
 *
 * 40 requesters (load-req-01..40@load.test) and 4 extra reviewers (load-rev-1..4@load.test),
 * all with DEMO_PASSWORD. Each API user may make 60 requests a minute, so a realistic peak
 * needs many users, not one fast one. Not part of DatabaseSeeder: never created by accident.
 */
class LoadTestUsersSeeder extends Seeder
{
    public const REQUESTERS = 40;

    public const REVIEWERS = 4;

    public function run(): void
    {
        $password = (string) config('permit.demo_password');
        if (strlen($password) < 12) {
            throw new RuntimeException('Set DEMO_PASSWORD (at least 12 characters) before seeding.');
        }
        $hash = Hash::make($password);

        $make = function (string $name, string $email, string $role) use ($hash) {
            // role and password are not mass-assignable on User, so set them explicitly.
            User::query()->firstOrNew(['email' => $email])->forceFill([
                'name' => $name, 'role' => $role, 'is_senior' => false, 'password' => $hash,
            ])->save();
        };

        for ($i = 1; $i <= self::REQUESTERS; $i++) {
            $n = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $make("Load Requester {$n}", "load-req-{$n}@load.test", 'requester');
        }
        for ($i = 1; $i <= self::REVIEWERS; $i++) {
            $make("Load Reviewer {$i}", "load-rev-{$i}@load.test", 'reviewer');
        }
    }
}
