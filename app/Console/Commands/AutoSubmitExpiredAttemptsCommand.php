<?php

namespace App\Console\Commands;

use App\Services\Assessment\AttemptService;
use Illuminate\Console\Command;

class AutoSubmitExpiredAttemptsCommand extends Command
{
    protected $signature = 'assessments:auto-submit-expired';

    protected $description = 'Auto-submit assessment attempts whose server due_at has passed';

    public function handle(AttemptService $attempts): int
    {
        $count = $attempts->autoSubmitExpired();
        $this->info("Auto-submitted {$count} attempt(s).");

        return self::SUCCESS;
    }
}
