<?php

namespace App\Services\Help;

use App\Enums\HelpArticleStatus;
use App\Enums\RoleType;
use App\Models\HelpArticle;
use App\Models\HelpArticleAudience;
use App\Models\HelpArticleLocale;
use App\Models\HelpCategory;
use Database\Seeders\HelpSeeder;
use Illuminate\Support\Facades\File;

/**
 * Ops import/export for the Help CMS catalog (CMS-3).
 *
 * Source of truth for live content is the DB. Lang files keep UI chrome only;
 * article bodies may still be imported from a legacy `articles` key or JSON.
 */
class HelpCatalogOpsService
{
    private const LOCALES = ['en', 'ar', 'fr'];

    /**
     * Idempotent upsert from a structured payload.
     *
     * @param  array{
     *   categories?: list<array{slug: string, sort_order?: int, is_published?: bool}>,
     *   articles: list<array<string, mixed>>
     * }  $payload
     * @return array{categories: int, articles: int, locales: int}
     */
    public function importPayload(array $payload): array
    {
        $categoryCount = 0;
        $articleCount = 0;
        $localeCount = 0;

        $categoryIds = [];
        foreach ($payload['categories'] ?? [] as $category) {
            $slug = (string) ($category['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $model = HelpCategory::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'sort_order' => (int) ($category['sort_order'] ?? 0),
                    'is_published' => (bool) ($category['is_published'] ?? true),
                ]
            );
            $categoryIds[$slug] = $model->id;
            $categoryCount++;
        }

        foreach ($payload['articles'] ?? [] as $article) {
            $slug = (string) ($article['slug'] ?? '');
            if ($slug === '') {
                continue;
            }

            $categorySlug = (string) ($article['category'] ?? $article['category_slug'] ?? 'shared');
            if (! isset($categoryIds[$categorySlug])) {
                $categoryIds[$categorySlug] = HelpCategory::query()->updateOrCreate(
                    ['slug' => $categorySlug],
                    ['sort_order' => 0, 'is_published' => true]
                )->id;
                $categoryCount++;
            }

            $status = HelpArticleStatus::tryFrom((string) ($article['status'] ?? HelpArticleStatus::Published->value))
                ?? HelpArticleStatus::Published;

            $model = HelpArticle::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'category_id' => $categoryIds[$categorySlug],
                    'status' => $status,
                    'sort_order' => (int) ($article['sort_order'] ?? 0),
                    'is_public' => (bool) ($article['is_public'] ?? false),
                    'published_at' => $status === HelpArticleStatus::Published
                        ? ($article['published_at'] ?? now())
                        : ($article['published_at'] ?? null),
                ]
            );
            $articleCount++;

            foreach ($article['locales'] ?? [] as $locale => $row) {
                $locale = (string) $locale;
                if (! in_array($locale, self::LOCALES, true)) {
                    continue;
                }
                $title = trim((string) ($row['title'] ?? ''));
                $body = (string) ($row['body_markdown'] ?? $row['body'] ?? '');
                if ($title === '' && trim($body) === '') {
                    continue;
                }
                HelpArticleLocale::query()->updateOrCreate(
                    [
                        'article_id' => $model->id,
                        'locale' => $locale,
                    ],
                    [
                        'title' => $title,
                        'summary' => $row['summary'] ?? null,
                        'body_markdown' => $body,
                    ]
                );
                $localeCount++;
            }

            if (array_key_exists('audiences', $article)) {
                HelpArticleAudience::query()->where('article_id', $model->id)->delete();
                foreach ($article['audiences'] as $role) {
                    $value = $role instanceof RoleType ? $role->value : (string) $role;
                    if ($value === '') {
                        continue;
                    }
                    HelpArticleAudience::query()->create([
                        'article_id' => $model->id,
                        'role' => $value,
                    ]);
                }
            }
        }

        return [
            'categories' => $categoryCount,
            'articles' => $articleCount,
            'locales' => $localeCount,
        ];
    }

    /**
     * Import from JSON file written by help:export (or compatible).
     *
     * @return array{categories: int, articles: int, locales: int}
     */
    public function importFromJsonFile(string $path): array
    {
        if (! File::isFile($path)) {
            throw new \InvalidArgumentException("Help import file not found: {$path}");
        }

        $decoded = json_decode(File::get($path), true);
        if (! is_array($decoded)) {
            throw new \InvalidArgumentException("Help import file is not valid JSON: {$path}");
        }

        return $this->importPayload($decoded);
    }

    /**
     * Merge legacy lang/{en,ar,fr}/help.php "articles" keys (if present) into a payload and import.
     *
     * @return array{categories: int, articles: int, locales: int}|null  null when no articles keys found
     */
    public function importFromLangFiles(): ?array
    {
        $merged = [];
        $found = false;

        foreach (self::LOCALES as $locale) {
            $path = lang_path("{$locale}/help.php");
            if (! File::isFile($path)) {
                continue;
            }
            /** @var array<string, mixed> $lines */
            $lines = include $path;
            if (! is_array($lines) || ! isset($lines['articles']) || ! is_array($lines['articles'])) {
                continue;
            }
            $found = true;
            foreach ($lines['articles'] as $slug => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $slug = (string) $slug;
                if (! isset($merged[$slug])) {
                    $merged[$slug] = [
                        'slug' => $slug,
                        'category' => (string) ($row['category'] ?? 'shared'),
                        'audiences' => $row['audiences'] ?? [],
                        'is_public' => (bool) ($row['is_public'] ?? false),
                        'sort_order' => (int) ($row['sort_order'] ?? 0),
                        'status' => (string) ($row['status'] ?? HelpArticleStatus::Published->value),
                        'locales' => [],
                    ];
                }
                $title = trim((string) ($row['title'] ?? ''));
                $body = (string) ($row['body_markdown'] ?? $row['body'] ?? '');
                $summary = $row['summary'] ?? null;
                if ($title === '' && trim($body) === '') {
                    continue;
                }
                $merged[$slug]['locales'][$locale] = [
                    'title' => $title,
                    'summary' => $summary,
                    'body_markdown' => $body,
                ];
                if (isset($row['category'])) {
                    $merged[$slug]['category'] = (string) $row['category'];
                }
                if (array_key_exists('audiences', $row)) {
                    $merged[$slug]['audiences'] = $row['audiences'];
                }
                if (array_key_exists('is_public', $row)) {
                    $merged[$slug]['is_public'] = (bool) $row['is_public'];
                }
            }
        }

        if (! $found) {
            return null;
        }

        return $this->importPayload([
            'articles' => array_values($merged),
        ]);
    }

    /**
     * Documented default seed path: HelpSeeder (idempotent upsert by slug).
     *
     * @return array{categories: int, articles: int, locales: int, source: string}
     */
    public function importFromSeeder(): array
    {
        (new HelpSeeder)->run();

        return [
            'categories' => HelpCategory::query()->count(),
            'articles' => HelpArticle::query()->count(),
            'locales' => HelpArticleLocale::query()->count(),
            'source' => 'HelpSeeder',
        ];
    }

    /**
     * @return array{exported_at: string, categories: list<array<string, mixed>>, articles: list<array<string, mixed>>}
     */
    public function exportToArray(): array
    {
        $categories = HelpCategory::query()
            ->orderBy('sort_order')
            ->orderBy('slug')
            ->get()
            ->map(fn (HelpCategory $c) => [
                'slug' => $c->slug,
                'sort_order' => $c->sort_order,
                'is_published' => $c->is_published,
            ])
            ->values()
            ->all();

        $articles = HelpArticle::query()
            ->with(['locales', 'audiences', 'category'])
            ->orderBy('sort_order')
            ->orderBy('slug')
            ->get()
            ->map(function (HelpArticle $article) {
                $locales = [];
                foreach ($article->locales as $row) {
                    $locales[$row->locale] = [
                        'title' => $row->title,
                        'summary' => $row->summary,
                        'body_markdown' => $row->body_markdown,
                    ];
                }

                return [
                    'slug' => $article->slug,
                    'category' => $article->category?->slug,
                    'status' => $article->status->value,
                    'sort_order' => $article->sort_order,
                    'is_public' => $article->is_public,
                    'published_at' => $article->published_at?->toIso8601String(),
                    'audiences' => $article->audiences->map(
                        fn ($a) => $a->role instanceof RoleType ? $a->role->value : (string) $a->role
                    )->values()->all(),
                    'locales' => $locales,
                ];
            })
            ->values()
            ->all();

        return [
            'exported_at' => now()->toIso8601String(),
            'categories' => $categories,
            'articles' => $articles,
        ];
    }

    public function exportToJsonFile(string $path): string
    {
        $dir = dirname($path);
        File::ensureDirectoryExists($dir);
        $json = json_encode($this->exportToArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        File::put($path, $json."\n");

        return $path;
    }
}
