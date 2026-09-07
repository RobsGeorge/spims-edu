<?php

namespace App\Console\Commands;

use App\Services\Finance\PaymentPlanService;
use Illuminate\Console\Command;

class DunnOverdueInstallmentsCommand extends Command
{
    protected $signature = 'finance:dunning-overdue-installments';

    protected $description = 'Send one S2 reminder per overdue payment-plan installment';

    public function handle(PaymentPlanService $plans): int
    {
        $sent = $plans->dunnOverdue();
        $this->info("Dunning reminders sent: {$sent}");

        return self::SUCCESS;
    }
}
