@extends('layouts.app')

@section('title', $program->name . ' — ' . __('programs.brochure'))

@section('content')
<main id="main-content" class="container-xl py-4 py-md-6">

    {{-- Back link --}}
    <a href="{{ route('programs.catalog.index') }}"
       class="d-inline-flex align-items-center gap-2 spims-text-dim mb-4"
       style="font-size: var(--text-sm); text-decoration: none; min-height: 44px;">
        <i class="bi-arrow-inline-start" aria-hidden="true"></i>
        {{ __('programs.all_programs') }}
    </a>

    {{-- ── Hero ──────────────────────────────────────────────── --}}
    <x-card variant="panel" class="mb-4 program-hero">
        <div class="d-flex flex-wrap align-items-start gap-3 mb-3">
            <span class="spims-badge spims-badge--info">
                {{ __('program_type.' . $program->type->value) }}
            </span>
        </div>
        <h1 class="spims-title mb-2" style="font-size: var(--text-3xl); line-height: var(--leading-3xl);">
            {{ $program->name }}
        </h1>
        @if($program->marketing_summary)
            <p class="mb-0" style="font-size: var(--text-lg); line-height: var(--leading-lg); color: var(--color-text-muted); max-width: 65ch;">
                {{ $program->marketing_summary }}
            </p>
        @endif
    </x-card>

    {{-- ── Stat row ──────────────────────────────────────────── --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <x-stat
                :label="__('programs.total_credits')"
                :value="(string) $totalCredits"
                icon="credential"
            />
        </div>
        @if($program->elective_credits_required)
        <div class="col-6 col-md-3">
            <x-stat
                :label="__('programs.elective_credits')"
                :value="(string) $program->elective_credits_required"
                icon="course"
            />
        </div>
        @endif
        @if($program->max_semesters_to_graduate)
        <div class="col-6 col-md-3">
            <x-stat
                :label="__('programs.max_semesters')"
                :value="(string) $program->max_semesters_to_graduate"
                icon="attendance"
            />
        </div>
        @endif
        @if($program->passing_threshold)
        <div class="col-6 col-md-3">
            <x-stat
                :label="__('programs.passing_threshold')"
                :value="number_format($program->passing_threshold, 0) . '%'"
                icon="grade"
            />
        </div>
        @endif
    </div>

    {{-- ── Course sequence ───────────────────────────────────── --}}
    @if($coursesByYear->isNotEmpty())
        <x-card variant="quiet" class="mb-4">
            <h2 class="spims-title mb-4" style="font-size: var(--text-2xl);">
                {{ __('programs.course_sequence') }}
            </h2>

            @foreach($coursesByYear as $yearLevel => $programCourses)
                <div class="mb-4">
                    <h3 class="mb-3" style="font-size: var(--text-lg); color: var(--color-title); font-weight: 700;">
                        {{ __('programs.year_label', ['year' => $yearLevel]) }}
                    </h3>
                    <div class="d-flex flex-column gap-2">
                        @foreach($programCourses as $pc)
                            <x-card variant="bare" class="d-flex align-items-center gap-3 py-2 px-3"
                                    style="border-bottom: 1px solid var(--color-hairline);">
                                <div class="flex-grow-1 min-width-0">
                                    <span style="font-weight: 600; color: var(--color-text);">
                                        {{ $pc->course->code }} — {{ $pc->course->title }}
                                    </span>
                                    @if($pc->course->description)
                                        <p class="mb-0 spims-text-dim" style="font-size: var(--text-sm);">
                                            {{ Str::limit($pc->course->description, 100) }}
                                        </p>
                                    @endif
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                    <span class="spims-badge {{ $pc->requirement->value === 'REQUIRED' ? 'spims-badge--default' : 'spims-badge--secondary' }}">
                                        {{ __('requirement_type.' . $pc->requirement->value) }}
                                    </span>
                                    <span class="spims-text-dim" style="font-size: var(--text-sm); font-variant-numeric: tabular-nums; white-space: nowrap;">
                                        {{ $pc->course->credit_hours }}&nbsp;{{ __('programs.cr') }}
                                    </span>
                                </div>
                            </x-card>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </x-card>
    @endif

    {{-- ── Apply CTA ─────────────────────────────────────────── --}}
    <x-card variant="panel" class="program-apply-cta">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-4">
            <div>
                <h2 class="spims-title mb-1" style="font-size: var(--text-xl);">
                    {{ __('programs.ready_to_apply') }}
                </h2>
                <p class="spims-text-dim mb-0" style="font-size: var(--text-sm);">
                    {{ __('programs.apply_hint') }}
                </p>
            </div>

            <div class="d-flex flex-wrap gap-2">
                @if($program->applicationForms->isNotEmpty() && Route::has('applications.create'))
                    <a href="{{ route('applications.create', ['form' => $program->applicationForms->first()->id]) }}"
                       class="btn btn-primary">
                        <i class="bi-send me-1" aria-hidden="true"></i>
                        {{ __('programs.apply_now') }}
                    </a>
                @else
                    <a href="mailto:admissions@spims-edu.com" class="btn btn-primary">
                        <i class="bi-envelope me-1" aria-hidden="true"></i>
                        {{ __('programs.contact_admissions') }}
                    </a>
                @endif
                <a href="{{ route('programs.catalog.index') }}" class="btn btn-outline-primary">
                    {{ __('programs.all_programs') }}
                </a>
            </div>
        </div>
    </x-card>

</main>
@endsection

@push('styles')
<style>
.program-hero { border-inline-start: 4px solid var(--color-primary); }
.program-apply-cta { background: linear-gradient(135deg, color-mix(in srgb, var(--color-primary) 8%, var(--color-surface)), var(--color-surface)); }

@media print {
    .spims-nav, .spims-skip-link, .app-topbar, .app-sidebar,
    .app-bottom-nav, a.btn { display: none !important; }
    body { background: #fff !important; color: #000 !important; }
    .spims-card-panel, .spims-card-quiet, .spims-card-bare { box-shadow: none !important; border: 1px solid #ccc !important; }
    .container-xl { max-width: 100%; padding: 0; }
    main { padding: 0 !important; }
    @page { size: A4; margin: 15mm; }
}
@media (prefers-reduced-motion: reduce) {
    .program-apply-cta { transition: none; }
}
</style>
@endpush
