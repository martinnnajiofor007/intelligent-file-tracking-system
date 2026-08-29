<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use App\Services\OverdueTransferService;
use Illuminate\Console\Command;

class NotifyOverdueTransfers extends Command
{
    protected $signature = 'transfers:notify-overdue';

    protected $description = 'Create notifications for overdue pending transfers';

    public function handle(OverdueTransferService $overdue, NotificationService $notifications): int
    {
        $transfers = $overdue->findOverdue();
        $created = 0;

        foreach ($transfers as $transfer) {
            if ($notifications->notifyTransferOverdue($transfer)) {
                $created++;
            }
        }

        $this->info("Processed {$transfers->count()} overdue transfer(s); created {$created} notification(s).");

        return self::SUCCESS;
    }
}
