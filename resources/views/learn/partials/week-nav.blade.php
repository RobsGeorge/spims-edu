{{-- Shared week sidebar for learn views --}}
@php
    /** @var \App\Models\CourseOffering $offering */
    /** @var \App\Models\Enrollment $enrollment */
    /** @var \Illuminate\Support\Collection $weeks */
    /** @var \App\Models\Week|null $activeWeek */
    /** @var \App\Services\Offerings\LearningProgressService $progress */
@endphp
<aside class="col-lg-3 mb-3">
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 mb-0">{{ __('learn.weeks') }}</h2>
                <span class="badge text-bg-secondary">{{ number_format($enrollment->progress_percent, 0) }}%</span>
            </div>
            <div class="progress mb-3" style="height: 6px;" role="progressbar" aria-label="{{ __('learn.progress') }}" aria-valuenow="{{ (int) $enrollment->progress_percent }}" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar" style="width: {{ min(100, max(0, $enrollment->progress_percent)) }}%"></div>
            </div>
            <div class="list-group list-group-flush">
                @forelse($weeks as $week)
                    @php
                        $unlocked = $progress->isWeekUnlocked($enrollment, $offering, $week);
                        $done = $progress->isWeekComplete($enrollment, $week);
                        $active = $activeWeek && $activeWeek->id === $week->id;
                    @endphp
                    <a href="{{ $unlocked ? route('learn.week', [$offering, $week]) : '#' }}"
                       class="list-group-item list-group-item-action px-0 {{ $active ? 'fw-semibold' : '' }} {{ ! $unlocked ? 'disabled text-muted' : '' }}"
                       @if(! $unlocked) aria-disabled="true" tabindex="-1" @endif>
                        <div class="d-flex justify-content-between gap-2">
                            <span>{{ __('learn.week', ['number' => $week->number]) }}: {{ $week->title }}</span>
                            @if($done)
                                <span class="badge text-bg-success">{{ __('learn.completed') }}</span>
                            @elseif(! $unlocked)
                                <span class="badge text-bg-light text-muted">{{ __('learn.locked') }}</span>
                            @endif
                        </div>
                    </a>
                @empty
                    <p class="text-muted-theme mb-0 small">{{ __('learn.no_weeks') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</aside>
