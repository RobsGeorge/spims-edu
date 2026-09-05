<?php

namespace Tests\Feature\Api\V1;

use App\Models\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_is_public_and_returns_defaults_with_no_active_theme(): void
    {
        $this->getJson(route('api.v1.branding'))
            ->assertOk()
            ->assertJsonPath('data.site_name', 'SPIMS')
            ->assertJsonStructure(['data' => ['tokens']]);
    }

    #[Test]
    public function it_returns_the_active_theme_when_one_exists(): void
    {
        Theme::query()->create([
            'name' => 'Custom',
            'site_name' => 'Custom SPIMS',
            'is_active' => true,
            'tokens' => ['primary' => '#5d0326'],
        ]);

        $this->getJson(route('api.v1.branding'))
            ->assertOk()
            ->assertJsonPath('data.site_name', 'Custom SPIMS');
    }
}
