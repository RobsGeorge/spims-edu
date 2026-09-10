@extends('layouts.app')
@section('title', ($snapshot['quiz']['title'] ?? __('live_quiz.play')).' — '.__('live_quiz.play'))

@push('head')
    @if(!empty($autoRefresh))
        {{-- Keep for no-JS fallback and tests; Alpine removes it at runtime --}}
        <meta id="lq-auto-refresh" http-equiv="refresh" content="2">
    @endif
@endpush

@php
    $state       = $snapshot['state'] ?? '';
    $question    = $snapshot['current_question'] ?? null;
    $you         = $snapshot['you'] ?? null;
    $answered    = (bool) ($you['answered'] ?? false);
    $showForm    = $state === 'QUESTION_OPEN' && $question && ! $answered;
    $leaderboard = $snapshot['leaderboard'] ?? [];
    $alpineData  = collect($snapshot)->except('join_code')->all();
@endphp

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8"
         x-data="lqPlayer({{ Js::from($alpineData) }})"
         x-init="init()">

        <x-page-header
            :title="$snapshot['quiz']['title'] ?? __('live_quiz.play')"
            :subtitle="__('live_quiz.play')"
            :eyebrow="__('live_quiz.student')"
        >
            <x-slot:actions>
                <a href="{{ route('live-quiz.join') }}" class="btn btn-outline-secondary btn-sm">
                    {{ __('live_quiz.join_another') }}
                </a>
            </x-slot:actions>
        </x-page-header>

        {{-- Status strip --}}
        <x-card variant="quiet" class="d-flex align-items-center justify-content-between p-3 mb-4">
            <x-status-badge :status="$state" :label="__('live_quiz.state_'.$state)" />
            @if($you)
                <span class="small spims-text-dim">{{ $you['display_name'] }}</span>
            @endif
        </x-card>

        @if($state === 'LOBBY')
            {{-- ── LOBBY ──────────────────────────────────────────────── --}}
            <x-card variant="panel" class="p-5 text-center">
                <i class="bi-people-fill lq-lobby-icon" aria-hidden="true"></i>
                <p class="lead mb-2">{{ __('live_quiz.waiting_lobby') }}</p>
                <p class="small spims-text-dim mb-0">
                    {{ __('live_quiz.lobby_count', ['count' => $snapshot['participant_count'] ?? 0]) }}
                </p>
            </x-card>

        @elseif($state === 'QUESTION_OPEN' || $state === 'QUESTION_CLOSED')
            {{-- ── QUESTION phase ─────────────────────────────────────── --}}
            @if($question)
                <x-card variant="panel" class="p-4 mb-4">
                    <p class="fw-semibold lead mb-3">{{ $question['prompt'] }}</p>

                    @if($state === 'QUESTION_OPEN' && ($question['closes_at'] ?? null))
                        {{-- Timer row --}}
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <span class="small spims-text-dim">{{ __('live_quiz.time_left') }}</span>
                            <span class="lq-timer"
                                  :class="timerClass"
                                  x-text="timeRemaining"
                                  aria-live="polite">—</span>
                        </div>
                        {{-- Progress bar --}}
                        <div class="lq-timer-bar mb-4"
                             role="progressbar"
                             aria-label="{{ __('live_quiz.time_left') }}">
                            <div class="lq-timer-bar__fill"
                                 :class="timerBarClass"
                                 :style="'width:' + timerBarWidth"></div>
                        </div>
                    @endif

                    @if($showForm)
                        {{-- Answer form (QUESTION_OPEN, not yet answered) --}}
                        <form method="POST"
                              action="{{ route('live-quiz.sessions.answer', [$snapshot['id'], $question['id']]) }}">
                            @csrf
                            <fieldset class="mb-4">
                                <legend class="visually-hidden">{{ __('live_quiz.choose_option') }}</legend>
                                @foreach($question['options'] as $option)
                                    <label class="lq-answer-btn rounded-3 mb-2"
                                           for="option-{{ $option['id'] }}">
                                        <input class="visually-hidden"
                                               type="radio"
                                               name="option_id"
                                               id="option-{{ $option['id'] }}"
                                               value="{{ $option['id'] }}"
                                               required>
                                        <span class="lq-answer-btn__text">{{ $option['label'] }}</span>
                                    </label>
                                @endforeach
                            </fieldset>
                            <button type="submit" class="btn btn-primary w-100">
                                {{ __('live_quiz.submit_answer') }}
                            </button>
                        </form>
                    @else
                        {{-- Read-only option list --}}
                        @if($answered)
                            <p class="spims-text-dim mb-3">{{ __('live_quiz.waiting_answered') }}</p>
                        @elseif($state === 'QUESTION_CLOSED')
                            <p class="spims-text-dim mb-3">{{ __('live_quiz.waiting_closed') }}</p>
                        @endif
                        <ul class="list-unstyled mb-0">
                            @foreach($question['options'] as $option)
                                @php
                                    $mod = '';
                                    if (array_key_exists('is_correct', $option)) {
                                        $mod = $option['is_correct'] ? ' lq-answer-btn--correct' : ' lq-answer-btn--wrong';
                                    }
                                @endphp
                                <li class="lq-answer-btn rounded-3 mb-2{{ $mod }}">
                                    <span class="lq-answer-btn__text">{{ $option['label'] }}</span>
                                    @if(array_key_exists('is_correct', $option) && $option['is_correct'])
                                        <span class="badge text-success ms-auto">
                                            {{ __('live_quiz.correct') }}
                                        </span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        @if($you && $you['score'] !== null && $state === 'RESULTS')
                            <p class="mt-3 mb-0 fw-semibold">
                                {{ __('live_quiz.your_score', ['score' => $you['score']]) }}
                            </p>
                        @endif
                    @endif
                </x-card>
            @endif

        @elseif($state === 'RESULTS')
            {{-- ── RESULTS ────────────────────────────────────────────── --}}
            <x-card variant="panel" class="p-4 mb-4">
                <p class="fw-semibold lead mb-3">{{ __('live_quiz.results') }}</p>

                @if(!empty($leaderboard))
                    <div class="table-responsive mb-3">
                        <table class="lq-leaderboard">
                            <caption class="visually-hidden">{{ __('live_quiz.leaderboard') }}</caption>
                            <thead>
                                <tr>
                                    <th>{{ __('live_quiz.rank') }}</th>
                                    <th>{{ __('live_quiz.student') }}</th>
                                    <th>{{ __('live_quiz.total_score') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($leaderboard as $entry)
                                    <tr @class(['lq-rank-1' => $entry['rank'] === 1])>
                                        <td>#{{ $entry['rank'] }}</td>
                                        <td>{{ $entry['display_name'] }}</td>
                                        <td>{{ $entry['total_score'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if($you && ($you['total_score'] ?? null) !== null)
                    <p class="mb-0 fw-semibold">
                        {{ __('live_quiz.your_score', ['score' => $you['total_score']]) }}
                    </p>
                @endif
            </x-card>

        @elseif($state === 'ENDED')
            {{-- ── ENDED ──────────────────────────────────────────────── --}}
            <x-card variant="panel" class="p-5 text-center">
                <i class="bi-trophy-fill lq-trophy-icon" aria-hidden="true"></i>
                <p class="lead mb-3">{{ __('live_quiz.ended') }}</p>

                @if(!empty($leaderboard))
                    <div class="table-responsive mb-3">
                        <table class="lq-leaderboard">
                            <caption class="visually-hidden">{{ __('live_quiz.leaderboard') }}</caption>
                            <thead>
                                <tr>
                                    <th>{{ __('live_quiz.rank') }}</th>
                                    <th>{{ __('live_quiz.student') }}</th>
                                    <th>{{ __('live_quiz.total_score') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($leaderboard as $entry)
                                    <tr @class(['lq-rank-1' => $entry['rank'] === 1])>
                                        <td>#{{ $entry['rank'] }}</td>
                                        <td>{{ $entry['display_name'] }}</td>
                                        <td>{{ $entry['total_score'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if($you && ($you['total_score'] ?? null) !== null)
                    <p class="mb-0 fw-semibold">
                        {{ __('live_quiz.your_score', ['score' => $you['total_score']]) }}
                    </p>
                @endif
            </x-card>
        @endif

    </div>{{-- /col --}}
</div>{{-- /row --}}
@endsection

@push('scripts')
<script>
/**
 * Live Quiz player Alpine component.
 *
 * Timer rule: the deadline is always server-supplied (current_question.closes_at +
 * server_now as reference). Date.now() is used ONLY as an elapsed-time source,
 * anchored to clientNowAtCapture captured at the moment each state response arrives.
 *   estimatedNowMs = serverNowMs + (Date.now() - clientNowAtCapture)
 * This means we never compute a deadline from the client clock alone.
 */
function lqPlayer(initial) {
    return {
        timeRemaining: 0,
        serverNowMs: 0,
        clientNowAtCapture: 0,
        _timerHandle: null,
        _pollHandle: null,

        /** CSS class for the numeric countdown element. */
        get timerClass() {
            if (this.timeRemaining <= 5)  return 'lq-timer lq-timer--critical';
            if (this.timeRemaining <= 10) return 'lq-timer lq-timer--warn';
            return 'lq-timer';
        },

        /** CSS class for the progress-bar fill. */
        get timerBarClass() {
            if (this.timeRemaining <= 5)  return 'lq-timer-bar__fill lq-timer-bar__fill--critical';
            if (this.timeRemaining <= 10) return 'lq-timer-bar__fill lq-timer-bar__fill--warn';
            return 'lq-timer-bar__fill';
        },

        /** Width percentage string for the progress-bar fill. */
        get timerBarWidth() {
            const limit = initial.current_question?.time_limit_seconds;
            if (!limit) return '100%';
            return Math.min(100, Math.round((this.timeRemaining / limit) * 100)) + '%';
        },

        init() {
            // Remove meta-refresh; Alpine takes over polling.
            document.getElementById('lq-auto-refresh')?.remove();

            // Anchor server time to client time at capture moment.
            this.serverNowMs = new Date(initial.server_now).getTime();
            this.clientNowAtCapture = Date.now();
            this._recalcTimer();

            this._timerHandle = setInterval(() => this._recalcTimer(), 500);
            this._pollHandle  = setInterval(() => this._pollState(), 2000);
        },

        /** Recompute timeRemaining from server deadline — never from client clock alone. */
        _recalcTimer() {
            const closesAt = initial.current_question?.closes_at;
            if (!closesAt) { this.timeRemaining = 0; return; }

            const deadlineMs     = new Date(closesAt).getTime();
            const estimatedNowMs = this.serverNowMs + (Date.now() - this.clientNowAtCapture);
            this.timeRemaining   = Math.max(0, Math.round((deadlineMs - estimatedNowMs) / 1000));
        },

        /** Poll /state endpoint; reload page when the host advances to a new phase. */
        async _pollState() {
            try {
                const resp = await fetch(
                    {{ Js::from(route('live-quiz.sessions.state', $snapshot['id'])) }},
                    { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }
                );
                if (!resp.ok) return;
                const data = await resp.json();
                if (data.state !== initial.state) {
                    window.location.reload();
                }
            } catch (_) {
                // Network error — keep polling silently.
            }
        },
    };
}
</script>
@endpush
