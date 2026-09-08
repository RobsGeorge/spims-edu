@extends('layouts.app')
@section('title', __('credentials.transcript'))
@section('content')
<x-page-header :title="__('credentials.transcript')">
    <x-slot:subtitle>
        {{ $student->first_name }} {{ $student->last_name }}
        @if($gpa !== null)
            &middot; GPA: {{ $gpa }}
        @endif
    </x-slot:subtitle>
</x-page-header>

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
