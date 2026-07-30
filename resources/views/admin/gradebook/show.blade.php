@extends('layouts.app')
@section('title', __('assessment.gradebook'))
@section('content')
<x-page-header
    :title="__('assessment.gradebook').' — '.$offering->course->code"
    :subtitle="__('teach.tab_gradebook_help')"
>
    <x-slot:actions>
        <a href="{{ route('teach.show', ['offering' => $offering, 'tab' => 'gradebook']) }}" class="btn btn-outline-secondary btn-sm">{{ __('teach.workspace') }}</a>
    </x-slot:actions>
</x-page-header>

@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="d-flex gap-2 mb-3 flex-wrap">
    <form method="POST" action="{{ route('admin.gradebook.seed', $offering) }}">@csrf<button class="btn btn-sm btn-outline-primary">{{ __('assessment.seed_template') }}</button></form>
    <form method="POST" action="{{ route('admin.gradebook.submit', $offering) }}">@csrf<button class="btn btn-sm btn-warning">{{ __('assessment.submit_grades') }}</button></form>
    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#lockGradesModal">{{ __('assessment.lock_grades') }}</button>
    <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#reopenGradesModal">{{ __('assessment.reopen_grades') }}</button>
</div>

<form method="POST" action="{{ route('admin.gradebook.lock', $offering) }}" id="lockGradesForm">@csrf</form>
<form method="POST" action="{{ route('admin.gradebook.reopen', $offering) }}" id="reopenGradesForm">@csrf</form>

<x-confirm-dialog
    id="lockGradesModal"
    :title="__('teach.lock_confirm_title')"
    :message="__('teach.lock_confirm_body')"
    tone="danger"
>
    <x-slot:confirm>
        <button type="submit" form="lockGradesForm" class="btn btn-danger">{{ __('assessment.lock_grades') }}</button>
    </x-slot:confirm>
</x-confirm-dialog>

<x-confirm-dialog
    id="reopenGradesModal"
    :title="__('teach.reopen_confirm_title')"
    :message="__('teach.reopen_confirm_body')"
    tone="primary"
>
    <x-slot:confirm>
        <button type="submit" form="reopenGradesForm" class="btn btn-primary">{{ __('assessment.reopen_grades') }}</button>
    </x-slot:confirm>
</x-confirm-dialog>

<form method="POST" action="{{ route('admin.gradebook.components', $offering) }}" class="row g-2 mb-4">@csrf
    <div class="col-md-3"><input name="name" class="form-control" placeholder="{{ __('assessment.component') }}" required></div>
    <div class="col-md-2"><input type="number" step="0.01" name="weight_percent" class="form-control" placeholder="%" required></div>
    <div class="col-md-2"><select name="kind" class="form-select"><option>EXAM</option><option>QUIZ</option><option>ASSIGNMENT</option><option>OTHER</option></select></div>
    <div class="col-md-2"><button class="btn btn-primary">{{ __('ui.save') }}</button></div>
</form>

<ul>@foreach($components as $c)<li>{{ $c->name }} — {{ $c->weight_percent }}% ({{ $c->kind->value }})</li>@endforeach</ul>

<div class="spims-table-wrap">
<table class="table">
    <thead><tr><th>{{ __('assessment.student') }}</th><th>%</th><th>{{ __('ui.status') }}</th><th>{{ __('assessment.letter') }}</th></tr></thead>
    <tbody>
    @forelse($enrollments as $e)
        <tr>
            <td>{{ $e->student->first_name }} {{ $e->student->last_name }} <span class="text-muted-theme small">{{ $e->student->email }}</span></td>
            <td>{{ $e->computed['percent'] }}</td>
            <td><x-status-badge :status="$e->grade_status->value" :label="$e->grade_status->value" /></td>
            <td>{{ $e->final_letter }}</td>
        </tr>
    @empty
        <tr><td colspan="4"><x-empty-state :title="__('teach.empty_roster')" icon="bi-people" /></td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endsection
