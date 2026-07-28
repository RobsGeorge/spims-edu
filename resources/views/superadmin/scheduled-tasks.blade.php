@extends('layouts.app')

@section('title', __('superadmin.scheduled_title'))

@section('content')
<div class="hub-page animate-in" style="max-width:800px;margin:0 auto;">
    <div class="mb-3">
        <a href="{{ route('superadmin.index') }}" class="text-decoration-none text-muted-theme">
            @include('partials.superadmin-entry-tag', ['class' => 'me-1']) {{ __('superadmin.title') }}
        </a>
    </div>
    <h1 class="page-title">{{ __('superadmin.scheduled_title') }}</h1>
    <p class="text-muted-theme mb-4">{{ __('superadmin.scheduled_desc') }}</p>

    <div class="table-responsive app-card card shadow-sm">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>{{ __('superadmin.command') }}</th>
                    <th>{{ __('superadmin.schedule') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tasks as $task)
                    <tr>
                        <td><code>{{ $task['command'] }}</code></td>
                        <td>{{ $task['schedule'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
