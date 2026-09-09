{{--
    Partial: program-card
    Variables:
      $program  — Program model (with programCourses.course, applicationForms)
    Expects $program->total_price_usd to be annotated by the controller.
--}}
@php
    use App\Enums\Currency;
    $hasActiveForm = $program->applicationForms->isNotEmpty();
    $courseCount   = $program->programCourses->count();
    $totalCredits  = $program->programCourses->sum(fn ($pc) => $pc->course->credit_hours ?? 0);
    $priceMinor    = (int) ($program->total_price_usd ?? 0);
@endphp

<a href="{{ route('programs.catalog.show', $program->code) }}"
   class="text-decoration-none program-catalog-card-link"
   aria-label="{{ $program->name }}">
    <x-card variant="panel" class="program-catalog-card h-100">

        <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
            <span class="spims-badge spims-badge--info">
                {{ __('program_type.' . $program->type->value) }}
            </span>
            <i class="bi-journal-text spims-icon-md spims-text-dim flex-shrink-0" aria-hidden="true"></i>
        </div>

        <h2 class="spims-title mb-1 catalog-card-title">
            {{ $program->name }}
        </h2>

        @if($program->description || $program->marketing_summary)
            <p class="spims-text-dim mb-3 catalog-card-desc">
                {{ \Illuminate\Support\Str::limit($program->marketing_summary ?? $program->description, 120) }}
            </p>
        @endif

        <dl class="catalog-card-meta mb-3">
            <div class="catalog-card-meta-item">
                <i class="bi-award" aria-hidden="true"></i>
                <span>{{ $totalCredits }}&nbsp;{{ __('programs.credits') }}</span>
            </div>
            <div class="catalog-card-meta-item">
                <i class="bi-journal-text" aria-hidden="true"></i>
                <span>{{ $courseCount }}&nbsp;{{ __('programs.courses') }}</span>
            </div>
            @if($program->max_semesters_to_graduate)
                <div class="catalog-card-meta-item">
                    <i class="bi-calendar3" aria-hidden="true"></i>
                    <span>{{ $program->max_semesters_to_graduate }}&nbsp;{{ __('programs.semesters') }}</span>
                </div>
            @endif
        </dl>

        <div class="d-flex align-items-center justify-content-between gap-2 mt-auto pt-2">
            <div class="catalog-card-price">
                @if($priceMinor === 0)
                    <span class="badge-brand">{{ __('catalog.free_badge') }}</span>
                @else
                    <x-money :minor="$priceMinor" :currency="\App\Enums\Currency::Usd" />
                @endif
            </div>
            <span class="btn btn-primary btn-sm">
                {{ $hasActiveForm ? __('programs.apply_now') : __('programs.learn_more') }}
            </span>
        </div>

    </x-card>
</a>
