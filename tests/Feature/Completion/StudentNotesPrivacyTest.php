<?php

namespace Tests\Feature\Completion;

use App\Enums\RoleType;
use App\Exceptions\AuthorizationException;
use App\Models\User;
use App\Services\Completion\StudentNoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StudentNotesPrivacyTest extends TestCase
{
    use CompletionFixtures;
    use RefreshDatabase;

    #[Test]
    public function a_student_cannot_read_notes_about_themselves(): void
    {
        $offering = $this->offering('NOTE1');
        $instructor = $this->instructorOn($offering);
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);

        $notes = app(StudentNoteService::class);
        $notes->add($instructor, $offering, $student, 'Needs follow-up');

        $this->expectException(AuthorizationException::class);
        $notes->forStudent($student, $offering, $student);
    }

    #[Test]
    public function a_non_staffed_instructor_cannot_read_or_write_notes(): void
    {
        $offering = $this->offering('NOTE2');
        $outsider = User::factory()->withRole(RoleType::Instructor)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);

        $notes = app(StudentNoteService::class);

        try {
            $notes->forStudent($outsider, $offering, $student);
            $this->fail('Expected AuthorizationException on read');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $this->expectException(AuthorizationException::class);
        $notes->add($outsider, $offering, $student, 'Should fail');
    }

    #[Test]
    public function a_student_is_forbidden_on_the_teach_notes_route(): void
    {
        $offering = $this->offering('NOTE3');
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);

        $this->actingAs($student)
            ->get(route('teach.completion.show', ['offering' => $offering, 'student_id' => $student->id]))
            ->assertForbidden();
    }
}
