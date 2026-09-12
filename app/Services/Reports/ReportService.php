<?php

namespace App\Services\Reports;

use App\Enums\AcademicStanding;
use App\Enums\ApplicationStatus;
use App\Enums\Currency;
use App\Enums\EnrollmentStatus;
use App\Enums\GradeStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\AuthorizationException;
use App\Models\Application;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\StudentProgram;
use App\Models\User;
use App\Services\Live\AttendanceService;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportService
{
    public const SLUGS = [
        'headcount',
        'admissions',
        'attendance',
        'grades',
        'finance',
        'standing',
    ];

    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly AttendanceService $attendance,
    ) {}

    public function authorizeView(User $actor): void
    {
        $this->authorize->authorize($actor, 'reports.view');
    }

    public function authorizeFinance(User $actor): void
    {
        if ($this->authorize->allows($actor, 'reports.view')
            || $this->authorize->allows($actor, 'finance.invoices')
            || $this->authorize->allows($actor, 'finance.wallet')) {
            return;
        }

        $this->authorize->authorize($actor, 'finance.invoices');
    }

    public function authorizeReport(User $actor, string $report): void
    {
        if ($report === 'finance') {
            $this->authorizeFinance($actor);

            return;
        }

        $this->authorizeView($actor);
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    public function headcountRows(): Collection
    {
        $rows = Enrollment::query()
            ->join('course_offerings', 'course_offerings.id', '=', 'enrollments.offering_id')
            ->join('courses', 'courses.id', '=', 'course_offerings.course_id')
            ->leftJoin('semesters', 'semesters.id', '=', 'course_offerings.semester_id')
            ->leftJoin('student_programs', 'student_programs.id', '=', 'enrollments.student_program_id')
            ->leftJoin('programs', 'programs.id', '=', 'student_programs.program_id')
            ->whereNull('course_offerings.deleted_at')
            ->whereNull('courses.deleted_at')
            // A shadow enrollment created by the legacy importer never appears in a
            // headcount report — see docs/legacy-data-import-plan.md §4.2 and
            // ImportCatalogIsolationTest.
            ->whereNull('enrollments.source_system')
            ->selectRaw('programs.code as program_code')
            ->selectRaw('programs.name as program_name')
            ->selectRaw('courses.code as course_code')
            ->selectRaw('courses.title as course_title')
            ->selectRaw('semesters.name as semester_name')
            ->selectRaw('enrollments.status as enrollment_status')
            ->selectRaw('COUNT(enrollments.id) as headcount')
            ->groupBy(
                'programs.code',
                'programs.name',
                'courses.code',
                'courses.title',
                'semesters.name',
                'enrollments.status',
            )
            ->orderBy('programs.code')
            ->orderBy('courses.code')
            ->orderBy('semesters.name')
            ->get();

        return $rows->map(fn ($row): array => [
            'program_code' => (string) ($row->program_code ?: '—'),
            'program_name' => (string) ($row->program_name ?: '—'),
            'course_code' => (string) $row->course_code,
            'course_title' => (string) $row->course_title,
            'semester' => (string) ($row->semester_name ?: '—'),
            'status' => (string) $row->enrollment_status,
            'headcount' => (string) (int) $row->headcount,
        ]);
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    public function admissionsRows(): Collection
    {
        $counts = Application::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(ApplicationStatus::cases())->map(fn (ApplicationStatus $status): array => [
            'status' => $status->value,
            'count' => (string) (int) ($counts[$status->value] ?? 0),
        ]);
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    public function attendanceRows(): Collection
    {
        $offeringIds = Enrollment::query()->select('offering_id')->distinct();
        $sessionOfferingIds = ClassSession::query()->select('offering_id')->distinct();

        $offerings = CourseOffering::query()
            ->whereNull('source_system')
            ->with('course')
            ->where(function ($q) use ($offeringIds, $sessionOfferingIds) {
                $q->whereIn('id', $offeringIds)
                    ->orWhereIn('id', $sessionOfferingIds);
            })
            ->orderByDesc('created_at')
            ->get();

        return $offerings->map(function (CourseOffering $offering): array {
            $percents = $this->attendance->offeringPercents($offering);
            $values = array_values(array_filter($percents, static fn ($p) => $p !== null));
            $avg = $values === [] ? null : round(array_sum($values) / count($values), 2);
            $sessionCount = ClassSession::query()
                ->where('offering_id', $offering->id)
                ->count();
            $studentCount = Enrollment::query()
                ->where('offering_id', $offering->id)
                ->where('status', EnrollmentStatus::Enrolled)
                ->count();

            return [
                'course_code' => (string) ($offering->course?->code ?? '—'),
                'course_title' => (string) ($offering->course?->title ?? '—'),
                'offering_id' => (string) $offering->id,
                'students' => (string) $studentCount,
                'sessions' => (string) $sessionCount,
                'attendance_percent' => $avg === null ? '—' : (string) $avg,
            ];
        })->values();
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    public function gradeDistributionRows(): Collection
    {
        $rows = Enrollment::query()
            ->join('course_offerings', 'course_offerings.id', '=', 'enrollments.offering_id')
            ->join('courses', 'courses.id', '=', 'course_offerings.course_id')
            ->where('enrollments.grade_status', GradeStatus::Locked->value)
            ->whereNotNull('enrollments.final_letter')
            ->whereNull('course_offerings.deleted_at')
            ->whereNull('courses.deleted_at')
            ->whereNull('enrollments.source_system')
            ->selectRaw('courses.code as course_code')
            ->selectRaw('courses.title as course_title')
            ->selectRaw('enrollments.final_letter as final_letter')
            ->selectRaw('COUNT(enrollments.id) as total')
            ->groupBy('courses.code', 'courses.title', 'enrollments.final_letter')
            ->orderBy('courses.code')
            ->orderBy('enrollments.final_letter')
            ->get();

        return $rows->map(fn ($row): array => [
            'course_code' => (string) $row->course_code,
            'course_title' => (string) $row->course_title,
            'letter' => (string) $row->final_letter,
            'count' => (string) (int) $row->total,
        ]);
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    public function standingRows(): Collection
    {
        return StudentProgram::query()
            ->with(['student', 'program'])
            ->where('academic_standing', AcademicStanding::Probation)
            ->orderBy('enrolled_at')
            ->get()
            ->map(fn (StudentProgram $sp): array => [
                'student' => trim(($sp->student?->first_name ?? '').' '.($sp->student?->last_name ?? '')),
                'email' => (string) ($sp->student?->email ?? ''),
                'program' => (string) ($sp->program?->code ?? '—'),
                'gpa' => $sp->cached_gpa === null ? '—' : number_format((float) $sp->cached_gpa, 2),
                'standing' => (string) ($sp->academic_standing?->value ?? ''),
            ]);
    }

    /**
     * Outstanding vs paid by currency plus aging buckets (0–14 / 15–30 / 31+).
     *
     * @return array{
     *     outstanding: array<string, array{minor: int, formatted: string}>,
     *     paidRevenue: array<string, array{minor: int, formatted: string}>,
     *     aging: list<array<string, string>>
     * }
     */
    public function financeSummary(): array
    {
        $outstandingByCurrency = [];
        $invoices = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Open, InvoiceStatus::Partial])
            ->with('payments')
            ->get();

        $agingMinors = [];

        foreach ($invoices as $invoice) {
            $key = $invoice->currency->value;
            $due = $invoice->amountDue();
            $outstandingByCurrency[$key] = ($outstandingByCurrency[$key] ?? 0) + $due;

            $bucket = $this->agingBucket($invoice);
            $agingMinors[$key][$bucket] = ($agingMinors[$key][$bucket] ?? 0) + $due;
        }

        $paidRows = Payment::query()
            ->where('status', PaymentStatus::Completed)
            ->selectRaw('currency, SUM(amount_minor) as total_minor')
            ->groupBy('currency')
            ->get();

        $paidByCurrency = [];
        foreach ($paidRows as $row) {
            $currencyKey = $row->currency instanceof Currency
                ? $row->currency->value
                : (string) $row->currency;
            $paidByCurrency[$currencyKey] = (int) $row->total_minor;
        }

        $outstanding = $this->formatMinors($outstandingByCurrency);
        $paidRevenue = $this->formatMinors($paidByCurrency);

        $currencies = collect(array_unique(array_merge(
            array_keys($outstandingByCurrency),
            array_keys($paidByCurrency),
            array_keys($agingMinors),
        )))->sort()->values();

        $aging = $currencies->map(function (string $currency) use ($agingMinors, $outstanding, $paidRevenue): array {
            $buckets = $agingMinors[$currency] ?? [];

            return [
                'currency' => $currency,
                'bucket_0_14' => $this->formatOne((int) ($buckets['0_14'] ?? 0), $currency),
                'bucket_15_30' => $this->formatOne((int) ($buckets['15_30'] ?? 0), $currency),
                'bucket_31_plus' => $this->formatOne((int) ($buckets['31_plus'] ?? 0), $currency),
                'outstanding' => $outstanding[$currency]['formatted'] ?? $this->formatOne(0, $currency),
                'paid' => $paidRevenue[$currency]['formatted'] ?? $this->formatOne(0, $currency),
                'outstanding_minor' => (string) ($outstanding[$currency]['minor'] ?? 0),
                'paid_minor' => (string) ($paidRevenue[$currency]['minor'] ?? 0),
                'bucket_0_14_minor' => (string) (int) ($buckets['0_14'] ?? 0),
                'bucket_15_30_minor' => (string) (int) ($buckets['15_30'] ?? 0),
                'bucket_31_plus_minor' => (string) (int) ($buckets['31_plus'] ?? 0),
            ];
        })->values()->all();

        return [
            'outstanding' => $outstanding,
            'paidRevenue' => $paidRevenue,
            'aging' => $aging,
        ];
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    public function financeRows(): Collection
    {
        return collect($this->financeSummary()['aging'])->map(fn (array $row): array => [
            'currency' => $row['currency'],
            'bucket_0_14' => $row['bucket_0_14'],
            'bucket_15_30' => $row['bucket_15_30'],
            'bucket_31_plus' => $row['bucket_31_plus'],
            'outstanding' => $row['outstanding'],
            'paid' => $row['paid'],
            'outstanding_minor' => $row['outstanding_minor'],
            'paid_minor' => $row['paid_minor'],
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    public function headers(string $report): array
    {
        return match ($report) {
            'headcount' => [
                __('reports.col_program_code'),
                __('reports.col_program_name'),
                __('reports.col_course_code'),
                __('reports.col_course_title'),
                __('reports.col_semester'),
                __('reports.col_status'),
                __('reports.col_headcount'),
            ],
            'admissions' => [
                __('reports.col_status'),
                __('reports.col_count'),
            ],
            'attendance' => [
                __('reports.col_course_code'),
                __('reports.col_course_title'),
                __('reports.col_offering'),
                __('reports.col_students'),
                __('reports.col_sessions'),
                __('reports.col_attendance_percent'),
            ],
            'grades' => [
                __('reports.col_course_code'),
                __('reports.col_course_title'),
                __('reports.col_letter'),
                __('reports.col_count'),
            ],
            'finance' => [
                __('reports.col_currency'),
                __('reports.col_aging_0_14'),
                __('reports.col_aging_15_30'),
                __('reports.col_aging_31_plus'),
                __('reports.col_outstanding'),
                __('reports.col_paid'),
                __('reports.col_outstanding_minor'),
                __('reports.col_paid_minor'),
            ],
            'standing' => [
                __('reports.col_student'),
                __('reports.col_email'),
                __('reports.col_program_code'),
                __('reports.col_gpa'),
                __('reports.col_standing'),
            ],
            default => throw new AuthorizationException(__('auth.forbidden')),
        };
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    public function rowsFor(string $report): Collection
    {
        return match ($report) {
            'headcount' => $this->headcountRows(),
            'admissions' => $this->admissionsRows(),
            'attendance' => $this->attendanceRows(),
            'grades' => $this->gradeDistributionRows(),
            'finance' => $this->financeRows(),
            'standing' => $this->standingRows(),
            default => throw new AuthorizationException(__('auth.forbidden')),
        };
    }

    public function paginate(string $report, int $perPage = 25): LengthAwarePaginator
    {
        $rows = $this->rowsFor($report);
        $page = Paginator::resolveCurrentPage();

        return new Paginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    public function exportCsv(User $actor, string $report): StreamedResponse
    {
        if (! in_array($report, self::SLUGS, true)) {
            throw new AuthorizationException(__('auth.forbidden'));
        }

        $this->authorizeReport($actor, $report);

        $this->audit->write(
            $actor,
            'reports.csv',
            'Report',
            $report,
            after: ['report' => $report],
            actorRole: $actor->roleTypes()->first()?->value,
        );

        $headers = $this->headers($report);
        $rows = $this->rowsFor($report);

        return response()->streamDownload(function () use ($headers, $rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, array_values($row));
            }
            fclose($out);
        }, 'report-'.$report.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function agingBucket(Invoice $invoice): string
    {
        $due = $invoice->due_date;
        if ($due === null) {
            return '0_14';
        }

        $daysPastDue = (int) $due->copy()->startOfDay()->diffInDays(now()->startOfDay(), false);

        if ($daysPastDue <= 14) {
            return '0_14';
        }

        if ($daysPastDue <= 30) {
            return '15_30';
        }

        return '31_plus';
    }

    /**
     * @param  array<string, int>  $minorsByCurrency
     * @return array<string, array{minor: int, formatted: string}>
     */
    private function formatMinors(array $minorsByCurrency): array
    {
        $out = [];
        foreach ($minorsByCurrency as $currency => $minor) {
            $enum = Currency::tryFrom((string) $currency);
            if ($enum === null) {
                continue;
            }
            $out[$currency] = [
                'minor' => (int) $minor,
                'formatted' => Money::fromMinor((int) $minor, $enum)->format(),
            ];
        }
        ksort($out);

        return $out;
    }

    private function formatOne(int $minor, string $currency): string
    {
        $enum = Currency::tryFrom($currency);
        if ($enum === null) {
            return (string) $minor;
        }

        return Money::fromMinor($minor, $enum)->format();
    }
}
