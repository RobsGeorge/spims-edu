<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Services\Storage\ObjectStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'phone' => $this->phone,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'roles' => $this->roleTypes()->map(fn ($role) => $role->value)->values(),
            'status' => $this->status->value,
            'email_verified' => $this->email_verified,
            'preferred_locale' => $this->preferred_locale,
            'theme_preference' => $this->theme_preference?->value,
            'notify_email' => $this->notify_email,
            'avatar_url' => $this->avatarUrl(),
            'enrolled_offering_count' => Enrollment::query()
                ->where('student_id', $this->id)
                ->whereIn('status', [EnrollmentStatus::Enrolled, EnrollmentStatus::Completed])
                ->count(),
        ];
    }

    private function avatarUrl(): ?string
    {
        if (! filled($this->avatar_path)) {
            return null;
        }

        return app(ObjectStorageService::class)->temporaryUrl((string) $this->avatar_path);
    }
}
