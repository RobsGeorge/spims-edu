@extends('layouts.app')
@section('title', __('communications.report_title'))
@section('content')
<x-page-header :title="__('communications.report_title')" :subtitle="__('communications.report_sub')" />

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-2"><input name="type" value="{{ $filters['type'] ?? '' }}" class="form-control" placeholder="{{ __('communications.filter_type') }}"></div>
    <div class="col-md-2"><input name="channel" value="{{ $filters['channel'] ?? '' }}" class="form-control" placeholder="{{ __('communications.filter_channel') }}"></div>
    <div class="col-md-2"><input name="status" value="{{ $filters['status'] ?? '' }}" class="form-control" placeholder="{{ __('communications.filter_status') }}"></div>
    <div class="col-md-2"><input name="locale" value="{{ $filters['locale'] ?? '' }}" class="form-control" placeholder="{{ __('communications.filter_locale') }}"></div>
    <div class="col-md-2"><button class="btn btn-outline-primary w-100">{{ __('communications.apply_filters') }}</button></div>
    <div class="col-md-2">
        <a class="btn btn-outline-secondary w-100" href="{{ route('admin.communications.export', $filters) }}">{{ __('communications.export_csv') }}</a>
    </div>
</form>

@if($logs->isEmpty())
    <x-empty-state :title="__('communications.empty_report')" icon="bi-envelope" />
@else
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>{{ __('communications.col_type') }}</th>
                    <th>{{ __('communications.col_channel') }}</th>
                    <th>{{ __('communications.col_recipient') }}</th>
                    <th>{{ __('communications.col_subject') }}</th>
                    <th>{{ __('communications.col_status') }}</th>
                    <th>{{ __('communications.col_opened') }}</th>
                    <th>{{ __('communications.col_sent') }}</th>
                </tr>
            </thead>
            <tbody>
            @foreach($logs as $log)
                <tr>
                    <td>{{ $log->type }}</td>
                    @php $logChannel = $log->channel->value; $logStatus = $log->status->value; @endphp
                    <td>{{ $logChannel }}</td>
                    <td>{{ $log->recipient?->email }}</td>
                    <td>{{ $log->subject }}</td>
                    <td>{{ $logStatus }}</td>
                    <td>{{ $log->opened_at?->toDateTimeString() }}</td>
                    <td>{{ $log->created_at?->toDateTimeString() }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $logs->withQueryString()->links() }}
@endif
@endsection
