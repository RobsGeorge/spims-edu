@extends('layouts.app')
@section('title', __('learning.grades'))
@section('content')
<div class="animate-in">
    <h1 class="spims-title mb-2">{{ __('learning.grades') }}</h1>
    <p class="text-muted-theme mb-4">{{ __('learning.released_only') }}</p>

    @if(empty($rows))
        <div class="spims-empty app-card p-5 text-center">
            <p class="mb-0 text-muted-theme">{{ __('learning.grades_empty') }}</p>
        </div>
    @else
        @foreach($rows as $row)
            <section class="app-card p-3 mb-3">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                    <div>
                        <h2 class="h5 spims-title mb-1">{{ $row['course_code'] }} · {{ $row['course_title'] }}</h2>
                        <p class="mb-0 text-muted-theme small">
                            {{ __('learning.running_grade') }}:
                            {{ $row['running_percent'] !== null ? number_format($row['running_percent'], 1).'%' : '—' }}
                            @if($row['final_letter'])
                                · {{ __('learning.final_grade') }}: {{ $row['final_letter'] }}
                                @if($row['final_percent'] !== null) ({{ number_format($row['final_percent'], 1) }}%) @endif
                            @endif
                        </p>
                    </div>
                    <a href="{{ $row['player_url'] }}" class="btn btn-sm btn-outline-primary">{{ __('learning.open_player') }}</a>
                </div>
                <div class="table-responsive spims-table-wrap">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('learning.component') }}</th>
                                <th>{{ __('learning.score') }}</th>
                                <th>{{ __('learning.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($row['items'] as $item)
                                <tr>
                                    <td><a href="{{ $item['url'] }}">{{ $item['title'] }}</a></td>
                                    <td>{{ $item['score'] !== null ? number_format($item['score'], 1) : '—' }}</td>
                                    <td>{{ $item['status'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted-theme">{{ __('learning.grades_empty') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    @endif
</div>
@endsection
