<?php

namespace Tests\Feature\Demo;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\DemoPersonas;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DemoConsoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['spims.demo_console' => true]);
    }

    #[Test]
    public function disabled_console_is_hidden_and_not_found(): void
    {
        config(['spims.demo_console' => false]);
        $this->seed();

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee(__('ui.home_cta_demo'))
            ->assertDontSee(__('ui.nav_demo'));

        $this->get('/demo')->assertNotFound();
        $this->post('/demo/seed')->assertNotFound();
        $this->post('/demo/enter/student1')->assertNotFound();
    }

    #[Test]
    public function home_and_demo_page_render_without_showing_the_password(): void
    {
        $this->seed();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(__('ui.home_cta_demo'), false)
            ->assertSee(route('demo.show'), false)
            ->assertDontSee(DemoDataSeeder::PASSWORD, false);

        $page = $this->get(route('demo.show'));
        $page->assertOk()
            ->assertSee(__('demo.heading'))
            ->assertSee(__('demo.enter', ['name' => 'John']))
            ->assertSee('John Student')
            ->assertSee('student1@spims.test', false)
            ->assertDontSee(DemoDataSeeder::PASSWORD, false);
    }

    #[Test]
    public function seed_and_reset_require_a_confirmation_token(): void
    {
        $this->seed();

        $this->from(route('demo.show'))
            ->post(route('demo.seed'))
            ->assertInvalid(['confirmation_token']);

        $this->from(route('demo.show'))
            ->post(route('demo.reset'), ['confirmation_token' => 'deadbeefdeadbeefdeadbeefdeadbeef'])
            ->assertInvalid(['confirmation_token']);
    }

    #[Test]
    public function public_visitor_can_seed_reset_and_enter_a_persona(): void
    {
        $this->seed();

        $page = $this->get(route('demo.show'));
        $seedToken = $this->tokenFrom($page->getContent(), 'demoSeedForm');

        $this->from(route('demo.show'))
            ->post(route('demo.seed'), ['confirmation_token' => $seedToken])
            ->assertRedirect(route('demo.show'))
            ->assertSessionHas('status', __('demo.seeded'));

        $student = User::query()->where('email', 'student1@spims.test')->first();
        $this->assertNotNull($student);

        $this->assertTrue(
            AuditLog::query()->where('action', 'demo.refresh')->exists()
        );

        $resetPage = $this->get(route('demo.show'));
        $resetToken = $this->tokenFrom($resetPage->getContent(), 'demoResetForm');

        $this->from(route('demo.show'))
            ->post(route('demo.reset'), ['confirmation_token' => $resetToken])
            ->assertRedirect(route('demo.show'))
            ->assertSessionHas('status', __('demo.reset_done'));

        $this->assertNotNull(User::query()->where('email', 'student1@spims.test')->first());

        $this->from(route('demo.show'))
            ->post(route('demo.enter', 'student1'))
            ->assertRedirect();

        $this->assertAuthenticatedAs($student->fresh());
        $this->assertTrue(
            AuditLog::query()->where('action', 'auth.demo_enter')->where('actor_id', $student->id)->exists()
        );

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(DemoDataSeeder::PASSWORD, false);
    }

    #[Test]
    public function enter_prepares_demo_data_when_the_persona_is_missing(): void
    {
        $this->seed();

        $this->assertNull(User::query()->where('email', 'ins1@spims.test')->first());

        $this->from(route('demo.show'))
            ->post(route('demo.enter', 'ins1'))
            ->assertRedirect();

        $instructor = User::query()->where('email', 'ins1@spims.test')->first();
        $this->assertNotNull($instructor);
        $this->assertAuthenticatedAs($instructor);
    }

    #[Test]
    public function unknown_persona_is_not_found(): void
    {
        $this->seed();
        config(['spims.seed_demo_data' => true]);
        $this->seed(DemoDataSeeder::class);

        $this->post(route('demo.enter', 'superadmin'))->assertNotFound();
        $this->post(route('demo.enter', 'nobody'))->assertNotFound();
        $this->assertGuest();
    }

    #[Test]
    public function a_full_persona_walk_is_not_throttled(): void
    {
        $this->seed();
        config(['spims.seed_demo_data' => true]);
        $this->seed(DemoDataSeeder::class);

        foreach (DemoPersonas::all() as $persona) {
            $this->from(route('demo.show'))
                ->post(route('demo.enter', $persona['slug']))
                ->assertRedirect();
            $this->assertAuthenticated();
            $this->post(route('auth.logout'));
        }
    }

    #[Test]
    public function arabic_demo_page_is_rtl(): void
    {
        $this->seed();

        $this->withCookie('locale', 'ar')
            ->get(route('demo.show'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee(__('demo.heading', locale: 'ar'));
    }

    private function tokenFrom(string $html, string $formId): string
    {
        $pattern = '/id="'.preg_quote($formId, '/').'"[\s\S]*?name="confirmation_token" value="([a-f0-9]+)"/';
        $this->assertSame(1, preg_match($pattern, $html, $matches), "Missing confirmation token in {$formId}");

        return $matches[1];
    }
}
