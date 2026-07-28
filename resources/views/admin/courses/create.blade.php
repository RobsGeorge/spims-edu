@extends('layouts.app')
@section('title', __('academics.create_course'))
@section('content')
<h1 class="spims-title mb-3">{{ __('academics.create_course') }}</h1>
<form method="POST" action="{{ route('admin.courses.store') }}" class="card border-0 shadow-sm">
    @csrf
    <div class="card-body row g-3">
        <div class="col-md-3"><label class="form-label">{{ __('academics.code') }}</label><input name="code" class="form-control" value="{{ old('code') }}" required></div>
        <div class="col-md-9"><label class="form-label">{{ __('academics.title') }}</label><input name="title" class="form-control" value="{{ old('title') }}" required></div>
        <div class="col-md-3"><label class="form-label">{{ __('academics.credits') }}</label><input type="number" name="credit_hours" class="form-control" value="{{ old('credit_hours', 3) }}" required></div>
        <div class="col-md-3"><label class="form-label">{{ __('academics.price_usd') }}</label><input type="number" name="default_price_usd" class="form-control" value="{{ old('default_price_usd', 0) }}"></div>
        <div class="col-md-3"><label class="form-label">{{ __('academics.price_egp') }}</label><input type="number" name="default_price_egp" class="form-control" value="{{ old('default_price_egp', 0) }}"></div>
        <div class="col-md-3"><label class="form-label">{{ __('academics.assessment_template') }}</label>
            <select name="assessment_template_id" class="form-select"><option value="">—</option>
                @foreach($templates as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-6"><label class="form-label">{{ __('academics.prerequisite') }}</label>
            <select name="prerequisite_id" class="form-select"><option value="">—</option>
                @foreach($prerequisiteOptions as $c)<option value="{{ $c->id }}">{{ $c->code }} — {{ $c->title }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-3 form-check mt-4"><input type="checkbox" name="is_free" value="1" class="form-check-input" id="is_free"><label for="is_free" class="form-check-label">{{ __('academics.is_free') }}</label></div>
        <div class="col-md-3 form-check mt-4"><input type="checkbox" name="is_standalone" value="1" class="form-check-input" id="is_standalone"><label for="is_standalone" class="form-check-label">{{ __('academics.is_standalone') }}</label></div>
        <div class="col-12"><button class="btn btn-primary">{{ __('ui.save') }}</button></div>
    </div>
</form>
@endsection
