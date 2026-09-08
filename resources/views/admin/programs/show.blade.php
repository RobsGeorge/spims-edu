@extends('layouts.app')
@section('title', $program->name)
@section('content')
<x-page-header
    :title="$program->code.' — '.$program->name"
    :subtitle="$program->type->value.' · '.__('academics.passing_threshold').': '.$program->passing_threshold.'% · '.($program->active ? __('academics.active') : __('academics.inactive'))"
>
    <x-slot:actions>
        @if(!empty($canManageProgram))
            <a href="{{ route('admin.programs.edit', $program) }}" class="btn btn-outline-primary">{{ __('ui.edit') }}</a>
        @endif
    </x-slot:actions>
</x-page-header>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

@include('admin.programs._standing')

<x-card variant="panel" class="mb-4">
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
                @foreach($requirements as $req)
                    @php $reqVal = $req->value; @endphp
                    <option value="{{ $reqVal }}">{{ $reqVal }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><input type="number" name="year_level" class="form-control" placeholder="{{ __('academics.year_level') }}"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">{{ __('ui.save') }}</button></div>
    </form>
</x-card>

<x-card variant="panel">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>{{ __('academics.code') }}</th><th>{{ __('academics.title') }}</th><th>{{ __('academics.requirement') }}</th><th>{{ __('academics.year_level') }}</th><th></th></tr></thead>
            <tbody>
            @forelse($program->programCourses as $pc)
                <tr>
                    <td>{{ $pc->course->code }}</td>
                    <td>{{ $pc->course->title }}</td>
                    <td><x-badge :value="$pc->requirement" /></td>
                    <td>{{ $pc->year_level ?? '—' }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.programs.detach-course', [$program, $pc]) }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">{{ __('academics.detach_course') }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="spims-text-dim">{{ __('academics.no_courses') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-card>
@endsection
