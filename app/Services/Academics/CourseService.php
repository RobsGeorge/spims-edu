<?php

namespace App\Services\Academics;

use App\Models\Course;
use App\Models\CourseInterestFlag;
use App\Models\CoursePrerequisite;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use App\Support\Ui\CourseCoverLibrary;
use Illuminate\Validation\ValidationException;

class CourseService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    public function create(User $actor, array $data): Course
    {
        $this->authorize->authorize($actor, 'courses.manage');

        return $this->audit->withAudit($actor, 'courses.create', function () use ($data) {
            $code = strtoupper($data['code']);
            $cover = $data['cover_image_url'] ?? null;
            if (! is_string($cover) || $cover === '') {
                $cover = CourseCoverLibrary::urlForSeed($code);
            }

            return Course::query()->create([
                'code' => $code,
                'title' => $data['title'],
                'cover_image_url' => $cover,
                'credit_hours' => $data['credit_hours'],
                'default_price_usd' => $data['default_price_usd'] ?? 0,
                'default_price_egp' => $data['default_price_egp'] ?? 0,
                'is_free' => (bool) ($data['is_free'] ?? false),
                'is_standalone' => (bool) ($data['is_standalone'] ?? false),
                'passing_threshold' => $data['passing_threshold'] ?? null,
                'assessment_template_id' => $data['assessment_template_id'] ?? null,
                'active' => $data['active'] ?? true,
            ]);
        }, 'Course');
    }

    public function update(User $actor, Course $course, array $data): Course
    {
        $this->authorize->authorize($actor, 'courses.manage');
        $before = $course->toArray();

        $payload = [
            'title' => $data['title'] ?? $course->title,
            'credit_hours' => $data['credit_hours'] ?? $course->credit_hours,
            'default_price_usd' => $data['default_price_usd'] ?? $course->default_price_usd,
            'default_price_egp' => $data['default_price_egp'] ?? $course->default_price_egp,
            'is_free' => array_key_exists('is_free', $data) ? (bool) $data['is_free'] : $course->is_free,
            'is_standalone' => array_key_exists('is_standalone', $data) ? (bool) $data['is_standalone'] : $course->is_standalone,
            'passing_threshold' => array_key_exists('passing_threshold', $data) ? $data['passing_threshold'] : $course->passing_threshold,
            'assessment_template_id' => array_key_exists('assessment_template_id', $data) ? $data['assessment_template_id'] : $course->assessment_template_id,
            'active' => $data['active'] ?? $course->active,
        ];

        if (array_key_exists('cover_image_url', $data)) {
            $cover = $data['cover_image_url'];
            $payload['cover_image_url'] = (is_string($cover) && $cover !== '')
                ? $cover
                : CourseCoverLibrary::urlForSeed((string) $course->code);
        }

        $course->update($payload);

        $this->audit->write($actor, 'courses.update', 'Course', $course->id, $before, $course->fresh()->toArray());

        return $course->fresh();
    }

    public function addPrerequisite(User $actor, Course $course, string $prerequisiteId): void
    {
        $this->authorize->authorize($actor, 'courses.manage');

        if ($course->id === $prerequisiteId) {
            throw ValidationException::withMessages(['prerequisite_id' => [__('academics.self_prerequisite')]]);
        }

        CoursePrerequisite::query()->updateOrCreate(
            ['course_id' => $course->id, 'prerequisite_id' => $prerequisiteId]
        );

        $this->audit->write($actor, 'courses.add_prerequisite', 'Course', $course->id, null, [
            'prerequisite_id' => $prerequisiteId,
        ]);
    }

    public function removePrerequisite(User $actor, Course $course, CoursePrerequisite $prerequisite): void
    {
        $this->authorize->authorize($actor, 'courses.manage');

        if ($prerequisite->course_id !== $course->id) {
            throw ValidationException::withMessages(['prerequisite_id' => [__('academics.prerequisite_not_attached')]]);
        }

        $this->audit->withAudit($actor, 'courses.remove_prerequisite', function () use ($prerequisite) {
            $prerequisite->delete();

            return $prerequisite;
        }, 'CoursePrerequisite');
    }

    public function flagInterest(User $student, Course $course): CourseInterestFlag
    {
        $this->authorize->authorize($student, 'courses.flag_interest');

        return CourseInterestFlag::query()->firstOrCreate([
            'student_id' => $student->id,
            'course_id' => $course->id,
        ]);
    }

    public function interestCounts(): array
    {
        return CourseInterestFlag::query()
            ->selectRaw('course_id, COUNT(*) as interest_count')
            ->groupBy('course_id')
            ->pluck('interest_count', 'course_id')
            ->all();
    }
}
