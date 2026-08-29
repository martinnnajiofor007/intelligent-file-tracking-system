<?php

namespace App\Console\Commands;

use App\Services\OverdueTransferService;
use Illuminate\Console\Command;

class DetectOverdueTransfers extends Command
{
    protected $signature = 'transfers:detect-overdue';

    protected $description = 'Identify pending transfers whose due date has passed';

    public function handle(OverdueTransferService $service): int
    {
        $count = $service->countOverdue();

        $this->info("Found {$count} overdue pending transfer(s).");

        return self::SUCCESS;
    }
}
