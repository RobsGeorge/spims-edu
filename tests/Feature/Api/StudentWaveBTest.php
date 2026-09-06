<?php

namespace Tests\Feature\Api;

use App\Enums\AssessmentMode;
use App\Enums\ProgramType;
use App\Enums\StudentProgramStatus;
use App\Models\AcademicRecord;
use App\Models\Assessment;
use App\Models\GradingScheme;
use App\Models\Program;
use App\Models\StudentProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StudentWaveBTest extends TestCase
{
    use RefreshDatabase;
    use StudentApiFixtures;

    #[Test]
    public function offerings_list_includes_only_enrolled_offerings(): void
    {
        $bundle = $this->playerBundle('ENR1');
        $other = $this->offering('SKIP');
        $this->enroll($this->student(), $other);

        $ids = collect($this->withToken($this->apiToken($bundle['student']))
            ->getJson(route('api.v1.offerings.index'))
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($bundle['offering']->id));
        $this->assertFalse($ids->contains($other->id));
        $this->assertSame(1, $this->withToken($this->apiToken($bundle['student']))
            ->getJson(route('api.v1.offerings.index'))
            ->json('meta.total'));
    }

    #[Test]
    public function item_complete_and_week_complete_succeed_for_enrolled_student(): void
    {
        $bundle = $this->playerBundle('CMP1');
        $token = $this->apiToken($bundle['student']);

        $this->withToken($token)
            ->postJson(route('api.v1.items.complete', $bundle['video']))
            ->assertOk()
            ->assertJsonPath('data.item_id', $bundle['video']->id);

        $this->withToken($token)
            ->postJson(route('api.v1.items.complete', $bundle['reading']))
            ->assertOk();

        $this->withToken($token)
            ->postJson(route('api.v1.offerings.weeks.complete', [$bundle['offering'], $bundle['week1']]))
            ->assertOk()
            ->assertJsonPath('data.completed', true);
    }

    #[Test]
    public function grades_omit_unreleased_items(): void
    {
        $bundle = $this->playerBundle('GRD1');
        $released = $this->assignmentOn($bundle['offering'], released: true, title: 'Visible essay');
        $hidden = $this->assignmentOn($bundle['offering'], released: false, title: 'Hidden essay');
        Assessment::query()->create([
            'offering_id' => $bundle['offering']->id,
            'title' => 'Hidden quiz',
            'mode' => AssessmentMode::Quiz,
            'released' => false,
            'attempts_allowed' => 1,
            'max_points' => 10,
        ]);
        Assessment::query()->create([
            'offering_id' => $bundle['offering']->id,
            'title' => 'Visible quiz',
            'mode' => AssessmentMode::Quiz,
            'released' => true,
            'attempts_allowed' => 1,
            'max_points' => 10,
        ]);

        $titles = collect($this->withToken($this->apiToken($bundle['student']))
            ->getJson(route('api.v1.offerings.grades', $bundle['offering']))
            ->assertOk()
            ->json('data.items'))->pluck('title');

        $this->assertTrue($titles->contains('Visible essay'));
        $this->assertTrue($titles->contains('Visible quiz'));
        $this->assertFalse($titles->contains('Hidden quiz'));
        $this->assertFalse($titles->contains('Hidden essay'));
    }

    #[Test]
    public function transcript_returns_200_with_records(): void
    {
        $student = $this->student();
        $course = \App\Models\Course::query()->create([
            'code' => 'TR1',
            'title' => 'Transcript Course',
            'credit_hours' => 3,
            'active' => true,
        ]);
        AcademicRecord::query()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'letter_grade' => 'A',
            'percent' => 95,
            'gpa_points' => 4,
            'credit_hours' => 3,
            'term' => 'Fall',
            'is_passing' => true,
            'completed_at' => now()->subMonth(),
        ]);

        $this->withToken($this->apiToken($student))
            ->getJson(route('api.v1.transcript'))
            ->assertOk()
            ->assertJsonPath('data.gpa', 4)
            ->assertJsonPath('data.records.0.course_code', 'TR1');
    }

    #[Test]
    public function degree_audit_404s_for_another_students_program(): void
    {
        $this->seed(\Database\Seeders\GradingSchemeSeeder::class);
        $mine = $this->student();
        $theirs = $this->student();
        $program = Program::query()->create([
            'code' => 'DIP',
            'name' => 'Diploma',
            'type' => ProgramType::Diploma,
            'max_credits_per_semester' => 15,
            'max_courses_per_semester' => 5,
            'max_semesters_to_graduate' => 8,
            'elective_credits_required' => 0,
            'grading_scheme_id' => GradingScheme::query()->first()->id,
            'active' => true,
        ]);
        $sp = StudentProgram::query()->create([
            'student_id' => $theirs->id,
            'program_id' => $program->id,
            'status' => StudentProgramStatus::Active,
            'enrolled_at' => now(),
        ]);

        $this->withToken($this->apiToken($mine))
            ->getJson(route('api.v1.degree-audit.show', $sp))
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    #[Test]
    public function gated_item_hides_body(): void
    {
        $bundle = $this->playerBundle('GATE');
        $token = $this->apiToken($bundle['student']);

        $locked = $this->withToken($token)
            ->getJson(route('api.v1.items.show', $bundle['locked']))
            ->assertOk()
            ->json('data');

        $this->assertArrayNotHasKey('body', $locked);
        $this->assertFalse($locked['unlocked']);
        $this->assertSame('Week 2 text', $locked['title']);

        $open = $this->withToken($token)
            ->getJson(route('api.v1.items.show', $bundle['video']))
            ->assertOk()
            ->json('data');
        $this->assertSame('Video body', $open['body']);
        $this->assertSame('999', $open['vimeo_id']);
    }
}
