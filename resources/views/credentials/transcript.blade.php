@extends('layouts.app')
@section('title', __('credentials.transcript'))
@section('content')
<x-page-header :title="__('credentials.transcript')">
    <x-slot:subtitle>
        {{ $student->first_name }} {{ $student->last_name }}
        @if($gpa !== null)
            &middot; {{ __('credentials.computed_gpa_label') }}: {{ $gpa }}
        @endif
    </x-slot:subtitle>
</x-page-header>

@if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif

<x-card variant="panel" class="mb-4">
    <div class="table-responsive spims-table-wrap">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th scope="col">{{ __('academics.code') }}</th>
                    <th scope="col">{{ __('credentials.letter') }}</th>
                    <th scope="col">%</th>
                    <th scope="col">{{ __('credentials.credits') }}</th>
                    <th scope="col">{{ __('credentials.term') }}</th>
                </tr>
            </thead>
            <tbody>
            @forelse($records as $record)
                <tr>
                    <td>{{ $record->course->code }}</td>
                    <td>{{ $record->letter_grade }}</td>
                    <td>{{ $record->percent }}</td>
                    <td>{{ $record->credit_hours }}</td>
                    <td>{{ $record->term }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="spims-text-dim">{{ __('credentials.no_records') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-card>

@if($legacySummaries->isNotEmpty() || $priorStudy->isNotEmpty())
    <h2 class="h5 spims-title mt-4 mb-2">{{ __('credentials.prior_study_title') }}</h2>
    <p class="small spims-text-dim mb-3">{{ __('credentials.prior_study_lead') }}</p>

    @foreach($legacySummaries as $summary)
        <x-card variant="quiet" class="mb-3">
            <span class="spims-status-badge spims-status-info">
                {{ __('credentials.legacy_gpa_line', [
                    'gpa' => number_format((float) $summary->gpa, 2),
                    'scale' => number_format((float) $summary->gpa_scale, 2),
                    'source' => $summary->source->name,
                    'date' => $summary->as_of->format('Y-m-d'),
                ]) }}
            </span>
            <span class="small spims-text-dim ms-2">{{ __('credentials.legacy_gpa_scope_'.$summary->scope) }}</span>
        </x-card>
    @endforeach

    @forelse($priorStudy as $source => $records)
        <x-card variant="panel" class="mb-4">
            <h3 class="h6 page-title mb-3">{{ $source }}</h3>
            <div class="table-responsive spims-table-wrap">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('academics.code') }}</th>
                            <th scope="col">{{ __('credentials.term') }}</th>
                            <th scope="col">{{ __('credentials.letter') }}</th>
                            <th scope="col">%</th>
                            <th scope="col">{{ __('credentials.credits') }}</th>
                            <th scope="col">{{ __('credentials.col_counts_gpa') }}</th>
                            @if($canPromote)
                                <th scope="col"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($records as $record)
                        <tr>
                            <td>{{ $record->course->code }}</td>
                            <td>{{ $record->term }}</td>
                            <td>{{ $record->letter_grade }}</td>
                            <td>{{ $record->percent }}</td>
                            <td>{{ $record->credit_hours }}</td>
                            <td>
                                <x-status-badge :status="$record->counts_toward_gpa ? 'success' : 'neutral'"
                                    :label="$record->counts_toward_gpa ? __('credentials.counts_gpa_yes') : __('credentials.counts_gpa_no')" />
                            </td>
                            @if($canPromote)
                                <td>
                                    @unless($record->counts_toward_gpa)
                                        <form method="POST" action="{{ route('transcript.promote', $record) }}" onsubmit="return confirm('{{ __('credentials.promote_confirm') }}')">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-primary">{{ __('credentials.promote_button') }}</button>
                                        </form>
                                    @endunless
                                </td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @empty
    @endforelse
@endif

<h2 class="h5 spims-title mt-4 mb-2">{{ __('credentials.my_credentials') }}</h2>
<ul class="list-unstyled">
@forelse($credentials as $c)
    <li class="py-2 border-bottom border-opacity-25 d-flex flex-wrap align-items-center gap-2">
        <x-badge :value="$c->type" />
        <span class="spims-text-dim small">{{ $c->serial }}</span>
        <a href="{{ $c->verifyUrl() }}">{{ __('credentials.verify_link') }}</a>
        <a href="{{ route('credentials.download', $c) }}">{{ __('credentials.download') }}</a>
    </li>
@empty
    <x-empty-state :title="__('credentials.no_credentials')" icon="bi-award" />
@endforelse
</ul>
@endsection
