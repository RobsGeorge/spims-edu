@extends('layouts.app')
@section('title', __('credentials.admin_title'))
@section('content')
<h1 class="spims-title mb-3">{{ __('credentials.admin_title') }}</h1>
@if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif

<form method="POST" action="{{ route('admin.credentials.store') }}" class="card border-0 shadow-sm mb-4">
    @csrf
    <div class="card-body row g-2">
        <div class="col-md-3"><input name="student_id" class="form-control" placeholder="student ULID" required aria-label="{{ __('credentials.student') }}"></div>
        <div class="col-md-2">
            <select name="type" class="form-select" required aria-label="{{ __('credentials.type') }}">
                <option value="TRANSCRIPT">TRANSCRIPT</option>
                <option value="PROGRAM_CERTIFICATE">PROGRAM_CERTIFICATE</option>
                <option value="STANDALONE_CERTIFICATE">STANDALONE_CERTIFICATE</option>
            </select>
        </div>
        <div class="col-md-2"><input name="program_id" class="form-control" placeholder="program ULID"></div>
        <div class="col-md-2"><input name="offering_id" class="form-control" placeholder="offering ULID"></div>
        <div class="col-md-1">
            <select name="language" class="form-select"><option>en</option><option>ar</option><option>fr</option></select>
        </div>
        <div class="col-md-2"><button class="btn btn-primary w-100">{{ __('credentials.issue') }}</button></div>
    </div>
</form>

<div class="table-responsive spims-table-wrap">
<table class="table table-sm">
    <thead><tr><th>{{ __('credentials.serial') }}</th><th>{{ __('credentials.student') }}</th><th>{{ __('credentials.type') }}</th><th></th></tr></thead>
    <tbody>
    @foreach($credentials as $c)
        <tr class="@if($c->revoked_at) table-secondary @endif">
            <td>{{ $c->serial }}</td>
            <td>{{ $c->student->email }}</td>
            <td>{{ $c->type->value }}</td>
            <td class="d-flex gap-1">
                <a class="btn btn-sm btn-outline-secondary" href="{{ $c->verifyUrl() }}">QR</a>
                @if(!$c->revoked_at)
                <form method="POST" action="{{ route('admin.credentials.regenerate', $c) }}">@csrf
                    <button class="btn btn-sm btn-outline-primary">{{ __('credentials.regenerate') }}</button>
                </form>
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
@endsection
