@extends('layouts.app')
@section('title', __('assessment.assessments'))
@section('content')
<h1 class="spims-title mb-3">{{ __('assessment.assessments') }} — {{ $offering->course->code }}</h1>
<form method="POST" action="{{ route('admin.assessments.store', $offering) }}" class="card border-0 shadow-sm">@csrf
    <div class="card-body row g-2">
        <div class="col-md-6"><input name="title" class="form-control" placeholder="Title" required></div>
        <div class="col-md-3">
            <select name="mode" class="form-select"><option value="EXAM">EXAM</option><option value="QUIZ">QUIZ</option></select>
        </div>
        <div class="col-md-3"><input type="number" name="time_limit_minutes" class="form-control" placeholder="Minutes" value="30"></div>
        <div class="col-md-3"><input type="number" name="attempts_allowed" class="form-control" value="1"></div>
        <div class="col-md-3"><input type="number" name="max_points" class="form-control" value="100"></div>
        <div class="col-md-3">
            <select name="draw_from_bank_id" class="form-select">
                <option value="">Fixed questions</option>
                @foreach($banks as $bank)<option value="{{ $bank->id }}">{{ $bank->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-3"><input type="number" name="questions_to_draw" class="form-control" placeholder="Draw N"></div>
        <div class="col-md-3">
            <select name="component_id" class="form-select">
                <option value="">No component</option>
                @foreach($components as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-12"><button class="btn btn-primary">{{ __('ui.save') }}</button></div>
    </div>
</form>
@endsection
