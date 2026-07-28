@extends('layouts.app')
@section('title', __('ui.nav_semesters'))
@section('content')
<h1 class="spims-title mb-3">{{ __('ui.nav_semesters') }}</h1>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h6">{{ __('offerings.create_year') }}</h2>
        <form method="POST" action="{{ route('admin.academic-years.store') }}" class="row g-2">
            @csrf
            <div class="col-md-4"><input name="name" class="form-control" placeholder="2026/2027" required></div>
            <div class="col-md-3"><input type="date" name="start_date" class="form-control" required></div>
            <div class="col-md-3"><input type="date" name="end_date" class="form-control" required></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">{{ __('ui.save') }}</button></div>
        </form>
    </div>
</div>

@foreach($years as $year)
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <h2 class="h5">{{ $year->name }}</h2>
        <form method="POST" action="{{ route('admin.semesters.store', $year) }}" class="row g-2 mb-3">
            @csrf
            <div class="col-md-2"><input name="name" class="form-control" placeholder="Fall" required></div>
            <div class="col-md-2"><input type="date" name="start_date" class="form-control" required></div>
            <div class="col-md-2"><input type="date" name="end_date" class="form-control" required></div>
            <div class="col-md-2"><input type="date" name="registration_start" class="form-control" required></div>
            <div class="col-md-2"><input type="date" name="registration_end" class="form-control" required></div>
            <div class="col-md-1"><input type="number" name="add_drop_end_week" class="form-control" value="2" required title="{{ __('offerings.add_drop_week') }}"></div>
            <div class="col-md-1"><input type="number" name="last_withdrawal_week" class="form-control" value="8" required title="{{ __('offerings.withdrawal_week') }}"></div>
            <div class="col-md-2"><input type="number" step="0.01" name="withdrawal_refund_percent" class="form-control" value="50" placeholder="%"></div>
            <div class="col-md-2"><button class="btn btn-outline-primary w-100">{{ __('offerings.add_semester') }}</button></div>
        </form>
        <ul class="mb-0">
            @forelse($year->semesters as $semester)
                <li>{{ $semester->name }} — {{ $semester->status->value }} (reg {{ $semester->registration_start->toDateString() }} → {{ $semester->registration_end->toDateString() }})</li>
            @empty
                <li class="text-muted-theme">{{ __('offerings.no_semesters') }}</li>
            @endforelse
        </ul>
    </div>
</div>
@endforeach
@endsection
