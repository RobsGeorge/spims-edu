@extends('layouts.app')
@section('title', __('credentials.verify_title'))
@section('content')
<div class="spims-hero p-4">
    <h1 class="spims-title mb-3">{{ __('credentials.verify_title') }}</h1>
    @if(!$credential)
        <div class="alert alert-danger" role="alert">{{ __('credentials.not_found') }}</div>
    @elseif(!$valid)
        <div class="alert alert-warning" role="alert">{{ __('credentials.revoked') }}</div>
        <p>{{ __('credentials.serial') }}: {{ $credential->serial }}</p>
    @else
        <div class="alert alert-success" role="alert">{{ __('credentials.valid') }}</div>
        <dl class="row mb-0">
            <dt class="col-sm-3">{{ __('credentials.serial') }}</dt><dd class="col-sm-9">{{ $credential->serial }}</dd>
            <dt class="col-sm-3">{{ __('credentials.type') }}</dt><dd class="col-sm-9">{{ $credential->type->value }}</dd>
            <dt class="col-sm-3">{{ __('credentials.student') }}</dt>
            <dd class="col-sm-9">{{ $credential->student->first_name }} {{ $credential->student->last_name }}</dd>
            <dt class="col-sm-3">{{ __('credentials.issued_at') }}</dt><dd class="col-sm-9">{{ $credential->issued_at }}</dd>
            @if($credential->program)
                <dt class="col-sm-3">{{ __('credentials.program') }}</dt><dd class="col-sm-9">{{ $credential->program->name }}</dd>
            @endif
            @if($credential->offering)
                <dt class="col-sm-3">{{ __('credentials.course') }}</dt><dd class="col-sm-9">{{ $credential->offering->course->code }}</dd>
            @endif
            <dt class="col-sm-3">{{ __('credentials.signatory') }}</dt>
            <dd class="col-sm-9">{{ $credential->signatory_name }} — {{ $credential->signatory_title }}</dd>
        </dl>
    @endif
</div>
@endsection
