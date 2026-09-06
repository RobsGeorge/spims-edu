<?php

namespace Tests\Feature\Assessment;

use App\Enums\ContentItemType;
use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Enums\SubmissionType;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ContentItem;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\Week;
use App\Services\Storage\ObjectStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssignmentFileSubmitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{student: User, assignment: Assignment, offering: CourseOffering}
     */
    private function bundle(): array
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $student = User::factory()->withRole(RoleType::Student)->create();
        $course = Course::query()->create([
            'code' => 'UPL1',
            'title' => 'Upload Course',
            'credit_hours' => 3,
            'is_standalone' => true,
            'active' => true,
        ]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
        ]);
        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);
        $week = Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 1,
            'title' => 'Week 1',
            'order' => 1,
        ]);
        $item = ContentItem::query()->create([
            'week_id' => $week->id,
            'type' => ContentItemType::Assignment,
            'title' => 'Essay',
            'order' => 1,
        ]);
        $assignment = Assignment::query()->create([
            'content_item_id' => $item->id,
            'instructions' => 'Write an essay.',
            'submission_type' => SubmissionType::Both,
            'allowed_file_types' => ['pdf'],
            'max_points' => 100,
            'released' => true,
            'allow_resubmission' => true,
        ]);

        return compact('student', 'assignment', 'offering');
    }

    #[Test]
    public function pasted_file_url_is_rejected_with_422(): void
    {
        ['student' => $student, 'assignment' => $assignment] = $this->bundle();

        $this->actingAs($student)
            ->postJson(route('assignments.submit', $assignment), [
                'text_body' => 'draft',
                'file_url' => 'https://evil.example/essay.pdf',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file_url');

        $this->assertSame(0, AssignmentSubmission::query()->count());
    }

    #[Test]
    public function uploaded_file_is_stored_under_submissions_and_readable_via_temporary_url(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        ['student' => $student, 'assignment' => $assignment] = $this->bundle();
        $file = UploadedFile::fake()
            ->createWithContent('essay.pdf', "%PDF-1.4\n%assignment-upload\n")
            ->mimeType('application/pdf');

        $this->actingAs($student)
            ->post(route('assignments.submit', $assignment), [
                'text_body' => 'draft',
                'file' => $file,
            ])
            ->assertRedirect();

        $submission = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('student_id', $student->id)
            ->sole();

        $this->assertNotNull($submission->file_url);
        $this->assertStringStartsWith('submissions/'.$student->id.'/', $submission->file_url);

        $storage = app(ObjectStorageService::class);
        $this->assertTrue($storage->exists($submission->file_url));
        $this->assertNotSame('', (string) $storage->disk()->get($submission->file_url));

        $url = $storage->temporaryUrl($submission->file_url);
        $this->assertNotEmpty($url);
        $this->assertStringContainsString('storage/', $url);
    }

    #[Test]
    public function assignment_show_renders_file_input_not_file_url_field(): void
    {
        ['student' => $student, 'assignment' => $assignment] = $this->bundle();

        $html = $this->actingAs($student)
            ->get(route('assignments.show', $assignment))
            ->assertOk()
            ->assertSee(__('assessment.file_label'))
            ->assertSee(__('assessment.text_body_label'))
            ->getContent();

        $this->assertStringContainsString('type="file"', $html);
        $this->assertStringContainsString('name="file"', $html);
        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
        $this->assertStringNotContainsString('name="file_url"', $html);
    }

    #[Test]
    public function foreign_submission_path_is_rejected(): void
    {
        ['student' => $student, 'assignment' => $assignment] = $this->bundle();
        $other = User::factory()->withRole(RoleType::Student)->create();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(\App\Services\Assessment\AssignmentService::class)->submit(
            $student,
            $assignment,
            textBody: 'stolen',
            fileUrl: 'submissions/'.$other->id.'/01hzzzzzzzzzzzzzzzzzzzzzzz.pdf',
        );
    }
}
