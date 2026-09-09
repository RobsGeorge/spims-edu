@extends('layouts.app')
@section('title', $program->name)
@section('content')
<x-page-header
    :title="$program->code.' — '.$program->name"
>
    <x-slot:actions>
        @if(!empty($canManageProgram))
            <a href="{{ route('admin.programs.edit', $program) }}" class="btn btn-outline-primary">{{ __('ui.edit') }}</a>
        @endif
    </x-slot:actions>
</x-page-header>

@if(session('status'))
    <div class="alert alert-success mb-4" role="status">{{ session('status') }}</div>
@endif

{{-- ═══ Server-side rule preview (same sentence as Alpine edit form) ══════════ --}}
<x-card variant="quiet" class="mb-4" id="rule-preview">
    <p class="spims-title fw-semibold mb-2">
        <i class="bi bi-eye me-2" aria-hidden="true"></i>{{ __('academics.rule_preview_title') }}
    </p>
    <p class="spims-text-dim fst-italic mb-0">{{ $program->rulePreviewSentence() }}</p>
</x-card>

{{-- ═══ Program meta badges ══════════════════════════════════════════════════ --}}
<x-card variant="panel" class="mb-4">
    <div class="row g-3">
        <div class="col-12 col-md-4">
            <p class="spims-text-dim small mb-1">{{ __('academics.type') }}</p>
            <x-badge :value="$program->type" />
        </div>
        @if($program->level)
        <div class="col-12 col-md-4">
            <p class="spims-text-dim small mb-1">{{ __('academics.level') }}</p>
            <span class="fw-semibold">{{ $program->level }}</span>
        </div>
        @endif
        <div class="col-12 col-md-4">
            <p class="spims-text-dim small mb-1">{{ __('academics.active') }}</p>
            @if($program->active)
                <span class="badge spims-badge spims-badge--success">{{ __('academics.active') }}</span>
            @else
                <span class="badge spims-badge spims-badge--secondary">{{ __('academics.inactive') }}</span>
            @endif
        </div>
        <div class="col-12 col-md-4">
            <p class="spims-text-dim small mb-1">{{ __('academics.max_credits') }}</p>
            <span class="fw-semibold tabular-nums">{{ $program->max_credits_per_semester }}</span>
        </div>
        <div class="col-12 col-md-4">
            <p class="spims-text-dim small mb-1">{{ __('academics.max_courses') }}</p>
            <span class="fw-semibold tabular-nums">{{ $program->max_courses_per_semester }}</span>
        </div>
        <div class="col-12 col-md-4">
            <p class="spims-text-dim small mb-1">{{ __('academics.max_semesters') }}</p>
            <span class="fw-semibold tabular-nums">{{ $program->max_semesters_to_graduate }}</span>
        </div>
        <div class="col-12 col-md-4">
            <p class="spims-text-dim small mb-1">{{ __('academics.passing_threshold') }}</p>
            <span class="fw-semibold tabular-nums">{{ $program->passing_threshold }}%</span>
        </div>
        @if($program->enforce_year_sequence !== null)
        <div class="col-12 col-md-4">
            <p class="spims-text-dim small mb-1">{{ __('academics.enforce_year_sequence') }}</p>
            @if($program->enforce_year_sequence)
                <span class="badge spims-badge spims-badge--warning">{{ __('academics.rule_preview_year_blocked') }}</span>
            @else
                <span class="badge spims-badge spims-badge--info">{{ __('academics.rule_preview_year_warned') }}</span>
            @endif
        </div>
        @endif
        @if($program->issue_credential_on_completion)
        <div class="col-12 col-md-4">
            <p class="spims-text-dim small mb-1">{{ __('academics.issue_credential_on_completion') }}</p>
            <span class="badge spims-badge spims-badge--success">{{ __('academics.active') }}</span>
        </div>
        @endif
    </div>
</x-card>

@include('admin.programs._standing')

{{-- ═══ Attach course ══════════════════════════════════════════════════════ --}}
@if(!empty($canManageProgram))
<x-card variant="panel" class="mb-4">
    <h2 class="h6 spims-title mb-3">{{ __('academics.attach_course') }}</h2>
    <form method="POST" action="{{ route('admin.programs.attach-course', $program) }}" class="row g-2">
        @csrf
        <div class="col-12 col-md-5">
            <select name="course_id" class="form-select" required>
                @foreach($courses as $course)
                    <option value="{{ $course->id }}">{{ $course->code }} — {{ $course->title }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-3">
            <select name="requirement" class="form-select" required>
                @foreach($requirements as $req)
                    @php $reqVal = $req->value; @endphp
                    <option value="{{ $reqVal }}">{{ __('requirement_type.' . $reqVal) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-2">
            <input type="number" name="year_level" class="form-control"
                   placeholder="{{ __('academics.year_level') }}" min="1">
        </div>
        <div class="col-12 col-md-2">
            <button class="btn btn-primary w-100">{{ __('ui.save') }}</button>
        </div>
    </form>
</x-card>
@endif

{{-- ═══ Program courses ════════════════════════════════════════════════════ --}}
<x-card variant="panel">
    <div class="spims-table-wrap">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>{{ __('academics.code') }}</th>
                    <th>{{ __('academics.title') }}</th>
                    <th>{{ __('academics.requirement') }}</th>
                    <th>{{ __('academics.year_level') }}</th>
                    @if(!empty($canManageProgram))
                        <th></th>
                    @endif
                </tr>
            </thead>
            <tbody>
            @forelse($program->programCourses as $pc)
                <tr>
                    <td>{{ $pc->course->code }}</td>
                    <td>{{ $pc->course->title }}</td>
                    <td><x-badge :value="$pc->requirement" /></td>
                    <td>{{ $pc->year_level ?? '—' }}</td>
                    @if(!empty($canManageProgram))
                        <td>
                            <form method="POST"
                                  action="{{ route('admin.programs.detach-course', [$program, $pc]) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">
                                    {{ __('academics.detach_course') }}
                                </button>
                            </form>
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ !empty($canManageProgram) ? 5 : 4 }}" class="spims-text-dim">
                        {{ __('academics.no_courses') }}
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-card>
@endsection
