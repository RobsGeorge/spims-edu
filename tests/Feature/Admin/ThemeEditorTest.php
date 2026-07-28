<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleType;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ThemeEditorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_update_active_theme(): void
    {
        $this->seed();
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $theme = Theme::query()->where('is_active', true)->first();

        $this->actingAs($admin)->put(route('admin.theme.update', $theme), [
            'name' => 'Liturgical Updated',
            'site_name' => 'SPIMS Academy',
            'is_active' => true,
        ])->assertRedirect();

        $this->assertSame('SPIMS Academy', $theme->fresh()->site_name);
    }
}
