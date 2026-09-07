<?php

namespace Tests\Feature\Api;

use App\Enums\ContentItemType;
use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Enums\VideoProvider;
use App\Models\ContentItem;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\Week;
use App\Support\AuthorizeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContentTeachApiParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(AuthorizeService::class)->forgetMatrixCache();
    }

    #[Test]
    public function instructor_pastes_youtube_watch_url_stores_canonical_unpublished_draft(): void
    {
        [$offering, $instructor] = $this->staffedOffering('TAP1');
        $weekId = $this->createWeek($instructor, $offering);

        $item = $this->asInstructor($instructor)
            ->postJson(route('api.v1.teach.weeks.items.store', $weekId), [
                'type' => ContentItemType::Video->value,
                'title' => 'Lecture 1',
                'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Lecture 1')
            ->assertJsonPath('data.type', ContentItemType::Video->value)
            ->assertJsonPath('data.video_provider', VideoProvider::YouTube->value)
            ->assertJsonPath('data.vimeo_id', 'dQw4w9WgXcQ')
            ->assertJsonPath('data.published', false)
            ->assertJsonPath('data.published_at', null)
            ->json('data');

        $row = ContentItem::query()->findOrFail($item['id']);
        $this->assertSame(VideoProvider::YouTube, $row->video_provider);
        $this->assertSame('dQw4w9WgXcQ', $row->vimeo_id);
        $this->assertFalse($row->published);
        $this->assertNull($row->published_at);
        $this->assertSame($weekId, $item['week_id']);
        $this->assertArrayHasKey('file_url', $item);
        $this->assertArrayHasKey('body', $item);
        $this->assertArrayHasKey('order', $item);
    }

    #[Test]
    public function instructor_can_publish_then_student_week_items_omit_drafts_and_include_published(): void
    {
        [$offering, $instructor] = $this->staffedOffering('TAP2');
        $weekId = $this->createWeek($instructor, $offering);
        $student = User::factory()->withRole(RoleType::Student)->create();
        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
            'progress_percent' => 0,
        ]);

        $draftId = $this->asInstructor($instructor)
            ->postJson(route('api.v1.teach.weeks.items.store', $weekId), [
                'type' => ContentItemType::Text->value,
                'title' => 'Hidden draft',
            ])
            ->assertCreated()
            ->assertJsonPath('data.published', false)
            ->json('data.id');

        $liveId = $this->asInstructor($instructor)
            ->postJson(route('api.v1.teach.weeks.items.store', $weekId), [
                'type' => ContentItemType::Text->value,
                'title' => 'Visible notes',
                'published' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.published', true)
            ->json('data.id');

        $before = $this->asStudent($student)
            ->getJson(route('api.v1.offerings.weeks.items', [$offering, $weekId]))
            ->assertOk()
            ->json('data');
        $beforeTitles = collect($before)->pluck('title');
        $this->assertFalse($beforeTitles->contains('Hidden draft'));
        $this->assertTrue($beforeTitles->contains('Visible notes'));
        $this->assertFalse(collect($before)->pluck('id')->contains($draftId));
        $this->assertTrue(collect($before)->pluck('id')->contains($liveId));

        $this->asInstructor($instructor)
            ->postJson(route('api.v1.teach.items.publish', $draftId))
            ->assertOk()
            ->assertJsonPath('data.published', true)
            ->assertJsonPath('data.id', $draftId);

        $this->assertNotNull(ContentItem::query()->find($draftId)?->published_at);

        $after = $this->asStudent($student)
            ->getJson(route('api.v1.offerings.weeks.items', [$offering, $weekId]))
            ->assertOk()
            ->json('data');
        $afterTitles = collect($after)->pluck('title');
        $this->assertTrue($afterTitles->contains('Hidden draft'));
        $this->assertTrue($afterTitles->contains('Visible notes'));

        $this->asInstructor($instructor)
            ->postJson(route('api.v1.teach.items.unpublish', $liveId))
            ->assertOk()
            ->assertJsonPath('data.published', false);

        $afterUnpublish = collect($this->asStudent($student)
            ->getJson(route('api.v1.offerings.weeks.items', [$offering, $weekId]))
            ->assertOk()
            ->json('data'))->pluck('title');
        $this->assertFalse($afterUnpublish->contains('Visible notes'));
        $this->assertTrue($afterUnpublish->contains('Hidden draft'));
    }

    #[Test]
    public function instructor_can_reorder_move_down_and_move_to_another_week(): void
    {
        [$offering, $instructor] = $this->staffedOffering('TAP3');
        $week1 = $this->createWeek($instructor, $offering, 1, 'Week 1');
        $week2 = $this->createWeek($instructor, $offering, 2, 'Week 2');

        $firstId = $this->asInstructor($instructor)
            ->postJson(route('api.v1.teach.weeks.items.store', $week1), [
                'type' => ContentItemType::Text->value,
                'title' => 'Alpha',
            ])
            ->assertCreated()
            ->json('data.id');
        $secondId = $this->asInstructor($instructor)
            ->postJson(route('api.v1.teach.weeks.items.store', $week1), [
                'type' => ContentItemType::Text->value,
                'title' => 'Beta',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->asInstructor($instructor)
            ->postJson(route('api.v1.teach.items.move-down', $firstId))
            ->assertOk()
            ->assertJsonPath('data.id', $firstId)
            ->assertJsonPath('data.order', 2);

        $this->assertSame(
            ['Beta', 'Alpha'],
            ContentItem::query()->where('week_id', $week1)->orderBy('order')->pluck('title')->all(),
        );

        $this->asInstructor($instructor)
            ->postJson(route('api.v1.teach.items.move', $firstId), [
                'week_id' => $week2,
            ])
            ->assertOk()
            ->assertJsonPath('data.week_id', $week2)
            ->assertJsonPath('data.id', $firstId);

        $this->assertSame($week2, ContentItem::query()->find($firstId)?->week_id);
        $this->assertSame(['Beta'], ContentItem::query()->where('week_id', $week1)->orderBy('order')->pluck('title')->all());
        $this->assertSame(['Alpha'], ContentItem::query()->where('week_id', $week2)->orderBy('order')->pluck('title')->all());
        $this->assertSame($secondId, ContentItem::query()->where('week_id', $week1)->value('id'));
    }

    #[Test]
    public function cross_offering_instructor_is_forbidden_on_publish_and_move(): void
    {
        [$mine, $instructorA] = $this->staffedOffering('TAP4A');
        [, $instructorB] = $this->staffedOffering('TAP4B');
        $weekA = $this->createWeek($instructorA, $mine);
        $itemId = $this->asInstructor($instructorA)
            ->postJson(route('api.v1.teach.weeks.items.store', $weekA), [
                'type' => ContentItemType::Text->value,
                'title' => 'Mine',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->asInstructor($instructorB)
            ->postJson(route('api.v1.teach.items.publish', $itemId))
            ->assertForbidden();

        $this->asInstructor($instructorB)
            ->postJson(route('api.v1.teach.items.move', $itemId), [
                'week_id' => $weekA,
            ])
            ->assertForbidden();

        $this->asInstructor($instructorB)
            ->postJson(route('api.v1.teach.items.move-up', $itemId))
            ->assertForbidden();
    }

    #[Test]
    public function file_with_only_https_file_url_succeeds_without_upload(): void
    {
        [$offering, $instructor] = $this->staffedOffering('TAP5');
        $weekId = $this->createWeek($instructor, $offering);

        $drive = $this->asInstructor($instructor)
            ->postJson(route('api.v1.teach.weeks.items.store', $weekId), [
                'type' => ContentItemType::File->value,
                'title' => 'Drive handout',
                'file_url' => 'https://drive.google.com/file/d/abcDriveId/view?usp=sharing',
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', ContentItemType::File->value)
            ->assertJsonPath('data.file_url', 'https://drive.google.com/file/d/abcDriveId/preview')
            ->assertJsonPath('data.published', false)
            ->json('data');

        $this->assertNull($this->asInstructor($instructor)->postJson(route('api.v1.teach.weeks.items.store', $weekId), [
            'type' => ContentItemType::File->value,
            'title' => 'Should not 422',
            'file_url' => 'https://drive.google.com/file/d/abcDriveId/view',
        ])->json('errors.file'));

        config(['spims.content.allow_unknown_reading_urls' => true]);
        $this->asInstructor($instructor)
            ->postJson(route('api.v1.teach.weeks.items.store', $weekId), [
                'type' => ContentItemType::Reading->value,
                'title' => 'W3C note',
                'file_url' => 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
            ])
            ->assertCreated()
            ->assertJsonPath('data.file_url', 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf');

        $this->assertNotEmpty($drive['id']);
    }

    #[Test]
    public function outsider_and_student_are_denied(): void
    {
        [$offering, $instructor] = $this->staffedOffering('TAP6');
        $weekId = $this->createWeek($instructor, $offering);
        $itemId = $this->asInstructor($instructor)
            ->postJson(route('api.v1.teach.weeks.items.store', $weekId), [
                'type' => ContentItemType::Text->value,
                'title' => 'Staff only',
            ])
            ->assertCreated()
            ->json('data.id');

        $student = User::factory()->withRole(RoleType::Student)->create();
        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
            'progress_percent' => 0,
        ]);

        $this->asStudent($student)
            ->postJson(route('api.v1.teach.weeks.items.store', $weekId), [
                'type' => ContentItemType::Text->value,
                'title' => 'Student write',
            ])
            ->assertForbidden();

        $this->asStudent($student)
            ->postJson(route('api.v1.teach.items.publish', $itemId))
            ->assertForbidden();

        $this->postJson(route('api.v1.teach.items.publish', $itemId))
            ->assertUnauthorized();

        $outsider = User::factory()->withRole(RoleType::Instructor)->create();
        $this->asInstructor($outsider)
            ->postJson(route('api.v1.teach.items.publish', $itemId))
            ->assertForbidden();
    }

    /**
     * @return array{0: CourseOffering, 1: User}
     */
    private function staffedOffering(string $code): array
    {
        $course = Course::query()->create([
            'code' => $code,
            'title' => "Course $code",
            'credit_hours' => 3,
            'is_standalone' => true,
            'active' => true,
        ]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::Cohort,
            'status' => 'OPEN',
        ]);
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $this->staffOffering($instructor, $offering);

        return [$offering, $instructor];
    }

    private function createWeek(User $instructor, CourseOffering $offering, int $number = 1, string $title = 'Week 1'): string
    {
        return $this->asInstructor($instructor)
            ->postJson(route('api.v1.teach.offerings.weeks.store', $offering), [
                'number' => $number,
                'title' => $title,
            ])
            ->assertCreated()
            ->json('data.id');
    }

    private function asInstructor(User $user)
    {
        Auth::forgetGuards();

        return $this->withToken($user->createToken('api', ['role:INSTRUCTOR'])->plainTextToken);
    }

    private function asStudent(User $user)
    {
        Auth::forgetGuards();

        return $this->withToken($user->createToken('api', ['role:STUDENT'])->plainTextToken);
    }
}
