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
}
