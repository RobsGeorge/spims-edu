@extends('layouts.app')

@section('title', __('programs.catalog_title'))

@section('content')
<main id="main-content" class="container-xl py-4 py-md-6">

    <x-page-header
        :title="__('programs.catalog_title')"
        :subtitle="__('programs.catalog_subtitle')"
    />

    @if($programs->isEmpty())
        <x-empty-state
            :title="__('programs.no_programs')"
            :message="__('programs.no_programs_hint')"
            icon="bi-journal-text"
        />
    @else
        <div class="grid-auto-md mt-4">
            @foreach($programs as $program)
                @php
                    $totalCredits = $program->programCourses->sum(fn ($pc) => $pc->course->credit_hours ?? 0);
                    $hasActiveForm = $program->applicationForms->isNotEmpty();
                @endphp

                <a href="{{ route('programs.catalog.show', $program->code) }}"
                   class="text-decoration-none"
                   aria-label="{{ $program->name }}">
                    <x-card variant="panel" class="h-100 program-catalog-card">

                        <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                            <span class="spims-badge spims-badge--info">
                                {{ __('program_type.' . $program->type->value) }}
                            </span>
                            <i class="bi-journal-text spims-icon-md spims-text-dim flex-shrink-0" aria-hidden="true"></i>
                        </div>

                        <h2 class="spims-title mb-1" style="font-size: var(--text-xl); line-height: var(--leading-xl);">
                            {{ $program->name }}
                        </h2>

                        @if($program->marketing_summary)
                            <p class="spims-text-dim mb-3" style="font-size: var(--text-sm); line-height: var(--leading-base);">
                                {{ Str::limit($program->marketing_summary, 120) }}
                            </p>
                        @endif

                        <div class="d-flex flex-wrap gap-3 mb-3" style="font-size: var(--text-sm); color: var(--color-text-muted);">
                            <span>
                                <i class="bi-award me-1" aria-hidden="true"></i>
                                {{ $totalCredits }}&nbsp;{{ __('programs.credits') }}
                            </span>
                            <span>
                                <i class="bi-journal-text me-1" aria-hidden="true"></i>
                                {{ $program->programCourses->count() }}&nbsp;{{ __('programs.courses') }}
                            </span>
                            @if($program->max_semesters_to_graduate)
                                <span>
                                    <i class="bi-calendar3 me-1" aria-hidden="true"></i>
                                    {{ $program->max_semesters_to_graduate }}&nbsp;{{ __('programs.semesters') }}
                                </span>
                            @endif
                        </div>

                        <div class="mt-auto pt-2">
                            <span class="btn btn-primary btn-sm">
                                {{ $hasActiveForm ? __('programs.apply_now') : __('programs.learn_more') }}
                            </span>
                        </div>

                    </x-card>
                </a>
            @endforeach
        </div>
    @endif

</main>
@endsection

@push('styles')
<style>
.program-catalog-card {
    display: flex;
    flex-direction: column;
    height: 100%;
    transition: transform var(--motion-base), box-shadow var(--motion-base);
}
.program-catalog-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-floating);
}
@media (prefers-reduced-motion: reduce) {
    .program-catalog-card { transition: none; }
    .program-catalog-card:hover { transform: none; }
}
</style>
@endpush
