@extends('layouts.app')
@section('title', __('attendance.title').' — '.$offering->course->code)
@section('content')
<x-page-header
    :title="__('attendance.title')"
    :subtitle="$offering->course->code.' — '.$offering->course->title"
    :eyebrow="__('teach.workspace')"
>
    <x-slot:actions>
        <a href="{{ route('teach.show', ['offering' => $offering, 'tab' => 'attendance']) }}" class="btn btn-outline-secondary btn-sm">{{ __('teach.back') }}</a>
    </x-slot:actions>
</x-page-header>

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<ul class="nav nav-pills gap-2 mb-3">
    <li class="nav-item"><a class="nav-link {{ $tab === 'sessions' ? 'active' : '' }}" href="{{ route('teach.attendance.index', $offering) }}">{{ __('attendance.sessions') }}</a></li>
    <li class="nav-item"><a class="nav-link {{ $tab === 'report' ? 'active' : '' }}" href="{{ route('teach.attendance.index', ['offering' => $offering, 'tab' => 'report']) }}">{{ __('attendance.report') }}</a></li>
    <li class="nav-item"><a class="nav-link {{ $tab === 'roster' ? 'active' : '' }}" href="{{ route('teach.attendance.index', ['offering' => $offering, 'tab' => 'roster']) }}">{{ __('teach.tab_roster') }}</a></li>
</ul>

@if($tab === 'report')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0">{{ __('attendance.report') }}</h2>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('teach.attendance.report.csv', $offering) }}">{{ __('attendance.attendance_export') }}</a>
    </div>
    @if(empty($report['students']))
        <x-empty-state :title="__('attendance.report_empty')" icon="bi-clipboard-data" />
    @else
        <p class="spims-text-dim">{{ __('attendance.session_count') }}: {{ $report['aggregate']['session_count'] }} · {{ __('attendance.student_count') }}: {{ $report['aggregate']['student_count'] }}</p>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('learning.first_name') }}</th>
                        <th>{{ __('learning.last_name') }}</th>
                        <th>{{ __('attendance.present') }}</th>
                        <th>{{ __('attendance.absent') }}</th>
                        <th>{{ __('attendance.late') }}</th>
                        <th>{{ __('attendance.excused') }}</th>
                        <th>{{ __('attendance.percent') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['students'] as $row)
                        <tr>
                            <td>{{ $row['first_name'] }}</td>
                            <td>{{ $row['last_name'] }}</td>
                            <td>{{ $row['present'] }}</td>
                            <td>{{ $row['absent'] }}</td>
                            <td>{{ $row['late'] }}</td>
                            <td>{{ $row['excused'] }}</td>
                            <td>{{ $row['percent'] === null ? '—' : $row['percent'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@elseif($tab === 'roster')
    <div class="d-flex flex-wrap gap-2 mb-3">
        <a class="btn btn-outline-primary btn-sm" href="{{ route('teach.attendance.roster.csv', $offering) }}">{{ __('attendance.roster_export') }}</a>
    </div>
    <h2 class="h6">{{ __('attendance.birthdays') }}</h2>
    @forelse($birthdays as $row)
        <div class="border rounded-3 p-2 mb-2">
            <strong>{{ $row['student']->first_name }} {{ $row['student']->last_name }}</strong>
            <span class="spims-text-dim">{{ $row['date_of_birth'] }} · {{ $row['days_until'] }}d</span>
        </div>
    @empty
        <x-empty-state :title="__('attendance.birthdays_empty')" icon="bi-cake2" />
    @endforelse

    <h2 class="h6 mt-4">{{ __('attendance.roster_announce') }}</h2>
    <form method="POST" action="{{ route('teach.attendance.announce', $offering) }}" class="row g-2">
        @csrf
        <div class="col-md-4"><input name="title" class="form-control" placeholder="{{ __('attendance.announcement_title') }}" required></div>
        <div class="col-md-6"><input name="body" class="form-control" placeholder="{{ __('attendance.announcement_body') }}" required></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">{{ __('ui.save') }}</button></div>
    </form>
@else
    <form method="POST" action="{{ route('teach.attendance.store', $offering) }}" class="row g-2 mb-4">
        @csrf
        <div class="col-md-3"><input name="title" class="form-control" required placeholder="{{ __('attendance.new_session') }}"></div>
        <div class="col-md-3"><input type="datetime-local" name="scheduled_start" class="form-control" required></div>
        <div class="col-md-2"><input type="number" name="duration_minutes" class="form-control" value="60" min="15" required aria-label="{{ __('attendance.duration_minutes') }}"></div>
        <div class="col-md-2">
            <select name="mode" class="form-select" aria-label="{{ __('attendance.mode') }}">
                @foreach($modes as $mode)
                    @php $modeVal = $mode->value; @endphp
                    <option value="{{ $modeVal }}">{{ __('attendance.mode_'.$modeVal) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><input name="location" class="form-control" placeholder="{{ __('attendance.location') }}"></div>
        <div class="col-12">
            <label class="form-check">
                <input type="checkbox" name="notify_students" value="1" class="form-check-input">
                <span class="form-check-label">{{ __('attendance.notify_students') }}</span>
            </label>
        </div>
        <div class="col-md-2"><button class="btn btn-primary">{{ __('ui.save') }}</button></div>
    </form>

    @forelse($sessions as $session)
        <div class="d-flex justify-content-between align-items-center border rounded-3 p-3 mb-2">
            <div>
                <strong>{{ $session->title }}</strong>
                <div class="small spims-text-dim">
                    {{ $session->scheduled_start }} · {{ __('attendance.mode_'.$session->mode->value) }}
                    @if($session->isClosed())
                        <x-status-badge status="warning" :label="__('attendance.closed_badge')" />
                    @else
                        <x-status-badge status="info" :label="__('attendance.open_badge')" />
                    @endif
                </div>
            </div>
            <a class="btn btn-sm btn-outline-primary" href="{{ route('teach.attendance.show', [$offering, $session]) }}">{{ __('attendance.grid') }}</a>
        </div>
    @empty
        <x-empty-state :title="__('attendance.no_sessions')" icon="bi-calendar-week" />
    @endforelse
@endif
@endsection
