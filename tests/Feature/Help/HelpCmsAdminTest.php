<?php

namespace Tests\Feature\Help;

use App\Enums\HelpArticleStatus;
use App\Enums\RoleType;
use App\Models\AuditLog;
use App\Models\HelpArticle;
use App\Models\HelpArticleLocale;
use App\Models\HelpCategory;
use App\Models\User;
use App\Services\Rbac\RolePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HelpCmsAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(RolePermissionService::class)->syncFromConfig(force: true);
    }

    #[Test]
    public function admin_can_crud_categories_and_articles(): void
    {
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();

        $this->actingAs($admin)->post(route('admin.help.categories.store'), [
            'slug' => 'ops',
            'sort_order' => 5,
            'is_published' => '1',
        ])->assertRedirect(route('admin.help.categories.index'));

        $category = HelpCategory::query()->where('slug', 'ops')->firstOrFail();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'help.category.create',
            'entity_id' => $category->id,
        ]);

        $this->actingAs($admin)->put(route('admin.help.categories.update', $category), [
            'slug' => 'ops',
            'sort_order' => 15,
            'is_published' => '1',
        ])->assertRedirect(route('admin.help.categories.index'));

        $this->assertSame(15, $category->fresh()->sort_order);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'help.category.update',
            'entity_id' => $category->id,
        ]);

        $this->actingAs($admin)->post(route('admin.help.articles.store'), [
            'category_id' => $category->id,
            'slug' => 'billing-guide',
            'sort_order' => 1,
            'is_public' => '0',
            'audiences' => [RoleType::Student->value],
            'intent' => 'draft',
            'locales' => [
                'en' => [
                    'title' => 'Billing guide',
                    'summary' => 'Pay invoices',
                    'body_markdown' => "## Pay\n\nUse Finance hub.",
                ],
            ],
        ])->assertRedirect();

        $article = HelpArticle::query()->where('slug', 'billing-guide')->firstOrFail();
        $this->assertSame(HelpArticleStatus::Draft, $article->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'help.article.create',
            'entity_id' => $article->id,
        ]);

        $this->actingAs($admin)->get(route('admin.help.articles.edit', $article))
            ->assertOk()
            ->assertSee('Billing guide')
            ->assertSee(route('help.show', ['slug' => 'billing-guide', 'preview' => 1]), false);

        $this->actingAs($admin)->put(route('admin.help.articles.update', $article), [
            'category_id' => $category->id,
            'slug' => 'billing-guide',
            'sort_order' => 2,
            'intent' => 'draft',
            'locales' => [
                'en' => [
                    'title' => 'Billing guide updated',
                    'summary' => 'Pay invoices',
                    'body_markdown' => "## Pay\n\nUpdated body.",
                ],
                'ar' => [
                    'title' => '',
                    'summary' => '',
                    'body_markdown' => '',
                ],
            ],
        ])->assertRedirect(route('admin.help.articles.edit', $article));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'help.article.update',
            'entity_id' => $article->id,
        ]);
        $this->assertSame('Billing guide updated', $article->fresh()->localeFor('en')?->title);

        $this->actingAs($admin)
            ->get(route('admin.help.articles.index', ['status' => 'draft', 'category_id' => $category->id]))
            ->assertOk()
            ->assertSee('billing-guide');
    }

    #[Test]
    public function student_cannot_access_help_admin(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)->get(route('admin.help.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.help.categories.index'))->assertForbidden();
        $this->actingAs($student)->post(route('admin.help.categories.store'), [
            'slug' => 'x',
        ])->assertForbidden();
    }

    #[Test]
    public function publish_requires_title_and_body_in_one_locale(): void
    {
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $category = HelpCategory::factory()->create();
        $article = HelpArticle::factory()->create([
            'category_id' => $category->id,
            'status' => HelpArticleStatus::Draft,
        ]);
        HelpArticleLocale::factory()->create([
            'article_id' => $article->id,
            'locale' => 'en',
            'title' => 'Only title',
            'body_markdown' => '',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.help.articles.publish', $article))
            ->assertSessionHasErrors('locales');

        $this->assertSame(HelpArticleStatus::Draft, $article->fresh()->status);

        $article->locales()->where('locale', 'en')->update([
            'body_markdown' => 'Full body text',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.help.articles.publish', $article))
            ->assertRedirect();

        $this->assertSame(HelpArticleStatus::Published, $article->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'help.article.publish',
            'entity_id' => $article->id,
        ]);
    }

    #[Test]
    public function archive_and_media_are_audited(): void
    {
        Storage::fake('local');

        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $category = HelpCategory::factory()->create();
        $article = HelpArticle::factory()->published()->create([
            'category_id' => $category->id,
        ]);
        HelpArticleLocale::factory()->create([
            'article_id' => $article->id,
            'title' => 'Guide',
            'body_markdown' => 'Body',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.help.articles.archive', $article))
            ->assertRedirect();

        $this->assertSame(HelpArticleStatus::Archived, $article->fresh()->status);
        $this->assertTrue(AuditLog::query()->where('action', 'help.article.archive')->where('entity_id', $article->id)->exists());

        $file = UploadedFile::fake()->image('diagram.png');
        $this->actingAs($admin)
            ->post(route('admin.help.articles.media.store', $article), [
                'file' => $file,
                'alt' => 'Diagram',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('help_media', [
            'article_id' => $article->id,
            'alt' => 'Diagram',
        ]);
        $this->assertTrue(AuditLog::query()->where('action', 'help.media.create')->exists());

        $media = $article->fresh()->media()->firstOrFail();
        $this->actingAs($admin)
            ->delete(route('admin.help.articles.media.destroy', [$article, $media]))
            ->assertRedirect();

        $this->assertTrue(AuditLog::query()->where('action', 'help.media.delete')->exists());
        $this->assertDatabaseMissing('help_media', ['id' => $media->id]);
    }

    #[Test]
    public function admin_hub_includes_help_center_tile(): void
    {
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();

        $this->actingAs($admin)
            ->get(route('hubs.admin'))
            ->assertOk()
            ->assertSee(__('hubs.help_center'))
            ->assertSee(route('admin.help.index'), false);
    }

    #[Test]
    public function incomplete_locale_warning_shown_on_editor(): void
    {
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $category = HelpCategory::factory()->create();
        $article = HelpArticle::factory()->create(['category_id' => $category->id]);
        HelpArticleLocale::factory()->create([
            'article_id' => $article->id,
            'locale' => 'en',
            'title' => 'English only',
            'body_markdown' => 'Body',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.help.articles.edit', $article))
            ->assertOk()
            ->assertSee(__('help.admin_missing_locales', ['locales' => 'ar, fr']));
    }

    #[Test]
    public function locale_with_title_but_empty_body_counts_incomplete(): void
    {
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $category = HelpCategory::factory()->create();
        $article = HelpArticle::factory()->create(['category_id' => $category->id]);
        HelpArticleLocale::factory()->create([
            'article_id' => $article->id,
            'locale' => 'en',
            'title' => 'Has title',
            'body_markdown' => 'Body text',
        ]);
        HelpArticleLocale::factory()->create([
            'article_id' => $article->id,
            'locale' => 'ar',
            'title' => 'عنوان بلا نص',
            'body_markdown' => '',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.help.articles.edit', $article))
            ->assertOk()
            ->assertSee(__('help.admin_missing_locales', ['locales' => 'ar, fr']));
    }
}
