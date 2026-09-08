@extends('layouts.app')
@section('title', __('advising.title'))
@section('content')
<x-page-header
    :title="__('advising.title')"
    :subtitle="__('advising.subtitle')"
/>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row g-3">
    @if($canAssign)
    <div class="col-lg-5">
        <x-card variant="panel" class="h-100">
            <h2 class="h6 spims-title">{{ __('advising.assign_title') }}</h2>
            <form method="POST" action="{{ route('advising.assign') }}" class="row g-2">
                @csrf
                <div class="col-12">
                    <label class="form-label" for="advising-student">{{ __('advising.student') }}</label>
                    <select id="advising-student" name="student_id" class="form-select" required>
                        @foreach($students as $student)
                            <option value="{{ $student->id }}">{{ $student->last_name }}, {{ $student->first_name }} — {{ $student->email }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label" for="advising-advisor">{{ __('advising.advisor') }}</label>
                    <select id="advising-advisor" name="advisor_id" class="form-select" required>
                        @foreach($advisors as $advisor)
                            <option value="{{ $advisor->id }}">{{ $advisor->last_name }}, {{ $advisor->first_name }} — {{ $advisor->email }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label" for="advising-program">{{ __('advising.program') }}</label>
                    <select id="advising-program" name="program_id" class="form-select">
                        <option value="">{{ __('advising.any_program') }}</option>
                        @foreach($programs as $program)
                            <option value="{{ $program->id }}">{{ $program->code }} — {{ $program->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary">{{ __('advising.assign') }}</button>
                </div>
            </form>
        </x-card>
    </div>
    @endif

    <div class="{{ $canAssign ? 'col-lg-7' : 'col-12' }}">
        <x-card variant="panel">
            <h2 class="h6 spims-title">{{ __('advising.roster') }}</h2>
            @forelse($assignments as $assignment)
                <div class="py-2 border-bottom border-opacity-25 d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <a href="{{ route('advising.show', $assignment->student) }}">{{ $assignment->student->first_name }} {{ $assignment->student->last_name }}</a>
                        <div class="small spims-text-dim">{{ $assignment->student->email }}</div>
                        <div class="small spims-text-dim">
                            {{ __('advising.advisor') }}: {{ $assignment->advisor->first_name }} {{ $assignment->advisor->last_name }}
                            @if($assignment->program)
                                · {{ $assignment->program->code }}
                            @endif
                        </div>
                    </div>
                    <a class="btn btn-sm btn-outline-primary align-self-center" href="{{ route('advising.show', $assignment->student) }}">{{ __('advising.open') }}</a>
                </div>
            @empty
                <x-empty-state :title="__('advising.roster_empty')" icon="bi-people" />
            @endforelse
        </x-card>
    </div>
</div>
@endsection
