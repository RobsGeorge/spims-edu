<?php

namespace App\Http\Controllers\Admin;

use App\Enums\HelpArticleStatus;
use App\Enums\RoleType;
use App\Http\Controllers\Controller;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Models\HelpMedia;
use App\Services\Admin\HelpAdminService;
use App\Services\Storage\ObjectStorageService;
use App\Support\HelpMarkdown;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HelpArticleAdminController extends Controller
{
    private const LOCALES = ['en', 'ar', 'fr'];

    public function index(Request $request): View
    {
        $filters = [
            'status' => $request->string('status')->toString(),
            'category_id' => $request->string('category_id')->toString(),
            'audience' => $request->string('audience')->toString(),
            'locale_completeness' => $request->string('locale_completeness')->toString(),
            'q' => $request->string('q')->toString(),
        ];

        $query = HelpArticle::query()
            ->with(['category', 'locales', 'audiences'])
            ->orderBy('sort_order')
            ->orderBy('slug');

        if ($filters['status'] !== '' && HelpArticleStatus::tryFrom($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if ($filters['category_id'] !== '') {
            $query->where('category_id', $filters['category_id']);
        }

        if ($filters['audience'] !== '' && RoleType::tryFrom($filters['audience'])) {
            $query->whereHas('audiences', fn (Builder $q) => $q->where('role', $filters['audience']));
        }

        if ($filters['q'] !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $filters['q']).'%';
            $query->where(function (Builder $outer) use ($like): void {
                $outer->where('slug', 'like', $like)
                    ->orWhereHas('locales', function (Builder $q) use ($like): void {
                        $q->where('title', 'like', $like)
                            ->orWhere('summary', 'like', $like);
                    });
            });
        }

        $articles = $query->paginate(30)->withQueryString();

        if ($filters['locale_completeness'] !== '') {
            $articles->setCollection(
                $articles->getCollection()->filter(function (HelpArticle $article) use ($filters): bool {
                    $missing = $this->missingLocales($article);

                    return match ($filters['locale_completeness']) {
                        'complete' => $missing === [],
                        'incomplete' => $missing !== [],
                        'missing_ar' => in_array('ar', $missing, true),
                        'missing_fr' => in_array('fr', $missing, true),
                        default => true,
                    };
                })->values()
            );
        }

        return view('admin.help.articles.index', [
            'articles' => $articles,
            'categories' => HelpCategory::query()->orderBy('sort_order')->orderBy('slug')->get(),
            'filters' => $filters,
            'statuses' => HelpArticleStatus::cases(),
            'roles' => RoleType::cases(),
            'missingByArticle' => $articles->getCollection()
                ->mapWithKeys(fn (HelpArticle $a) => [$a->id => $this->missingLocales($a)])
                ->all(),
        ]);
    }

    public function create(): View
    {
        return view('admin.help.articles.edit', $this->editorPayload(null));
    }

    public function store(Request $request, HelpAdminService $service): RedirectResponse
    {
        $data = $this->validatedArticle($request);
        $intent = $request->string('intent')->toString();
        $data['status'] = HelpArticleStatus::Draft->value;

        try {
            $article = $service->createArticle($request->user(), $data);

            if ($intent === 'publish') {
                $service->publishArticle($request->user(), $article);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()
            ->route('admin.help.articles.edit', $article)
            ->with('status', $intent === 'publish'
                ? __('help.admin_article_published')
                : __('help.admin_article_saved'));
    }

    public function edit(HelpArticle $article): View
    {
        $article->load(['locales', 'audiences', 'media', 'category']);

        return view('admin.help.articles.edit', $this->editorPayload($article));
    }

    public function update(Request $request, HelpArticle $article, HelpAdminService $service): RedirectResponse
    {
        $data = $this->validatedArticle($request, $article);
        $intent = $request->string('intent')->toString();

        if ($intent === 'draft') {
            $data['status'] = HelpArticleStatus::Draft->value;
        }

        try {
            $article = $service->updateArticle($request->user(), $article, $data);

            if ($intent === 'publish') {
                $service->publishArticle($request->user(), $article->fresh(['locales']));
            } elseif ($intent === 'archive') {
                $service->archiveArticle($request->user(), $article);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()
            ->route('admin.help.articles.edit', $article)
            ->with('status', match ($intent) {
                'publish' => __('help.admin_article_published'),
                'archive' => __('help.admin_article_archived'),
                default => __('help.admin_article_saved'),
            });
    }

    public function publish(Request $request, HelpArticle $article, HelpAdminService $service): RedirectResponse
    {
        try {
            $service->publishArticle($request->user(), $article);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', __('help.admin_article_published'));
    }

    public function archive(Request $request, HelpArticle $article, HelpAdminService $service): RedirectResponse
    {
        $service->archiveArticle($request->user(), $article);

        return back()->with('status', __('help.admin_article_archived'));
    }

    public function storeMedia(
        Request $request,
        HelpArticle $article,
        HelpAdminService $service,
        ObjectStorageService $storage,
    ): RedirectResponse {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,svg'],
            'alt' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $data['file'];
        $path = $storage->signedUploadPath(
            'help-media',
            (string) $request->user()->id,
            $file->getClientOriginalExtension() ?: $file->extension()
        );
        $storage->store($path, $file->get() ?: '');

        $service->attachMedia($request->user(), $article, $path, $data['alt'] ?? null);

        return back()->with('status', __('help.admin_media_attached'));
    }

    public function attachMediaPath(
        Request $request,
        HelpArticle $article,
        HelpAdminService $service,
    ): RedirectResponse {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:500', 'starts_with:help-media/'],
            'alt' => ['nullable', 'string', 'max:255'],
        ]);

        $service->attachMedia($request->user(), $article, $data['path'], $data['alt'] ?? null);

        return back()->with('status', __('help.admin_media_attached'));
    }

    public function destroyMedia(
        Request $request,
        HelpArticle $article,
        HelpMedia $media,
        HelpAdminService $service,
    ): RedirectResponse {
        abort_unless($media->article_id === $article->id, 404);

        $service->deleteMedia($request->user(), $media);

        return back()->with('status', __('help.admin_media_deleted'));
    }

    public function previewMarkdown(Request $request, HelpMarkdown $markdown): JsonResponse
    {
        $data = $request->validate([
            'body_markdown' => ['nullable', 'string', 'max:100000'],
        ]);

        return response()->json([
            'html' => $markdown->toHtml((string) ($data['body_markdown'] ?? '')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function editorPayload(?HelpArticle $article): array
    {
        $localeMap = [];
        foreach (self::LOCALES as $locale) {
            $row = $article?->locales->firstWhere('locale', $locale);
            $localeMap[$locale] = [
                'title' => old("locales.$locale.title", $row?->title ?? ''),
                'summary' => old("locales.$locale.summary", $row?->summary ?? ''),
                'body_markdown' => old("locales.$locale.body_markdown", $row?->body_markdown ?? ''),
            ];
        }

        $selectedAudiences = old('audiences', $article?->audiences->map(fn ($a) => $a->role->value)->all() ?? []);

        return [
            'article' => $article,
            'categories' => HelpCategory::query()->orderBy('sort_order')->orderBy('slug')->get(),
            'roles' => RoleType::cases(),
            'statuses' => HelpArticleStatus::cases(),
            'locales' => self::LOCALES,
            'localeMap' => $localeMap,
            'selectedAudiences' => is_array($selectedAudiences) ? $selectedAudiences : [],
            'missingLocales' => $article ? $this->missingLocales($article) : ['ar', 'fr'],
            'previewUrl' => $article
                ? route('help.show', ['slug' => $article->slug, 'preview' => 1])
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedArticle(Request $request, ?HelpArticle $article = null): array
    {
        $data = $request->validate([
            'category_id' => ['required', 'string', 'exists:help_categories,id'],
            'slug' => [
                'required',
                'string',
                'max:120',
                'alpha_dash',
                Rule::unique('help_articles', 'slug')->ignore($article?->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_public' => ['sometimes', 'boolean'],
            'audiences' => ['nullable', 'array'],
            'audiences.*' => ['string', Rule::in(array_column(RoleType::cases(), 'value'))],
            'locales' => ['nullable', 'array'],
            'locales.en.title' => ['nullable', 'string', 'max:255'],
            'locales.en.summary' => ['nullable', 'string', 'max:2000'],
            'locales.en.body_markdown' => ['nullable', 'string', 'max:100000'],
            'locales.ar.title' => ['nullable', 'string', 'max:255'],
            'locales.ar.summary' => ['nullable', 'string', 'max:2000'],
            'locales.ar.body_markdown' => ['nullable', 'string', 'max:100000'],
            'locales.fr.title' => ['nullable', 'string', 'max:255'],
            'locales.fr.summary' => ['nullable', 'string', 'max:2000'],
            'locales.fr.body_markdown' => ['nullable', 'string', 'max:100000'],
        ]);

        $data['is_public'] = $request->boolean('is_public');
        $data['audiences'] = $data['audiences'] ?? [];
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        $locales = [];
        foreach (self::LOCALES as $locale) {
            $payload = $data['locales'][$locale] ?? null;
            if (! is_array($payload)) {
                continue;
            }
            $title = trim((string) ($payload['title'] ?? ''));
            $summary = isset($payload['summary']) ? trim((string) $payload['summary']) : null;
            $body = (string) ($payload['body_markdown'] ?? '');
            if ($title === '' && ($summary === null || $summary === '') && trim($body) === '') {
                continue;
            }
            $locales[$locale] = [
                'title' => $title,
                'summary' => $summary === '' ? null : $summary,
                'body_markdown' => $body,
            ];
        }
        $data['locales'] = $locales;

        return $data;
    }

    /**
     * Locales missing a non-empty title+body (soft incompleteness warnings).
     * Publish still requires at least one complete locale (see HelpAdminService).
     *
     * @return list<string>
     */
    private function missingLocales(HelpArticle $article): array
    {
        $present = $article->locales
            ->filter(fn ($row) => trim((string) $row->title) !== ''
                && trim((string) $row->body_markdown) !== '')
            ->pluck('locale')
            ->all();

        return array_values(array_diff(self::LOCALES, $present));
    }
}
