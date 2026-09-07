<?php

namespace App\Services\Learning;

use App\Models\CourseOffering;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Http\Request;

class StudentPreviewService
{
    public const SESSION_KEY = 'spims.student_preview.offering_id';

    public const RETURN_KEY = 'spims.student_preview.return';

    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly OfferingAccessService $access,
    ) {}

    public function start(User $actor, CourseOffering $offering, ?string $returnUrl = null): void
    {
        $this->assertCanPreview($actor, $offering);
        session()->put(self::SESSION_KEY, $offering->id);
        if (is_string($returnUrl) && $returnUrl !== '') {
            session()->put(self::RETURN_KEY, $returnUrl);
        }

        $this->audit->write($actor, 'learning.student_preview', 'CourseOffering', $offering->id);
    }

    public function stop(): ?string
    {
        $return = session()->pull(self::RETURN_KEY);
        session()->forget(self::SESSION_KEY);

        return is_string($return) ? $return : null;
    }

    public function isActive(Request $request, CourseOffering $offering): bool
    {
        $user = $request->user();
        if ($user === null) {
            return false;
        }

        if ($request->session()->get(self::SESSION_KEY) !== $offering->id) {
            return false;
        }

        return $this->access->isStaffOrAdmin($user, $offering)
            || $this->authorize->allows($user, 'offerings.content', $offering)
            || $this->authorize->allows($user, 'offerings.view', $offering);
    }

    public function assertCanPreview(User $actor, CourseOffering $offering): void
    {
        if ($this->access->isStaffOrAdmin($actor, $offering)) {
            return;
        }

        $this->authorize->authorize($actor, 'offerings.content', $offering);
    }

    public function assertNotPreview(Request $request, CourseOffering $offering): void
    {
        abort_if($this->isActive($request, $offering), 403, __('offerings.preview_read_only'));
    }
}
