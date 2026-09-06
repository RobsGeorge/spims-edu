@extends('layouts.app')
@section('title', __('enrollment.waitlist'))
@section('content')
<x-page-header
    :title="__('enrollment.waitlist')"
    :subtitle="$offering->course->code.' — '.$offering->course->title"
>
    <x-slot:actions>
        <a href="{{ route('admin.offerings.show', $offering) }}" class="btn btn-outline-secondary btn-sm">{{ __('enrollment.back_to_offering') }}</a>
    </x-slot:actions>
</x-page-header>

<p class="text-muted-theme">{{ __('enrollment.waitlist_promote_note') }}</p>

@if($waitlisted->isEmpty())
    <x-empty-state :title="__('enrollment.waitlist_empty')" icon="bi-hourglass" />
@else
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>{{ __('enrollment.waitlist_position') }}</th>
                        <th>{{ __('ui.name') }}</th>
                        <th>{{ __('ui.email') }}</th>
                        <th>{{ __('enrollment.waitlist_enrolled_at') }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($waitlisted as $enrollment)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $enrollment->student->first_name }} {{ $enrollment->student->last_name }}</td>
                        <td>{{ $enrollment->student->email }}</td>
                        <td>{{ optional($enrollment->enrolled_at)->toDateTimeString() }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
