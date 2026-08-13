@extends('layouts.app')
@section('title', __('learn.player_title').' — '.$offering->course->code)
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="spims-title mb-1">{{ $offering->course->code }} — {{ $offering->course->title }}</h1>
        <p class="text-muted-theme mb-0">{{ __('offerings.mode') }}: {{ $offering->mode->value }} · {{ __('learn.progress') }}: {{ number_format($enrollment->progress_percent, 0) }}%</p>
    </div>
    <a href="{{ route('enrollments.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('ui.nav_enrollments') }}</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@error('learn')<div class="alert alert-danger">{{ $message }}</div>@enderror

<div class="row">
    @include('learn.partials.week-nav')
    <div class="col-lg-9">
        @if($activeWeek)
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h5">{{ __('learn.week', ['number' => $activeWeek->number]) }}: {{ $activeWeek->title }}</h2>
                    <p class="text-muted-theme">{{ __('learn.items') }}</p>
                    <ul class="list-group list-group-flush">
                        @forelse($activeWeek->items as $item)
                            @php $done = in_array($item->id, $completedItemIds, true); @endphp
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <a href="{{ route('learn.item', [$offering, $item]) }}">
                                    <span class="badge text-bg-light me-1">{{ $item->type->value }}</span>
                                    {{ $item->title }}
                                </a>
                                @if($done)<span class="badge text-bg-success">{{ __('learn.completed') }}</span>@endif
                            </li>
                        @empty
                            <li class="list-group-item px-0 text-muted-theme">{{ __('learn.no_items') }}</li>
                        @endforelse
                    </ul>
                    <a class="btn btn-primary mt-3" href="{{ route('learn.week', [$offering, $activeWeek]) }}">{{ __('learn.week', ['number' => $activeWeek->number]) }}</a>
                </div>
            </div>
        @else
            <div class="alert alert-info mb-0">{{ __('learn.no_weeks') }}</div>
        @endif
    </div>
</div>
@endsection
