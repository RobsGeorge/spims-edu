<?php

namespace App\Services\Offerings;

use App\Enums\OfferingStatus;
use App\Models\AcademicYear;
use App\Models\Semester;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;

class SemesterService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    public function createYear(User $actor, array $data): AcademicYear
    {
        $this->authorize->authorize($actor, 'semesters.manage');

        return $this->audit->withAudit($actor, 'academic_years.create', fn () => AcademicYear::query()->create([
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
        ]), 'AcademicYear');
    }

    public function createSemester(User $actor, AcademicYear $year, array $data): Semester
    {
        $this->authorize->authorize($actor, 'semesters.manage');

        return $this->audit->withAudit($actor, 'semesters.create', fn () => Semester::query()->create([
            'academic_year_id' => $year->id,
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'registration_start' => $data['registration_start'],
            'registration_end' => $data['registration_end'],
            'add_drop_end_week' => $data['add_drop_end_week'],
            'last_withdrawal_week' => $data['last_withdrawal_week'],
            'withdrawal_refund_percent' => $data['withdrawal_refund_percent'] ?? 0,
            'status' => OfferingStatus::from($data['status'] ?? OfferingStatus::Draft->value),
        ]), 'Semester');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateYear(User $actor, AcademicYear $year, array $data): AcademicYear
    {
        $this->authorize->authorize($actor, 'semesters.manage');
        $before = $year->only(['name', 'start_date', 'end_date']);

        $year->update([
            'name' => $data['name'] ?? $year->name,
            'start_date' => $data['start_date'] ?? $year->start_date,
            'end_date' => $data['end_date'] ?? $year->end_date,
        ]);

        $fresh = $year->fresh();
        $this->audit->write($actor, 'academic_years.update', 'AcademicYear', $year->id, $before, $fresh->only(['name', 'start_date', 'end_date']));

        return $fresh;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateSemester(User $actor, Semester $semester, array $data): Semester
    {
        $this->authorize->authorize($actor, 'semesters.manage');
        $before = $semester->toArray();

        $semester->update([
            'name' => $data['name'] ?? $semester->name,
            'start_date' => $data['start_date'] ?? $semester->start_date,
            'end_date' => $data['end_date'] ?? $semester->end_date,
            'registration_start' => $data['registration_start'] ?? $semester->registration_start,
            'registration_end' => $data['registration_end'] ?? $semester->registration_end,
            'add_drop_end_week' => $data['add_drop_end_week'] ?? $semester->add_drop_end_week,
            'last_withdrawal_week' => $data['last_withdrawal_week'] ?? $semester->last_withdrawal_week,
            'withdrawal_refund_percent' => $data['withdrawal_refund_percent'] ?? $semester->withdrawal_refund_percent,
            'status' => isset($data['status'])
                ? OfferingStatus::from($data['status'])
                : $semester->status,
        ]);

        $fresh = $semester->fresh();
        $this->audit->write($actor, 'semesters.update', 'Semester', $semester->id, $before, $fresh->toArray());

        return $fresh;
    }
}
