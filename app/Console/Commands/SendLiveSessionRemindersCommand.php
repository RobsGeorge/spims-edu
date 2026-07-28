<?php

namespace App\Console\Commands;

use App\Services\Live\LiveSessionService;
use Illuminate\Console\Command;

class SendLiveSessionRemindersCommand extends Command
{
    protected $signature = 'live:send-reminders';

    protected $description = 'Send 24h and 15m live session reminder notifications';

    public function handle(LiveSessionService $sessions): int
    {
        $result = $sessions->sendDueReminders();
        $this->info("Reminders sent — 24h: {$result['h24']}, 15m: {$result['m15']}");

        return self::SUCCESS;
    }
}
