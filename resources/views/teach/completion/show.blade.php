@extends('layouts.app')
@section('title', __('completion.cohort_title'))
@section('content')
<x-page-header :title="__('completion.cohort_title').' — '.$offering->course->code" />
@include('partials.offering-workspace-tabs', ['offering' => $offering, 'active' => 'completion', 'prefix' => 'teach'])
@if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif

<div class="table-responsive spims-table-wrap mb-4">
<table class="table table-sm">
    <thead><tr><th>{{ __('completion.student') }}</th><th>{{ __('completion.outcome') }}</th><th>{{ __('completion.met_criteria') }}</th><th></th></tr></thead>
    <tbody>
    @forelse($results as $result)
        <tr>
            <td>{{ $result->student?->email }}</td>
            <td>{{ $result->outcome->value }}</td>
            <td class="small">
                @foreach($result->met_criteria ?? [] as $row)
                    {{ $row['kind'] }}: {{ $row['passed'] ? __('completion.passed') : __('completion.failed') }}@if(! $loop->last), @endif
                @endforeach
            </td>
            <td class="text-nowrap">
                <a href="{{ route('teach.students.show', [$offering, $result->student_id]) }}">{{ __('teach.view_dossier') }}</a>
                <span class="text-muted-theme">·</span>
                <a href="{{ route('teach.completion.show', ['offering' => $offering, 'student_id' => $result->student_id]) }}">{{ __('completion.notes') }}</a>
            </td>
        </tr>
    @empty
        <tr><td colspan="4" class="text-muted-theme">{{ __('completion.no_results') }}</td></tr>
    @endforelse
    </tbody>
</table>
</div>

@if($selectedStudent)
<h2 class="h5">{{ __('completion.notes') }} — {{ $selectedStudent->email }}
    <a class="fs-6 fw-normal" href="{{ route('teach.students.show', [$offering, $selectedStudent]) }}">{{ __('teach.view_dossier') }}</a>
</h2>
<form method="POST" action="{{ route('teach.completion.notes.store', [$offering, $selectedStudent]) }}" class="card border-0 shadow-sm mb-4">
    @csrf
    <div class="card-body">
        <textarea name="body" class="form-control mb-2" required></textarea>
        <button class="btn btn-primary">{{ __('completion.add_note') }}</button>
    </div>
</form>
<ul>
    @foreach($notes as $note)
        <li>{{ $note->body }} — {{ $note->author?->email }}</li>
    @endforeach
</ul>
@endif

@if($weeks->isNotEmpty() && $selectedStudent)
<h2 class="h5 mt-4">{{ __('completion.module_assessments') }}</h2>
@foreach($weeks as $week)
    @php $existing = $weekAssessments[$week->id][$selectedStudent->id] ?? null; @endphp
    <form method="POST" action="{{ route('teach.completion.assess', [$offering, $week, $selectedStudent]) }}" class="row g-2 mb-2">
        @csrf
        <div class="col-md-4">{{ $week->title }}</div>
        <div class="col-md-2"><input name="rating" type="number" min="1" max="5" class="form-control" value="{{ $existing?->rating ?? 3 }}" required></div>
        <div class="col-md-4"><input name="comment" class="form-control" value="{{ $existing?->comment }}"></div>
        <div class="col-md-2"><button class="btn btn-outline-primary w-100">{{ __('ui.save') }}</button></div>
    </form>
@endforeach
@endif
@endsection
