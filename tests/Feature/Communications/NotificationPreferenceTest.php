<?php

namespace Tests\Feature\Communications;

use App\Enums\CommunicationChannel;
use App\Enums\NotificationChannel;
use App\Enums\RoleType;
use App\Models\Notification;
use App\Models\NotificationReminder;
use App\Models\User;
use App\Services\Communications\NotificationPreferenceService;
use App\Services\Mail\TransactionalMailer;
use App\Services\Notifications\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationPreferenceTest extends TestCase
{
    use RefreshAuthorization;
    use RefreshDatabase;

    #[Test]
    public function disabled_channel_suppresses_only_that_channel(): void
    {
        $user = User::factory()->withRole(RoleType::Student)->create(['notify_email' => true]);
        $prefs = app(NotificationPreferenceService::class);
        $prefs->put($user, $user, [
            ['event_key' => 'test.event', 'channel' => CommunicationChannel::Mail->value, 'enabled' => false],
            ['event_key' => 'test.event', 'channel' => CommunicationChannel::InApp->value, 'enabled' => true],
        ]);

        $mailer = Mockery::mock(TransactionalMailer::class);
        $mailer->shouldNotReceive('send');
        $this->app->instance(TransactionalMailer::class, $mailer);

        app(NotificationService::class)->notify($user, 'test.event', 'Title', 'Body');

        $this->assertTrue(
            Notification::query()
                ->where('user_id', $user->id)
                ->where('channel', NotificationChannel::InApp)
                ->exists()
        );
        $this->assertFalse(
            Notification::query()
                ->where('user_id', $user->id)
                ->where('channel', NotificationChannel::Email)
                ->exists()
        );
    }

    #[Test]
    public function notify_email_false_without_override_still_delivers_in_app_only(): void
    {
        $user = User::factory()->withRole(RoleType::Student)->create(['notify_email' => false]);
        $mailer = Mockery::mock(TransactionalMailer::class);
        $mailer->shouldNotReceive('send');
        $this->app->instance(TransactionalMailer::class, $mailer);

        app(NotificationService::class)->notify($user, 'test.event', 'Title', 'Body');

        $this->assertTrue(
            Notification::query()
                ->where('user_id', $user->id)
                ->where('channel', NotificationChannel::InApp)
                ->exists()
        );
        $this->assertFalse(
            Notification::query()
                ->where('user_id', $user->id)
                ->where('channel', NotificationChannel::Email)
                ->exists()
        );
    }

    #[Test]
    public function reminders_fire_once_at_remind_at(): void
    {
        $user = User::factory()->withRole(RoleType::Student)->create(['notify_email' => true]);
        $prefs = app(NotificationPreferenceService::class);
        $reminder = $prefs->schedule(
            $user,
            $user,
            'Announcement',
            '01ANNOUNCEMENTID0000000000',
            now()->subMinute(),
        );

        $this->assertNull($reminder->sent_at);

        $first = $prefs->fireDueReminders();
        $this->assertSame(1, $first);
        $this->assertNotNull($reminder->fresh()->sent_at);

        $second = $prefs->fireDueReminders();
        $this->assertSame(0, $second);
        $this->assertSame(1, NotificationReminder::query()->whereNotNull('sent_at')->count());
        $this->assertTrue(
            Notification::query()
                ->where('user_id', $user->id)
                ->where('type', 'reminder.due')
                ->where('channel', NotificationChannel::InApp)
                ->exists()
        );
    }
}
