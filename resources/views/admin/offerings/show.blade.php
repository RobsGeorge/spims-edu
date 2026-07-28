@extends('layouts.app')
@section('title', $offering->course->code)
@section('content')
<h1 class="spims-title">{{ $offering->course->code }} — {{ $offering->course->title }}</h1>
<p class="text-muted-theme">{{ $offering->mode->value }} · {{ $offering->status->value }} ·
    <a href="{{ route('offerings.preview', $offering) }}">{{ __('offerings.public_preview') }}</a>
</p>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="row g-3">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6">{{ __('offerings.assign_staff') }}</h2>
                <form method="POST" action="{{ route('admin.offerings.staff', $offering) }}" class="row g-2">
                    @csrf
                    <div class="col-8">
                        <select name="user_id" class="form-select" required>
                            @foreach($instructors as $user)
                                <option value="{{ $user->id }}">{{ $user->first_name }} {{ $user->last_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-4">
                        <select name="role" class="form-select" required>
                            @foreach($staffRoles as $role)<option value="{{ $role->value }}">{{ $role->value }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-12"><button class="btn btn-primary btn-sm">{{ __('ui.save') }}</button></div>
                </form>
                <ul class="mt-3 mb-0">
                    @foreach($offering->staff as $staff)
                        <li>{{ $staff->user->first_name }} {{ $staff->user->last_name }} ({{ $staff->role->value }})</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6">{{ __('offerings.pricing') }}</h2>
                <p class="small text-muted-theme">{{ __('offerings.resolved') }}: USD {{ $offering->resolvedPriceUsd() }} / EGP {{ $offering->resolvedPriceEgp() }}</p>
                <form method="POST" action="{{ route('admin.offerings.pricing', $offering) }}" class="row g-2">
                    @csrf
                    <div class="col-6"><input type="number" name="price_usd_override" class="form-control" placeholder="USD minor" value="{{ $offering->price_usd_override }}"></div>
                    <div class="col-6"><input type="number" name="price_egp_override" class="form-control" placeholder="EGP minor" value="{{ $offering->price_egp_override }}"></div>
                    <div class="col-12"><button class="btn btn-outline-primary btn-sm">{{ __('offerings.save_pricing') }}</button></div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3">
    <div class="card-body">
        <h2 class="h6">{{ __('offerings.add_week') }}</h2>
        <form method="POST" action="{{ route('admin.offerings.weeks', $offering) }}" class="row g-2 mb-4">
            @csrf
            <div class="col-md-2"><input type="number" name="number" class="form-control" placeholder="#" required></div>
            <div class="col-md-6"><input name="title" class="form-control" placeholder="{{ __('academics.title') }}" required></div>
            <div class="col-md-3"><input type="date" name="unlock_date" class="form-control"></div>
            <div class="col-md-1"><button class="btn btn-primary w-100">+</button></div>
        </form>

        @foreach($offering->weeks as $week)
            <div class="border rounded p-3 mb-3">
                <h3 class="h6">Week {{ $week->number }} — {{ $week->title }}</h3>
                <form method="POST" action="{{ route('admin.weeks.items', $week) }}" class="row g-2 mb-2">
                    @csrf
                    <div class="col-md-2">
                        <select name="type" class="form-select form-select-sm" required>
                            @foreach($contentTypes as $type)<option value="{{ $type->value }}">{{ $type->value }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3"><input name="title" class="form-control form-control-sm" placeholder="{{ __('academics.title') }}" required></div>
                    <div class="col-md-2"><input name="vimeo_id" class="form-control form-control-sm" placeholder="Vimeo ID"></div>
                    <div class="col-md-3"><input name="file_url" class="form-control form-control-sm" placeholder="PDF URL"></div>
                    <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">{{ __('offerings.add_item') }}</button></div>
                    <div class="col-12"><textarea name="body" class="form-control form-control-sm" rows="2" placeholder="{{ __('offerings.text_body') }}"></textarea></div>
                </form>
                <ul class="mb-0 small">
                    @foreach($week->items as $item)
                        <li>{{ $item->type->value }}: {{ $item->title }}</li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</div>
@endsection
