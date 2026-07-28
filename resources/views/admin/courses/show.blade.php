@extends('layouts.app')
@section('title', $course->title)
@section('content')
<h1 class="spims-title">{{ $course->code }} — {{ $course->title }}</h1>
<p class="text-muted-theme">{{ __('academics.credits') }}: {{ $course->credit_hours }} · {{ __('academics.interest') }}: {{ $course->interestFlags->count() }}</p>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h6">{{ __('academics.add_prerequisite') }}</h2>
        <form method="POST" action="{{ route('admin.courses.prerequisites', $course) }}" class="row g-2">
            @csrf
            <div class="col-md-8">
                <select name="prerequisite_id" class="form-select" required>
                    @foreach($prerequisiteOptions as $c)<option value="{{ $c->id }}">{{ $c->code }} — {{ $c->title }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-4"><button class="btn btn-primary w-100">{{ __('ui.save') }}</button></div>
        </form>
        <ul class="mt-3 mb-0">
            @forelse($course->prerequisites as $prereq)
                <li>{{ $prereq->code }} — {{ $prereq->title }}</li>
            @empty
                <li class="text-muted-theme">{{ __('academics.no_prerequisites') }}</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
