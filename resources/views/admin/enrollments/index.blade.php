@extends('layouts.app')
@section('title', __('enrollment.admin_title'))
@section('content')
<x-page-header
    :title="__('enrollment.admin_title')"
    :subtitle="__('enrollment.admin_subtitle')"
/>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row g-3">
    <div class="col-lg-6">
        <x-card variant="panel" class="h-100">
                <h2 class="h6">{{ __('enrollment.override_register') }}</h2>
                <form method="POST" action="{{ route('admin.enrollments.override') }}" class="row g-2">
                    @csrf
                    <div class="col-12">
                        <label class="form-label" for="override-student">{{ __('enrollment.student') }}</label>
                        <select id="override-student" name="student_id" class="form-select" required>
                            @foreach($students as $student)
                                <option value="{{ $student->id }}">{{ $student->last_name }}, {{ $student->first_name }} — {{ $student->email }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="override-offering">{{ __('enrollment.offering') }}</label>
                        <select id="override-offering" name="offering_id" class="form-select" required>
                            @foreach($offerings as $offering)
                                <option value="{{ $offering->id }}">{{ $offering->course->code }} — {{ $offering->course->title }}@if($offering->semester) ({{ $offering->semester->name }})@endif</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="override-program">{{ __('enrollment.program') }}</label>
                        <select id="override-program" name="student_program_id" class="form-select">
                            <option value="">{{ __('enrollment.standalone_or_none') }}</option>
                            @foreach($programs as $program)
                                <option value="{{ $program->id }}">{{ $program->student->email }} — {{ $program->program->code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-check">
                            <input type="checkbox" name="is_audit" value="1" class="form-check-input">
                            <span class="form-check-label">{{ __('enrollment.audit_registration') }}</span>
                        </label>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary">{{ __('enrollment.override_register') }}</button>
                    </div>
                </form>
        </x-card>
    </div>
    <div class="col-lg-6">
        <x-card variant="panel" class="h-100">
                <h2 class="h6">{{ __('enrollment.financial_hold_label') }}</h2>
                @forelse($students as $student)
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border-bottom py-2">
                        <div>
                            <a href="{{ route('admin.users.show', $student) }}">{{ $student->first_name }} {{ $student->last_name }}</a>
                            <div class="small spims-text-dim">{{ $student->email }}</div>
                            <div class="small">{{ in_array($student->id, $holds, true) ? __('enrollment.hold_on') : __('enrollment.hold_off') }}</div>
                        </div>
                        <form method="POST" action="{{ route('admin.enrollments.financial-hold', $student) }}">
                            @csrf
                            @if(in_array($student->id, $holds, true))
                                <input type="hidden" name="held" value="0">
                                <button class="btn btn-sm btn-outline-secondary">{{ __('enrollment.release_hold') }}</button>
                            @else
                                <input type="hidden" name="held" value="1">
                                <button class="btn btn-sm btn-outline-danger">{{ __('enrollment.place_hold') }}</button>
                            @endif
                        </form>
                    </div>
                @empty
                    <x-empty-state :title="__('enrollment.no_students')" icon="bi-people" />
                @endforelse
        </x-card>
    </div>
</div>
@endsection
