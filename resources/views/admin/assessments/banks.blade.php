@extends('layouts.app')
@section('title', __('assessment.banks'))
@section('content')
<h1 class="spims-title mb-3">{{ __('assessment.banks') }} — {{ $course->code }}</h1>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<form method="POST" action="{{ route('admin.banks.store', $course) }}" class="row g-2 mb-4">@csrf
    <div class="col-auto"><input name="name" class="form-control" placeholder="Bank name" required></div>
    <div class="col-auto"><button class="btn btn-primary">Create bank</button></div>
</form>
@foreach($banks as $bank)
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <h2 class="h6">{{ $bank->name }} ({{ $bank->questions_count }})</h2>
        <form method="POST" action="{{ route('admin.banks.questions', $bank) }}" class="row g-2">@csrf
            <div class="col-md-2">
                <select name="type" class="form-select">
                    <option value="MCQ_SINGLE">MCQ</option>
                    <option value="TRUE_FALSE">T/F</option>
                    <option value="ESSAY">Essay</option>
                    <option value="NUMERIC">Numeric</option>
                    <option value="SHORT_ANSWER">Short</option>
                </select>
            </div>
            <div class="col-md-4"><input name="prompt" class="form-control" placeholder="Prompt" required></div>
            <div class="col-md-2"><input name="points" type="number" step="0.01" class="form-control" value="1"></div>
            <div class="col-md-2"><input name="options[]" class="form-control" placeholder="Option A"></div>
            <div class="col-md-1"><input name="options[]" class="form-control" placeholder="Option B"></div>
            <div class="col-md-1"><input name="correct_option" type="number" class="form-control" value="0" title="correct index"></div>
            <div class="col-12"><button class="btn btn-sm btn-outline-primary">Add question</button></div>
        </form>
    </div>
</div>
@endforeach
@endsection
