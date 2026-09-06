<?php

namespace Tests\Feature\Api\V1;

use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function accept_language_ar_returns_arabic_error_copy(): void
    {
        $this->withHeaders(['Accept-Language' => 'ar'])
            ->getJson(route('api.v1.me'))
            ->assertJsonPath('message', __('auth.unauthorized', [], 'ar'));
    }

    #[Test]
    public function accept_language_fr_returns_french_error_copy(): void
    {
        $this->withHeaders(['Accept-Language' => 'fr'])
            ->getJson(route('api.v1.me'))
            ->assertJsonPath('message', __('auth.unauthorized', [], 'fr'));
    }

    #[Test]
    public function a_multi_value_accept_language_header_picks_the_first_supported_tag(): void
    {
        // "de" is not supported; "ar" is the next candidate and must win over any
        // fallback, matching real browser/device Accept-Language lists.
        $this->withHeaders(['Accept-Language' => 'de;q=1.0, ar;q=0.8, en;q=0.5'])
            ->getJson(route('api.v1.me'))
            ->assertJsonPath('message', __('auth.unauthorized', [], 'ar'));
    }

    #[Test]
    public function no_header_and_no_stored_preference_falls_back_to_english(): void
    {
        $this->getJson(route('api.v1.me'))
            ->assertJsonPath('message', __('auth.unauthorized', [], 'en'));
    }

    #[Test]
    public function an_authenticated_users_stored_preference_is_used_when_no_header_is_sent(): void
    {
        // Exercised directly against the resolver rather than through a route: a
        // request that matches no route at all never runs the auth middleware, so
        // $request->user() can never be populated there, and there is no S1 route
        // yet that both requires auth and can still fail after auth succeeds.
        $user = User::factory()->withRole(RoleType::Student)->create(['preferred_locale' => 'ar']);

        $request = \Illuminate\Http\Request::create('/api/v1/probe', 'GET');
        // Request::create() injects a default Accept-Language header (from the
        // CLI/testing environment's own server vars) when none is passed. A real
        // client either sends the header or does not; simulate "does not".
        $request->headers->remove('Accept-Language');
        $request->setUserResolver(fn () => $user);

        $this->assertSame('ar', \App\Support\Api\ApiLocale::resolve($request));
    }

    #[Test]
    public function a_header_wins_over_the_users_stored_preference(): void
    {
        $user = User::factory()->withRole(RoleType::Student)->create(['preferred_locale' => 'ar']);

        $request = \Illuminate\Http\Request::create('/api/v1/probe', 'GET', server: [
            'HTTP_ACCEPT_LANGUAGE' => 'fr',
        ]);
        $request->setUserResolver(fn () => $user);

        $this->assertSame('fr', \App\Support\Api\ApiLocale::resolve($request));
    }

    #[Test]
    public function an_unsupported_stored_preference_falls_back_to_english(): void
    {
        // Defensive: the column has no DB-level enum constraint tying it to
        // ['ar','en','fr'], so a bad value must not propagate into __() calls.
        $user = User::factory()->withRole(RoleType::Student)->create(['preferred_locale' => 'de']);

        $request = \Illuminate\Http\Request::create('/api/v1/probe', 'GET');
        $request->headers->remove('Accept-Language');
        $request->setUserResolver(fn () => $user);

        $this->assertSame('en', \App\Support\Api\ApiLocale::resolve($request));
    }

    #[Test]
    public function the_legacy_unversioned_api_user_route_is_unaffected(): void
    {
        // SetApiLocale is scoped to the v1 group only, not the shared `api`
        // middleware group — this proves the legacy scaffold route is untouched.
        $user = User::factory()->withRole(RoleType::Student)->create();

        $this->withHeaders(['Accept-Language' => 'ar'])
            ->actingAs($user)
            ->getJson('/api/user')
            ->assertOk();

        // No error envelope was ever introduced for this route: `id` is a raw
        // model attribute at the top level, not nested under `data`.
        $this->assertNotNull(
            $this->withHeaders(['Accept-Language' => 'ar'])->actingAs($user)->getJson('/api/user')->json('id')
        );
    }
}
