@extends('layouts.app')

@section('title', __('ui.nav_theme'))

@section('content')
<h1 class="spims-title mb-3">{{ __('ui.nav_theme') }}</h1>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<form method="POST" action="{{ route('admin.theme.update', $theme) }}">
    @csrf
    @method('PUT')
    <div class="card border-0 shadow-sm">
        <div class="card-body row g-3">
            <div class="col-md-6"><label class="form-label">{{ __('ui.theme_name') }}</label><input name="name" class="form-control" value="{{ $theme->name }}" required></div>
            <div class="col-md-6"><label class="form-label">{{ __('ui.site_name') }}</label><input name="site_name" class="form-control" value="{{ $theme->site_name }}" required></div>
            <div class="col-12"><label class="form-check"><input type="checkbox" name="is_active" value="1" @checked($theme->is_active)> {{ __('ui.theme_active') }}</label></div>
            <div class="col-12"><button class="btn btn-primary">{{ __('ui.save') }}</button></div>
        </div>
    </div>
</form>
@endsection
