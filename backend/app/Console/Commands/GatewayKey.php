<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GatewayKey extends Command
{
    protected $signature = 'gateway:key';

    protected $description = 'Print a new random 32-byte payload key (base64) for GATEWAY_PAYLOAD_KEY';

    public function handle(): int
    {
        // Printed once for the operator to copy into .env; never written to a log.
        $this->line(base64_encode(random_bytes(32)));

        return self::SUCCESS;
    }
}
