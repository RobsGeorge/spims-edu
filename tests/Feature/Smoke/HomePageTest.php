<?php

namespace Tests\Feature\Smoke;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function home_page_renders_successfully(): void
    {
        $this->seed();

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(__('ui.home_heading'));
    }

    #[Test]
    public function home_page_sets_rtl_for_arabic_locale_cookie(): void
    {
        $this->seed();

        $response = $this->withCookie('locale', 'ar')->get(route('home'));

        $response->assertOk();
        $response->assertSee('dir="rtl"', false);
    }

    #[Test]
    public function branding_api_is_public(): void
    {
        $this->seed();

        $response = $this->getJson(route('api.branding'));

        $response->assertOk()
            ->assertJsonStructure(['siteName', 'tokens']);
    }

    #[Test]
    public function foundation_demo_requires_auth_and_permission(): void
    {
        $this->postJson(route('foundation.demo'), ['note' => 'x'])->assertUnauthorized();
    }
}
