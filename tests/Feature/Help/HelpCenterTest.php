<?php

namespace Tests\Feature\Help;

use App\Enums\HelpArticleStatus;
use App\Enums\RoleType;
use App\Models\HelpArticle;
use App\Models\HelpArticleAudience;
use App\Models\HelpArticleLocale;
use App\Models\HelpCategory;
use App\Models\User;
use App\Services\Rbac\RolePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HelpCenterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guest_can_open_help_index_and_public_articles(): void
    {
        $this->seed();

        $this->get(route('help.index'))
            ->assertOk()
            ->assertSee(__('help.title'))
            ->assertSee('Hubs explained')
            ->assertDontSee('Control plane');

        $this->get(route('help.show', 'hubs-explained'))
            ->assertOk()
            ->assertSee('Hubs explained');
    }

    #[Test]
    public function unknown_slug_returns_404(): void
    {
        $this->seed();

        $this->get(route('help.show', 'not-a-real-article'))->assertNotFound();
    }

    #[Test]
    public function student_sees_student_articles_but_not_superadmin_only(): void
    {
        $this->seed();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)->get(route('help.index'))
            ->assertOk()
            ->assertSee('Getting started')
            ->assertSee('Hubs explained')
            ->assertDontSee('Control plane');

        $this->actingAs($student)->get(route('help.show', 'getting-started'))->assertOk();
        $this->actingAs($student)->get(route('help.show', 'control-plane'))->assertNotFound();
    }

    #[Test]
    public function guest_cannot_open_role_private_article(): void
    {
        $this->seed();

        $this->get(route('help.show', 'getting-started'))->assertNotFound();
        $this->get(route('help.show', 'users-roles'))->assertNotFound();
    }

    #[Test]
    public function admin_sees_admin_guides(): void
    {
        $this->seed();
        app(RolePermissionService::class)->syncFromConfig(force: true);
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();

        $this->actingAs($admin)->get(route('help.index'))
            ->assertOk()
            ->assertSee('Users and roles')
            ->assertDontSee('Control plane');

        $this->actingAs($admin)->get(route('help.show', 'users-roles'))->assertOk();
        $this->actingAs($admin)->get(route('help.show', 'control-plane'))->assertNotFound();
    }

    #[Test]
    public function draft_hidden_unless_manager_preview(): void
    {
        $this->seed();
        app(RolePermissionService::class)->syncFromConfig(force: true);

        $category = HelpCategory::query()->where('slug', 'student')->firstOrFail();
        $draft = HelpArticle::factory()->create([
            'category_id' => $category->id,
            'slug' => 'draft-only-guide',
            'status' => HelpArticleStatus::Draft,
            'is_public' => false,
        ]);
        HelpArticleLocale::factory()->create([
            'article_id' => $draft->id,
            'locale' => 'en',
            'title' => 'Draft only guide',
            'body_markdown' => "## Draft\n\nSecret",
        ]);
        HelpArticleAudience::factory()->create([
            'article_id' => $draft->id,
            'role' => RoleType::Student,
        ]);

        $student = User::factory()->withRole(RoleType::Student)->create();
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();

        $this->actingAs($student)->get(route('help.show', 'draft-only-guide'))->assertNotFound();
        $this->actingAs($admin)->get(route('help.show', 'draft-only-guide'))->assertNotFound();
        $this->actingAs($admin)->get(route('help.show', ['slug' => 'draft-only-guide', 'preview' => 1]))
            ->assertOk()
            ->assertSee('Draft only guide')
            ->assertSee(__('help.preview_banner'));
        $this->actingAs($student)->get(route('help.show', ['slug' => 'draft-only-guide', 'preview' => 1]))
            ->assertNotFound();
    }

    #[Test]
    public function role_filter_lists_matching_guides(): void
    {
        $this->seed();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)->get(route('help.role', 'STUDENT'))
            ->assertOk()
            ->assertSee('Getting started');

        $this->actingAs($student)->get(route('help.index', ['role' => 'STUDENT']))
            ->assertOk()
            ->assertSee('Getting started');

        $this->get(route('help.role', 'not-a-role'))->assertNotFound();
    }

    #[Test]
    public function category_listing_filters_articles(): void
    {
        $this->seed();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)->get(route('help.category', 'student'))
            ->assertOk()
            ->assertSee('Getting started')
            ->assertDontSee('Users and roles');

        $this->get(route('help.category', 'missing-category'))->assertNotFound();
    }

    #[Test]
    public function search_matches_title_and_body(): void
    {
        $this->seed();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)->get(route('help.index', ['q' => 'Getting started']))
            ->assertOk()
            ->assertSee('Getting started');

        $this->actingAs($student)->get(route('help.index', ['q' => 'Learning hub']))
            ->assertOk()
            ->assertSee('Getting started');

        $this->actingAs($student)->get(route('help.index', ['q' => 'zzzz-no-match-zzzz']))
            ->assertOk()
            ->assertSee(__('help.empty'));
    }

    #[Test]
    public function arabic_locale_renders_arabic_titles(): void
    {
        $this->seed();

        $this->withCookie('locale', 'ar')
            ->get(route('help.index'))
            ->assertOk()
            ->assertSee(__('help.title', [], 'ar'))
            ->assertSee('شرح المراكز');
    }

    #[Test]
    public function locale_falls_back_to_english_with_notice(): void
    {
        $category = HelpCategory::factory()->create(['slug' => 'shared', 'is_published' => true]);
        $article = HelpArticle::factory()->published()->publicGuest()->create([
            'category_id' => $category->id,
            'slug' => 'en-only-guide',
        ]);
        HelpArticleLocale::factory()->create([
            'article_id' => $article->id,
            'locale' => 'en',
            'title' => 'English only guide',
            'summary' => 'Summary',
            'body_markdown' => "## Hello\n\nBody text",
        ]);

        $this->withCookie('locale', 'fr')
            ->get(route('help.show', 'en-only-guide'))
            ->assertOk()
            ->assertSee('English only guide')
            ->assertSee(__('help.locale_fallback', [], 'fr'));
    }

    #[Test]
    public function rendered_markdown_strips_xss(): void
    {
        $category = HelpCategory::factory()->create(['is_published' => true]);
        $article = HelpArticle::factory()->published()->publicGuest()->create([
            'category_id' => $category->id,
            'slug' => 'xss-guide',
        ]);
        HelpArticleLocale::factory()->create([
            'article_id' => $article->id,
            'locale' => 'en',
            'title' => 'XSS guide',
            'body_markdown' => "Safe\n\n<script>alert(1)</script>\n\nMore **text**",
        ]);

        $response = $this->get(route('help.show', 'xss-guide'))->assertOk();
        $response->assertSee('Safe');
        $response->assertSee('<strong>text</strong>', false);
        preg_match('/class="help-article-body">(.*?)<\/article>/s', $response->getContent(), $matches);
        $body = $matches[1] ?? '';
        $this->assertNotSame('', $body);
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringContainsString('<strong>text</strong>', $body);
    }

    #[Test]
    public function authenticated_shell_exposes_help_nav(): void
    {
        $this->seed();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('help.nav'));
    }
}
