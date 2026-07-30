@extends('layouts.app')
@section('title', __('finance.reports_title'))
@section('content')
<div class="hub-page animate-in" style="max-width:920px;margin:0 auto;">
    <div class="mb-3">
        <a href="{{ route('admin.finance.index') }}" class="text-decoration-none text-muted-theme">
            {{ __('finance.admin_title') }}
        </a>
    </div>
    <h1 class="page-title">{{ __('finance.reports_title') }}</h1>
    <p class="text-muted-theme mb-4">{{ __('finance.reports_desc') }}</p>

    <div class="row g-4">
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
</div>
@endsection
