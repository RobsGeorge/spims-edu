<?php

namespace Tests\Feature\Api;

use App\Enums\ContentItemType;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Models\ContentItem;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\User;
use App\Models\Week;
use App\Support\AuthorizeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InstructorOpsContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(AuthorizeService::class)->forgetMatrixCache();
    }

    #[Test]
    public function instructor_creates_updates_and_deletes_week_items(): void
    {
        Storage::fake('local');
        [$offering, $instructor] = $this->staffedOffering('OC1');

        $weekId = $this->asInstructor($instructor)
            ->postJson(route('api.v1.teach.offerings.weeks.store', $offering), [
                'number' => 1,
                'title' => 'Week 1',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Week 1')
            ->json('data.id');

        $file = UploadedFile::fake()->createWithContent('notes.pdf', "%PDF-1.4\n%%EOF");
        $itemId = $this->asInstructor($instructor)
            ->post(route('api.v1.teach.weeks.items.store', $weekId), [
                'type' => ContentItemType::File->value,
                'title' => 'Syllabus',
                'file' => $file,
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.type', ContentItemType::File->value)
            ->assertJsonPath('data.title', 'Syllabus')
            ->json('data.id');

        $this->assertNotEmpty(ContentItem::query()->find($itemId)?->file_url);

        $this->asInstructor($instructor)
            ->putJson(route('api.v1.teach.items.update', $itemId), [
                'title' => 'Syllabus (revised)',
                'body' => 'Read this',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Syllabus (revised)')
            ->assertJsonPath('data.body', 'Read this');

        $this->asInstructor($instructor)
            ->deleteJson(route('api.v1.teach.items.destroy', $itemId))
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertNull(ContentItem::query()->find($itemId));
        $this->assertNotNull(Week::query()->find($weekId));
    }

    #[Test]
    public function content_mutations_are_gated_by_offerings_content_scope(): void
    {
        [$mine, $instructorA] = $this->staffedOffering('OC2A');
        [, $instructorB] = $this->staffedOffering('OC2B');

        $this->asInstructor($instructorB)
            ->postJson(route('api.v1.teach.offerings.weeks.store', $mine), [
                'number' => 1,
                'title' => 'No',
            ])
            ->assertForbidden();

        $week = Week::query()->create([
            'offering_id' => $mine->id,
            'number' => 1,
            'title' => 'Intro',
            'order' => 1,
        ]);
        $item = $week->items()->create([
            'type' => ContentItemType::Text,
            'title' => 'Notes',
            'order' => 1,
            'body' => 'Hi',
        ]);

        $this->asInstructor($instructorA)
            ->postJson(route('api.v1.teach.weeks.items.store', $week), [
                'type' => ContentItemType::Text->value,
                'title' => 'Allowed',
            ])
            ->assertCreated();

        $this->asInstructor($instructorB)
            ->postJson(route('api.v1.teach.weeks.items.store', $week), [
                'type' => ContentItemType::Text->value,
                'title' => 'Denied',
            ])
            ->assertForbidden();

        $this->asInstructor($instructorB)
            ->putJson(route('api.v1.teach.items.update', $item), ['title' => 'Nope'])
            ->assertForbidden();

        $this->asInstructor($instructorB)
            ->deleteJson(route('api.v1.teach.items.destroy', $item))
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

    private function asInstructor(User $user)
    {
        Auth::forgetGuards();

        return $this->withToken($user->createToken('api', ['role:INSTRUCTOR'])->plainTextToken);
    }
}
