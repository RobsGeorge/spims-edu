<?php

namespace Tests\Feature\Live;

use App\Enums\NotificationChannel;
use App\Enums\RoleType;
use App\Models\Notification;
use App\Models\User;
use App\Services\Mail\TransactionalMailer;
use App\Services\Notifications\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The `notify_email` toggle is exposed in settings; these assert it actually
 * suppresses mail rather than only appearing to.
 */
class NotificationEmailPreferenceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function opting_out_of_email_suppresses_the_mail_channel_but_not_the_in_app_notification(): void
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
                ->exists(),
            'The in-app notification must still be delivered.'
        );

        $this->assertFalse(
            Notification::query()
                ->where('user_id', $user->id)
                ->where('channel', NotificationChannel::Email)
                ->exists(),
            'No EMAIL channel row may be written when the user opted out.'
        );
    }

    #[Test]
    public function opting_in_to_email_still_sends_both_channels(): void
    {
        $user = User::factory()->withRole(RoleType::Student)->create(['notify_email' => true]);

        $mailer = Mockery::mock(TransactionalMailer::class);
        $mailer->shouldReceive('send')->once()->andReturnTrue();
        $this->app->instance(TransactionalMailer::class, $mailer);

        app(NotificationService::class)->notify($user, 'test.event', 'Title', 'Body');

        $this->assertTrue(
            Notification::query()
                ->where('user_id', $user->id)
                ->where('channel', NotificationChannel::Email)
                ->exists()
        );
    }

    #[Test]
    public function callers_that_explicitly_disable_email_are_unaffected_by_the_preference(): void
    {
        $user = User::factory()->withRole(RoleType::Student)->create(['notify_email' => true]);

        $mailer = Mockery::mock(TransactionalMailer::class);
        $mailer->shouldNotReceive('send');
        $this->app->instance(TransactionalMailer::class, $mailer);

        app(NotificationService::class)->notify($user, 'test.event', 'Title', 'Body', alsoEmail: false);

        $this->assertSame(1, Notification::query()->where('user_id', $user->id)->count());
    }
}
