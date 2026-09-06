@extends('layouts.app')
@section('title', __('teach.dossier').' — '.$student->first_name.' '.$student->last_name)
@section('content')
<x-page-header
    :title="$student->first_name.' '.$student->last_name"
    :subtitle="$offering->course->code.' — '.$offering->course->title"
    :eyebrow="__('teach.dossier')"
>
    <x-slot:actions>
        <a href="{{ route('teach.show', ['offering' => $offering, 'tab' => 'roster']) }}" class="btn btn-outline-secondary btn-sm">{{ __('teach.back_to_roster') }}</a>
        <a href="{{ route('teach.completion.show', ['offering' => $offering, 'student_id' => $student->id]) }}" class="btn btn-outline-primary btn-sm">{{ __('teach.open_notes') }}</a>
    </x-slot:actions>
</x-page-header>

@include('partials.offering-workspace-tabs', ['offering' => $offering, 'active' => 'roster', 'prefix' => 'teach'])

<div class="row g-3 mt-1">
    <div class="col-lg-6">
        <section class="app-card p-3 h-100">
            <h2 class="h6 spims-title mb-3">{{ __('teach.profile') }}</h2>
            <dl class="row mb-0">
                <dt class="col-sm-4">{{ __('teach.first_name') }}</dt>
                <dd class="col-sm-8">{{ $student->first_name }}</dd>
                <dt class="col-sm-4">{{ __('teach.last_name') }}</dt>
                <dd class="col-sm-8">{{ $student->last_name }}</dd>
                <dt class="col-sm-4">{{ __('teach.email') }}</dt>
                <dd class="col-sm-8">{{ $student->email }}</dd>
                <dt class="col-sm-4">{{ __('teach.phone') }}</dt>
                <dd class="col-sm-8">{{ $student->phone ?: '—' }}</dd>
                <dt class="col-sm-4">{{ __('teach.date_of_birth') }}</dt>
                <dd class="col-sm-8">{{ $student->date_of_birth?->toDateString() ?: '—' }}</dd>
                <dt class="col-sm-4">{{ __('teach.preferred_locale') }}</dt>
                <dd class="col-sm-8">{{ $student->preferred_locale ?: '—' }}</dd>
            </dl>
        </section>
    </div>
    <div class="col-lg-6">
        <section class="app-card p-3 h-100">
            <h2 class="h6 spims-title mb-3">{{ __('teach.enrollment') }}</h2>
            <dl class="row mb-0">
                <dt class="col-sm-4">{{ __('teach.status') }}</dt>
                <dd class="col-sm-8">
                    <x-status-badge :status="$enrollment->status->value" :label="$enrollment->status->value" />
                </dd>
                <dt class="col-sm-4">{{ __('teach.enrolled_at') }}</dt>
                <dd class="col-sm-8">{{ $enrollment->enrolled_at?->toDayDateTimeString() ?: '—' }}</dd>
                <dt class="col-sm-4">{{ __('teach.dropped_at') }}</dt>
                <dd class="col-sm-8">{{ $enrollment->dropped_at?->toDayDateTimeString() ?: '—' }}</dd>
                <dt class="col-sm-4">{{ __('teach.grade_status') }}</dt>
                <dd class="col-sm-8">{{ $enrollment->grade_status?->value ?: '—' }}</dd>
                <dt class="col-sm-4">{{ __('teach.final_percent') }}</dt>
                <dd class="col-sm-8">{{ $enrollment->final_percent !== null ? number_format($enrollment->final_percent, 2).'%' : '—' }}</dd>
                <dt class="col-sm-4">{{ __('teach.final_letter') }}</dt>
                <dd class="col-sm-8">{{ $enrollment->final_letter ?: '—' }}</dd>
                <dt class="col-sm-4">{{ __('teach.attendance_percent') }}</dt>
                <dd class="col-sm-8">
                    {{ $attendancePercent !== null ? number_format($attendancePercent, 2).'%' : __('teach.no_attendance') }}
                </dd>
            </dl>
        </section>
    </div>
    <div class="col-12">
        <section class="app-card p-3">
            <h2 class="h6 spims-title mb-3">{{ __('teach.grades') }}</h2>
            @if($grades)
                <p class="mb-3">
                    {{ __('teach.computed_percent') }}:
                    <strong>{{ number_format($grades['percent'], 2) }}%</strong>
                </p>
                <div class="table-responsive spims-table-wrap">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('teach.component') }}</th>
                                <th>{{ __('teach.weight') }}</th>
                                <th>{{ __('teach.score') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($grades['components'] as $component)
                                <tr>
                                    <td>{{ $component['name'] }}</td>
                                    <td>{{ number_format((float) $component['weight'], 2) }}%</td>
                                    <td>{{ $component['score'] !== null ? number_format((float) $component['score'], 2) : '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-muted-theme">{{ __('teach.no_grades') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @else
                <p class="mb-0 text-muted-theme">{{ __('teach.no_grades') }}</p>
            @endif
        </section>
    </div>
</div>
@endsection
