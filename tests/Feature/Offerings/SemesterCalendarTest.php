<?php

namespace Tests\Feature\Offerings;

use App\Enums\RoleType;
use App\Enums\SemesterStatus;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Gate tests for Step 5 — Semester Calendar state machine.
 *
 * Filter matches: Offerings (via namespace Tests\Feature\Offerings)
 */
class SemesterCalendarTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------ helpers

    private function makeAdmin(): User
    {
        return User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
    }

    private function makeStudent(): User
    {
        return User::factory()->withRole(RoleType::Student)->create();
    }

    private function createYear(User $adm): AcademicYear
    {
        $this->actingAs($adm)->post(route('admin.academic-years.store'), [
            'name'       => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date'   => '2027-06-30',
        ]);
        return AcademicYear::query()->latest('id')->first();
    }

    private function createSemester(User $adm, AcademicYear $year, string $status = 'DRAFT'): Semester
    {
        $this->actingAs($adm)->post(route('admin.semesters.store', $year), [
            'name'                     => 'Fall',
            'start_date'               => '2026-09-01',
            'end_date'                 => '2026-12-20',
            'registration_start'       => '2026-08-01',
            'registration_end'         => '2026-08-31',
            'add_drop_end_week'        => 2,
            'last_withdrawal_week'     => 8,
            'withdrawal_refund_percent' => 50,
        ]);
        $sem = Semester::query()->latest('id')->first();
        if ($status !== 'DRAFT') {
            $sem->update(['status' => SemesterStatus::from($status)]);
        }
        return $sem->fresh();
    }

    // ------------------------------------------------------------------ legal transitions + audit

    #[Test]
    public function draft_to_open_writes_audit_log(): void
    {
        $adm = $this->makeAdmin();
        $year = $this->createYear($adm);
        $semester = $this->createSemester($adm, $year, 'DRAFT');

        $this->assertSame(SemesterStatus::Draft, $semester->status);

        $this->actingAs($adm)
            ->post(route('admin.semesters.transition', $semester), ['status' => 'OPEN'])
            ->assertRedirect();

        $semester->refresh();
        $this->assertSame(SemesterStatus::Open, $semester->status);

        $this->assertDatabaseHas('audit_logs', [
            'action'      => 'semesters.status_transition',
            'entity_type' => 'Semester',
        ]);
    }

    #[Test]
    public function open_to_in_progress_writes_audit_log(): void
    {
        $adm = $this->makeAdmin();
        $year = $this->createYear($adm);
        $semester = $this->createSemester($adm, $year, 'OPEN');

        $this->actingAs($adm)
            ->post(route('admin.semesters.transition', $semester), ['status' => 'IN_PROGRESS'])
            ->assertRedirect();

        $this->assertSame(SemesterStatus::InProgress, $semester->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'semesters.status_transition']);
    }

    #[Test]
    public function in_progress_to_closed_writes_audit_log(): void
    {
        $adm = $this->makeAdmin();
        $year = $this->createYear($adm);
        $semester = $this->createSemester($adm, $year, 'IN_PROGRESS');

        $this->actingAs($adm)
            ->post(route('admin.semesters.transition', $semester), ['status' => 'CLOSED'])
            ->assertRedirect();

        $this->assertSame(SemesterStatus::Closed, $semester->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'semesters.status_transition']);
    }

    // ------------------------------------------------------------------ illegal transitions (full matrix)

    public static function illegalTransitionMatrix(): array
    {
        return [
            'CLOSED->DRAFT'       => ['CLOSED',      'DRAFT'],
            'CLOSED->OPEN'        => ['CLOSED',      'OPEN'],
            'CLOSED->IN_PROGRESS' => ['CLOSED',      'IN_PROGRESS'],
            'OPEN->CLOSED'        => ['OPEN',        'CLOSED'],
            'DRAFT->IN_PROGRESS'  => ['DRAFT',       'IN_PROGRESS'],
            'DRAFT->CLOSED'       => ['DRAFT',       'CLOSED'],
        ];
    }

    #[Test]
    #[DataProvider('illegalTransitionMatrix')]
    public function illegal_transition_is_rejected(string $fromStatus, string $toStatus): void
    {
        $adm = $this->makeAdmin();
        $year = $this->createYear($adm);
        $semester = $this->createSemester($adm, $year, $fromStatus);

        $auditCountBefore = AuditLog::query()
            ->where('action', 'semesters.status_transition')
            ->count();

        $this->actingAs($adm)
            ->post(route('admin.semesters.transition', $semester), ['status' => $toStatus])
            ->assertSessionHasErrors('status');

        // Status must be unchanged
        $this->assertSame(
            SemesterStatus::from($fromStatus),
            $semester->fresh()->status,
            "Semester status should remain {$fromStatus} after illegal {$fromStatus}->{$toStatus}",
        );

        // No audit written for illegal transition
        $auditCountAfter = AuditLog::query()
            ->where('action', 'semesters.status_transition')
            ->count();
        $this->assertSame($auditCountBefore, $auditCountAfter, 'Illegal transition must not write audit log');
    }

    // ------------------------------------------------------------------ view-only user

    #[Test]
    public function view_only_user_gets_403_on_transition_post(): void
    {
        $student = $this->makeStudent();
        $adm = $this->makeAdmin();
        $year = $this->createYear($adm);
        $semester = $this->createSemester($adm, $year, 'DRAFT');

        $this->actingAs($student)
            ->post(route('admin.semesters.transition', $semester), ['status' => 'OPEN'])
            ->assertForbidden();
    }

    #[Test]
    public function view_only_user_index_page_has_no_advance_buttons(): void
    {
        $adm = $this->makeAdmin();
        $year = $this->createYear($adm);
        $this->createSemester($adm, $year, 'DRAFT');

        $student = $this->makeStudent();

        $this->actingAs($student)
            ->get(route('admin.semesters.index'))
            ->assertOk()
            ->assertDontSee(route('admin.semesters.transition', Semester::query()->first()));
    }

    // ------------------------------------------------------------------ RTL rendering

    #[Test]
    public function index_renders_without_errors_with_dir_rtl(): void
    {
        $adm = $this->makeAdmin();
        $year = $this->createYear($adm);
        $this->createSemester($adm, $year, 'DRAFT');

        // The index page must render (200) — RTL is a client-side attribute,
        // but we can assert the view contains logical CSS property markers.
        $response = $this->actingAs($adm)
            ->get(route('admin.semesters.index'))
            ->assertOk();

        // RTL-safe marker: inset-inline-start (logical CSS) must appear in output
        $response->assertSee('inset-inline-start', false);
    }

    // ------------------------------------------------------------------ index page reachable

    #[Test]
    public function admin_can_see_semester_calendar_index(): void
    {
        $adm = $this->makeAdmin();
        $year = $this->createYear($adm);
        $this->createSemester($adm, $year, 'OPEN');

        $this->actingAs($adm)
            ->get(route('admin.semesters.index'))
            ->assertOk()
            ->assertSee($year->name);
    }

    #[Test]
    public function year_selector_switches_displayed_year(): void
    {
        $adm = $this->makeAdmin();

        $this->actingAs($adm)->post(route('admin.academic-years.store'), [
            'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-06-30',
        ]);
        $this->actingAs($adm)->post(route('admin.academic-years.store'), [
            'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-06-30',
        ]);

        $year1 = AcademicYear::query()->where('name', '2025/2026')->first();
        $year2 = AcademicYear::query()->where('name', '2026/2027')->first();

        // Both years visible in selector
        $this->actingAs($adm)
            ->get(route('admin.semesters.index'))
            ->assertOk()
            ->assertSee('2025/2026')
            ->assertSee('2026/2027');

        // Selecting year1 shows its semesters
        $this->actingAs($adm)
            ->get(route('admin.semesters.index', ['year' => $year1->id]))
            ->assertOk()
            ->assertSee('2025/2026');
    }
}
