{{-- Published Help CMS articles by role (from RolesHubController $roleGuides / $guideRoles). --}}
@php
    $guideRoles = $guideRoles ?? [];
    $roleGuides = $roleGuides ?? [];
    $helpLocale = $helpLocale ?? app()->getLocale();
@endphp
<section class="roles-hub-portal-guides mb-4" aria-labelledby="roles-hub-portal-guides-heading">
    <h2 id="roles-hub-portal-guides-heading" class="roles-hub-help-heading">{{ __('roles_hub.portal_guides_title') }}</h2>
    <p class="spims-text-dim small mb-3">{{ __('roles_hub.portal_guides_hint') }}</p>

    @forelse($guideRoles as $guideRole)
        @php
            /** @var \App\Enums\RoleType $guideRole */
            if ($guideRole === \App\Enums\RoleType::SuperAdmin) { continue; }
            $articles = $roleGuides[$guideRole->value] ?? collect();
            $roleLabel = __('roles_hub.role_'.$guideRole->value);
            if ($roleLabel === 'roles_hub.role_'.$guideRole->value) {
                $roleLabel = __('help.audience_'.$guideRole->value);
            }
        @endphp
        <details class="roles-hub-panel mb-2" @if($loop->first) open @endif>
            <summary class="roles-hub-summary">
                <span class="fw-semibold">{{ $roleLabel }}</span>
                <span class="spims-text-dim small ms-2">{{ $articles->count() }}</span>
            </summary>
            <div class="p-3 pt-2">
                @if($articles->isEmpty())
                    <p class="small spims-text-dim mb-0">{{ __('roles_hub.portal_guides_empty') }}</p>
                @else
                    <ul class="list-unstyled mb-0">
                        @foreach($articles as $article)
                            @php
                                $row = $article->localeFor($helpLocale);
                                $title = $row?->title ?? $article->slug;
                            @endphp
                            <li class="mb-1">
                                <a href="{{ route('help.show', $article->slug) }}">{{ $title }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </details>
    @empty
        <p class="small spims-text-dim mb-0">{{ __('roles_hub.portal_guides_empty') }}</p>
    @endforelse

    <p class="mt-3 mb-0">
        <a href="{{ route('help.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('help.back_to_index') }}</a>
    </p>
</section>
