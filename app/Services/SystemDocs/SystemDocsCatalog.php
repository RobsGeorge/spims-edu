<?php

namespace App\Services\SystemDocs;

use App\Models\Setting;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\HelpMarkdown;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class SystemDocsCatalog
{
    public function __construct(
        private readonly HelpMarkdown $markdown,
        private readonly AuditLogWriter $audit,
    ) {}

    public function settingKey(): string
    {
        return (string) config('system_docs.setting_key', 'system_docs.guest_published');
    }

    public function isGuestPublished(): bool
    {
        $row = Setting::query()->find($this->settingKey());

        return (bool) ($row?->value['enabled'] ?? true);
    }

    public function setGuestPublished(User $actor, bool $enabled): void
    {
        $this->audit->withAudit(
            $actor,
            $enabled ? 'system_docs.guest_publish' : 'system_docs.guest_unpublish',
            function () use ($actor, $enabled): Setting {
                return Setting::query()->updateOrCreate(
                    ['key' => $this->settingKey()],
                    [
                        'value' => [
                            'enabled' => $enabled,
                            'updated_at' => now()->toIso8601String(),
                        ],
                        'updated_by_id' => $actor->id,
                    ]
                );
            },
            'Setting'
        );
    }

    /**
     * @return Collection<int, array{
     *     slug: string,
     *     audience: string,
     *     guest_eligible: bool,
     *     sort: int,
     *     icon: string,
     *     title: string,
     *     summary: string
     * }>
     */
    public function pagesVisibleTo(?User $viewer, ?string $audience = null): Collection
    {
        $guestPublished = $this->isGuestPublished();

        return $this->allPages()
            ->filter(function (array $page) use ($viewer, $guestPublished, $audience): bool {
                if ($audience !== null && $page['audience'] !== $audience) {
                    return false;
                }

                if ($viewer !== null) {
                    return true;
                }

                return $guestPublished && $page['guest_eligible'];
            })
            ->values();
    }

    /**
     * @return array{
     *     slug: string,
     *     audience: string,
     *     guest_eligible: bool,
     *     sort: int,
     *     icon: string,
     *     title: string,
     *     summary: string
     * }|null
     */
    public function findPage(string $slug, ?User $viewer): ?array
    {
        $page = $this->allPages()->firstWhere('slug', $slug);
        if ($page === null) {
            return null;
        }

        if ($viewer !== null) {
            return $page;
        }

        if (! $this->isGuestPublished() || ! $page['guest_eligible']) {
            return null;
        }

        return $page;
    }

    public function canBrowse(?User $viewer): bool
    {
        if ($viewer !== null) {
            return true;
        }

        return $this->isGuestPublished();
    }

    /**
     * @return array{html: string, used_fallback: bool, locale: string}
     */
    public function renderBody(string $slug, string $locale): array
    {
        $path = $this->contentPath($locale, $slug);
        $usedFallback = false;

        if (! File::isFile($path)) {
            $path = $this->contentPath('en', $slug);
            $usedFallback = $locale !== 'en';
        }

        abort_unless(File::isFile($path), 404);

        $markdown = File::get($path);

        return [
            'html' => $this->markdown->toHtml($markdown),
            'used_fallback' => $usedFallback,
            'locale' => $usedFallback ? 'en' : $locale,
        ];
    }

    /**
     * @return Collection<int, array{
     *     slug: string,
     *     audience: string,
     *     guest_eligible: bool,
     *     sort: int,
     *     icon: string,
     *     title: string,
     *     summary: string
     * }>
     */
    public function allPages(): Collection
    {
        /** @var list<array{slug: string, audience: string, guest_eligible: bool, sort: int, icon?: string}> $raw */
        $raw = config('system_docs.pages', []);

        return collect($raw)
            ->map(function (array $page): array {
                $slug = $page['slug'];

                return [
                    'slug' => $slug,
                    'audience' => $page['audience'],
                    'guest_eligible' => (bool) ($page['guest_eligible'] ?? false),
                    'sort' => (int) ($page['sort'] ?? 0),
                    'icon' => $page['icon'] ?? 'bi-file-text',
                    'title' => __('system_docs.pages.'.$slug.'.title'),
                    'summary' => __('system_docs.pages.'.$slug.'.summary'),
                ];
            })
            ->sortBy('sort')
            ->values();
    }

    private function contentPath(string $locale, string $slug): string
    {
        return resource_path('system-docs'.DIRECTORY_SEPARATOR.$locale.DIRECTORY_SEPARATOR.$slug.'.md');
    }
}
