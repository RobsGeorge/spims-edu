@php
    $prefix = $prefix ?? 'admin';
    $active = $active ?? 'content';
    $tabs = [
        'content' => __('teach.tab_content'),
        'assessments' => __('teach.tab_assessments'),
        'gradebook' => __('teach.tab_gradebook'),
        'live' => __('teach.tab_live'),
        'attendance' => __('teach.tab_attendance'),
        'discussions' => __('teach.tab_discussions'),
        'announcements' => __('teach.tab_announcements'),
        'roster' => __('teach.tab_roster'),
        'completion' => __('teach.tab_completion'),
    ];
@endphp
<nav class="offering-workspace-tabs" aria-label="{{ __('teach.workspace') }}">
    <ul class="nav nav-pills flex-nowrap gap-2 overflow-auto pb-1">
        @foreach($tabs as $key => $label)
            @php
                $href = $prefix === 'teach'
                    ? ($key === 'completion'
                        ? route('teach.completion.show', $offering)
                        : route('teach.show', ['offering' => $offering, 'tab' => $key]))
                    : ($key === 'completion'
                        ? route('admin.offering-closing.show', $offering)
                        : route('admin.offerings.show', $offering).'#workspace-'.$key);
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
