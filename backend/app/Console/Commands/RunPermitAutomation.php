<?php

namespace App\Console\Commands;

use App\Services\PermitAutomation;
use App\Support\StructuredLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class RunPermitAutomation extends Command
{
    protected $signature = 'permits:run-automation {--now= : Pretend the time is this (YYYY-MM-DD HH:MM), for demos only}';

    protected $description = 'Re-route unassigned permits, escalate stalled ones, queue expiry reminders, expire lapsed permits';

    public function handle(PermitAutomation $automation): int
    {
        if ($this->option('now')) {
            Carbon::setTestNow(Carbon::parse($this->option('now')));
        }
        app()->instance('correlation_id', 'job-'.Str::uuid());

        $result = $automation->run();
        StructuredLog::event('automation.run_completed', $result + ['simulated_now' => $this->option('now')]);
        $this->table(array_keys($result), [array_values($result)]);

        return in_array(-1, $result, true) ? self::FAILURE : self::SUCCESS;
    }
}
