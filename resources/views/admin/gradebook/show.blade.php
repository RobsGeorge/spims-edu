@extends('layouts.app')
@section('title', __('assessment.gradebook'))
@section('content')
<h1 class="spims-title mb-3">{{ __('assessment.gradebook') }} — {{ $offering->course->code }}</h1>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<div class="d-flex gap-2 mb-3 flex-wrap">
    <form method="POST" action="{{ route('admin.gradebook.seed', $offering) }}">@csrf<button class="btn btn-sm btn-outline-primary">Seed template</button></form>
    <form method="POST" action="{{ route('admin.gradebook.submit', $offering) }}">@csrf<button class="btn btn-sm btn-warning">Submit grades</button></form>
    <form method="POST" action="{{ route('admin.gradebook.lock', $offering) }}">@csrf<button class="btn btn-sm btn-danger">Lock</button></form>
    <form method="POST" action="{{ route('admin.gradebook.reopen', $offering) }}">@csrf<button class="btn btn-sm btn-secondary">Reopen</button></form>
</div>
<form method="POST" action="{{ route('admin.gradebook.components', $offering) }}" class="row g-2 mb-4">@csrf
    <div class="col-md-3"><input name="name" class="form-control" placeholder="Component" required></div>
    <div class="col-md-2"><input type="number" step="0.01" name="weight_percent" class="form-control" placeholder="%" required></div>
    <div class="col-md-2"><select name="kind" class="form-select"><option>EXAM</option><option>QUIZ</option><option>ASSIGNMENT</option><option>OTHER</option></select></div>
    <div class="col-md-2"><button class="btn btn-primary">Add</button></div>
</form>
<ul>@foreach($components as $c)<li>{{ $c->name }} — {{ $c->weight_percent }}% ({{ $c->kind->value }})</li>@endforeach</ul>
<table class="table">
    <thead><tr><th>Student</th><th>%</th><th>Status</th><th>Letter</th></tr></thead>
    <tbody>
    @foreach($enrollments as $e)
        <tr>
            <td>{{ $e->student->email }}</td>
            <td>{{ $e->computed['percent'] }}</td>
            <td>{{ $e->grade_status->value }}</td>
            <td>{{ $e->final_letter }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endsection
