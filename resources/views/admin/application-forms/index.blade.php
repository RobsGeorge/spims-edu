@extends('layouts.app')
@section('title', __('ui.nav_app_forms'))
@section('content')
<h1 class="spims-title mb-3">{{ __('ui.nav_app_forms') }}</h1>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.application-forms.store') }}">
            @csrf
            <div class="row g-2 mb-2">
                <div class="col-md-4">
                    <select name="program_id" class="form-select" required>
                        @foreach($programs as $program)<option value="{{ $program->id }}">{{ $program->code }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4"><input name="name" class="form-control" placeholder="{{ __('admissions.form_name') }}" required></div>
            </div>
            @foreach([0,1] as $i)
            <div class="row g-2 mb-2">
                <div class="col-md-5"><input name="fields[{{ $i }}][label]" class="form-control" placeholder="{{ __('admissions.field_label') }}" @required($i===0)></div>
                <div class="col-md-4">
                    <select name="fields[{{ $i }}][type]" class="form-select">
                        @foreach($fieldTypes as $type)<option value="{{ $type->value }}">{{ $type->value }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-3 form-check mt-2"><input type="checkbox" name="fields[{{ $i }}][required]" value="1" class="form-check-input" @checked($i===0)><label class="form-check-label">{{ __('admissions.required') }}</label></div>
            </div>
            @endforeach
            <button class="btn btn-primary">{{ __('ui.save') }}</button>
        </form>
    </div>
</div>
<ul>
@foreach($forms as $form)
    <li>{{ $form->name }} — {{ $form->program->code }}</li>
@endforeach
</ul>
@endsection
