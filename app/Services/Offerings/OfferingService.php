<?php

namespace App\Services\Offerings;

use App\Enums\ContentItemType;
use App\Enums\OfferingMode;
use App\Support\Content\ExternalReadingUrl;
use App\Support\Content\VideoUrlParser;
use App\Enums\OfferingStaffRole;
use App\Enums\OfferingStatus;
use App\Models\ContentItem;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\OfferingStaff;
use App\Models\User;
use App\Models\Week;
use App\Services\Discussions\DiscussionService;
use App\Services\Storage\ObjectStorageService;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OfferingService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly ContentGatingService $gating,
        private readonly DiscussionService $discussions,
        private readonly ObjectStorageService $storage,
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

        return $this->audit->withAudit($actor, 'offerings.create', function () use ($actor, $data, $mode) {
            $offering = CourseOffering::query()->create([
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
            ]);

            $this->discussions->provisionBoard($actor, $offering);

            return $offering;
        }, 'CourseOffering');
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

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, CourseOffering $offering, array $data): CourseOffering
    {
        $this->authorize->authorize($actor, 'offerings.manage');

        if ($offering->mode === OfferingMode::Cohort && array_key_exists('semester_id', $data) && empty($data['semester_id'])) {
            throw ValidationException::withMessages([
                'semester_id' => [__('offerings.cohort_requires_semester')],
            ]);
        }

        $before = $offering->only([
            'seat_capacity',
            'start_date',
            'end_date',
            'status',
            'semester_id',
            'attendance_threshold_percent',
        ]);
        $previousStatus = $offering->status;

        $payload = [
            'seat_capacity' => array_key_exists('seat_capacity', $data) ? $data['seat_capacity'] : $offering->seat_capacity,
            'start_date' => array_key_exists('start_date', $data) ? $data['start_date'] : $offering->start_date,
            'end_date' => array_key_exists('end_date', $data) ? $data['end_date'] : $offering->end_date,
            'attendance_threshold_percent' => $data['attendance_threshold_percent'] ?? $offering->attendance_threshold_percent,
        ];

        if (array_key_exists('semester_id', $data) && $offering->mode === OfferingMode::Cohort) {
            $payload['semester_id'] = $data['semester_id'];
        }

        if (isset($data['status'])) {
            $payload['status'] = $data['status'] instanceof OfferingStatus
                ? $data['status']
                : OfferingStatus::from($data['status']);
        }

        $offering->update($payload);
        $fresh = $offering->fresh();

        $this->audit->write(
            $actor,
            'offerings.update',
            'CourseOffering',
            $offering->id,
            $before,
            $fresh->only([
                'seat_capacity',
                'start_date',
                'end_date',
                'status',
                'semester_id',
                'attendance_threshold_percent',
            ]),
        );

        if ($previousStatus !== $fresh->status) {
            $this->audit->write($actor, 'offerings.status_change', 'CourseOffering', $offering->id, [
                'status' => $previousStatus->value,
            ], [
                'status' => $fresh->status->value,
            ]);
        }

        return $fresh;
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

    public function removeStaff(User $actor, CourseOffering $offering, OfferingStaff $staff): void
    {
        $this->authorize->authorize($actor, 'offerings.manage');

        if ($staff->offering_id !== $offering->id) {
            throw ValidationException::withMessages(['staff' => [__('offerings.staff_not_assigned')]]);
        }

        $this->audit->withAudit($actor, 'offerings.remove_staff', function () use ($staff) {
            $staff->delete();

            return $staff;
        }, 'OfferingStaff');
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
        $this->authorize->authorize($actor, 'offerings.content', $offering);

        return $this->audit->withAudit($actor, 'offerings.add_week', fn () => Week::query()->create([
            'offering_id' => $offering->id,
            'number' => $data['number'],
            'title' => $data['title'],
            'unlock_date' => $data['unlock_date'] ?? null,
            'order' => $data['order'] ?? $data['number'],
        ]), 'Week');
    }

    public function addContentItem(User $actor, Week $week, array $data, ?UploadedFile $file = null): ContentItem
    {
        $this->authorize->authorize($actor, 'offerings.content', $week);

        if ($file !== null) {
            $data['file_url'] = $this->storeItemFile($week, $file);
        }

        $data = $this->applyVideoInput($data);
        $data = $this->applyReadingUrl($data);

        $published = array_key_exists('published', $data) ? (bool) $data['published'] : false;

        return $this->audit->withAudit($actor, 'offerings.add_content', fn () => ContentItem::query()->create([
            'week_id' => $week->id,
            'type' => ContentItemType::from($data['type']),
            'title' => $data['title'],
            'order' => $data['order'] ?? (($week->items()->max('order') ?? 0) + 1),
            'vimeo_id' => $data['vimeo_id'] ?? null,
            'video_provider' => $data['video_provider'] ?? null,
            'file_url' => $data['file_url'] ?? null,
            'body' => $data['body'] ?? null,
            'published' => $published,
            'published_at' => $published ? now() : null,
        ]), 'ContentItem');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateContentItem(User $actor, ContentItem $item, array $data, ?UploadedFile $file = null): ContentItem
    {
        $item->loadMissing('week');
        $this->authorize->authorize($actor, 'offerings.content', $item);

        return $this->audit->withAudit($actor, 'offerings.update_content', function () use ($item, $data, $file) {
            if ($file !== null && $item->week !== null) {
                $data['file_url'] = $this->storeItemFile($item->week, $file);
            }

            $data = $this->applyVideoInput($data, $item);
            $data = $this->applyReadingUrl($data);

            if (isset($data['type'])) {
                $data['type'] = $data['type'] instanceof ContentItemType
                    ? $data['type']
                    : ContentItemType::from($data['type']);
            }

            $item->fill(array_intersect_key($data, array_flip([
                'type', 'title', 'order', 'vimeo_id', 'video_provider', 'file_url', 'body',
            ])));
            $item->save();

            return $item->fresh();
        }, 'ContentItem');
    }

    public function publishContentItem(User $actor, ContentItem $item): ContentItem
    {
        $this->authorize->authorize($actor, 'offerings.content', $item);

        return $this->audit->withAudit($actor, 'offerings.publish_content', function () use ($item) {
            $item->published = true;
            $item->published_at = $item->published_at ?? now();
            $item->save();

            return $item->fresh();
        }, 'ContentItem');
    }

    public function unpublishContentItem(User $actor, ContentItem $item): ContentItem
    {
        $this->authorize->authorize($actor, 'offerings.content', $item);

        return $this->audit->withAudit($actor, 'offerings.unpublish_content', function () use ($item) {
            $item->published = false;
            $item->save();

            return $item->fresh();
        }, 'ContentItem');
    }

    public function deleteContentItem(User $actor, ContentItem $item): void
    {
        $this->authorize->authorize($actor, 'offerings.content', $item);

        $this->audit->withAudit($actor, 'offerings.delete_content', function () use ($item) {
            $item->delete();

            return $item;
        }, 'ContentItem');
    }

    /**
     * @param  list<string>  $orderedIds
     */
    public function reorderContentItems(User $actor, Week $week, array $orderedIds): void
    {
        $this->authorize->authorize($actor, 'offerings.content', $week);

        $ids = array_values(array_filter($orderedIds, fn ($id) => is_string($id) && $id !== ''));
        $existing = $week->items()->orderBy('order')->pluck('id')->all();
        sort($ids);
        $expected = $existing;
        sort($expected);
        if ($ids !== $expected) {
            throw ValidationException::withMessages([
                'items' => [__('offerings.reorder_invalid')],
            ]);
        }

        $this->audit->withAudit($actor, 'offerings.reorder_content', function () use ($week, $orderedIds) {
            foreach (array_values($orderedIds) as $index => $id) {
                ContentItem::query()->whereKey($id)->where('week_id', $week->id)->update(['order' => $index + 1]);
            }

            return $week->fresh();
        }, 'Week');
    }

    public function moveContentItem(User $actor, ContentItem $item, Week $target): ContentItem
    {
        $item->loadMissing('week.offering');
        $target->loadMissing('offering');
        $this->authorize->authorize($actor, 'offerings.content', $item);

        if ($item->week?->offering_id !== $target->offering_id) {
            throw ValidationException::withMessages([
                'week_id' => [__('offerings.move_week_invalid')],
            ]);
        }

        if ($item->week_id === $target->id) {
            return $item;
        }

        $fromWeekId = $item->week_id;

        return $this->audit->withAudit($actor, 'offerings.move_content', function () use ($item, $target, $fromWeekId) {
            $item->week_id = $target->id;
            $item->order = ((int) $target->items()->max('order')) + 1;
            $item->save();
            $this->compactWeekOrder($target->id);
            $this->compactWeekOrder($fromWeekId);

            return $item->fresh();
        }, 'ContentItem');
    }

    public function moveContentItemByDelta(User $actor, ContentItem $item, int $delta): void
    {
        $item->loadMissing('week');
        $this->authorize->authorize($actor, 'offerings.content', $item);
        $week = $item->week;
        abort_unless($week !== null, 404);

        $ids = $week->items()->orderBy('order')->pluck('id')->values()->all();
        $index = array_search($item->id, $ids, true);
        if ($index === false) {
            return;
        }
        $swap = $index + $delta;
        if ($swap < 0 || $swap >= count($ids)) {
            return;
        }
        $tmp = $ids[$index];
        $ids[$index] = $ids[$swap];
        $ids[$swap] = $tmp;
        $this->reorderContentItems($actor, $week, $ids);
    }

    private function compactWeekOrder(?string $weekId): void
    {
        if ($weekId === null || $weekId === '') {
            return;
        }

        $ids = ContentItem::query()->where('week_id', $weekId)->orderBy('order')->pluck('id')->all();
        foreach ($ids as $index => $id) {
            ContentItem::query()->whereKey($id)->update(['order' => $index + 1]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyVideoInput(array $data, ?ContentItem $existing = null): array
    {
        $raw = $data['video_url'] ?? $data['vimeo_id'] ?? null;
        if ($raw === null || $raw === '') {
            return $data;
        }

        $ref = VideoUrlParser::parse((string) $raw);
        $data['vimeo_id'] = $ref->id;
        $data['video_provider'] = $ref->provider;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyReadingUrl(array $data): array
    {
        $raw = $data['file_url'] ?? null;
        if (! is_string($raw) || $raw === '' || ! str_starts_with(strtolower($raw), 'http')) {
            return $data;
        }

        $ref = ExternalReadingUrl::parse($raw);
        $data['file_url'] = $ref->canonicalUrl;

        return $data;
    }

    private function storeItemFile(Week $week, UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: (string) $file->extension());
        $allowed = config('spims.content.upload_mimes', ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif']);
        if (! in_array($ext, $allowed, true) || in_array($ext, ['html', 'htm', 'svg', 'js'], true)) {
            throw ValidationException::withMessages([
                'file' => [__('offerings.upload_type_blocked')],
            ]);
        }

        $maxMb = max(1, (int) config('spims.content.upload_max_mb', 20));
        if ($file->getSize() > $maxMb * 1024 * 1024) {
            throw ValidationException::withMessages([
                'file' => [__('offerings.upload_too_large', ['mb' => $maxMb])],
            ]);
        }

        $path = $this->storage->signedUploadPath('uploads', $week->id, $ext);
        $real = $file->getRealPath();
        $contents = ($real && is_readable($real)) ? (string) file_get_contents($real) : (string) $file->get();
        $this->storage->store($path, $contents);

        return $path;
    }

    public function previewPayload(CourseOffering $offering): array
    {
        $offering->load(['weeks.items', 'course']);

        return $this->gating->publicPreview($offering);
    }
}
