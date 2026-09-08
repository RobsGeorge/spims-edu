@extends('layouts.app')
@section('title', __('reports.finance_title'))
@section('content')
<div class="hub-page hub-page-wide animate-in">
    <x-page-header :title="__('reports.finance_title')" :subtitle="__('reports.finance_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.reports.index') }}" class="btn btn-outline-secondary">{{ __('reports.back_hub') }}</a>
            <a href="{{ route('admin.reports.csv', 'finance') }}" class="btn btn-primary">{{ __('reports.download_csv') }}</a>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-4 mb-4">
        <div class="col-12 col-md-6">
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
        <div class="col-12 col-md-6">
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

    <h2 class="h5 mb-3">{{ __('reports.aging_title') }}</h2>
    @if($aging->isEmpty())
        <x-empty-state :title="__('reports.empty')" icon="bi-table" />
    @else
        <div class="table-responsive spims-table-wrap">
            <table class="table align-middle">
                <thead>
                    <tr>
                        @foreach($headers as $header)
                            <th>{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($aging as $row)
                        <tr>
                            @foreach($row as $cell)
                                <td>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $aging->withQueryString()->links() }}
    @endif
</div>
@endsection
