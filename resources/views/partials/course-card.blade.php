{{--
    Partial: course-card
    Variables:
      $course   — Course model (with programCourses.program, prerequisites)
      $offering — CourseOffering|null  (first open offering for this course)
    Optional:
      $index    — int (for media alt pattern)
--}}
@php
    use App\Enums\Currency;
    $index     ??= 0;
    $programs    = $course->programCourses->map(fn ($pc) => $pc->program)->filter()->unique('id');
    $prereqCount = $course->prerequisites->count();
    $priceMinor  = $course->is_free ? 0 : (int) ($course->default_price_usd ?? 0);

    $applyTargets = $programs
        ->map(function ($prog) {
            $form = $prog->applicationForms?->first();

            return $form ? ['program' => $prog, 'form' => $form] : null;
        })
        ->filter()
        ->values();
@endphp

<article class="h-100">
    <x-card variant="panel" class="catalog-course-card h-100" tag="div">
        <div class="catalog-card-media {{ $index % 2 === 1 ? 'catalog-card-media--alt' : '' }}" aria-hidden="true"></div>

        {{-- Program chips: "Part of:" --}}
        @if($programs->isNotEmpty())
            <p class="spims-text-dim mb-2 catalog-part-of-row" style="font-size: var(--text-xs);">
                {{ __('catalog.part_of') }}:
                @foreach($programs as $prog)
                    <a href="{{ route('programs.catalog.show', $prog->code) }}"
                       class="catalog-chip spims-badge spims-badge--default ms-1"
                       style="text-decoration: none;">
                        {{ $prog->code }}
                    </a>
                @endforeach
            </p>
        @endif

        {{-- Free badge --}}
        @if($course->is_free)
            <span class="badge-brand mb-2 d-inline-block">{{ __('catalog.free_badge') }}</span>
        @endif

        <h2 class="spims-title catalog-card-title mb-1">{{ $course->code }}</h2>
        <p class="mb-2">{{ $course->title }}</p>

        <p class="spims-text-dim mb-3 catalog-card-meta-line" style="font-size: var(--text-sm);">
            {{ __('catalog.credits', ['count' => $course->credit_hours]) }}
            @if($prereqCount > 0)
                &middot;
                {{ trans_choice('catalog.prereqs', $prereqCount, ['count' => $prereqCount]) }}
            @endif
            @if($course->interest_flags_count > 0)
                &middot;
                {{ __('catalog.interest_count', ['count' => $course->interest_flags_count]) }}
            @endif
        </p>

        {{-- Price --}}
        <div class="catalog-card-price mb-3">
            @if($course->is_free)
                <span class="spims-text-dim" style="font-size: var(--text-sm);">{{ __('catalog.free_badge') }}</span>
            @elseif($priceMinor > 0)
                <x-money :minor="$priceMinor" :currency="\App\Enums\Currency::Usd" />
            @endif
        </div>

        <div class="mt-auto d-flex flex-wrap gap-2">
            @if($offering)
                <a class="btn btn-sm btn-outline-primary" href="{{ route('offerings.preview', $offering) }}">
                    {{ __('catalog.preview') }}
                </a>
            @else
                <span class="spims-text-dim small align-self-center">{{ __('catalog.no_offering') }}</span>
            @endif
            @auth
                @foreach($applyTargets as $target)
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('applications.create', $target['form']) }}">
                        {{ __('catalog.apply_to', ['program' => $target['program']->code]) }}
                    </a>
                @endforeach
                <form method="POST" action="{{ route('catalog.interest', $course) }}">
                    @csrf
                    <button class="btn btn-sm btn-outline-secondary">{{ __('catalog.flag_interest') }}</button>
                </form>
            @endauth
        </div>
    </x-card>
</article>
