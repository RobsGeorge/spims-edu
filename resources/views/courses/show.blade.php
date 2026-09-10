@extends('layouts.app')

@section('title', __('courses.detail_title', ['title' => $course->title]))

@section('content')
<main id="main-content" class="container-xl py-4 py-md-6">

    {{-- Back link --}}
    <a href="{{ route('catalog.index', ['tab' => 'courses']) }}"
       class="d-inline-flex align-items-center gap-2 mb-4 spims-back-link"
       style="font-size: var(--text-sm); text-decoration: none; min-height: 44px;">
        <i class="bi-arrow-inline-start" aria-hidden="true"></i>
        {{ __('courses.back_to_catalog') }}
    </a>

    <div class="row g-4">

        {{-- ── Main column ───────────────────────────────────────── --}}
        <div class="col-12 col-lg-8">

            {{-- Hero panel --}}
            <x-card variant="panel" class="mb-4 course-detail-hero">
                <div class="d-flex flex-wrap align-items-start gap-3 mb-3">
                    <span class="spims-badge spims-badge--info">
                        {{ $course->code }}
                    </span>
                    @if($course->credit_hours)
                        <span class="spims-badge spims-badge--secondary">
                            {{ trans_choice('courses.credit_hours', $course->credit_hours, ['n' => $course->credit_hours]) }}
                        </span>
                    @endif
                    @if($course->is_free)
                        <span class="spims-badge spims-badge--success">
                            {{ __('courses.free') }}
                        </span>
                    @endif
                </div>

                <h1 class="spims-title mb-3 course-hero-title">
                    {{ $course->title }}
                </h1>

                @if($course->description)
                    <p class="course-description mb-0">
                        {{ $course->description }}
                    </p>
                @else
                    <p class="spims-text-dim fst-italic mb-0">
                        {{ __('courses.no_description') }}
                    </p>
                @endif
            </x-card>

            {{-- Prerequisites --}}
            <x-card variant="quiet" class="mb-4">
                <h2 class="spims-title mb-3 course-section-title">
                    <i class="bi-diagram-3 me-2" aria-hidden="true"></i>
                    {{ __('courses.prerequisites') }}
                </h2>

                @if($course->prerequisites->isEmpty())
                    <p class="spims-text-dim course-meta-sm mb-0">
                        {{ __('courses.no_prerequisites') }}
                    </p>
                @else
                    <ul class="list-unstyled d-flex flex-column gap-2 mb-0">
                        @foreach($course->prerequisites as $prereq)
                            <li>
                                <a href="{{ route('courses.public.show', $prereq->code) }}"
                                   class="d-inline-flex align-items-center gap-2 course-prereq-link">
                                    <i class="bi-journal-text" aria-hidden="true"></i>
                                    <span>{{ $prereq->code }} — {{ $prereq->title }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            {{-- Part of these programs --}}
            <x-card variant="quiet" class="mb-4">
                <h2 class="spims-title mb-3 course-section-title">
                    <i class="bi-collection me-2" aria-hidden="true"></i>
                    {{ __('courses.programs') }}
                </h2>

                @php
                    $activePrograms = $course->programCourses->filter(fn ($pc) => $pc->program !== null);
                @endphp

                @if($activePrograms->isEmpty())
                    <p class="spims-text-dim course-meta-sm mb-0">
                        {{ __('courses.no_programs') }}
                    </p>
                @else
                    <div class="d-flex flex-column gap-2">
                        @foreach($activePrograms as $pc)
                            <x-card variant="bare"
                                    class="d-flex flex-wrap align-items-center justify-content-between gap-3 py-2 px-3 course-program-row">
                                <div>
                                    <a href="{{ route('programs.catalog.show', $pc->program->code) }}"
                                       class="course-program-link fw-semibold">
                                        {{ $pc->program->name }}
                                    </a>
                                    <p class="spims-text-dim course-meta-sm mb-0">
                                        {{ __('courses.year_level', ['year' => $pc->year_level]) }}
                                    </p>
                                </div>
                                <span class="spims-badge {{ $pc->requirement->value === 'REQUIRED' ? 'spims-badge--default' : 'spims-badge--secondary' }}">
                                    {{ $pc->requirement->value === 'REQUIRED' ? __('courses.required') : __('courses.elective') }}
                                </span>
                            </x-card>
                        @endforeach
                    </div>
                @endif
            </x-card>

        </div>

        {{-- ── Sidebar column ────────────────────────────────────── --}}
        <div class="col-12 col-lg-4">

            {{-- Open offerings --}}
            <x-card variant="panel" class="mb-4">
                <h2 class="spims-title mb-3 course-section-title">
                    {{ __('courses.open_offerings') }}
                </h2>

                @if($course->offerings->isEmpty())
                    <x-empty-state
                        :title="__('courses.no_open_offerings')"
                        icon="bi-calendar-x"
                    />
                @else
                    <div class="d-flex flex-column gap-4">
                        @foreach($course->offerings as $offering)
                            @php
                                $enrollment = $enrollmentMap[$offering->id] ?? null;
                                $priceUsd    = $offering->resolvedPriceUsd();
                                $priceEgp    = $offering->resolvedPriceEgp();

                                // Seats calculation
                                $enrolledCount = \App\Models\Enrollment::query()
                                    ->where('offering_id', $offering->id)
                                    ->whereIn('status', [
                                        \App\Enums\EnrollmentStatus::Enrolled,
                                        \App\Enums\EnrollmentStatus::Waitlisted,
                                    ])
                                    ->count();
                                $seatsLeft = $offering->seat_capacity
                                    ? max(0, $offering->seat_capacity - $enrolledCount)
                                    : null;
                                $isFull = $offering->seat_capacity && $seatsLeft === 0;
                            @endphp

                            <div class="course-offering-block">

                                {{-- Mode + seats badges --}}
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <span class="spims-badge spims-badge--info">
                                        {{ __('offering_mode.' . $offering->mode->value) }}
                                    </span>
                                    @if($isFull)
                                        <span class="spims-badge spims-badge--danger">
                                            {{ __('courses.seats_full') }}
                                        </span>
                                    @elseif($seatsLeft !== null)
                                        <span class="spims-badge spims-badge--warning">
                                            {{ trans_choice('courses.seats_available', $seatsLeft, ['count' => $seatsLeft]) }}
                                        </span>
                                    @else
                                        <span class="spims-badge spims-badge--success">
                                            {{ __('courses.unlimited_seats') }}
                                        </span>
                                    @endif
                                </div>

                                {{-- Dates --}}
                                <dl class="course-offering-dl mb-2">
                                    @if($offering->start_date)
                                        <dt class="spims-text-dim">{{ __('courses.start_date') }}</dt>
                                        <dd class="course-tabnum">
                                            {{ $offering->start_date->toFormattedDateString() }}
                                        </dd>
                                    @endif
                                    @if($offering->end_date)
                                        <dt class="spims-text-dim">{{ __('courses.end_date') }}</dt>
                                        <dd class="course-tabnum">
                                            {{ $offering->end_date->toFormattedDateString() }}
                                        </dd>
                                    @endif
                                </dl>

                                {{-- Price --}}
                                <p class="spims-text-dim course-meta-sm mb-3">
                                    {{ __('courses.price') }}:
                                    @if($course->is_free)
                                        <strong class="course-price-free">{{ __('courses.free') }}</strong>
                                    @else
                                        <strong>
                                            <x-money :minor="$priceUsd" :currency="\App\Enums\Currency::Usd" />
                                        </strong>
                                        @if($priceEgp > 0)
                                            <span class="spims-text-dim">
                                                /
                                                <x-money :minor="$priceEgp" :currency="\App\Enums\Currency::Egp" />
                                            </span>
                                        @endif
                                    @endif
                                </p>

                                {{-- Auth-aware CTA --}}
                                @if($user === null)
                                    {{-- Guest --}}
                                    <a href="{{ route('auth.login') }}"
                                       class="btn btn-primary w-100 course-cta-btn">
                                        {{ __('courses.sign_in_to_enroll') }}
                                    </a>
                                @elseif($enrollment !== null)
                                    @php
                                        $enrollStatus = $enrollment['status'] ?? null;
                                        $isWaitlisted = $enrollStatus === \App\Enums\EnrollmentStatus::Waitlisted->value;
                                    @endphp
                                    <div class="btn btn-outline-primary w-100 disabled course-cta-btn"
                                         aria-disabled="true">
                                        <i class="bi-check-circle me-1" aria-hidden="true"></i>
                                        {{ $isWaitlisted ? __('courses.already_waitlisted') : __('courses.already_enrolled') }}
                                    </div>
                                @elseif($isFull)
                                    <form method="POST" action="{{ route('enrollments.store') }}">
                                        @csrf
                                        <input type="hidden" name="offering_id" value="{{ $offering->id }}">
                                        <button type="submit"
                                                class="btn btn-outline-primary w-100 course-cta-btn">
                                            {{ __('courses.join_waitlist') }}
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('enrollments.store') }}">
                                        @csrf
                                        <input type="hidden" name="offering_id" value="{{ $offering->id }}">
                                        <button type="submit"
                                                class="btn btn-primary w-100 course-cta-btn">
                                            {{ __('courses.enroll') }}
                                        </button>
                                    </form>
                                @endif

                            </div>{{-- /offering block --}}
                        @endforeach
                    </div>
                @endif
            </x-card>

            {{-- Instructors --}}
            @php
                $instructors = $course->offerings
                    ->flatMap(fn ($o) => $o->staff)
                    ->unique(fn ($s) => $s->user_id)
                    ->filter(fn ($s) => $s->user !== null);
            @endphp

            @if($instructors->isNotEmpty())
                <x-card variant="quiet">
                    <h2 class="spims-title mb-3 course-section-title">
                        {{ __('courses.instructors') }}
                    </h2>

                    <div class="d-flex flex-column gap-3">
                        @foreach($instructors as $staffMember)
                            <div class="d-flex align-items-center gap-3">
                                <x-avatar
                                    :name="$staffMember->user->displayName()"
                                    :src="$staffMember->user->avatar_path ? asset('storage/' . $staffMember->user->avatar_path) : null"
                                    size="md"
                                />
                                <span class="fw-semibold">
                                    {{ $staffMember->user->displayName() }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif

        </div>{{-- /sidebar --}}

    </div>{{-- /row --}}

    {{-- Guest sign-in prompt banner (when offerings exist) --}}
    @if($user === null && $course->offerings->isNotEmpty())
        <div class="mt-4 course-guest-prompt" role="note">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <p class="fw-semibold mb-1 spims-title">
                        {{ __('courses.sign_in_to_enroll') }}
                    </p>
                    <p class="spims-text-dim course-meta-sm mb-0">
                        {{ __('courses.sign_in_hint') }}
                    </p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('auth.login') }}" class="btn btn-primary course-cta-btn">
                        {{ __('courses.sign_in') }}
                    </a>
                    <a href="{{ route('auth.register') }}" class="btn btn-outline-primary course-cta-btn">
                        {{ __('courses.register') }}
                    </a>
                </div>
            </div>
        </div>
    @endif

</main>
@endsection

@push('styles')
<style>
/* ── Course detail page styles ───────────────────────── */
.course-back-link,
.spims-back-link {
    color: var(--color-link);
}

.course-detail-hero {
    border-inline-start: 4px solid var(--color-primary);
}

.course-hero-title {
    font-size: var(--text-3xl);
    line-height: var(--leading-3xl);
}

.course-section-title {
    font-size: var(--text-xl);
}

.course-description {
    font-size: var(--text-base);
    line-height: var(--leading-base);
    max-width: 72ch;
}

.course-meta-sm {
    font-size: var(--text-sm);
}

.course-prereq-link {
    font-weight: 600;
    text-decoration: none;
    min-height: 44px;
}

.course-program-row {
    border-block-end: 1px solid var(--color-hairline);
}

.course-program-link {
    text-decoration: none;
}

.course-offering-block {
    border-block-start: 1px solid var(--color-hairline);
    padding-block-start: var(--space-4);
}

.course-offering-dl {
    font-size: var(--text-sm);
    display: grid;
    grid-template-columns: auto 1fr;
    column-gap: var(--space-3);
    row-gap: var(--space-1);
}

.course-tabnum {
    font-variant-numeric: tabular-nums;
}

.course-price-free {
    color: var(--color-success);
}

.course-cta-btn {
    min-height: 44px;
}

.course-guest-prompt {
    padding: var(--space-6);
    border-radius: var(--radius-md);
    background: color-mix(in srgb, var(--color-primary) 6%, var(--color-surface));
    border: 1px solid var(--color-surface-border);
}

@media (prefers-reduced-motion: reduce) {
    .course-guest-prompt { transition: none; }
}
</style>
@endpush
