<?php

namespace App\Http\Controllers;

use App\Enums\RoleType;
use App\Models\HelpArticle;
use App\Services\Help\HelpCatalogService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class HelpController extends Controller
{
    public function __construct(
        private readonly HelpCatalogService $catalog,
    ) {}

    public function index(Request $request): View
    {
        $locale = app()->getLocale();
        $user = $request->user();
        $query = $request->string('q')->trim()->toString();
        $roleFilter = $this->parseRoleFilter($request->input('role'));

        $audienceFilter = $roleFilter !== null ? [$roleFilter] : null;

        $articles = $this->catalog->listArticles(
            viewer: $user,
            query: $query !== '' ? $query : null,
            audienceFilter: $audienceFilter,
            locale: $locale,
        );

        return view('help.index', [
            'articles' => $articles,
            'categories' => $this->catalog->publishedCategories(),
            'locale' => $locale,
            'searchQuery' => $query,
            'roleFilter' => $roleFilter?->value,
            'roleLabel' => $this->roleLabel($roleFilter),
            'roleChips' => $this->roleChips($user),
            'category' => null,
        ]);
    }

    public function category(Request $request, string $category): View
    {
        $locale = app()->getLocale();
        $user = $request->user();
        $cat = $this->catalog->findPublishedCategory($category);
        abort_if($cat === null, 404);

        $query = $request->string('q')->trim()->toString();
        $roleFilter = $this->parseRoleFilter($request->input('role'));
        $audienceFilter = $roleFilter !== null ? [$roleFilter] : null;

        $articles = $this->catalog->listArticles(
            viewer: $user,
            categorySlug: $cat->slug,
            query: $query !== '' ? $query : null,
            audienceFilter: $audienceFilter,
            locale: $locale,
        );

        return view('help.index', [
            'articles' => $articles,
            'categories' => $this->catalog->publishedCategories(),
            'locale' => $locale,
            'searchQuery' => $query,
            'roleFilter' => $roleFilter?->value,
            'roleLabel' => $this->roleLabel($roleFilter),
            'roleChips' => $this->roleChips($user),
            'category' => $cat,
        ]);
    }

    public function byRole(Request $request, string $role): View
    {
        $roleType = $this->parseRoleFilter($role);
        abort_if($roleType === null, 404);

        $request->merge(['role' => $roleType->value]);

        return $this->index($request);
    }

    public function show(Request $request, string $slug): View
    {
        $locale = app()->getLocale();
        $user = $request->user();
        $preview = $request->boolean('preview');

        $article = $this->catalog->findPublishedBySlug($slug, $user, $preview);
        abort_if($article === null, 404);

        $localeRow = $article->localeFor($locale);
        $usedFallback = $localeRow !== null
            && $localeRow->locale !== $locale
            && $article->locales->firstWhere('locale', $locale) === null;

        $bodyHtml = $this->catalog->renderBody($article, $locale) ?? '';

        $siblings = $this->catalog->listArticles(viewer: $user, locale: $locale)
            ->reject(fn (HelpArticle $a): bool => $a->slug === $article->slug)
            ->take(6)
            ->values();

        return view('help.show', [
            'article' => $article,
            'localeRow' => $localeRow,
            'bodyHtml' => $bodyHtml,
            'siblings' => $siblings,
            'locale' => $locale,
            'usedFallback' => $usedFallback,
            'isPreview' => $preview && $this->catalog->canManage($user),
        ]);
    }

    /**
     * Role keys used for Roles Hub “Role guides” section.
     *
     * @return list<RoleType>
     */
    public static function guideRoles(): array
    {
        return [
            RoleType::Student,
            RoleType::Instructor,
            RoleType::Ta,
            RoleType::AdministrativeAdmin,
            RoleType::AcademicAdmin,
            RoleType::FinancialAdmin,
            RoleType::SuperAdmin,
        ];
    }

    private function parseRoleFilter(mixed $role): ?RoleType
    {
        if (! is_string($role) || $role === '') {
            return null;
        }

        $role = strtoupper($role);

        return RoleType::tryFrom($role);
    }

    private function roleLabel(?RoleType $role): ?string
    {
        if ($role === null) {
            return null;
        }

        $key = 'roles_hub.role_'.$role->value;
        $label = __($key);

        return $label === $key ? $role->value : $label;
    }

    /**
     * @return Collection<int, RoleType>
     */
    private function roleChips(?\App\Models\User $user): Collection
    {
        if ($user === null) {
            return collect();
        }

        if ($user->isSuperAdmin()) {
            return collect(self::guideRoles());
        }

        return $user->roleTypes()->unique()->values();
    }
}
