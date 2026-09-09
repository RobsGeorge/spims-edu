@extends('layouts.app')
@section('title', __('learning.grades'))
@section('content')
<div class="animate-in">
    <h1 class="spims-title mb-2">{{ __('learning.grades') }}</h1>
    <p class="spims-text-dim mb-4">{{ __('learning.released_only') }}</p>

    @if(empty($rows))
        <x-empty-state :title="__('learning.grades_empty')" icon="bi-bar-chart" />
    @else
        @foreach($rows as $row)
            <x-card variant="panel" tag="section" class="mb-3">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h2 class="h5 spims-title mb-2">{{ $row['course_code'] }} · {{ $row['course_title'] }}</h2>
                        <div class="d-flex align-items-baseline gap-2 mb-1">
                            <span class="h3 mb-0 spims-title">
                                {{ $row['running_percent'] !== null ? number_format($row['running_percent'], 1).'%' : '—' }}
                            </span>
                            @if($row['final_letter'])
                                <span class="h5 mb-0 spims-text-dim">{{ $row['final_letter'] }}</span>
                            @endif
                        </div>
                        <p class="small spims-text-dim mb-0">{{ __('learning.running_grade') }}</p>
                        @if($row['final_letter'] && $row['final_percent'] !== null)
                            <p class="small spims-text-dim mb-0">
                                {{ __('learning.final_grade') }}: {{ number_format($row['final_percent'], 1) }}%
                            </p>
                        @endif
                    </div>
                    <a href="{{ $row['player_url'] }}" class="btn btn-sm btn-outline-primary align-self-start">
                        {{ __('learning.open_player') }}
                    </a>
                </div>
                <div class="table-responsive spims-table-wrap">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('learning.component') }}</th>
                                <th class="text-end tabular-nums">{{ __('learning.score') }}</th>
                                <th>{{ __('learning.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($row['items'] as $item)
                                <tr>
                                    <td><a href="{{ $item['url'] }}">{{ $item['title'] }}</a></td>
                                    <td class="text-end tabular-nums">
                                        @if($item['score'] === null)
                                            <span class="spims-text-dim">—</span>
                                        @elseif($item['score'] >= 60)
                                            <span class="text-success fw-semibold">{{ number_format($item['score'], 1) }}</span>
                                        @else
                                            <span class="text-danger fw-semibold">{{ number_format($item['score'], 1) }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $statusMap = [
                                                'PASSED'      => 'success',
                                                'GRADED'      => 'success',
                                                'FAILED'      => 'danger',
                                                'SUBMITTED'   => 'info',
                                                'PENDING'     => 'warning',
                                                'NOT_STARTED' => 'secondary',
                                            ];
                                            $statusBadge = $statusMap[$item['status']] ?? 'secondary';
                                        @endphp
                                        <x-status-badge :status="$statusBadge" :label="__('learning.grade_status_'.strtolower($item['status']))" />
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="spims-text-dim">{{ __('learning.grades_empty') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        @endforeach
    @endif
</div>
@endsection
