<?php

namespace App\Services\Admin;

use App\Enums\HelpArticleStatus;
use App\Enums\RoleType;
use App\Models\HelpArticle;
use App\Models\HelpArticleAudience;
use App\Models\HelpArticleLocale;
use App\Models\HelpCategory;
use App\Models\HelpMedia;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HelpAdminService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    public function createCategory(User $actor, array $data): HelpCategory
    {
        $this->authorize->authorize($actor, 'help.manage');

        return $this->audit->withAudit($actor, 'help.category.create', function () use ($data) {
            return HelpCategory::query()->create([
                'slug' => $data['slug'],
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'is_published' => (bool) ($data['is_published'] ?? true),
            ]);
        }, 'HelpCategory');
    }

    public function updateCategory(User $actor, HelpCategory $category, array $data): HelpCategory
    {
        $this->authorize->authorize($actor, 'help.manage');

        $before = $category->only(['slug', 'sort_order', 'is_published']);

        $category->update([
            'slug' => $data['slug'] ?? $category->slug,
            'sort_order' => array_key_exists('sort_order', $data)
                ? (int) $data['sort_order']
                : $category->sort_order,
            'is_published' => array_key_exists('is_published', $data)
                ? (bool) $data['is_published']
                : $category->is_published,
        ]);

        $this->audit->write(
            $actor,
            'help.category.update',
            'HelpCategory',
            $category->id,
            $before,
            $category->only(['slug', 'sort_order', 'is_published'])
        );

        return $category->fresh();
    }

    public function deleteCategory(User $actor, HelpCategory $category): void
    {
        $this->authorize->authorize($actor, 'help.manage');

        $before = $category->only(['slug', 'sort_order', 'is_published']);
        $id = $category->id;
        $category->delete();

        $this->audit->write($actor, 'help.category.delete', 'HelpCategory', $id, $before, null);
    }

    public function createArticle(User $actor, array $data): HelpArticle
    {
        $this->authorize->authorize($actor, 'help.manage');

        return $this->audit->withAudit($actor, 'help.article.create', function () use ($actor, $data) {
            return DB::transaction(function () use ($actor, $data) {
                $article = HelpArticle::query()->create([
                    'category_id' => $data['category_id'],
                    'slug' => $data['slug'],
                    'status' => HelpArticleStatus::from($data['status'] ?? HelpArticleStatus::Draft->value),
                    'sort_order' => (int) ($data['sort_order'] ?? 0),
                    'is_public' => (bool) ($data['is_public'] ?? false),
                    'published_at' => ($data['status'] ?? null) === HelpArticleStatus::Published->value
                        ? now()
                        : null,
                    'updated_by_id' => $actor->id,
                ]);

                if (! empty($data['locales']) && is_array($data['locales'])) {
                    foreach ($data['locales'] as $locale => $payload) {
                        $this->upsertLocaleRow($article, (string) $locale, $payload);
                    }
                }

                if (array_key_exists('audiences', $data)) {
                    $this->syncAudiences($article, $data['audiences'] ?? []);
                }

                return $article->fresh(['locales', 'audiences']);
            });
        }, 'HelpArticle');
    }

    public function updateArticle(User $actor, HelpArticle $article, array $data): HelpArticle
    {
        $this->authorize->authorize($actor, 'help.manage');

        $before = $article->only([
            'category_id', 'slug', 'status', 'sort_order', 'is_public', 'published_at',
        ]);

        return DB::transaction(function () use ($actor, $article, $data, $before) {
            $status = array_key_exists('status', $data)
                ? HelpArticleStatus::from($data['status'])
                : $article->status;

            $publishedAt = $article->published_at;
            if ($status === HelpArticleStatus::Published && $publishedAt === null) {
                $publishedAt = now();
            }
            if ($status !== HelpArticleStatus::Published) {
                // Keep published_at history for archive; clear only when returning to draft.
                if ($status === HelpArticleStatus::Draft) {
                    $publishedAt = null;
                }
            }

            $article->update([
                'category_id' => $data['category_id'] ?? $article->category_id,
                'slug' => $data['slug'] ?? $article->slug,
                'status' => $status,
                'sort_order' => array_key_exists('sort_order', $data)
                    ? (int) $data['sort_order']
                    : $article->sort_order,
                'is_public' => array_key_exists('is_public', $data)
                    ? (bool) $data['is_public']
                    : $article->is_public,
                'published_at' => $publishedAt,
                'updated_by_id' => $actor->id,
            ]);

            if (! empty($data['locales']) && is_array($data['locales'])) {
                foreach ($data['locales'] as $locale => $payload) {
                    $this->upsertLocaleRow($article, (string) $locale, $payload);
                }
            }

            if (array_key_exists('audiences', $data)) {
                $this->syncAudiences($article, $data['audiences'] ?? []);
            }

            $fresh = $article->fresh(['locales', 'audiences']);

            $this->audit->write(
                $actor,
                'help.article.update',
                'HelpArticle',
                $article->id,
                $before,
                $fresh?->only([
                    'category_id', 'slug', 'status', 'sort_order', 'is_public', 'published_at',
                ])
            );

            return $fresh;
        });
    }

    public function publishArticle(User $actor, HelpArticle $article): HelpArticle
    {
        $this->authorize->authorize($actor, 'help.manage');

        $publishable = $article->locales()
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->whereNotNull('body_markdown')
            ->where('body_markdown', '!=', '')
            ->exists();

        if (! $publishable) {
            throw ValidationException::withMessages([
                'locales' => [__('help.admin_publish_needs_locale')],
            ]);
        }

        $before = $article->only(['status', 'published_at']);
        $article->update([
            'status' => HelpArticleStatus::Published,
            'published_at' => $article->published_at ?? now(),
            'updated_by_id' => $actor->id,
        ]);

        $this->audit->write(
            $actor,
            'help.article.publish',
            'HelpArticle',
            $article->id,
            $before,
            $article->only(['status', 'published_at'])
        );

        return $article->fresh(['locales', 'audiences']);
    }

    public function archiveArticle(User $actor, HelpArticle $article): HelpArticle
    {
        $this->authorize->authorize($actor, 'help.manage');

        $before = $article->only(['status']);
        $article->update([
            'status' => HelpArticleStatus::Archived,
            'updated_by_id' => $actor->id,
        ]);

        $this->audit->write(
            $actor,
            'help.article.archive',
            'HelpArticle',
            $article->id,
            $before,
            $article->only(['status'])
        );

        return $article->fresh();
    }

    public function attachMedia(User $actor, HelpArticle $article, string $path, ?string $alt = null): HelpMedia
    {
        $this->authorize->authorize($actor, 'help.manage');

        return $this->audit->withAudit($actor, 'help.media.create', function () use ($article, $path, $alt, $actor) {
            $article->update(['updated_by_id' => $actor->id]);

            return HelpMedia::query()->create([
                'article_id' => $article->id,
                'path' => $path,
                'alt' => $alt,
            ]);
        }, 'HelpMedia');
    }

    public function deleteMedia(User $actor, HelpMedia $media): void
    {
        $this->authorize->authorize($actor, 'help.manage');

        $before = $media->only(['article_id', 'path', 'alt']);
        $id = $media->id;
        $articleId = $media->article_id;
        $media->delete();

        HelpArticle::query()->whereKey($articleId)->update(['updated_by_id' => $actor->id]);

        $this->audit->write($actor, 'help.media.delete', 'HelpMedia', $id, $before, null);
    }

    /**
     * @param  array{title?: string, summary?: string|null, body_markdown?: string}  $payload
     */
    private function upsertLocaleRow(HelpArticle $article, string $locale, array $payload): HelpArticleLocale
    {
        return HelpArticleLocale::query()->updateOrCreate(
            [
                'article_id' => $article->id,
                'locale' => $locale,
            ],
            [
                'title' => $payload['title'] ?? '',
                'summary' => $payload['summary'] ?? null,
                'body_markdown' => $payload['body_markdown'] ?? '',
            ]
        );
    }

    /**
     * @param  list<string|RoleType>  $roles
     */
    private function syncAudiences(HelpArticle $article, array $roles): void
    {
        $normalized = collect($roles)
            ->map(fn ($role) => $role instanceof RoleType ? $role->value : (string) $role)
            ->unique()
            ->values()
            ->all();

        HelpArticleAudience::query()->where('article_id', $article->id)->delete();

        foreach ($normalized as $role) {
            HelpArticleAudience::query()->create([
                'article_id' => $article->id,
                'role' => RoleType::from($role),
            ]);
        }
    }
}
