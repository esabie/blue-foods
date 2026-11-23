<?php

namespace App\Console\Commands;

use App\Services\LotTracker;
use Illuminate\Console\Command;

class AlertExpiringLots extends Command
{
    protected $signature = 'lots:alert-expiring';

    protected $description = 'Notify management and warehouse about open lots that are expiring soon or already expired';

    public function handle(LotTracker $lots): int
    {
        $result = $lots->alertExpiring();

        $this->info("{$result['expiring']} expiring soon, {$result['expired']} expired.");

        return self::SUCCESS;
    }
}
