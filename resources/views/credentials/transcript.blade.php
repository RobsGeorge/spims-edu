@extends('layouts.app')
@section('title', __('credentials.transcript'))
@section('content')
<h1 class="spims-title mb-3">{{ __('credentials.transcript') }}</h1>
<p class="text-muted-theme">{{ $student->first_name }} {{ $student->last_name }} · GPA: {{ $gpa ?? '—' }}</p>

<div class="table-responsive spims-table-wrap">
<table class="table">
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
        <tr><td colspan="5">{{ __('credentials.no_records') }}</td></tr>
    @endforelse
    </tbody>
</table>
</div>

<h2 class="h5 mt-4">{{ __('credentials.my_credentials') }}</h2>
<ul>
@foreach($credentials as $c)
    <li>{{ $c->type->value }} — {{ $c->serial }} — <a href="{{ $c->verifyUrl() }}">{{ __('credentials.verify_link') }}</a></li>
@endforeach
</ul>
@endsection
