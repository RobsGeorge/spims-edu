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

    <p class="small spims-text-dim" x-show="savedAt">{{ __('assessment.autosaved') }} <span x-text="savedAt"></span></p>
    <p class="small spims-text-dim" x-show="enforceFullScreen">{{ __('assessment.fullscreen_hint') }}</p>
    <p class="small text-danger" x-show="uploadError" x-text="uploadError"></p>

    <template x-for="(q, idx) in questions" :key="q.id">
        <x-card variant="panel" class="mb-3" x-show="!oneAtATime || idx === current">
                <p class="fw-semibold" x-text="(idx+1)+'. '+q.prompt"></p>

                <template x-if="q.type === 'MCQ_SINGLE' || q.type === 'TRUE_FALSE'">
                    <div>
                        <template x-for="opt in q.options" :key="opt.id">
                            <label class="d-block">
                                <input type="radio"
                                       :name="'q_'+q.id"
                                       :value="opt.id"
                                       :checked="answers[q.id] && answers[q.id].option_id === opt.id"
                                       @change="setAnswer(q.id, {option_id: opt.id})">
                                <span x-text="opt.text"></span>
                            </label>
                        </template>
                    </div>
                </template>

                <template x-if="q.type === 'MCQ_MULTI'">
                    <div class="exam-mcq-multi" role="group">
                        <template x-for="opt in q.options" :key="opt.id">
                            <label class="d-block">
                                <input type="checkbox"
                                       :name="'q_'+q.id+'[]'"
                                       :value="opt.id"
                                       :checked="isMultiSelected(q.id, opt.id)"
                                       @change="toggleMulti(q.id, opt.id, $event.target.checked)">
                                <span x-text="opt.text"></span>
                            </label>
                        </template>
                    </div>
                </template>

                <template x-if="q.type === 'ESSAY' || q.type === 'SHORT_ANSWER' || q.type === 'FILL_BLANK'">
                    <textarea class="form-control" rows="4"
                              :value="answers[q.id] && answers[q.id].text ? answers[q.id].text : ''"
                              @input="queueAnswer(q.id, {text: $event.target.value})"></textarea>
                </template>

                <template x-if="q.type === 'NUMERIC'">
                    <input type="number" step="any" class="form-control"
                           :value="answers[q.id] && answers[q.id].value !== undefined ? answers[q.id].value : ''"
                           @input="queueAnswer(q.id, {value: $event.target.value})">
                </template>

                <template x-if="q.type === 'MATCHING'">
                    <div class="exam-matching">
                        <template x-for="opt in q.options" :key="opt.id">
                            <div class="exam-matching__row d-flex flex-wrap align-items-center gap-2 mb-2">
                                <label class="mb-0" :for="'match_'+q.id+'_'+opt.id" x-text="opt.text"></label>
                                <select class="form-select exam-match-select"
                                        :id="'match_'+q.id+'_'+opt.id"
                                        :name="'match_'+q.id+'_'+opt.id"
                                        :value="matchValue(q.id, opt.id)"
                                        @change="setMatch(q.id, opt.id, $event.target.value)">
                                    <option value="">{{ __('assessment.choose_match') }}</option>
                                    <template x-for="choice in matchChoices(q)" :key="choice">
                                        <option :value="choice" :selected="matchValue(q.id, opt.id) === choice" x-text="choice"></option>
                                    </template>
                                </select>
                            </div>
                        </template>
                    </div>
                </template>

                <template x-if="q.type === 'ORDERING'">
                    <ol class="exam-ordering-list list-unstyled mb-0">
                        <template x-for="(opt, oi) in orderingList(q)" :key="opt.id">
                            <li class="exam-ordering-list__item d-flex align-items-center gap-2 mb-2">
                                <span class="flex-grow-1" x-text="opt.text"></span>
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        @click="moveOrder(q, oi, -1)"
                                        :disabled="oi === 0">{{ __('assessment.order_up') }}</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        @click="moveOrder(q, oi, 1)"
                                        :disabled="oi === orderingList(q).length - 1">{{ __('assessment.order_down') }}</button>
                            </li>
                        </template>
                    </ol>
                </template>

                <template x-if="q.type === 'FILE_UPLOAD'">
                    <div class="exam-file-upload">
                        <label class="form-label" :for="'file_'+q.id">{{ __('assessment.upload_file') }}</label>
                        <input type="file"
                               class="form-control"
                               :id="'file_'+q.id"
                               :name="'file_'+q.id"
                               @change="uploadFile(q, $event)">
                        <p class="small spims-text-dim mb-0 mt-1" x-show="answers[q.id] && answers[q.id].filename">
                            {{ __('assessment.file_uploaded') }}: <span x-text="answers[q.id] && answers[q.id].filename"></span>
                        </p>
                    </div>
                </template>
        </x-card>
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
    const uploadFailed = @json(__('assessment.upload_failed'));
    const savedAnswers = @json($attempt->answers->mapWithKeys(fn ($a) => [$a->question_id => $a->response])->all());
    return {
        questions: @json($attempt->exam_snapshot ?? []),
        answers: savedAnswers,
        current: 0,
        oneAtATime: @json((bool) $attempt->assessment->one_at_a_time),
        noBacktrack: @json((bool) $attempt->assessment->no_backtrack),
        enforceFullScreen: @json((bool) $attempt->assessment->enforce_full_screen),
        logFocusLoss: @json((bool) $attempt->assessment->log_focus_loss),
        dueAt: new Date(@json($dueAt)),
        clock: '--:--',
        remainingSeconds: null,
        nearExpiry: false,
        savedAt: null,
        timerId: null,
        debounceTimer: null,
        enteredFullscreen: false,
        uploadError: null,
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
            // Mobile default: one question at a time. Desktop honours the stored flag.
            if (window.matchMedia('(max-width: 767.98px)').matches) {
                this.oneAtATime = true;
            }
            this.tick();
            this.timerId = setInterval(() => this.tick(), 1000);
            if (this.logFocusLoss) {
                window.addEventListener('blur', () => this.focusLoss());
                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) this.focusLoss();
                });
            }
            if (this.enforceFullScreen) {
                this.requestFullscreen();
                document.addEventListener('fullscreenchange', () => this.onFullscreenChange());
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
        isMultiSelected(qid, optionId) {
            const ids = (this.answers[qid] && this.answers[qid].option_ids) ? this.answers[qid].option_ids : [];
            return ids.indexOf(optionId) !== -1;
        },
        toggleMulti(qid, optionId, checked) {
            const current = (this.answers[qid] && this.answers[qid].option_ids) ? this.answers[qid].option_ids.slice() : [];
            const idx = current.indexOf(optionId);
            if (checked && idx === -1) current.push(optionId);
            if (!checked && idx !== -1) current.splice(idx, 1);
            this.setAnswer(qid, {option_ids: current});
        },
        matchChoices(q) {
            if (q.match_choices && q.match_choices.length) return q.match_choices;
            const keys = (q.options || []).map(function (o) { return o.match_key; }).filter(Boolean);
            return keys.filter(function (key, i) { return keys.indexOf(key) === i; });
        },
        matchValue(qid, optionId) {
            if (!this.answers[qid] || !this.answers[qid].matches) return '';
            return this.answers[qid].matches[optionId] || '';
        },
        setMatch(qid, optionId, value) {
            const matches = Object.assign({}, (this.answers[qid] && this.answers[qid].matches) ? this.answers[qid].matches : {});
            if (value) {
                matches[optionId] = value;
            } else {
                delete matches[optionId];
            }
            this.setAnswer(qid, {matches: matches});
        },
        orderingList(q) {
            const opts = (q.options || []).slice();
            const order = (this.answers[q.id] && this.answers[q.id].order) ? this.answers[q.id].order : opts.map(function (o) { return o.id; });
            const byId = {};
            opts.forEach(function (o) { byId[o.id] = o; });
            return order.map(function (id) { return byId[id]; }).filter(Boolean);
        },
        moveOrder(q, index, delta) {
            const list = this.orderingList(q).map(function (o) { return o.id; });
            const next = index + delta;
            if (next < 0 || next >= list.length) return;
            const tmp = list[index];
            list[index] = list[next];
            list[next] = tmp;
            this.setAnswer(q.id, {order: list});
        },
        async uploadFile(q, event) {
            const file = event.target.files && event.target.files[0];
            if (!file) return;
            this.uploadError = null;
            const body = new FormData();
            body.append('file', file);
            try {
                const res = await fetch(@json(route('assessments.upload', $attempt)), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf(),
                    },
                    body: body,
                });
                if (!res.ok) {
                    this.uploadError = uploadFailed;
                    return;
                }
                const data = await res.json();
                this.setAnswer(q.id, {path: data.path, filename: data.filename});
            } catch (e) {
                this.uploadError = uploadFailed;
            }
        },
        async requestFullscreen() {
            try {
                if (document.documentElement.requestFullscreen) {
                    await document.documentElement.requestFullscreen();
                    this.enteredFullscreen = true;
                }
            } catch (e) {
                this.enteredFullscreen = false;
            }
        },
        onFullscreenChange() {
            if (this.enteredFullscreen && !document.fullscreenElement) {
                this.focusLoss();
            }
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
