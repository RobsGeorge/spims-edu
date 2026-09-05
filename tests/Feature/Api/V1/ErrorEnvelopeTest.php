<?php

namespace Tests\Feature\Api\V1;

use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every /api/v1 failure uses one shape: { message, code, errors? }. These four
 * exception types are handled in three different places (AuthorizationException
 * has its own render(); ValidationException, AuthenticationException, and
 * Symfony HTTP exceptions go through Handler::register()'s renderable callbacks),
 * so each is exercised here rather than assumed from one being correct.
 */
class ErrorEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function validation_failure_is_422_with_a_field_level_errors_map(): void
    {
        $this->postJson(route('api.v1.login'), [])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'code', 'errors'])
            ->assertJsonPath('code', 'VALIDATION_FAILED');
    }

    #[Test]
    public function unauthenticated_is_401_with_no_errors_key(): void
    {
        $this->getJson(route('api.v1.me'))
            ->assertStatus(401)
            ->assertJsonStructure(['message', 'code'])
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    #[Test]
    public function an_unmatched_route_is_404_with_the_shared_shape(): void
    {
        $this->getJson('/api/v1/does-not-exist')
            ->assertStatus(404)
            ->assertJsonPath('code', 'NOT_FOUND')
            ->assertJsonMissingPath('errors');
    }

    #[Test]
    public function a_missing_route_bound_model_is_404_with_a_generic_message_not_the_model_class(): void
    {
        // `/api/v1/does-not-exist` above proves the shape for a plain routing miss,
        // whose message is already generic. A route-model-binding miss is the case
        // that actually needs sanitizing: ModelNotFoundException's own message
        // ("No query results for model [App\Models\User] ...") would otherwise leak
        // the model's FQCN to a client probing an id. Registering a throwaway
        // model-bound route is the only way to exercise that path — S1 ships no
        // model-bound v1 route of its own yet.
        \Illuminate\Support\Facades\Route::middleware('api')
            ->get('/api/v1/_probe/{user}', fn (\App\Models\User $user) => $user)
            ->name('api.v1._probe');

        $response = $this->getJson('/api/v1/_probe/00000000000000000000000000');

        $response->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $message = $response->json('message');
        $this->assertStringNotContainsString('App\\Models\\User', (string) $message);
        $this->assertSame(__('api.not_found'), $message);
    }

    #[Test]
    public function throttling_is_429_and_the_shape_still_holds(): void
    {
        $user = User::factory()->withRole(RoleType::Student)->create();

        for ($i = 0; $i < 6; $i++) {
            $this->postJson(route('api.v1.login'), ['email' => $user->email, 'password' => 'x']);
        }

        $this->postJson(route('api.v1.login'), ['email' => $user->email, 'password' => 'x'])
            ->assertStatus(429)
            ->assertJsonPath('code', 'RATE_LIMITED');
    }

    #[Test]
    public function forbidden_is_403_and_still_uses_the_shared_shape(): void
    {
        // AuthorizationException renders itself; this is the one that would silently
        // diverge from the shape if AuthorizationException::render() were ever
        // changed without updating its api/v1 branch.
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();

        $this->actingAs($instructor);
        try {
            app(\App\Support\AuthorizeService::class)->authorize($instructor, 'gradebook.configure');
            $this->fail('Expected an AuthorizationException.');
        } catch (\App\Exceptions\AuthorizationException $e) {
            $request = \Illuminate\Http\Request::create('/api/v1/probe', 'GET');
            $response = $e->render($request);

            $this->assertSame(403, $response->getStatusCode());
            $body = json_decode($response->getContent(), true);
            $this->assertSame('FORBIDDEN', $body['code']);
            $this->assertArrayHasKey('message', $body);
        }
    }

    #[Test]
    public function web_routes_are_unaffected_by_the_api_v1_error_shape(): void
    {
        // The whole point of scoping every branch to `api/v1/*` is that nothing
        // about web error rendering changes. A 404 on a web route must still be
        // Laravel's normal HTML error page, not the API envelope.
        $response = $this->get('/this-route-does-not-exist');

        $response->assertStatus(404);
        $this->assertStringNotContainsString('"code":"NOT_FOUND"', $response->getContent());
    }
}
