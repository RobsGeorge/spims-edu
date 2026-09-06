@php
    $compact = $compact ?? false;
@endphp
<div class="{{ $compact ? 'col-sm-6 col-lg-4' : 'col-md-6' }}">
    <article class="app-tile hub-tile h-100 d-flex flex-column">
        <h3 class="h5 mb-1">
            <i class="bi {{ $persona['icon'] }}" aria-hidden="true"></i>
            {{ $persona['name'] }}
        </h3>
        <p class="small text-muted-theme mb-1">{{ $persona['email'] }}</p>
        <p class="small mb-2">
            <span class="badge badge-brand">{{ $persona['role_label'] }}</span>
            @if($persona['locale'] === 'ar')
                <span class="badge text-bg-light">العربية</span>
            @elseif($persona['locale'] === 'fr')
                <span class="badge text-bg-light">Français</span>
            @endif
        </p>
        <p class="text-muted-theme small mb-3 flex-grow-1">{{ $persona['blurb'] }}</p>
        <form method="POST" action="{{ route('demo.enter', $persona['slug']) }}" class="mt-auto">
            @csrf
            <button type="submit" class="btn btn-{{ $compact ? 'outline-primary btn-sm' : 'primary' }} w-100">
                {{ __('demo.enter', ['name' => $persona['first_name']]) }}
            </button>
        </form>
    </article>
</div>
