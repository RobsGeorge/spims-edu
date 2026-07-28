@extends('layouts.app')
@section('title', $program->name)
@section('content')
<h1 class="spims-title mb-1">{{ $program->code }} — {{ $program->name }}</h1>
<p class="text-muted-theme">{{ $program->type->value }} · {{ __('academics.passing_threshold') }}: {{ $program->passing_threshold }}%</p>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h6">{{ __('academics.attach_course') }}</h2>
        <form method="POST" action="{{ route('admin.programs.attach-course', $program) }}" class="row g-2">
            @csrf
            <div class="col-md-5">
                <select name="course_id" class="form-select" required>
                    @foreach($courses as $course)<option value="{{ $course->id }}">{{ $course->code }} — {{ $course->title }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="requirement" class="form-select" required>
                    @foreach($requirements as $req)<option value="{{ $req->value }}">{{ $req->value }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2"><input type="number" name="year_level" class="form-control" placeholder="{{ __('academics.year_level') }}"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">{{ __('ui.save') }}</button></div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>{{ __('academics.code') }}</th><th>{{ __('academics.title') }}</th><th>{{ __('academics.requirement') }}</th><th>{{ __('academics.year_level') }}</th></tr></thead>
            <tbody>
            @forelse($program->programCourses as $pc)
                <tr>
                    <td>{{ $pc->course->code }}</td>
                    <td>{{ $pc->course->title }}</td>
                    <td>{{ $pc->requirement->value }}</td>
                    <td>{{ $pc->year_level ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-muted-theme">{{ __('academics.no_courses') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
