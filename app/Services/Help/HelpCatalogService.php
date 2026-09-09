<?php

namespace App\Services\Help;

use App\Enums\HelpArticleStatus;
use App\Enums\RoleType;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Models\User;
use App\Support\HelpMarkdown;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * DB-backed help catalog for the portal reader.
 *
 * Theme Phase D / CMS-1 should inject this service rather than reading lang-file
 * article bodies. Controllers may keep UI chrome strings in lang/{locale}/help.php.
 */
class HelpCatalogService
{
    public function __construct(
        private readonly HelpMarkdown $markdown,
    ) {}

    /**
     * @return Collection<int, HelpCategory>
     */
    public function publishedCategories(): Collection
    {
        return HelpCategory::query()
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('slug')
            ->get();
    }

    public function findPublishedCategory(string $slug): ?HelpCategory
    {
        return HelpCategory::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();
    }

    /**
     * Published articles explicitly targeting a role (Roles Hub guides).
     * Shared/public (empty audience) articles are excluded.
     *
     * @return Collection<int, HelpArticle>
     */
    public function articlesForRoleGuide(RoleType $role, string $locale = 'en'): Collection
    {
        return HelpArticle::query()
            ->with(['locales', 'audiences', 'category'])
            ->where('status', HelpArticleStatus::Published)
            ->whereHas('category', fn (Builder $q) => $q->where('is_published', true))
            ->whereHas('audiences', function (Builder $q) use ($role): void {
                if ($role === RoleType::Ta) {
                    $q->whereIn('role', [RoleType::Ta->value, RoleType::Instructor->value]);
                } else {
                    $q->where('role', $role->value);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('slug')
            ->get();
    }

    /**
     * List published articles visible to the viewer (guest or authenticated).
     *
     * @param  list<RoleType>|null  $audienceFilter  optional role chip filter
     * @return Collection<int, HelpArticle>
     */
    public function listArticles(
        ?User $viewer = null,
        ?string $categorySlug = null,
        ?string $query = null,
        ?array $audienceFilter = null,
        string $locale = 'en',
    ): Collection {
        $builder = HelpArticle::query()
            ->with(['locales', 'audiences', 'category'])
            ->where('status', HelpArticleStatus::Published)
            ->whereHas('category', fn (Builder $q) => $q->where('is_published', true))
            ->orderBy('sort_order')
            ->orderBy('slug');

        if ($categorySlug !== null && $categorySlug !== '') {
            $builder->whereHas('category', fn (Builder $q) => $q->where('slug', $categorySlug));
        }

        $needle = $query !== null ? trim($query) : '';
        if ($needle !== '') {
            $like = '%'.$this->escapeLike($needle).'%';
            // Match title/summary/body in any locale; rank current locale first after fetch.
            $builder->whereHas('locales', function (Builder $q) use ($like): void {
                $q->where(function (Builder $fields) use ($like): void {
                    $fields->where('title', 'like', $like)
                        ->orWhere('summary', 'like', $like)
                        ->orWhere('body_markdown', 'like', $like);
                });
            });
        }

        $results = $builder->get()
            ->filter(fn (HelpArticle $article): bool => $this->isVisibleTo($article, $viewer, $audienceFilter))
            ->values();

        if ($needle === '') {
            return $results;
        }

        // Prefer matches in the viewer's current locale, then English, then others.
        return $results
            ->sortBy(function (HelpArticle $article) use ($needle, $locale): int {
                if ($this->localeRowMatches($article, $locale, $needle)) {
                    return 0;
                }
                if ($locale !== 'en' && $this->localeRowMatches($article, 'en', $needle)) {
                    return 1;
                }

                return 2;
            })
            ->values();
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['%', '_'], ['\\%', '\\_'], $value);
    }

    private function localeRowMatches(HelpArticle $article, string $locale, string $needle): bool
    {
        $row = $article->locales->firstWhere('locale', $locale);
        if ($row === null) {
            return false;
        }

        $haystacks = [
            (string) $row->title,
            (string) ($row->summary ?? ''),
            (string) $row->body_markdown,
        ];

        foreach ($haystacks as $text) {
            if ($text !== '' && mb_stripos($text, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    public function findPublishedBySlug(string $slug, ?User $viewer = null, bool $preview = false): ?HelpArticle
    {
        $article = HelpArticle::query()
            ->with(['locales', 'audiences', 'category', 'media'])
            ->where('slug', $slug)
            ->first();

        if ($article === null) {
            return null;
        }

        if ($preview && $viewer !== null && $this->canManage($viewer)) {
            return $article;
        }

        if ($article->status !== HelpArticleStatus::Published) {
            return null;
        }

        if ($article->category && ! $article->category->is_published) {
            return null;
        }

        if (! $this->isVisibleTo($article, $viewer)) {
            return null;
        }

        return $article;
    }

    /**
     * @param  list<RoleType>|null  $audienceFilter
     */
    public function isVisibleTo(HelpArticle $article, ?User $viewer, ?array $audienceFilter = null): bool
    {
        if ($viewer !== null && $viewer->isSuperAdmin()) {
            return $this->matchesAudienceFilter($article, $audienceFilter);
        }

        $audiences = $article->relationLoaded('audiences')
            ? $article->audiences
            : $article->audiences()->get();

        $roles = $audiences->pluck('role')->all();

        if ($roles === []) {
            if ($article->is_public) {
                return $this->matchesAudienceFilter($article, $audienceFilter);
            }

            // Empty audience + not public → all authenticated users.
            if ($viewer === null) {
                return false;
            }

            return $this->matchesAudienceFilter($article, $audienceFilter);
        }

        if ($viewer === null) {
            return false;
        }

        $userRoles = $viewer->roleTypes()->all();
        $intersection = array_filter(
            $roles,
            fn (RoleType $role): bool => in_array($role, $userRoles, true)
        );

        // TAs share instructor teach guides.
        if ($intersection === [] && in_array(RoleType::Ta, $userRoles, true)) {
            $hasInstructor = in_array(RoleType::Instructor, $roles, true);
            if (! $hasInstructor) {
                return false;
            }
        } elseif ($intersection === []) {
            return false;
        }

        return $this->matchesAudienceFilter($article, $audienceFilter);
    }

    public function renderBody(HelpArticle $article, string $locale = 'en'): ?string
    {
        $row = $article->localeFor($locale);
        if ($row === null) {
            return null;
        }

        return $this->markdown->toHtml($row->body_markdown);
    }

    public function canManage(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        try {
            app(\App\Support\AuthorizeService::class)->authorize($user, 'help.manage');

            return true;
        } catch (\App\Exceptions\AuthorizationException) {
            return false;
        }
    }

    /**
     * @param  list<RoleType>|null  $audienceFilter
     */
    private function matchesAudienceFilter(HelpArticle $article, ?array $audienceFilter): bool
    {
        if ($audienceFilter === null || $audienceFilter === []) {
            return true;
        }

        $audiences = $article->relationLoaded('audiences')
            ? $article->audiences
            : $article->audiences()->get();

        if ($audiences->isEmpty()) {
            // Shared / all-auth articles match any role chip.
            return true;
        }

        foreach ($audienceFilter as $role) {
            if ($audiences->contains(fn ($row): bool => $row->role === $role)) {
                return true;
            }
            if ($role === RoleType::Ta
                && $audiences->contains(fn ($row): bool => $row->role === RoleType::Instructor)) {
                return true;
            }
        }

        return false;
    }
}
