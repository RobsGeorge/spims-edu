@extends('layouts.app')
@section('title', __('assessment.banks'))
@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="spims-title mb-0">{{ __('assessment.banks') }} — {{ $course->code }}</h1>
    <a class="small" href="{{ route('help.show', 'banks-assessments') }}">{{ __('help.learn_more') }}</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<form method="POST" action="{{ route('admin.banks.store', $course) }}" class="row g-2 mb-4">@csrf
    <div class="col-auto"><input name="name" class="form-control" placeholder="{{ __('assessment.bank_name') }}" required aria-label="{{ __('assessment.bank_name') }}"></div>
    <div class="col-auto"><button class="btn btn-primary">{{ __('assessment.create_bank') }}</button></div>
</form>
@forelse($banks as $bank)
<x-card variant="panel" class="mb-3">
    <h2 class="h6">{{ $bank->name }} ({{ $bank->questions_count }})</h2>
    <form method="POST" action="{{ route('admin.banks.questions', $bank) }}" class="row g-2">@csrf
        <div class="col-md-2">
            <select name="type" class="form-select" aria-label="{{ __('assessment.type_mcq') }}">
                <option value="MCQ_SINGLE">{{ __('assessment.type_mcq') }}</option>
                <option value="TRUE_FALSE">{{ __('assessment.type_tf') }}</option>
                <option value="ESSAY">{{ __('assessment.type_essay') }}</option>
                <option value="NUMERIC">{{ __('assessment.type_numeric') }}</option>
                <option value="SHORT_ANSWER">{{ __('assessment.type_short') }}</option>
            </select>
        </div>
        <div class="col-md-4"><input name="prompt" class="form-control" placeholder="{{ __('assessment.prompt') }}" required aria-label="{{ __('assessment.prompt') }}"></div>
        <div class="col-md-2"><input name="points" type="number" step="0.01" class="form-control" value="1" aria-label="{{ __('assessment.points') }}"></div>
        <div class="col-md-2"><input name="options[]" class="form-control" placeholder="{{ __('assessment.option_a') }}" aria-label="{{ __('assessment.option_a') }}"></div>
        <div class="col-md-1"><input name="options[]" class="form-control" placeholder="{{ __('assessment.option_b') }}" aria-label="{{ __('assessment.option_b') }}"></div>
        <div class="col-md-1"><input name="correct_option" type="number" class="form-control" value="0" title="{{ __('assessment.correct_index') }}" aria-label="{{ __('assessment.correct_index') }}"></div>
        <div class="col-12"><button class="btn btn-sm btn-outline-primary">{{ __('assessment.add_question') }}</button></div>
    </form>
</x-card>
@empty
    <x-empty-state :title="__('assessment.empty_banks')" />
@endforelse
@endsection
