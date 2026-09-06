<?php

namespace App\Console\Commands;

use App\Services\Communications\NotificationPreferenceService;
use Illuminate\Console\Command;

class FireNotificationRemindersCommand extends Command
{
    protected $signature = 'communications:fire-reminders';

    protected $description = 'Send due user-scheduled notification reminders once';

    public function handle(NotificationPreferenceService $preferences): int
    {
        $fired = $preferences->fireDueReminders();
        $this->info("Reminders sent: {$fired}");

        return self::SUCCESS;
    }
}
