<?php

namespace App\Services\Attendance;

use App\Enums\EnrollmentStatus;
use App\Models\Announcement;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class RosterService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    /**
     * @return Collection<int, Enrollment>
     */
    public function roster(User $actor, CourseOffering $offering): Collection
    {
        $this->authorize->authorize($actor, 'roster.view', $offering);

        return Enrollment::query()
            ->where('offering_id', $offering->id)
            ->with('student')
            ->orderBy('enrolled_at')
            ->get();
    }

    public function exportCsv(User $actor, CourseOffering $offering): string
    {
        $this->authorize->authorize($actor, 'roster.export', $offering);

        $rows = $this->roster($actor, $offering);
        $lines = [
            implode(',', ['student_id', 'first_name', 'last_name', 'email', 'status', 'date_of_birth', 'enrolled_at']),
        ];

        foreach ($rows as $enrollment) {
            $student = $enrollment->student;
            $lines[] = implode(',', [
                $this->csv($student?->id),
                $this->csv($student?->first_name),
                $this->csv($student?->last_name),
                $this->csv($student?->email),
                $this->csv($enrollment->status->value),
                $this->csv($student?->date_of_birth?->toDateString()),
                $this->csv($enrollment->enrolled_at?->toDateString()),
            ]);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @return Collection<int, array{student: User, date_of_birth: string, days_until: int}>
     */
    public function birthdays(User $actor, CourseOffering $offering, int $windowDays = 14, ?CarbonInterface $from = null): Collection
    {
        $this->authorize->authorize($actor, 'roster.view', $offering);

        $from ??= now()->startOfDay();
        $windowDays = max(1, $windowDays);

        return $this->roster($actor, $offering)
            ->pluck('student')
            ->filter()
            ->filter(fn (User $student) => $student->date_of_birth !== null)
            ->map(function (User $student) use ($from) {
                $next = $student->date_of_birth->copy()->year($from->year);
                if ($next->lt($from)) {
                    $next->addYear();
                }

                return [
                    'student' => $student,
                    'date_of_birth' => $student->date_of_birth->toDateString(),
                    'next_birthday' => $next->toDateString(),
                    'days_until' => $from->diffInDays($next),
                ];
            })
            ->filter(fn (array $row) => $row['days_until'] <= $windowDays)
            ->sortBy('days_until')
            ->values();
    }

    /**
     * Roster-scoped announcement hand-off. Creates an Announcement with the
     * columns that already exist — does not invent S2 publish workflow.
     *
     * @param  array{title: string, body: string}  $data
     */
    public function announce(User $actor, CourseOffering $offering, array $data): Announcement
    {
        $this->authorize->authorize($actor, 'roster.announce', $offering);

        return $this->audit->withAudit($actor, 'roster.announce', function () use ($offering, $actor, $data) {
            return Announcement::query()->create([
                'offering_id' => $offering->id,
                'author_id' => $actor->id,
                'title' => $data['title'],
                'body' => $data['body'],
            ]);
        }, Announcement::class);
    }

    private function csv(?string $value): string
    {
        $value = (string) $value;
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }
}
