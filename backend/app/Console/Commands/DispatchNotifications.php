<?php

namespace App\Console\Commands;

use App\Services\NotificationDispatcher;
use App\Support\StructuredLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DispatchNotifications extends Command
{
    protected $signature = 'notifications:dispatch {--now= : Pretend the time is this (YYYY-MM-DD HH:MM), for demos only}';

    protected $description = 'Deliver due notifications to the gateway with bounded retry';

    public function handle(NotificationDispatcher $dispatcher): int
    {
        if ($this->option('now')) {
            Carbon::setTestNow(Carbon::parse($this->option('now')));
        }
        app()->instance('correlation_id', 'job-'.Str::uuid());

        $stats = $dispatcher->dispatchDue();
        StructuredLog::event('dispatch.run_completed', $stats + ['simulated_now' => $this->option('now')]);
        $this->table(array_keys($stats), [array_values($stats)]);

        return self::SUCCESS;
    }
}
