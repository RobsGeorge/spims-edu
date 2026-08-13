@extends('layouts.app')
@section('title', __('learning.player_title'))
@section('content')
@php
    $course = $offering->course;
@endphp
<div class="course-player animate-in">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <p class="text-muted-theme mb-1">{{ __('learning.player_title') }}</p>
            <h1 class="spims-title mb-1">{{ $course->code }} · {{ $course->title }}</h1>
            <p class="mb-0">{{ __('learning.progress', ['percent' => (int) $progress]) }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('discussions.board', $offering) }}" class="btn btn-outline-secondary">{{ __('learning.discussions') }}</a>
            <a href="{{ route('grades.index') }}" class="btn btn-outline-secondary">{{ __('learning.grades') }}</a>
        </div>
    </div>

    @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

    <div class="progress mb-4" role="progressbar" aria-valuenow="{{ (int) $progress }}" aria-valuemin="0" aria-valuemax="100">
        <div class="progress-bar" style="width: {{ $progress }}%; background: var(--color-accent);"></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="accordion" id="playerWeeks">
                @foreach($weeks as $week)
                    <div class="accordion-item app-card mb-2 border">
                        <h2 class="accordion-header">
                            <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#week-{{ $week['id'] }}">
                                <span class="me-2">{{ $week['number'] }}. {{ $week['title'] }}</span>
                                @if(! $week['unlocked'])
                                    <span class="badge-brand">{{ __('learning.locked_badge') }}</span>
                                @elseif($week['completed'])
                                    <span class="badge-brand">{{ __('learning.done_badge') }}</span>
                                @else
                                    <span class="badge-brand">{{ __('learning.open_badge') }}</span>
                                @endif
                            </button>
                        </h2>
                        <div id="week-{{ $week['id'] }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#playerWeeks">
                            <div class="accordion-body">
                                @if(! $week['unlocked'])
                                    <p class="text-muted-theme mb-0">{{ __('learning.week_locked') }}</p>
                                @else
                                    <ul class="list-unstyled mb-3">
                                        @foreach($week['items'] as $item)
                                            @php
                                                $typeKey = match ($item['type']) {
                                                    'VIDEO' => 'item_video',
                                                    'READING' => 'item_reading',
                                                    'TEXT' => 'item_text',
                                                    'ASSIGNMENT' => 'item_assignment',
                                                    'QUIZ' => 'item_quiz',
                                                    'EXAM' => 'item_exam',
                                                    default => 'item_discussion',
                                                };
                                            @endphp
                                            <li class="player-item p-3 mb-2 rounded-3">
                                                <div class="d-flex justify-content-between gap-2 align-items-start">
                                                    <div>
                                                        <div class="small text-muted-theme text-uppercase">{{ __('learning.'.$typeKey) }}</div>
                                                        <div class="fw-semibold">{{ $item['title'] }}</div>
                                                    </div>
                                                    @if($item['url'])
                                                        <a href="{{ $item['url'] }}" class="btn btn-sm btn-outline-primary">{{ __('learning.continue_learning') }}</a>
                                                    @endif
                                                </div>

                                                @if($item['type'] === 'VIDEO' && $item['vimeo_id'])
                                                    <div class="ratio ratio-16x9 mt-3 player-video">
                                                        <iframe src="{{ \App\Support\VimeoEmbed::iframeUrl($item['vimeo_id']) }}" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen title="{{ $item['title'] }}"></iframe>
                                                    </div>
                                                @elseif($item['type'] === 'READING' && $item['file_url'])
                                                    <p class="mt-3 mb-0"><a href="{{ $item['file_url'] }}" target="_blank" rel="noopener">{{ $item['file_url'] }}</a></p>
                                                @elseif($item['body'])
                                                    <div class="mt-3 text-muted-theme">{!! nl2br(e($item['body'])) !!}</div>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>

                                    @if($enrollment && ! $week['completed'])
                                        <form method="POST" action="{{ route('courses.weeks.complete', [$offering, $week['id']]) }}">
                                            @csrf
                                            <button class="btn btn-primary">{{ __('learning.week_complete') }}</button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="col-lg-4">
            <aside class="app-card p-3 mb-3">
                <h2 class="h5 spims-title">{{ __('learning.announcements') }}</h2>
                @forelse($announcements as $announcement)
                    <div class="py-2 border-bottom border-opacity-25">
                        <div class="fw-semibold">{{ $announcement->title }}</div>
                        <div class="small text-muted-theme">{{ \Illuminate\Support\Str::limit($announcement->body, 120) }}</div>
                    </div>
                @empty
                    <p class="text-muted-theme mb-0">{{ __('learning.no_announcements') }}</p>
                @endforelse
            </aside>
        </div>
    </div>
</div>
@endsection
