<?php

use Illuminate\Support\Facades\Schedule;

// Time-based triggers (ADR-001). Both commands are safe to run twice.
Schedule::command('permits:run-automation')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('notifications:dispatch')->everyMinute()->withoutOverlapping();
