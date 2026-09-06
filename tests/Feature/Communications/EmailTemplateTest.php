<?php

namespace Tests\Feature\Communications;

use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\User;
use App\Services\Communications\EmailTemplateService;
use App\Services\Mail\TransactionalMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailTemplateTest extends TestCase
{
    use RefreshAuthorization;
    use RefreshDatabase;

    #[Test]
    public function resolve_falls_back_from_course_to_global_to_lang_file(): void
    {
        $admin = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $course = Course::query()->create([
            'code' => 'TMP1',
            'title' => 'Template Course',
            'credit_hours' => 3,
            'is_standalone' => true,
            'active' => true,
        ]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::Cohort,
            'status' => 'OPEN',
        ]);
        $service = app(EmailTemplateService::class);

        $lang = $service->resolve('announcement.published', 'en', $offering);
        $this->assertSame('lang', $lang['source']);
        $this->assertStringContainsString('{{title}}', $lang['subject']);

        $service->upsert($admin, [
            'key' => 'announcement.published',
            'locale' => 'en',
            'subject' => 'Global {{title}}',
            'body' => 'Global {{body}}',
        ]);
        $global = $service->resolve('announcement.published', 'en', $offering);
        $this->assertSame('global', $global['source']);
        $this->assertSame('Global {{title}}', $global['subject']);

        $service->upsert($admin, [
            'key' => 'announcement.published',
            'locale' => 'en',
            'subject' => 'Course {{title}}',
            'body' => 'Course {{body}}',
            'scope_type' => 'course',
            'scope_id' => $course->id,
        ], $offering);
        $courseHit = $service->resolve('announcement.published', 'en', $offering);
        $this->assertSame('course', $courseHit['source']);
        $this->assertSame('Course {{title}}', $courseHit['subject']);
    }

    #[Test]
    public function locale_falls_back_to_english(): void
    {
        $admin = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $service = app(EmailTemplateService::class);
        $service->upsert($admin, [
            'key' => 'announcement.published',
            'locale' => 'en',
            'subject' => 'EN {{title}}',
            'body' => 'EN {{body}}',
        ]);

        $resolved = $service->resolve('announcement.published', 'de');
        $this->assertSame('EN {{title}}', $resolved['subject']);
    }

    #[Test]
    public function unknown_variables_are_rejected(): void
    {
        $service = app(EmailTemplateService::class);

        $this->expectException(ValidationException::class);
        $service->render('Hello {{secret}}', 'Body', ['secret' => 'nope']);
    }

    #[Test]
    public function preview_renders_without_sending_mail(): void
    {
        $admin = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $mailer = Mockery::mock(TransactionalMailer::class);
        $mailer->shouldNotReceive('send');
        $this->app->instance(TransactionalMailer::class, $mailer);

        $preview = app(EmailTemplateService::class)->preview(
            $admin,
            'announcement.published',
            'en',
            ['name' => 'Ada', 'title' => 'Hello', 'body' => 'World'],
        );

        $this->assertArrayHasKey('subject', $preview);
        $this->assertArrayHasKey('body', $preview);
        $this->assertSame(0, \App\Models\CommunicationLog::query()->count());
    }
}
