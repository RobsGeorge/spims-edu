<?php

namespace App\Services\Offerings;

use App\Enums\ContentItemType;
use App\Enums\OfferingMode;
use App\Enums\OfferingStaffRole;
use App\Enums\OfferingStatus;
use App\Models\ContentItem;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\OfferingStaff;
use App\Models\User;
use App\Models\Week;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OfferingService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly ContentGatingService $gating,
    ) {}

    public function create(User $actor, array $data): CourseOffering
    {
        $this->authorize->authorize($actor, 'offerings.manage');

        $mode = OfferingMode::from($data['mode'] ?? OfferingMode::Cohort->value);

        if ($mode === OfferingMode::Cohort && empty($data['semester_id'])) {
            throw ValidationException::withMessages([
                'semester_id' => [__('offerings.cohort_requires_semester')],
            ]);
        }

        if ($mode === OfferingMode::SelfPaced) {
            $data['semester_id'] = null;
        }

        return $this->audit->withAudit($actor, 'offerings.create', fn () => CourseOffering::query()->create([
            'course_id' => $data['course_id'],
            'semester_id' => $data['semester_id'] ?? null,
            'mode' => $mode,
            'price_usd_override' => $data['price_usd_override'] ?? null,
            'price_egp_override' => $data['price_egp_override'] ?? null,
            'seat_capacity' => $data['seat_capacity'] ?? null,
            'attendance_threshold_percent' => $data['attendance_threshold_percent'] ?? 60,
            'status' => OfferingStatus::from($data['status'] ?? OfferingStatus::Draft->value),
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
        ]), 'CourseOffering');
    }

    public function cloneFromCourse(User $actor, Course $course, array $data): CourseOffering
    {
        $this->authorize->authorize($actor, 'offerings.manage');

        return DB::transaction(function () use ($actor, $course, $data) {
            $offering = $this->create($actor, array_merge($data, ['course_id' => $course->id]));

            // Seed a Week 1 placeholder so preview always has structure.
            Week::query()->create([
                'offering_id' => $offering->id,
                'number' => 1,
                'title' => 'Week 1',
                'unlock_date' => $offering->start_date,
                'order' => 1,
            ]);

            $this->audit->write($actor, 'offerings.clone', 'CourseOffering', $offering->id, null, [
                'course_id' => $course->id,
            ]);

            return $offering->fresh(['weeks', 'course']);
        });
    }

    public function assignStaff(User $actor, CourseOffering $offering, string $userId, string $role): OfferingStaff
    {
        $this->authorize->authorize($actor, 'offerings.manage');

        return $this->audit->withAudit($actor, 'offerings.assign_staff', fn () => OfferingStaff::query()->updateOrCreate(
            [
                'offering_id' => $offering->id,
                'user_id' => $userId,
                'role' => OfferingStaffRole::from($role),
            ],
            ['role' => OfferingStaffRole::from($role)]
        ), 'OfferingStaff');
    }

    public function setPricing(User $actor, CourseOffering $offering, ?int $usd, ?int $egp): CourseOffering
    {
        $this->authorize->authorize($actor, 'offerings.pricing');

        $before = $offering->only(['price_usd_override', 'price_egp_override']);
        $offering->update([
            'price_usd_override' => $usd,
            'price_egp_override' => $egp,
        ]);
        $this->audit->write($actor, 'offerings.pricing', 'CourseOffering', $offering->id, $before, $offering->only(['price_usd_override', 'price_egp_override']));

        return $offering->fresh();
    }

    public function addWeek(User $actor, CourseOffering $offering, array $data): Week
    {
        $this->authorize->authorize($actor, 'offerings.content');

        return $this->audit->withAudit($actor, 'offerings.add_week', fn () => Week::query()->create([
            'offering_id' => $offering->id,
            'number' => $data['number'],
            'title' => $data['title'],
            'unlock_date' => $data['unlock_date'] ?? null,
            'order' => $data['order'] ?? $data['number'],
        ]), 'Week');
    }

    public function addContentItem(User $actor, Week $week, array $data): ContentItem
    {
        $this->authorize->authorize($actor, 'offerings.content');

        return $this->audit->withAudit($actor, 'offerings.add_content', fn () => ContentItem::query()->create([
            'week_id' => $week->id,
            'type' => ContentItemType::from($data['type']),
            'title' => $data['title'],
            'order' => $data['order'] ?? (($week->items()->max('order') ?? 0) + 1),
            'vimeo_id' => $data['vimeo_id'] ?? null,
            'file_url' => $data['file_url'] ?? null,
            'body' => $data['body'] ?? null,
        ]), 'ContentItem');
    }

    public function previewPayload(CourseOffering $offering): array
    {
        $offering->load(['weeks.items', 'course']);

        return $this->gating->publicPreview($offering);
    }
}
