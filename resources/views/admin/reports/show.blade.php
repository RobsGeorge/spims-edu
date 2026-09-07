@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="hub-page animate-in" style="max-width:1100px;margin:0 auto;">
    <x-page-header :title="$title" :subtitle="$subtitle">
        <x-slot:actions>
            <a href="{{ route('admin.reports.index') }}" class="btn btn-outline-secondary">{{ __('reports.back_hub') }}</a>
            @if(($report ?? '') === 'standing' && !empty($canManageStanding))
                <a href="{{ route('admin.reports.standing.thresholds') }}" class="btn btn-outline-primary">{{ __('reports.edit_thresholds') }}</a>
            @endif
            <a href="{{ route('admin.reports.csv', $report) }}" class="btn btn-primary">{{ __('reports.download_csv') }}</a>
        </x-slot:actions>
    </x-page-header>
    @include('partials.reports-entrance-banner', ['caption' => __('school_reports.entrance_from_reports')])

    @if($rows->isEmpty())
        <x-empty-state :title="__('reports.empty')" icon="bi-table" />
    @else
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        @foreach($headers as $header)
                            <th>{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            @foreach($row as $cell)
                                <td>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $rows->withQueryString()->links() }}
    @endif
</div>
@endsection
