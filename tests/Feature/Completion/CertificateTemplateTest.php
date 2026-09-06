<?php

namespace Tests\Feature\Completion;

use App\Enums\RoleType;
use App\Models\CertificateTemplate;
use App\Models\Credential;
use App\Models\User;
use App\Services\Credentials\CertificateTemplateService;
use App\Services\Credentials\CredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CertificateTemplateTest extends TestCase
{
    use CompletionFixtures;
    use RefreshDatabase;

    #[Test]
    public function a_course_template_beats_the_global_template_for_the_same_locale(): void
    {
        $offering = $this->offering('TMPL1');
        $admin = $this->admin();
        $student = User::factory()->withRole(RoleType::Student)->create(['first_name' => 'Mariam', 'last_name' => 'Shenouda']);
        $this->enroll($student, $offering);

        $templates = app(CertificateTemplateService::class);
        $templates->upsert($admin, [
            'course_id' => null,
            'locale' => 'en',
            'title' => 'Global title',
            'body' => 'Global {{student_name}}',
        ]);
        $templates->upsert($admin, [
            'course_id' => $offering->course_id,
            'locale' => 'en',
            'title' => 'Course title',
            'body' => 'Course {{student_name}}',
        ]);

        $resolved = $templates->resolve($offering->course_id, 'en');
        $this->assertSame('Course title', $resolved->title);

        $credential = app(CredentialService::class)->issueOfferingCompletion($admin, $student, $offering, 'en');
        $html = $templates->render($credential);
        $this->assertStringContainsString('Course Mariam Shenouda', $html);
        $this->assertStringNotContainsString('Global Mariam', $html);
    }

    #[Test]
    public function locale_falls_back_to_english_then_to_the_hardcoded_default(): void
    {
        $offering = $this->offering('TMPL2');
        $admin = $this->admin();
        $student = User::factory()->withRole(RoleType::Student)->create(['first_name' => 'Mariam', 'last_name' => 'Shenouda']);

        $templates = app(CertificateTemplateService::class);
        $templates->upsert($admin, [
            'course_id' => $offering->course_id,
            'locale' => 'en',
            'title' => 'English title',
            'body' => 'English {{student_name}}',
        ]);

        $credential = Credential::query()->make([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'language' => 'fr',
            'serial' => 'SPIMS-CRED-PREVIEW-1',
            'issued_at' => now(),
        ]);
        $credential->setRelation('student', $student);
        $credential->setRelation('offering', $offering->load('course'));

        $html = $templates->render($credential);
        $this->assertStringContainsString('English Mariam Shenouda', $html);
    }

    #[Test]
    public function arabic_render_round_trips_arabic_text(): void
    {
        $offering = $this->offering('TMPL3');
        $admin = $this->admin();
        $student = User::factory()->withRole(RoleType::Student)->create(['first_name' => 'مريم', 'last_name' => 'شنودة']);

        app(CertificateTemplateService::class)->upsert($admin, [
            'course_id' => $offering->course_id,
            'locale' => 'ar',
            'title' => 'شهادة إكمال',
            'body' => 'نشهد أن {{student_name}} قد أكمل المقرر',
        ]);

        $credential = app(CredentialService::class)->issueOfferingCompletion($admin, $student, $offering, 'ar');
        $html = app(CertificateTemplateService::class)->render($credential);

        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('مريم شنودة', $html);
        $this->assertStringContainsString('شهادة إكمال', $html);
        $this->assertSame(1, CertificateTemplate::query()->count());
    }
}
