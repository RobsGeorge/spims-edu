@php
    $roles = $article->audiences->pluck('role');
@endphp
@if($article->is_public && $roles->isEmpty())
    <span class="badge text-bg-secondary">{{ __('help.audience_public') }}</span>
@elseif($roles->isEmpty())
    <span class="badge text-bg-secondary">{{ __('help.audience_shared') }}</span>
@else
    @foreach($roles as $role)
        <span class="badge text-bg-secondary">{{ __('help.audience_'.$role->value) }}</span>
    @endforeach
@endif
