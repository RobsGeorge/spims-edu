<?php

namespace App\Services\Reports;

use App\Enums\AcademicStanding;
use App\Models\Program;
use App\Models\Setting;
use App\Models\StudentProgram;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Validation\ValidationException;

class AcademicStandingService
{
    public const SETTING_KEY = 'academic_standing.thresholds';

    /** Good standing at or above this GPA, stored as hundredths (200 = 2.00). */
    public const DEFAULT_GOOD_MIN = 200;

    /** Suspension below this GPA, stored as hundredths (100 = 1.00). */
    public const DEFAULT_SUSPENSION_BELOW = 100;

    public const MIN_HUNDREDTHS = 0;

    public const MAX_HUNDREDTHS = 400;

    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    /**
     * @return array{good_min: int, suspension_below: int}
     */
    public function thresholds(): array
    {
        $value = Setting::query()->find(self::SETTING_KEY)?->value ?? [];

        return [
            'good_min' => (int) ($value['good_min'] ?? self::DEFAULT_GOOD_MIN),
            'suspension_below' => (int) ($value['suspension_below'] ?? self::DEFAULT_SUSPENSION_BELOW),
        ];
    }

    /**
     * Effective cutoffs for a diploma. Both program columns must be set to override;
     * a half-filled pair inherits school-wide.
     *
     * @return array{good_min: int, suspension_below: int, source: string}
     */
    public function thresholdsFor(?Program $program): array
    {
        $school = $this->thresholds();

        if ($program !== null
            && $program->standing_good_min !== null
            && $program->standing_suspension_below !== null) {
            return [
                'good_min' => (int) $program->standing_good_min,
                'suspension_below' => (int) $program->standing_suspension_below,
                'source' => 'program',
            ];
        }

        return [
            'good_min' => $school['good_min'],
            'suspension_below' => $school['suspension_below'],
            'source' => 'school',
        ];
    }

    /**
     * @param  array{good_min: int|string, suspension_below: int|string}  $data
     * @return array{good_min: int, suspension_below: int}
     */
    public function updateThresholds(User $actor, array $data): array
    {
        $this->authorize->authorize($actor, 'academic_standing.manage');

        $goodMin = (int) $data['good_min'];
        $suspensionBelow = (int) $data['suspension_below'];
        $this->assertValidPair($goodMin, $suspensionBelow);

        $this->audit->withAudit($actor, 'academic_standing.thresholds', function () use ($actor, $goodMin, $suspensionBelow) {
            Setting::query()->updateOrCreate(
                ['key' => self::SETTING_KEY],
                [
                    'value' => [
                        'good_min' => $goodMin,
                        'suspension_below' => $suspensionBelow,
                    ],
                    'updated_by_id' => $actor->id,
                ]
            );

            $this->reapplyCached();

            // Setting PK is a 29-char key, not a ULID — do not write it to audit_logs.entity_id.
            return [
                'key' => self::SETTING_KEY,
                'good_min' => $goodMin,
                'suspension_below' => $suspensionBelow,
            ];
        }, 'Setting');

        return $this->thresholds();
    }

    public function updateProgramOverrides(User $actor, Program $program, ?int $goodMin, ?int $suspensionBelow): void
    {
        $this->authorize->authorize($actor, 'academic_standing.manage');

        if ($goodMin === null && $suspensionBelow === null) {
            $this->persistProgramOverrides($actor, $program, null, null);

            return;
        }

        if ($goodMin === null || $suspensionBelow === null) {
            throw ValidationException::withMessages([
                'good_min' => [__('reports.program_thresholds_half')],
                'suspension_below' => [__('reports.program_thresholds_half')],
            ]);
        }

        $this->assertValidPair($goodMin, $suspensionBelow);
        $this->persistProgramOverrides($actor, $program, $goodMin, $suspensionBelow);
    }

    public function apply(StudentProgram $sp): void
    {
        if ($sp->cached_gpa === null) {
            if ($sp->academic_standing !== null) {
                $sp->update(['academic_standing' => null]);
            }

            return;
        }

        $standing = $this->resolve((float) $sp->cached_gpa, $sp->program);
        if ($sp->academic_standing !== $standing) {
            $sp->update(['academic_standing' => $standing]);
        }
    }

    public function resolve(float $gpa, ?Program $program = null): AcademicStanding
    {
        $hundredths = (int) round($gpa * 100);
        $thresholds = $this->thresholdsFor($program);

        if ($hundredths < $thresholds['suspension_below']) {
            return AcademicStanding::Suspension;
        }

        if ($hundredths < $thresholds['good_min']) {
            return AcademicStanding::Probation;
        }

        return AcademicStanding::Good;
    }

    private function assertValidPair(int $goodMin, int $suspensionBelow): void
    {
        if ($goodMin < self::MIN_HUNDREDTHS || $goodMin > self::MAX_HUNDREDTHS
            || $suspensionBelow < self::MIN_HUNDREDTHS || $suspensionBelow > self::MAX_HUNDREDTHS) {
            throw ValidationException::withMessages([
                'good_min' => [__('reports.thresholds_range', [
                    'min' => self::MIN_HUNDREDTHS,
                    'max' => self::MAX_HUNDREDTHS,
                ])],
            ]);
        }

        if ($suspensionBelow >= $goodMin) {
            throw ValidationException::withMessages([
                'suspension_below' => [__('reports.thresholds_order_invalid')],
            ]);
        }
    }

    private function persistProgramOverrides(User $actor, Program $program, ?int $goodMin, ?int $suspensionBelow): void
    {
        $this->audit->withAudit($actor, 'academic_standing.program_thresholds', function () use ($program, $goodMin, $suspensionBelow) {
            $program->update([
                'standing_good_min' => $goodMin,
                'standing_suspension_below' => $suspensionBelow,
            ]);

            $this->reapplyForProgram($program->fresh());

            return $program->fresh();
        }, 'Program');
    }

    private function reapplyForProgram(Program $program): void
    {
        StudentProgram::query()
            ->where('program_id', $program->id)
            ->whereNotNull('cached_gpa')
            ->with('program')
            ->get()
            ->each(fn (StudentProgram $sp) => $this->apply($sp));
    }

    private function reapplyCached(): void
    {
        StudentProgram::query()
            ->whereNotNull('cached_gpa')
            ->with('program')
            ->get()
            ->each(fn (StudentProgram $sp) => $this->apply($sp));
    }
}
