<?php

namespace App\Services\SuperAdmin;

use App\Enums\ApplicationStatus;
use App\Enums\Currency;
use App\Enums\EnrollmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OfferingStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserStatus;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\ClassSession;
use App\Models\CommunicationLog;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Semester;
use App\Models\User;
use App\Services\Live\AttendanceService;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SchoolReportService
{
    public const SLUGS = [
        'census',
        'finance',
        'audit',
        'admissions',
        'enrollment',
        'attendance',
        'communications',
        'queue',
    ];

    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly AttendanceService $attendance,
    ) {}

    public function authorize(User $actor): void
    {
        $this->authorize->authorize($actor, 'reports.school');
    }

    /**
     * @return list<array{slug: string, icon: string, available: bool, table: ?string, kind: string}>
     */
    public function catalog(): array
    {
        $items = [];
        foreach (self::SLUGS as $slug) {
            $table = $this->requiredTable($slug);
            $items[] = [
                'slug' => $slug,
                'icon' => $this->icon($slug),
                'available' => $this->isAvailable($slug),
                'table' => $table,
                'kind' => $this->kind($slug),
            ];
        }

        return $items;
    }

    public function isAvailable(string $slug): bool
    {
        if (! in_array($slug, self::SLUGS, true)) {
            return false;
        }

        $table = $this->requiredTable($slug);

        return $table === null || Schema::hasTable($table);
    }

    /**
     * @return array{from: Carbon, to: Carbon, source: string, label: string}
     */
    public function resolveRange(?string $from, ?string $to): array
    {
        $defaults = $this->defaultRange();
        $start = $defaults['from'];
        $end = $defaults['to'];
        $source = $defaults['source'];

        if (is_string($from) && $from !== '') {
            if (! $this->isDate($from)) {
                throw ValidationException::withMessages([
                    'from' => [__('school_reports.range_invalid')],
                ]);
            }
            $start = Carbon::parse($from)->startOfDay();
            $source = 'custom';
        }

        if (is_string($to) && $to !== '') {
            if (! $this->isDate($to)) {
                throw ValidationException::withMessages([
                    'to' => [__('school_reports.range_invalid')],
                ]);
            }
            $end = Carbon::parse($to)->endOfDay();
            $source = 'custom';
        }

        if ($start->gt($end)) {
            throw ValidationException::withMessages([
                'from' => [__('school_reports.range_order')],
            ]);
        }

        if ($start->diffInDays($end) > 1100) {
            throw ValidationException::withMessages([
                'to' => [__('school_reports.range_too_long')],
            ]);
        }

        return [
            'from' => $start,
            'to' => $end,
            'source' => $source,
            'label' => $defaults['label'],
        ];
    }

    /**
     * @return array{
     *     slug: string,
     *     headers: list<string>,
     *     rows: list<list<string>>,
     *     summary: list<array{label: string, value: string, help: string, minor?: int}>,
     *     deep_links: list<array{url: string, label: string}>,
     *     notes: list<string>
     * }
     */
    public function payload(string $slug, Carbon $from, Carbon $to): array
    {
        return match ($slug) {
            'census' => $this->censusPayload(),
            'finance' => $this->financePayload($from, $to),
            'audit' => $this->auditPayload($from, $to),
            'admissions' => $this->admissionsPayload(),
            'enrollment' => $this->enrollmentPayload(),
            'attendance' => $this->attendancePayload(),
            'communications' => $this->communicationsPayload($from, $to),
            'queue' => $this->queuePayload(),
            default => throw ValidationException::withMessages([
                'report' => [__('school_reports.unknown')],
            ]),
        };
    }

    public function exportCsv(User $actor, string $slug, Carbon $from, Carbon $to): StreamedResponse
    {
        $this->authorize($actor);
        if (! $this->isAvailable($slug)) {
            abort(404);
        }

        $payload = $this->payload($slug, $from, $to);

        $this->audit->write(
            $actor,
            'reports.school.export',
            'Report',
            null,
            null,
            [
                'report' => $slug,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'rows' => count($payload['rows']),
            ],
            $actor->roleTypes()->first()?->value,
        );

        $filename = 'spims-report-'.$slug.'-'.$from->toDateString().'-to-'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($payload): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $payload['headers']);
            foreach ($payload['rows'] as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{from: Carbon, to: Carbon, source: string, label: string}
     */
    public function defaultRange(): array
    {
        $semester = null;
        if (Schema::hasTable('semesters')) {
            $semester = Semester::query()
                ->where('status', OfferingStatus::InProgress)
                ->orderByDesc('start_date')
                ->first()
                ?? Semester::query()
                    ->where('status', OfferingStatus::Open)
                    ->orderByDesc('start_date')
                    ->first();
        }

        if ($semester !== null && $semester->start_date !== null) {
            $from = $semester->start_date->copy()->startOfDay();
            $to = ($semester->end_date ?? now())->copy()->endOfDay();
            if ($to->gt(now()->endOfDay())) {
                $to = now()->endOfDay();
            }

            return [
                'from' => $from,
                'to' => $to,
                'source' => 'semester',
                'label' => (string) $semester->name,
            ];
        }

        return [
            'from' => now()->subDays(90)->startOfDay(),
            'to' => now()->endOfDay(),
            'source' => 'trailing_90',
            'label' => __('school_reports.range_trailing'),
        ];
    }

    /**
     * @return array{slug: string, headers: list<string>, rows: list<list<string>>, summary: list<array{label: string, value: string, help: string}>, deep_links: list<array{url: string, label: string}>, notes: list<string>}
     */
    private function censusPayload(): array
    {
        $rows = [];
        $grouped = User::query()
            ->join('user_roles', 'user_roles.user_id', '=', 'users.id')
            ->whereNull('users.deleted_at')
            ->selectRaw('user_roles.role as role')
            ->selectRaw('users.status as status')
            ->selectRaw('users.preferred_locale as locale')
            ->selectRaw('COUNT(DISTINCT users.id) as total')
            ->groupBy('user_roles.role', 'users.status', 'users.preferred_locale')
            ->orderBy('user_roles.role')
            ->orderBy('users.status')
            ->orderBy('users.preferred_locale')
            ->get();

        foreach ($grouped as $row) {
            $rows[] = [
                $this->scalar($row->role),
                $this->scalar($row->status),
                $this->scalar($row->locale),
                (string) (int) $row->total,
            ];
        }

        $people = User::query()->whereNull('deleted_at')->count();
        $byStatus = [];
        foreach (UserStatus::cases() as $status) {
            $byStatus[$status->value] = User::query()->where('status', $status)->whereNull('deleted_at')->count();
        }

        $summary = [
            [
                'label' => __('school_reports.census_people'),
                'value' => number_format($people),
                'help' => __('school_reports.census_people_help'),
            ],
        ];
        foreach ($byStatus as $status => $count) {
            $summary[] = [
                'label' => $status,
                'value' => number_format($count),
                'help' => __('school_reports.census_status_help', ['status' => $status]),
            ];
        }

        return [
            'slug' => 'census',
            'headers' => [
                __('school_reports.col_role'),
                __('school_reports.col_status'),
                __('school_reports.col_locale'),
                __('school_reports.col_count'),
            ],
            'rows' => $rows,
            'summary' => $summary,
            'deep_links' => $this->links([
                ['admin.users.index', 'school_reports.link_people'],
            ]),
            'notes' => [
                __('school_reports.census_note_snapshot'),
                __('school_reports.census_note_roles'),
            ],
        ];
    }

    /**
     * @return array{slug: string, headers: list<string>, rows: list<list<string>>, summary: list<array{label: string, value: string, help: string, minor?: int}>, deep_links: list<array{url: string, label: string}>, notes: list<string>}
     */
    private function financePayload(Carbon $from, Carbon $to): array
    {
        $outstandingByCurrency = [];
        $invoices = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Open, InvoiceStatus::Partial])
            ->with('payments')
            ->get();

        foreach ($invoices as $invoice) {
            $key = $invoice->currency->value;
            $outstandingByCurrency[$key] = ($outstandingByCurrency[$key] ?? 0) + $invoice->amountDue();
        }

        $paidRows = Payment::query()
            ->where('status', PaymentStatus::Completed)
            ->whereBetween('created_at', [$from, $to])
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

        $currencies = collect(array_unique(array_merge(
            array_keys($outstandingByCurrency),
            array_keys($paidByCurrency),
        )))->sort()->values();

        $rows = [];
        $summary = [];
        foreach ($currencies as $currency) {
            $outstandingMinor = (int) ($outstandingByCurrency[$currency] ?? 0);
            $paidMinor = (int) ($paidByCurrency[$currency] ?? 0);
            $rows[] = [
                $currency,
                (string) $outstandingMinor,
                $this->formatMoney($outstandingMinor, $currency),
                (string) $paidMinor,
                $this->formatMoney($paidMinor, $currency),
            ];
            $summary[] = [
                'label' => __('school_reports.finance_outstanding', ['currency' => $currency]),
                'value' => $this->formatMoney($outstandingMinor, $currency),
                'help' => __('school_reports.finance_outstanding_help'),
                'minor' => $outstandingMinor,
            ];
            $summary[] = [
                'label' => __('school_reports.finance_paid', ['currency' => $currency]),
                'value' => $this->formatMoney($paidMinor, $currency),
                'help' => __('school_reports.finance_paid_help'),
                'minor' => $paidMinor,
            ];
        }

        return [
            'slug' => 'finance',
            'headers' => [
                __('school_reports.col_currency'),
                __('school_reports.col_outstanding_minor'),
                __('school_reports.col_outstanding'),
                __('school_reports.col_paid_minor'),
                __('school_reports.col_paid'),
            ],
            'rows' => $rows,
            'summary' => $summary,
            'deep_links' => $this->links([
                ['admin.finance.reports', 'school_reports.link_finance_ops'],
                ['admin.reports.finance', 'school_reports.link_aging'],
            ]),
            'notes' => [
                __('school_reports.finance_note_integers'),
                __('school_reports.finance_note_range'),
            ],
        ];
    }

    /**
     * @return array{slug: string, headers: list<string>, rows: list<list<string>>, summary: list<array{label: string, value: string, help: string}>, deep_links: list<array{url: string, label: string}>, notes: list<string>}
     */
    private function auditPayload(Carbon $from, Carbon $to): array
    {
        $dayExpr = $this->dateExpression('created_at');
        $daily = AuditLog::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw($dayExpr.' as day')
            ->selectRaw('COUNT(*) as total')
            ->groupBy(DB::raw($dayExpr))
            ->orderBy('day')
            ->get();

        $rows = [];
        $total = 0;
        foreach ($daily as $row) {
            $count = (int) $row->total;
            $total += $count;
            $rows[] = [
                'day',
                (string) $row->day,
                (string) $count,
            ];
        }

        $actors = AuditLog::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('actor_id, COUNT(*) as total')
            ->groupBy('actor_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        foreach ($actors as $row) {
            $actor = $row->actor_id
                ? User::query()->withTrashed()->find($row->actor_id)
                : null;
            $rows[] = [
                'actor',
                $actor?->email ?? __('school_reports.audit_system'),
                (string) (int) $row->total,
            ];
        }

        return [
            'slug' => 'audit',
            'headers' => [
                __('school_reports.col_kind'),
                __('school_reports.col_key'),
                __('school_reports.col_count'),
            ],
            'rows' => $rows,
            'summary' => [
                [
                    'label' => __('school_reports.audit_total'),
                    'value' => number_format($total),
                    'help' => __('school_reports.audit_total_help'),
                ],
                [
                    'label' => __('school_reports.audit_days'),
                    'value' => number_format($daily->count()),
                    'help' => __('school_reports.audit_days_help'),
                ],
            ],
            'deep_links' => $this->links([
                ['superadmin.audit.index', 'school_reports.link_audit'],
            ]),
            'notes' => [
                __('school_reports.audit_note_append'),
                __('school_reports.audit_note_csv'),
            ],
        ];
    }

    /**
     * @return array{slug: string, headers: list<string>, rows: list<list<string>>, summary: list<array{label: string, value: string, help: string}>, deep_links: list<array{url: string, label: string}>, notes: list<string>}
     */
    private function admissionsPayload(): array
    {
        $grouped = Application::query()
            ->leftJoin('application_forms', 'application_forms.id', '=', 'applications.form_id')
            ->selectRaw('application_forms.name as form_name')
            ->selectRaw('applications.status as status')
            ->selectRaw('COUNT(applications.id) as total')
            ->groupBy('application_forms.name', 'applications.status')
            ->orderBy('application_forms.name')
            ->orderBy('applications.status')
            ->get();

        $rows = [];
        foreach ($grouped as $row) {
            $rows[] = [
                $this->scalar($row->form_name),
                $this->scalar($row->status),
                (string) (int) $row->total,
            ];
        }

        $byStatus = Application::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $summary = [];
        foreach (ApplicationStatus::cases() as $status) {
            $summary[] = [
                'label' => $status->value,
                'value' => number_format((int) ($byStatus[$status->value] ?? 0)),
                'help' => __('school_reports.admissions_status_help', ['status' => $status->value]),
            ];
        }

        return [
            'slug' => 'admissions',
            'headers' => [
                __('school_reports.col_form'),
                __('school_reports.col_status'),
                __('school_reports.col_count'),
            ],
            'rows' => $rows,
            'summary' => $summary,
            'deep_links' => $this->links([
                ['admin.applications.index', 'school_reports.link_applications'],
                ['admin.reports.admissions', 'school_reports.link_registrar_admissions'],
            ]),
            'notes' => [
                __('school_reports.admissions_note_snapshot'),
            ],
        ];
    }

    /**
     * @return array{slug: string, headers: list<string>, rows: list<list<string>>, summary: list<array{label: string, value: string, help: string}>, deep_links: list<array{url: string, label: string}>, notes: list<string>}
     */
    private function enrollmentPayload(): array
    {
        $grouped = Enrollment::query()
            ->join('course_offerings', 'course_offerings.id', '=', 'enrollments.offering_id')
            ->join('courses', 'courses.id', '=', 'course_offerings.course_id')
            ->whereNull('course_offerings.deleted_at')
            ->whereNull('courses.deleted_at')
            ->selectRaw('courses.code as course_code')
            ->selectRaw('courses.title as course_title')
            ->selectRaw('enrollments.offering_id as offering_id')
            ->selectRaw('enrollments.status as status')
            ->selectRaw('COUNT(enrollments.id) as total')
            ->groupBy('courses.code', 'courses.title', 'enrollments.offering_id', 'enrollments.status')
            ->orderBy('courses.code')
            ->orderBy('enrollments.status')
            ->get();

        $rows = [];
        foreach ($grouped as $row) {
            $rows[] = [
                $this->scalar($row->course_code),
                $this->scalar($row->course_title),
                $this->scalar($row->offering_id),
                $this->scalar($row->status),
                (string) (int) $row->total,
            ];
        }

        $summary = [];
        foreach (EnrollmentStatus::cases() as $status) {
            $summary[] = [
                'label' => $status->value,
                'value' => number_format(
                    Enrollment::query()->where('status', $status)->count()
                ),
                'help' => __('school_reports.enrollment_status_help', ['status' => $status->value]),
            ];
        }

        return [
            'slug' => 'enrollment',
            'headers' => [
                __('school_reports.col_course_code'),
                __('school_reports.col_course_title'),
                __('school_reports.col_offering'),
                __('school_reports.col_status'),
                __('school_reports.col_count'),
            ],
            'rows' => $rows,
            'summary' => $summary,
            'deep_links' => $this->links([
                ['admin.enrollments.index', 'school_reports.link_enrollment'],
                ['admin.reports.headcount', 'school_reports.link_headcount'],
            ]),
            'notes' => [
                __('school_reports.enrollment_note_snapshot'),
            ],
        ];
    }

    /**
     * @return array{slug: string, headers: list<string>, rows: list<list<string>>, summary: list<array{label: string, value: string, help: string}>, deep_links: list<array{url: string, label: string}>, notes: list<string>}
     */
    private function attendancePayload(): array
    {
        $offeringIds = Enrollment::query()->select('offering_id')->distinct();
        $sessionOfferingIds = ClassSession::query()->select('offering_id')->distinct();
        $offerings = CourseOffering::query()
            ->with('course')
            ->where(function ($q) use ($offeringIds, $sessionOfferingIds): void {
                $q->whereIn('id', $offeringIds)
                    ->orWhereIn('id', $sessionOfferingIds);
            })
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $rows = [];
        $withRate = 0;
        $rateSum = 0.0;
        foreach ($offerings as $offering) {
            $percents = $this->attendance->offeringPercents($offering);
            $values = array_values(array_filter($percents, static fn ($p) => $p !== null));
            $avg = $values === [] ? null : round(array_sum($values) / count($values), 2);
            if ($avg !== null) {
                $withRate++;
                $rateSum += $avg;
            }
            $sessionCount = ClassSession::query()->where('offering_id', $offering->id)->count();
            $studentCount = Enrollment::query()
                ->where('offering_id', $offering->id)
                ->where('status', EnrollmentStatus::Enrolled)
                ->count();
            $rows[] = [
                (string) ($offering->course?->code ?? '—'),
                (string) ($offering->course?->title ?? '—'),
                (string) $offering->id,
                (string) $studentCount,
                (string) $sessionCount,
                $avg === null ? '—' : (string) $avg,
            ];
        }

        $schoolAvg = $withRate === 0 ? '—' : (string) round($rateSum / $withRate, 2);

        return [
            'slug' => 'attendance',
            'headers' => [
                __('school_reports.col_course_code'),
                __('school_reports.col_course_title'),
                __('school_reports.col_offering'),
                __('school_reports.col_students'),
                __('school_reports.col_sessions'),
                __('school_reports.col_attendance_percent'),
            ],
            'rows' => $rows,
            'summary' => [
                [
                    'label' => __('school_reports.attendance_offerings'),
                    'value' => number_format($offerings->count()),
                    'help' => __('school_reports.attendance_offerings_help'),
                ],
                [
                    'label' => __('school_reports.attendance_school_avg'),
                    'value' => $schoolAvg,
                    'help' => __('school_reports.attendance_school_avg_help'),
                ],
            ],
            'deep_links' => $this->links([
                ['admin.reports.attendance', 'school_reports.link_registrar_attendance'],
            ]),
            'notes' => [
                __('school_reports.attendance_note_cap'),
            ],
        ];
    }

    /**
     * @return array{slug: string, headers: list<string>, rows: list<list<string>>, summary: list<array{label: string, value: string, help: string}>, deep_links: list<array{url: string, label: string}>, notes: list<string>}
     */
    private function communicationsPayload(Carbon $from, Carbon $to): array
    {
        $grouped = CommunicationLog::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('channel, status, COUNT(*) as total')
            ->groupBy('channel', 'status')
            ->orderBy('channel')
            ->orderBy('status')
            ->get();

        $rows = [];
        $total = 0;
        foreach ($grouped as $row) {
            $count = (int) $row->total;
            $total += $count;
            $rows[] = [
                $this->scalar($row->channel),
                $this->scalar($row->status),
                (string) $count,
            ];
        }

        return [
            'slug' => 'communications',
            'headers' => [
                __('school_reports.col_channel'),
                __('school_reports.col_status'),
                __('school_reports.col_count'),
            ],
            'rows' => $rows,
            'summary' => [
                [
                    'label' => __('school_reports.communications_total'),
                    'value' => number_format($total),
                    'help' => __('school_reports.communications_total_help'),
                ],
            ],
            'deep_links' => $this->links([
                ['admin.communications.report', 'school_reports.link_communications'],
            ]),
            'notes' => [
                __('school_reports.communications_note_range'),
            ],
        ];
    }

    /**
     * @return array{slug: string, headers: list<string>, rows: list<list<string>>, summary: list<array{label: string, value: string, help: string}>, deep_links: list<array{url: string, label: string}>, notes: list<string>}
     */
    private function queuePayload(): array
    {
        $failed = Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 0;
        $pending = Schema::hasTable('jobs') ? (int) DB::table('jobs')->count() : 0;

        return [
            'slug' => 'queue',
            'headers' => [
                __('school_reports.col_metric'),
                __('school_reports.col_count'),
            ],
            'rows' => [
                ['failed_jobs', (string) $failed],
                ['jobs', (string) $pending],
            ],
            'summary' => [
                [
                    'label' => __('school_reports.queue_failed'),
                    'value' => number_format($failed),
                    'help' => __('school_reports.queue_failed_help'),
                ],
                [
                    'label' => __('school_reports.queue_pending'),
                    'value' => number_format($pending),
                    'help' => __('school_reports.queue_pending_help'),
                ],
            ],
            'deep_links' => $this->links([
                ['superadmin.observability.index', 'school_reports.link_observability'],
            ]),
            'notes' => [
                __('school_reports.queue_note_sa6'),
            ],
        ];
    }

    private function requiredTable(string $slug): ?string
    {
        return match ($slug) {
            'census' => 'users',
            'finance' => 'invoices',
            'audit' => 'audit_logs',
            'admissions' => 'applications',
            'enrollment' => 'enrollments',
            'attendance' => 'class_sessions',
            'communications' => 'communication_logs',
            'queue' => 'failed_jobs',
            default => null,
        };
    }

    private function icon(string $slug): string
    {
        return match ($slug) {
            'census' => 'bi-people',
            'finance' => 'bi-cash-stack',
            'audit' => 'bi-journal-text',
            'admissions' => 'bi-funnel',
            'enrollment' => 'bi-person-plus',
            'attendance' => 'bi-calendar-check',
            'communications' => 'bi-envelope-paper',
            'queue' => 'bi-hourglass-split',
            default => 'bi-bar-chart',
        };
    }

    private function kind(string $slug): string
    {
        return match ($slug) {
            'finance' => 'mixed',
            'audit', 'communications' => 'range',
            default => 'snapshot',
        };
    }

    /**
     * @param  list<array{0: string, 1: string}>  $pairs
     * @return list<array{url: string, label: string}>
     */
    private function links(array $pairs): array
    {
        $out = [];
        foreach ($pairs as [$route, $labelKey]) {
            if (! Route::has($route)) {
                continue;
            }
            $out[] = [
                'url' => route($route),
                'label' => __($labelKey),
            ];
        }

        return $out;
    }

    private function formatMoney(int $minor, string $currency): string
    {
        $enum = Currency::tryFrom($currency);
        if ($enum === null) {
            return (string) $minor;
        }

        return Money::fromMinor($minor, $enum)->format();
    }

    private function scalar(mixed $value, string $empty = '—'): string
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }
        if ($value === null || $value === '') {
            return $empty;
        }

        return (string) $value;
    }

    private function isDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)
            && strtotime($value) !== false;
    }

    private function dateExpression(string $column): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'pgsql' => 'CAST('.$column.' AS date)',
            default => 'DATE('.$column.')',
        };
    }
}
