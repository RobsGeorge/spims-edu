@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="hub-page animate-in" style="max-width:1100px;margin:0 auto;">
    <x-page-header :title="$title" :subtitle="$subtitle">
        <x-slot:actions>
            <a href="{{ route('admin.reports.index') }}" class="btn btn-outline-secondary">{{ __('reports.back_hub') }}</a>
            <a href="{{ route('admin.reports.csv', $report) }}" class="btn btn-primary">{{ __('reports.download_csv') }}</a>
        </x-slot:actions>
    </x-page-header>

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
