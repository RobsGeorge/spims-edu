@extends('layouts.app')
@section('title', $course->title)
@section('content')
<x-course-cover :course="$course" class="spims-cover--hero mb-4" />
<x-page-header :title="$course->code.' — '.$course->title" :subtitle="__('academics.credits').': '.$course->credit_hours">
    <x-slot:actions>
        <a href="{{ route('admin.courses.edit', $course) }}" class="btn btn-outline-primary">{{ __('ui.edit') }}</a>
    </x-slot:actions>
</x-page-header>
<p><a href="{{ route('admin.completion-criteria.index', $course) }}">{{ __('completion.criteria_title') }}</a></p>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<x-card variant="quiet" class="mb-4">
        <x-section-heading :title="__('academics.add_prerequisite')" icon="add" class="h6 mb-3" />
        <form method="POST" action="{{ route('admin.courses.prerequisites', $course) }}" class="row g-2">
            @csrf
            <div class="col-12 col-md-8">
                <select name="prerequisite_id" class="form-select" required>
                    @foreach($prerequisiteOptions as $c)<option value="{{ $c->id }}">{{ $c->code }} — {{ $c->title }}</option>@endforeach
                </select>
            </div>
            <div class="col-12 col-md-4"><button class="btn btn-primary w-100">{{ __('ui.save') }}</button></div>
        </form>
        <ul class="mt-3 mb-0">
            @forelse($course->prerequisiteLinks as $link)
                <li class="d-flex justify-content-between align-items-center gap-2">
                    <span>{{ $link->prerequisite->code }} — {{ $link->prerequisite->title }}</span>
                    <form method="POST" action="{{ route('admin.courses.detach-prerequisite', [$course, $link]) }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">{{ __('academics.remove_prerequisite') }}</button>
                    </form>
                </li>
            @empty
                <li class="spims-text-dim">{{ __('academics.no_prerequisites') }}</li>
            @endforelse
        </ul>
</x-card>
@endsection
