@extends('layouts.app')
@section('title', __('assessment.runner_title'))
@section('content')
<div x-data="examRunner()" x-init="init()" class="exam-runner">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="spims-title h4 mb-0">{{ $attempt->assessment->title }}</h1>
        <div class="badge bg-primary fs-6 exam-timer"
             :class="{ 'exam-timer-pulse': nearExpiry }"
             role="timer"
             aria-live="polite">
            {{ __('assessment.time_remaining') }}: <span x-text="clock"></span>
        </div>
    </div>

    <div class="exam-progress-rail mb-3" role="group" :aria-label="progressLabel">
        <div class="d-flex justify-content-between align-items-center mb-1 small">
            <span x-text="progressLabel"></span>
            <span x-text="Math.round(progressPct) + '%'"></span>
        </div>
        <div class="exam-progress-rail__track" aria-hidden="true">
            <div class="exam-progress-rail__fill" :style="'width:' + progressPct + '%'"></div>
        </div>
    </div>

    <p class="small text-muted" x-show="savedAt">{{ __('assessment.autosaved') }} <span x-text="savedAt"></span></p>

    <template x-for="(q, idx) in questions" :key="q.id">
        <div class="card border-0 shadow-sm mb-3" x-show="!oneAtATime || idx === current">
            <div class="card-body">
                <p class="fw-semibold" x-text="(idx+1)+'. '+q.prompt"></p>
                <template x-if="q.type === 'MCQ_SINGLE' || q.type === 'TRUE_FALSE'">
                    <div>
                        <template x-for="opt in q.options" :key="opt.id">
                            <label class="d-block">
                                <input type="radio" :name="'q_'+q.id" :value="opt.id" @change="setAnswer(q.id, {option_id: opt.id})">
                                <span x-text="opt.text"></span>
                            </label>
                        </template>
                    </div>
                </template>
                <template x-if="q.type === 'ESSAY' || q.type === 'SHORT_ANSWER' || q.type === 'FILL_BLANK'">
                    <textarea class="form-control" rows="4" @input="queueAnswer(q.id, {text: $event.target.value})"></textarea>
                </template>
                <template x-if="q.type === 'NUMERIC'">
                    <input type="number" step="any" class="form-control" @input="queueAnswer(q.id, {value: $event.target.value})">
                </template>
            </div>
        </div>
    </template>

    <div class="d-flex gap-2 mb-3" x-show="oneAtATime">
        <button type="button" class="btn btn-outline-secondary" @click="prevQ()" x-show="!noBacktrack && current > 0">{{ __('assessment.prev') }}</button>
        <button type="button" class="btn btn-outline-secondary" @click="nextQ()" x-show="current < questions.length - 1">{{ __('assessment.next') }}</button>
    </div>

    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#examSubmitModal">{{ __('assessment.submit') }}</button>

    <div class="modal fade" id="examSubmitModal" tabindex="-1" aria-labelledby="examSubmitModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="examSubmitModalLabel">{{ __('assessment.submit_confirm_title') }}</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">{{ __('assessment.submit_confirm_body') }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('ui.cancel') }}</button>
                    <button type="button" class="btn btn-danger" @click="submitNow(true)" data-bs-dismiss="modal">{{ __('assessment.submit_confirm') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
<script>
function examRunner() {
    const progressTpl = @json(__('assessment.progress'));
    return {
        questions: @json($attempt->exam_snapshot ?? []),
        answers: {},
        current: 0,
        oneAtATime: @json((bool) $attempt->assessment->one_at_a_time),
        noBacktrack: @json((bool) $attempt->assessment->no_backtrack),
        dueAt: new Date(@json($dueAt)),
        clock: '--:--',
        remainingSeconds: null,
        nearExpiry: false,
        savedAt: null,
        timerId: null,
        debounceTimer: null,
        get progressPct() {
            if (!this.questions.length) return 0;
            return ((this.current + 1) / this.questions.length) * 100;
        },
        get progressLabel() {
            return progressTpl
                .replace(':current', String(this.current + 1))
                .replace(':total', String(this.questions.length));
        },
        init() {
            // Mobile default: one question at a time
            if (window.matchMedia('(max-width: 767.98px)').matches) {
                this.oneAtATime = true;
            }
            this.tick();
            this.timerId = setInterval(() => this.tick(), 1000);
            if (@json((bool) $attempt->assessment->log_focus_loss)) {
                window.addEventListener('blur', () => this.focusLoss());
            }
            setInterval(() => this.autosave(), 15000);
        },
        tick() {
            const rem = Math.max(0, Math.floor((this.dueAt - new Date()) / 1000));
            this.remainingSeconds = rem;
            this.nearExpiry = rem > 0 && rem < 60;
            this.clock = String(Math.floor(rem / 60)).padStart(2, '0') + ':' + String(rem % 60).padStart(2, '0');
            if (rem <= 0) {
                clearInterval(this.timerId);
                this.submitNow(true);
            }
        },
        setAnswer(qid, value) {
            this.answers[qid] = value;
            this.autosave();
        },
        queueAnswer(qid, value) {
            this.answers[qid] = value;
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => this.autosave(), 800);
        },
        prevQ() { if (!this.noBacktrack) this.current = Math.max(0, this.current - 1); },
        nextQ() { this.current = Math.min(this.questions.length - 1, this.current + 1); },
        csrf() { return document.querySelector('meta[name="csrf-token"]').content; },
        async autosave() {
            if (Object.keys(this.answers).length === 0) return;
            const res = await fetch(@json(route('assessments.save', $attempt)), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrf(),
                },
                body: JSON.stringify({ answers: this.answers }),
            });
            if (res.ok) {
                const data = await res.json();
                this.savedAt = new Date().toLocaleTimeString();
                if (data.status !== 'IN_PROGRESS') {
                    window.location = @json(route('assessments.show', $attempt->assessment_id));
                }
            }
        },
        async focusLoss() {
            await fetch(@json(route('assessments.focus-loss', $attempt)), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
            });
        },
        async submitNow(confirmed) {
            if (!confirmed) return;
            await this.autosave();
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = @json(route('assessments.submit', $attempt));
            const token = document.createElement('input');
            token.type = 'hidden'; token.name = '_token'; token.value = this.csrf();
            form.appendChild(token);
            document.body.appendChild(form);
            form.submit();
        }
    }
}
</script>
@endpush
