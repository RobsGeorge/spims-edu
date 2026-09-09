@php
    $effectiveTheme = in_array($cookieTheme ?? 'light', ['light', 'dark'], true)
        ? $cookieTheme
        : 'light';
    $nextTheme = $effectiveTheme === 'dark' ? 'light' : 'dark';
    $toggleLabel = $effectiveTheme === 'dark' ? __('ui.theme_switch_to_light') : __('ui.theme_switch_to_dark');
@endphp
<form method="POST" action="{{ route('theme.update') }}" class="d-inline spims-theme-toggle-form">
    @csrf
    <input type="hidden" name="theme" value="{{ $nextTheme }}">
    <button type="submit"
            class="btn btn-sm btn-outline-secondary app-icon-btn spims-theme-toggle"
            title="{{ $toggleLabel }}"
            aria-label="{{ $toggleLabel }}">
        @if($effectiveTheme === 'dark')
            <i class="bi bi-sun-fill" aria-hidden="true"></i>
            <span class="d-none d-md-inline">{{ __('ui.theme_light') }}</span>
        @else
            <i class="bi bi-moon-stars-fill" aria-hidden="true"></i>
            <span class="d-none d-md-inline">{{ __('ui.theme_dark') }}</span>
        @endif
    </button>
</form>
