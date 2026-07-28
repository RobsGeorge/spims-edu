@extends('layouts.app')
@section('title', __('assessment.runner_title'))
@section('content')
<div x-data="examRunner()" x-init="init()" class="exam-runner">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="spims-title h4 mb-0">{{ $attempt->assessment->title }}</h1>
        <div class="badge bg-primary fs-6">{{ __('assessment.time_remaining') }}: <span x-text="clock"></span></div>
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
        <button type="button" class="btn btn-outline-secondary" @click="prevQ()" x-show="!noBacktrack">Prev</button>
        <button type="button" class="btn btn-outline-secondary" @click="nextQ()">Next</button>
    </div>

    <button type="button" class="btn btn-danger" @click="submitNow()">{{ __('assessment.submit') }}</button>
</div>
@endsection

@push('scripts')
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
<script>
function examRunner() {
    return {
        questions: @json($attempt->exam_snapshot ?? []),
        answers: {},
        current: 0,
        oneAtATime: @json((bool) $attempt->assessment->one_at_a_time),
        noBacktrack: @json((bool) $attempt->assessment->no_backtrack),
        dueAt: new Date(@json($dueAt)),
        clock: '--:--',
        savedAt: null,
        timerId: null,
        debounceTimer: null,
        init() {
            this.tick();
            this.timerId = setInterval(() => this.tick(), 1000);
            if (@json((bool) $attempt->assessment->log_focus_loss)) {
                window.addEventListener('blur', () => this.focusLoss());
            }
            setInterval(() => this.autosave(), 15000);
        },
        tick() {
            const rem = Math.max(0, Math.floor((this.dueAt - new Date()) / 1000));
            this.clock = String(Math.floor(rem / 60)).padStart(2, '0') + ':' + String(rem % 60).padStart(2, '0');
            if (rem <= 0) {
                clearInterval(this.timerId);
                this.submitNow();
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
        async submitNow() {
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
