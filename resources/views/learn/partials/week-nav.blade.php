{{-- Shared week sidebar for learn views --}}
@php
    /** @var \App\Models\CourseOffering $offering */
    /** @var \App\Models\Enrollment $enrollment */
    /** @var \Illuminate\Support\Collection $weeks */
    /** @var \App\Models\Week|null $activeWeek */
    /** @var \App\Services\Offerings\LearningProgressService $progress */
@endphp
<aside class="col-lg-3 mb-3">
    <x-card variant="panel">
        @php $progressPercent = (float) ($enrollment->progress_percent ?? 0); @endphp
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="h6 mb-0">{{ __('learn.weeks') }}</h2>
            <span class="badge text-bg-primary">{{ number_format($progressPercent, 0) }}%</span>
        </div>
        <div class="progress mb-3" style="height: 6px;" role="progressbar" aria-label="{{ __('learn.progress') }}" aria-valuenow="{{ (int) $progressPercent }}" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar" style="width: {{ min(100, max(0, $progressPercent)) }}%"></div>
        </div>
        <div class="list-group list-group-flush">
            @forelse($weeks as $week)
                @php
                    $unlocked = $enrollment
                        ? $progress->isWeekUnlocked($enrollment, $offering, $week)
                        : app(\App\Services\Offerings\ContentGatingService::class)
                            ->isWeekUnlocked($offering, $week, enrolled: true, completedWeekNumbers: $completedWeekNumbers ?? []);
                    $done = $enrollment ? $progress->isWeekComplete($enrollment, $week) : false;
                    $active = $activeWeek && $activeWeek->id === $week->id;
                    $weekItemIds = $week->items->pluck('id')->toArray();
                    $weekTotal = count($weekItemIds);
                    $weekCompleted = count(array_intersect($weekItemIds, $completedItemIds ?? []));
                @endphp
                <a href="{{ $unlocked ? route('learn.week', [$offering, $week]) : '#' }}"
                   class="list-group-item list-group-item-action px-0 {{ $active ? 'fw-semibold' : '' }} {{ ! $unlocked ? 'disabled spims-text-dim' : '' }}"
                   @if(! $unlocked) aria-disabled="true" tabindex="-1" @endif>
                    <div class="d-flex justify-content-between gap-2">
                        <span>{{ __('learn.week', ['number' => $week->number]) }}: {{ $week->title }}<span class="badge text-bg-info ms-1">{{ $weekCompleted }} / {{ $weekTotal }}</span></span>
                        @if($done)
                            <span class="badge text-bg-success">{{ __('learn.completed') }}</span>
                        @elseif(! $unlocked)
                            <span class="badge text-bg-info">{{ __('learn.locked') }}</span>
                        @endif
                    </div>
                </a>
            @empty
                <p class="spims-text-dim mb-0 small">{{ __('learn.no_weeks') }}</p>
            @endforelse
        </div>
    </x-card>
</aside>
