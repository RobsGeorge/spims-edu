{{--
    Demo mode banner — cookie-based dismiss.
    Only rendered when config('spims.demo_mode') is true.
    Styled as a distinct info bar using design tokens only.
--}}
@if(config('spims.demo_mode') && !request()->cookie('demo_banner_dismissed'))
<div
    id="demo-banner"
    role="status"
    aria-label="{{ __('demo.banner_label') }}"
    style="
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--space-3);
        padding: var(--space-2) var(--space-4);
        background: color-mix(in srgb, var(--color-accent) 18%, var(--color-surface));
        border-bottom: 1px solid color-mix(in srgb, var(--color-accent) 45%, transparent);
        position: sticky;
        inset-block-start: 0;
        z-index: 1050;
        font-size: var(--text-sm);
    ">
    <i class="bi bi-play-circle-fill" aria-hidden="true" style="color: var(--color-accent); font-size: var(--text-lg); flex-shrink: 0;"></i>

    <span style="flex: 1 1 12rem; min-width: 0; font-weight: 600; color: var(--color-title);">
        {{ __('demo.banner_title') }}
        @auth
            &mdash;
            <span style="font-weight: 400; color: var(--color-text-muted);">
                {{ __('demo.banner_signed_in_as', ['name' => auth()->user()->displayName()]) }}
            </span>
        @endauth
    </span>

    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-2);">
        {{-- Quick-login buttons --}}
        <form method="POST" action="{{ route('demo.login') }}" style="display: contents;">
            @csrf
            <input type="hidden" name="role" value="student">
            <button type="submit" class="btn btn-sm btn-outline-secondary"
                    style="min-height: 38px; border-radius: var(--radius-full); font-size: var(--text-xs);">
                {{ __('demo.role_student') }}
            </button>
        </form>
        <form method="POST" action="{{ route('demo.login') }}" style="display: contents;">
            @csrf
            <input type="hidden" name="role" value="instructor">
            <button type="submit" class="btn btn-sm btn-outline-secondary"
                    style="min-height: 38px; border-radius: var(--radius-full); font-size: var(--text-xs);">
                {{ __('demo.role_instructor') }}
            </button>
        </form>
        <form method="POST" action="{{ route('demo.login') }}" style="display: contents;">
            @csrf
            <input type="hidden" name="role" value="admin">
            <button type="submit" class="btn btn-sm btn-outline-secondary"
                    style="min-height: 38px; border-radius: var(--radius-full); font-size: var(--text-xs);">
                {{ __('demo.role_admin') }}
            </button>
        </form>

        <a href="{{ route('demo.guide') }}"
           class="btn btn-sm"
           style="min-height: 38px; border-radius: var(--radius-full); font-size: var(--text-xs); background: var(--color-accent); color: var(--color-accent-text); font-weight: 600; border: none;">
            {{ __('demo.banner_guide_link') }}
        </a>
    </div>

    {{-- Dismiss button — sets cookie via JS --}}
    <button
        type="button"
        onclick="
            document.cookie = 'demo_banner_dismissed=1; path=/; max-age=86400; SameSite=Lax';
            document.getElementById('demo-banner').style.display = 'none';
        "
        class="btn btn-sm btn-outline-secondary"
        aria-label="{{ __('demo.banner_dismiss') }}"
        style="min-height: 38px; min-width: 38px; border-radius: var(--radius-full); flex-shrink: 0;">
        <i class="bi bi-x" aria-hidden="true"></i>
        <span class="visually-hidden">{{ __('demo.banner_dismiss') }}</span>
    </button>
</div>
@endif
