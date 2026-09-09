@extends('layouts.app')
@section('title', __('grading.grading_panel'))
@section('content')

<x-page-header
    :title="$submission->student->first_name . ' ' . $submission->student->last_name"
    :subtitle="$assignment->instructions"
    :eyebrow="$offering->course->code"
>
    <x-slot:actions>
        <a
            href="{{ route('teach.assignments.submissions.index', [$offering, $assignment]) }}"
            class="btn btn-outline-secondary btn-sm"
        >{{ __('grading.back_to_submissions') }}</a>
    </x-slot:actions>
</x-page-header>

@if(session('status'))
    <div class="alert alert-success mt-3" role="status">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger mt-3">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Two-column layout: content left, grading panel right --}}
<div class="row g-4 mt-1">

    {{-- LEFT: content + files + version history --}}
    <div class="col-12 col-md-7">

        {{-- Submission content --}}
        <x-card variant="panel" class="mb-4">
            <h2 class="h6 fw-semibold mb-3">{{ __('grading.content') }}</h2>

            @if($submission->text_body)
                <div class="spims-submission-text">
                    {{-- Rendered safely: e() escapes all HTML; newlines converted to <br> --}}
                    {!! nl2br(e($submission->text_body)) !!}
                </div>
            @else
                <p class="spims-text-dim mb-0">{{ __('grading.no_content') }}</p>
            @endif

            @if($submission->file_url)
                <div class="mt-4 pt-3" style="border-top: 1px solid var(--color-hairline)">
                    <h3 class="h6 fw-semibold mb-2">{{ __('grading.attached_files') }}</h3>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <x-icon name="download" size="sm" />
                        <a
                            href="{{ $submission->file_url }}"
                            class="text-truncate"
                            style="max-inline-size: 28rem"
                        >{{ basename($submission->file_url) }}</a>
                    </div>
                </div>
            @endif
        </x-card>

        {{-- Version history --}}
        <x-card variant="quiet">
            <h2 class="h6 fw-semibold mb-3">{{ __('grading.version_history') }}</h2>

            @if($versions->isEmpty())
                {{-- Only one version (current, never resubmitted) --}}
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge spims-badge spims-badge--info">{{ __('grading.version_latest') }}</span>
                    <span class="small">{{ __('grading.version_n', ['n' => $submission->attempt_no]) }}</span>
                    @if($submission->submitted_at)
                        <span class="spims-text-dim small">
                            · {{ $submission->submitted_at->format('Y-m-d H:i') }}
                        </span>
                    @endif
                </div>
            @else
                <ul class="list-unstyled mb-0">
                    {{-- Archived versions (older) --}}
                    @foreach($versions as $version)
                        <li class="d-flex align-items-start gap-2 flex-wrap pb-2 mb-2"
                            style="border-bottom: 1px solid var(--color-hairline)">
                            <span class="badge spims-badge spims-badge--secondary">
                                {{ __('grading.version_n', ['n' => $version->attempt_no]) }}
                            </span>
                            <span class="small">
                                {{ $version->submitted_at?->format('Y-m-d H:i') ?? '—' }}
                            </span>
                            @if($version->final_score !== null)
                                <span class="small spims-text-dim">
                                    · {{ __('grading.score_col') }}: {{ number_format((float)$version->final_score, 1) }}
                                </span>
                            @endif
                        </li>
                    @endforeach

                    {{-- Current version (latest) --}}
                    <li class="d-flex align-items-start gap-2 flex-wrap">
                        <span class="badge spims-badge spims-badge--info">{{ __('grading.version_latest') }}</span>
                        <span class="small fw-semibold">
                            {{ __('grading.version_n', ['n' => $submission->attempt_no]) }}
                        </span>
                        @if($submission->submitted_at)
                            <span class="small spims-text-dim">
                                · {{ $submission->submitted_at->format('Y-m-d H:i') }}
                            </span>
                        @endif
                    </li>
                </ul>
            @endif
        </x-card>

    </div>{{-- /col-12 col-md-7 --}}

    {{-- RIGHT: grading panel --}}
    <div class="col-12 col-md-5">

        <x-card variant="panel"
            x-data="{
                aiActive: false,
                aiScore: '',
                aiReason: '',
                aiLoading: false,
                dismiss() { this.aiActive = false; this.aiScore = ''; this.aiReason = ''; },
                async fetchSuggestion() {
                    this.aiLoading = true;
                    try {
                        const r = await fetch(
                            {{ Js::from(route('teach.assignments.submissions.ai-suggest', [$offering, $assignment, $submission])) }},
                            {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                },
                            }
                        );
                        const data = await r.json();
                        if (data.score !== undefined) {
                            this.aiScore  = data.score;
                            this.aiReason = data.feedback;
                            this.aiActive = true;
                            document.getElementById('raw_score').value   = data.score;
                            document.getElementById('feedback').value    = data.feedback;
                        }
                    } finally {
                        this.aiLoading = false;
                    }
                }
            }"
        >
            <h2 class="h6 fw-semibold mb-1">{{ __('grading.grading_panel') }}</h2>

            @if($submission->final_score !== null)
                <div class="mb-3 p-2 rounded-2"
                     style="background: var(--color-surface-low); border: 1px solid var(--color-hairline)">
                    <span class="small fw-semibold">{{ __('grading.current_grade') }}:</span>
                    <span class="ms-1" style="font-variant-numeric: tabular-nums">
                        {{ number_format((float)$submission->final_score, 1) }}
                        / {{ number_format((float)$assignment->max_points, 1) }}
                    </span>
                </div>
            @endif

            {{-- AI suggestion notice (visible when suggestion is loaded) --}}
            <div
                x-show="aiActive"
                x-cloak
                role="alert"
                aria-live="polite"
                class="mb-3 p-2 rounded-2"
                style="background: var(--color-bg-2); border: 1px solid var(--color-accent)"
            >
                <p class="mb-1 small fw-semibold" style="color: var(--color-title)">
                    <x-icon name="warning" size="sm" />
                    {{ __('grading.ai_suggestion_label') }}
                </p>
                <p class="mb-2 small spims-text-dim" x-text="aiReason"></p>
                <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary"
                    @click="dismiss()"
                >{{ __('grading.dismiss_suggestion') }}</button>
            </div>

            <form
                method="POST"
                action="{{ route('teach.assignments.submissions.grade', [$offering, $assignment, $submission]) }}"
            >
                @csrf

                <x-field
                    :label="__('grading.score_label', ['max' => number_format((float)$assignment->max_points, 1)])"
                    name="raw_score"
                    :error="$errors->first('raw_score')"
                    required
                >
                    <input
                        id="raw_score"
                        type="number"
                        name="raw_score"
                        class="form-control"
                        step="0.1"
                        min="0"
                        max="{{ $assignment->max_points }}"
                        value="{{ old('raw_score', $submission->raw_score) }}"
                        required
                        style="font-variant-numeric: tabular-nums"
                    >
                </x-field>

                <x-field
                    :label="__('grading.feedback_label')"
                    name="feedback"
                    :error="$errors->first('feedback')"
                >
                    <textarea
                        id="feedback"
                        name="feedback"
                        class="form-control"
                        rows="5"
                    >{{ old('feedback', $submission->feedback) }}</textarea>
                </x-field>

                <div class="d-flex gap-2 flex-wrap mt-3">
                    <button type="submit" class="btn btn-primary">
                        <x-icon name="grade" size="sm" />
                        {{ __('grading.save_grade') }}
                    </button>

                    {{-- AI Suggest — only for text/essay submissions --}}
                    @if($submission->text_body)
                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            @click="fetchSuggestion()"
                            :disabled="aiLoading"
                            :aria-busy="aiLoading"
                        >
                            <span x-show="!aiLoading">{{ __('grading.ai_suggest') }}</span>
                            <span x-show="aiLoading" x-cloak>…</span>
                        </button>
                    @endif
                </div>
            </form>
        </x-card>

    </div>{{-- /col-12 col-md-5 --}}

</div>{{-- /row --}}

@endsection
