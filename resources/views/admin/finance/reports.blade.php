@extends('layouts.app')
@section('title', __('finance.reports_title'))
@section('content')
<div class="hub-page animate-in" style="max-width:1100px;margin:0 auto;">
    <div class="mb-3">
        <a href="{{ route('admin.finance.index') }}" class="text-decoration-none text-muted-theme">
            {{ __('finance.admin_title') }}
        </a>
    </div>
    <x-page-header :title="__('finance.reports_title')" :subtitle="__('finance.reports_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.reports.csv', 'finance') }}" class="btn btn-primary">{{ __('finance.download_aging_csv') }}</a>
        </x-slot:actions>
    </x-page-header>
    @include('partials.reports-entrance-banner', ['caption' => __('school_reports.entrance_from_finance')])

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <h2 class="h5 mb-3">{{ __('finance.outstanding_by_currency') }}</h2>
            @forelse($outstanding as $currency => $row)
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span>{{ $currency }}</span>
                    <strong>{{ $row['formatted'] }}</strong>
                </div>
            @empty
                <p class="text-muted-theme">{{ __('finance.reports_empty') }}</p>
            @endforelse
        </div>
        <div class="col-md-6">
            <h2 class="h5 mb-3">{{ __('finance.paid_revenue_by_currency') }}</h2>
            @forelse($paidRevenue as $currency => $row)
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span>{{ $currency }}</span>
                    <strong>{{ $row['formatted'] }}</strong>
                </div>
            @empty
                <p class="text-muted-theme">{{ __('finance.reports_empty') }}</p>
            @endforelse
        </div>
    </div>

    <h2 class="h5 mb-3">{{ __('finance.aging_title') }}</h2>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>{{ __('reports.col_currency') }}</th>
                    <th>{{ __('finance.aging_0_14') }}</th>
                    <th>{{ __('finance.aging_15_30') }}</th>
                    <th>{{ __('finance.aging_31_plus') }}</th>
                    <th>{{ __('reports.col_outstanding') }}</th>
                    <th>{{ __('reports.col_paid') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($aging as $row)
                    <tr>
                        <td>{{ $row['currency'] }}</td>
                        <td>{{ $row['bucket_0_14'] }}</td>
                        <td>{{ $row['bucket_15_30'] }}</td>
                        <td>{{ $row['bucket_31_plus'] }}</td>
                        <td>{{ $row['outstanding'] }}</td>
                        <td>{{ $row['paid'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-muted-theme">{{ __('finance.reports_empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
