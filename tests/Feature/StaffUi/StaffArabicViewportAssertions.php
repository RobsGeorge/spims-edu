<?php

namespace Tests\Feature\StaffUi;

use App\Models\User;
use Illuminate\Testing\TestResponse;

trait StaffArabicViewportAssertions
{
    private function arabic(User $user): User
    {
        $user->forceFill(['preferred_locale' => 'ar'])->save();

        return $user->fresh();
    }

    private function assertArabicShell(TestResponse $response): void
    {
        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('lang="ar"', $html);
        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('name="viewport" content="width=device-width, initial-scale=1"', $html);
        $this->assertStringContainsString('bootstrap.rtl.min.css', $html);
        $this->assertStringContainsString('IBM+Plex+Sans+Arabic', $html);
        $this->assertStringContainsString('spims-theme.css', $html);
    }
}
