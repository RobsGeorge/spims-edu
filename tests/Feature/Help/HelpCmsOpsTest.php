<?php

namespace Tests\Feature\Help;

use App\Enums\HelpArticleStatus;
use App\Enums\RoleType;
use App\Models\HelpArticle;
use App\Models\HelpArticleLocale;
use App\Models\HelpCategory;
use App\Models\User;
use App\Services\Help\HelpCatalogOpsService;
use App\Services\Help\HelpCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HelpCmsOpsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function import_lang_uses_help_seeder_when_no_lang_articles(): void
    {
        $this->assertSame(0, HelpArticle::query()->count());

        $exit = Artisan::call('help:import-lang');
        $this->assertSame(0, $exit);
        $this->assertGreaterThan(0, HelpArticle::query()->count());
        $this->assertTrue(HelpArticle::query()->where('slug', 'getting-started')->exists());

        $count = HelpArticle::query()->count();
        $exit = Artisan::call('help:import-lang');
        $this->assertSame(0, $exit);
        $this->assertSame($count, HelpArticle::query()->count());
    }

    #[Test]
    public function import_lang_from_json_path_is_idempotent(): void
    {
        HelpCategory::factory()->create(['slug' => 'shared', 'is_published' => true]);

        $path = storage_path('app/help-export/test-import.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'categories' => [
                ['slug' => 'shared', 'sort_order' => 10, 'is_published' => true],
            ],
            'articles' => [
                [
                    'slug' => 'ops-guide',
                    'category' => 'shared',
                    'status' => HelpArticleStatus::Published->value,
                    'sort_order' => 1,
                    'is_public' => true,
                    'audiences' => [],
                    'locales' => [
                        'en' => [
                            'title' => 'Ops guide',
                            'summary' => 'Summary',
                            'body_markdown' => "## Ops\n\nBody",
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $this->assertSame(0, Artisan::call('help:import-lang', ['--path' => $path]));
        $this->assertDatabaseHas('help_articles', ['slug' => 'ops-guide']);
        $this->assertSame('Ops guide', HelpArticle::query()->where('slug', 'ops-guide')->firstOrFail()->localeFor('en')?->title);

        File::put($path, json_encode([
            'categories' => [
                ['slug' => 'shared', 'sort_order' => 10, 'is_published' => true],
            ],
            'articles' => [
                [
                    'slug' => 'ops-guide',
                    'category' => 'shared',
                    'status' => HelpArticleStatus::Published->value,
                    'sort_order' => 1,
                    'is_public' => true,
                    'audiences' => [],
                    'locales' => [
                        'en' => [
                            'title' => 'Ops guide updated',
                            'summary' => 'Summary',
                            'body_markdown' => "## Ops\n\nUpdated",
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $this->assertSame(0, Artisan::call('help:import-lang', ['--path' => $path]));
        $this->assertSame(1, HelpArticle::query()->where('slug', 'ops-guide')->count());
        $this->assertSame('Ops guide updated', HelpArticle::query()->where('slug', 'ops-guide')->firstOrFail()->localeFor('en')?->title);
    }

    #[Test]
    public function export_and_reimport_round_trips(): void
    {
        Artisan::call('help:import-lang', ['--seed' => true]);
        $before = HelpArticle::query()->count();
        $this->assertGreaterThan(0, $before);

        $path = storage_path('app/help-export/roundtrip.json');
        $this->assertSame(0, Artisan::call('help:export', ['--path' => $path]));
        $this->assertTrue(File::isFile($path));

        HelpArticle::query()->delete();
        $this->assertSame(0, HelpArticle::query()->count());

        $this->assertSame(0, Artisan::call('help:import-lang', ['--path' => $path]));
        $this->assertSame($before, HelpArticle::query()->count());
    }

    #[Test]
    public function search_ranks_current_locale_matches_first(): void
    {
        $category = HelpCategory::factory()->create(['is_published' => true]);
        $student = User::factory()->withRole(RoleType::Student)->create();

        $enHit = HelpArticle::factory()->published()->create([
            'category_id' => $category->id,
            'slug' => 'en-unique-token-article',
            'sort_order' => 10,
            'is_public' => false,
        ]);
        HelpArticleLocale::factory()->create([
            'article_id' => $enHit->id,
            'locale' => 'en',
            'title' => 'English only uniquetoken',
            'summary' => 's',
            'body_markdown' => 'body',
        ]);

        $arHit = HelpArticle::factory()->published()->create([
            'category_id' => $category->id,
            'slug' => 'ar-unique-token-article',
            'sort_order' => 20,
            'is_public' => false,
        ]);
        HelpArticleLocale::factory()->create([
            'article_id' => $arHit->id,
            'locale' => 'ar',
            'title' => 'Arabic uniquetoken guide',
            'summary' => 's',
            'body_markdown' => 'body',
        ]);
        HelpArticleLocale::factory()->create([
            'article_id' => $arHit->id,
            'locale' => 'en',
            'title' => 'Arabic guide English title',
            'summary' => 's',
            'body_markdown' => 'body without the marker',
        ]);

        $catalog = app(HelpCatalogService::class);
        $results = $catalog->listArticles($student, null, 'uniquetoken', null, 'ar');

        $this->assertGreaterThanOrEqual(2, $results->count());
        $this->assertSame('ar-unique-token-article', $results->first()->slug);
    }

    #[Test]
    public function ops_service_exports_structured_payload(): void
    {
        Artisan::call('help:import-lang', ['--seed' => true]);
        $payload = app(HelpCatalogOpsService::class)->exportToArray();

        $this->assertArrayHasKey('categories', $payload);
        $this->assertArrayHasKey('articles', $payload);
        $this->assertNotEmpty($payload['articles']);
        $this->assertArrayHasKey('locales', $payload['articles'][0]);
    }
}
