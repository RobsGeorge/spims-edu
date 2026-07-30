@php
    $prefix = $prefix ?? 'admin';
    $active = $active ?? 'content';
    $tabs = [
        'content' => __('teach.tab_content'),
        'assessments' => __('teach.tab_assessments'),
        'gradebook' => __('teach.tab_gradebook'),
        'live' => __('teach.tab_live'),
        'discussions' => __('teach.tab_discussions'),
        'announcements' => __('teach.tab_announcements'),
        'roster' => __('teach.tab_roster'),
    ];
@endphp
<nav class="offering-workspace-tabs" aria-label="{{ __('teach.workspace') }}">
    <ul class="nav nav-pills flex-nowrap gap-2 overflow-auto pb-1">
        @foreach($tabs as $key => $label)
            @php
                $href = $prefix === 'teach'
                    ? route('teach.show', ['offering' => $offering, 'tab' => $key])
                    : route('admin.offerings.show', $offering).'#workspace-'.$key;
                $isActive = $active === $key;
            @endphp
            <li class="nav-item">
                <a class="nav-link {{ $isActive ? 'active' : '' }}" href="{{ $href }}" @if($isActive) aria-current="page" @endif>
                    {{ $label }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
