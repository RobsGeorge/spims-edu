@if($courses->isEmpty())
    <div class="spims-empty app-card p-5 text-center">
        <h2 class="h5 spims-title">{{ __('catalog.empty') }}</h2>
        <p class="text-muted-theme mb-0">{{ __('catalog.empty_hint') }}</p>
    </div>
@else
    <div class="row g-3">
        @foreach($courses as $index => $course)
            @php
                $offering = $offeringsByCourse->get($course->id)?->first();
                $form = $course->programCourses
                    ->map(fn ($pc) => $pc->program?->applicationForms?->first())
                    ->filter()
                    ->first();
            @endphp
            <div class="col-12 col-md-6 col-xl-4">
                <article class="catalog-card app-card h-100 p-3 d-flex flex-column">
                    <x-course-cover :course="$course" class="catalog-card-media {{ $index % 2 === 1 ? 'catalog-card-media--alt' : '' }}" />
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        @if($course->is_free)<span class="badge-brand">{{ __('catalog.free_badge') }}</span>@endif
                        @if($course->is_standalone)<span class="badge-brand">{{ __('catalog.standalone_badge') }}</span>@endif
                    </div>
                    <h2 class="h5 spims-title mb-1">{{ $course->code }}</h2>
                    <p class="mb-2">{{ $course->title }}</p>
                    <p class="text-muted-theme small mb-3">
                        {{ __('catalog.credits', ['count' => $course->credit_hours]) }}
                        · {{ __('catalog.interest_count', ['count' => $course->interest_flags_count]) }}
                    </p>
                    <div class="mt-auto d-flex flex-wrap gap-2">
                        @if($offering)
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('offerings.preview', $offering) }}">{{ __('catalog.preview') }}</a>
                        @else
                            <span class="text-muted-theme small align-self-center">{{ __('catalog.no_offering') }}</span>
                        @endif
                        @auth
                            @if($form)
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('applications.create', $form) }}">{{ __('catalog.apply') }}</a>
                            @endif
                            <form method="POST" action="{{ route('catalog.interest', $course) }}">
                                @csrf
                                <button class="btn btn-sm btn-outline-secondary">{{ __('catalog.flag_interest') }}</button>
                            </form>
                        @endauth
                    </div>
                </article>
            </div>
        @endforeach
    </div>
    <div class="mt-4">{{ $courses->links() }}</div>
@endif
