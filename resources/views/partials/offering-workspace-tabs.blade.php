@php
    use Illuminate\Support\Facades\Route;

    $prefix = $prefix ?? 'admin';
    $active = $active ?? 'content';
    $tabs = [
        'content' => __('teach.tab_content'),
        'assessments' => __('teach.tab_assessments'),
        'assignments' => __('teach.tab_assignments'),
        'gradebook' => __('teach.tab_gradebook'),
        'live' => __('teach.tab_live'),
        'attendance' => __('teach.tab_attendance'),
        'discussions' => __('teach.tab_discussions'),
        'announcements' => __('teach.tab_announcements'),
        'roster' => __('teach.tab_roster'),
        'completion' => __('teach.tab_completion'),
    ];
    if ($prefix === 'teach') {
        if (Route::has('teach.projects.index')) {
            $tabs['projects'] = __('teach.tab_projects');
        }
        if (Route::has('teach.live-quiz.index')) {
            $tabs['live_quiz'] = __('teach.tab_live_quiz');
        }
        if (Route::has('teach.surveys.index')) {
            $tabs['surveys'] = __('teach.tab_surveys');
        }
    }
@endphp
<nav class="offering-workspace-tabs" aria-label="{{ __('teach.workspace') }}">
    <ul class="nav nav-pills flex-nowrap gap-2 overflow-auto pb-1">
        @foreach($tabs as $key => $label)
            @php
                $href = $prefix === 'teach'
                    ? match ($key) {
                        'completion' => route('teach.completion.show', $offering),
                        'assignments' => route('teach.assignments.index', $offering),
                        'discussions' => route('teach.discussions.index', $offering),
                        'projects' => route('teach.projects.index', $offering),
                        'live' => route('teach.live.index', $offering),
                        'live_quiz' => route('teach.live-quiz.index', $offering),
                        'surveys' => route('teach.surveys.index', $offering),
                        default => route('teach.show', ['offering' => $offering, 'tab' => $key]),
                    }
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
