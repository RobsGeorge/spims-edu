@extends('layouts.app')
@section('title', __('staff.surveys.report').' — '.$survey->title)
@section('content')
<x-page-header
    :title="__('staff.surveys.report')"
    :subtitle="$survey->title"
    :eyebrow="__('staff.surveys.aggregates_only')"
>
    <x-slot:actions>
        <a href="{{ $backRoute }}" class="btn btn-outline-secondary btn-sm">{{ __('staff.surveys.back_survey') }}</a>
    </x-slot:actions>
</x-page-header>

@if($offering)
    @include('partials.offering-workspace-tabs', ['offering' => $offering, 'active' => 'surveys', 'prefix' => 'teach'])
@endif

@if(empty($aggregates))
    <x-empty-state :title="__('staff.surveys.report_empty')" icon="bi-clipboard-data" />
@else
    @foreach($aggregates as $row)
        <div class="border rounded-3 p-3 mb-2">
            <strong>{{ $row['prompt'] }}</strong>
            <div class="small text-muted-theme mb-2">{{ __('staff.surveys.kind_'.$row['kind']) }} · {{ __('staff.surveys.response_count', ['count' => $row['response_count']]) }}</div>
            @if(isset($row['aggregates']['counts']))
                <ul class="mb-0">
                    @foreach((array) $row['aggregates']['counts'] as $label => $count)
                        <li>{{ $label }} — {{ $count }}</li>
                    @endforeach
                </ul>
            @elseif(isset($row['aggregates']['responses']))
                <ul class="mb-0">
                    @foreach($row['aggregates']['responses'] as $text)
                        <li>{{ $text }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endforeach
@endif

<h2 class="h6 mt-4">{{ __('staff.surveys.submissions') }}</h2>
@forelse($submissions as $row)
    <div class="border rounded-3 p-3 mb-2">
        <div class="small text-muted-theme">{{ $row['submitted_at'] }} · {{ $row['is_anonymous'] ? __('staff.surveys.anonymous') : __('staff.surveys.identified') }}</div>
        <form method="POST" action="{{ $revealRoute($row['id']) }}" class="row g-2 mt-2">
            @csrf
            <div class="col-12 col-md-8"><input name="reason" class="form-control form-control-sm" placeholder="{{ __('staff.surveys.reveal_reason') }}"></div>
            <div class="col-12 col-md-4"><button class="btn btn-sm btn-outline-secondary w-100">{{ __('staff.surveys.request_reveal') }}</button></div>
        </form>
    </div>
@empty
    <x-empty-state :title="__('staff.surveys.no_submissions')" icon="bi-inbox" />
@endforelse
@endsection
