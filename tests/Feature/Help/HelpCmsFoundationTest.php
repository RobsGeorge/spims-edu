<?php

namespace Tests\Feature\Help;

use App\Enums\HelpArticleStatus;
use App\Enums\RoleType;
use App\Models\AuditLog;
use App\Models\HelpArticle;
use App\Models\HelpArticleAudience;
use App\Models\HelpArticleLocale;
use App\Models\HelpCategory;
use App\Models\HelpMedia;
use App\Models\User;
use App\Services\Admin\HelpAdminService;
use App\Services\Help\HelpCatalogService;
use App\Services\Rbac\RolePermissionService;
use App\Services\Storage\ObjectStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HelpCmsFoundationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function migrations_create_help_tables(): void
    {
        $this->assertTrue(\Schema::hasTable('help_categories'));
        $this->assertTrue(\Schema::hasTable('help_articles'));
        $this->assertTrue(\Schema::hasTable('help_article_locales'));
        $this->assertTrue(\Schema::hasTable('help_article_audiences'));
        $this->assertTrue(\Schema::hasTable('help_media'));
    }

    #[Test]
    public function permissions_sync_includes_help_keys(): void
    {
        $written = app(RolePermissionService::class)->syncFromConfig(force: true);

        $this->assertGreaterThan(0, $written);
        $this->assertDatabaseHas('role_permissions', [
            'role' => RoleType::AdministrativeAdmin->value,
            'permission_key' => 'help.manage',
            'level' => 'F',
        ]);
        $this->assertDatabaseHas('role_permissions', [
            'role' => RoleType::Student->value,
            'permission_key' => 'help.view',
            'level' => 'R',
        ]);
    }

    #[Test]
    public function object_storage_allows_help_media_prefix(): void
    {
        $path = app(ObjectStorageService::class)->signedUploadPath('help-media', 'system', 'png');

        $this->assertStringStartsWith('help-media/system/', $path);
        $this->assertStringEndsWith('.png', $path);
    }

    #[Test]
    public function catalog_filters_by_audience_and_hides_drafts(): void
    {
        $category = HelpCategory::factory()->create(['slug' => 'student', 'is_published' => true]);

        $published = HelpArticle::factory()->published()->create([
            'category_id' => $category->id,
            'slug' => 'getting-started',
        ]);
        HelpArticleLocale::factory()->create([
            'article_id' => $published->id,
            'locale' => 'en',
            'title' => 'Getting started',
        ]);
        HelpArticleAudience::factory()->create([
            'article_id' => $published->id,
            'role' => RoleType::Student,
        ]);

        $draft = HelpArticle::factory()->create([
            'category_id' => $category->id,
            'slug' => 'secret-draft',
            'status' => HelpArticleStatus::Draft,
        ]);
        HelpArticleLocale::factory()->create(['article_id' => $draft->id]);

        $instructorOnly = HelpArticle::factory()->published()->create([
            'category_id' => $category->id,
            'slug' => 'teach-only',
        ]);
        HelpArticleLocale::factory()->create(['article_id' => $instructorOnly->id]);
        HelpArticleAudience::factory()->create([
            'article_id' => $instructorOnly->id,
            'role' => RoleType::Instructor,
        ]);

        $student = User::factory()->withRole(RoleType::Student)->create();
        $catalog = app(HelpCatalogService::class);

        $list = $catalog->listArticles($student);
        $slugs = $list->pluck('slug')->all();

        $this->assertContains('getting-started', $slugs);
        $this->assertNotContains('secret-draft', $slugs);
        $this->assertNotContains('teach-only', $slugs);

        $this->assertNotNull($catalog->findPublishedBySlug('getting-started', $student));
        $this->assertNull($catalog->findPublishedBySlug('secret-draft', $student));
        $this->assertNull($catalog->findPublishedBySlug('teach-only', $student));
    }

    #[Test]
    public function public_article_visible_to_guests(): void
    {
        $category = HelpCategory::factory()->create(['is_published' => true]);
        $article = HelpArticle::factory()->published()->publicGuest()->create([
            'category_id' => $category->id,
            'slug' => 'hubs-explained',
        ]);
        HelpArticleLocale::factory()->create([
            'article_id' => $article->id,
            'title' => 'Hubs explained',
        ]);

        $catalog = app(HelpCatalogService::class);

        $this->assertNotNull($catalog->findPublishedBySlug('hubs-explained', null));
        $this->assertTrue($catalog->listArticles(null)->contains('slug', 'hubs-explained'));
    }

    #[Test]
    public function admin_service_creates_publishes_and_audits(): void
    {
        app(RolePermissionService::class)->syncFromConfig(force: true);

        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $category = HelpCategory::factory()->create();
        $service = app(HelpAdminService::class);

        $article = $service->createArticle($admin, [
            'category_id' => $category->id,
            'slug' => 'new-guide',
            'locales' => [
                'en' => [
                    'title' => 'New guide',
                    'summary' => 'Summary',
                    'body_markdown' => "## Hello\n\nBody",
                ],
            ],
            'audiences' => [RoleType::Student->value],
        ]);

        $this->assertSame(HelpArticleStatus::Draft, $article->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'help.article.create',
            'entity_type' => 'HelpArticle',
            'entity_id' => $article->id,
        ]);

        $published = $service->publishArticle($admin, $article);
        $this->assertSame(HelpArticleStatus::Published, $published->status);
        $this->assertNotNull($published->published_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'help.article.publish',
            'entity_id' => $article->id,
        ]);

        $media = $service->attachMedia($admin, $published, 'help-media/system/'.Str::ulid().'.png', 'Diagram');
        $this->assertInstanceOf(HelpMedia::class, $media);
        $this->assertGreaterThan(0, AuditLog::query()->where('action', 'help.media.create')->count());
    }

    #[Test]
    public function help_seeder_loads_mvp_articles_with_locales(): void
    {
        $this->seed(\Database\Seeders\HelpSeeder::class);

        $this->assertGreaterThanOrEqual(7, HelpCategory::query()->count());
        $this->assertGreaterThanOrEqual(20, HelpArticle::query()->where('status', HelpArticleStatus::Published)->count());

        $gettingStarted = HelpArticle::query()->where('slug', 'getting-started')->first();
        $this->assertNotNull($gettingStarted);
        $this->assertSame(3, $gettingStarted->locales()->count());
        $this->assertTrue($gettingStarted->audiences()->where('role', RoleType::Student)->exists());

        $student = User::factory()->withRole(RoleType::Student)->create();
        $html = app(HelpCatalogService::class)->renderBody($gettingStarted, 'en');
        $this->assertNotNull($html);
        $this->assertStringContainsString('<h2>', $html);
        $this->assertNotNull(app(HelpCatalogService::class)->findPublishedBySlug('getting-started', $student));
    }

    #[Test]
    public function factories_create_related_models(): void
    {
        $article = HelpArticle::factory()->published()->create();
        $locale = HelpArticleLocale::factory()->create(['article_id' => $article->id, 'locale' => 'fr']);
        $audience = HelpArticleAudience::factory()->create([
            'article_id' => $article->id,
            'role' => RoleType::Instructor,
        ]);
        $media = HelpMedia::factory()->create(['article_id' => $article->id]);

        $this->assertTrue(Str::isUlid($article->id));
        $this->assertTrue(Str::isUlid($locale->id));
        $this->assertTrue(Str::isUlid($audience->id));
        $this->assertTrue(Str::isUlid($media->id));
        $this->assertSame(HelpArticleStatus::Published, $article->status);
    }
}
