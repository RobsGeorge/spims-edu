@if($courses->isEmpty())
    <x-empty-state
        :title="$emptyTitle ?? __('catalog.empty')"
        :message="$emptyHint ?? __('catalog.empty_hint')"
        icon="bi-inbox"
    />
@else
    <div class="row g-3">
        @foreach($courses as $index => $course)
            @php
                $offering = $offeringsByCourse->get($course->id)?->first();
            @endphp
            <div class="col-12 col-md-6 col-xl-4">
                @include('partials.course-card', [
                    'course'   => $course,
                    'offering' => $offering,
                    'index'    => $index,
                ])
            </div>
        @endforeach
    </div>
    <div class="mt-4">{{ $courses->links() }}</div>
@endif
