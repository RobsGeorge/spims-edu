@extends('layouts.app')
@section('title', __('academics.edit_program'))
@section('content')
<x-page-header :title="__('academics.edit_program')" :subtitle="$program->code.' — '.$program->name">
    <x-slot:actions>
        <a href="{{ route('admin.programs.show', $program) }}" class="btn btn-outline-secondary">{{ __('ui.cancel') }}</a>
    </x-slot:actions>
</x-page-header>

@if(session('status'))
    <div class="alert alert-success mb-4" role="status">{{ session('status') }}</div>
@endif

{{--
    Rule-builder form.
    Alpine x-data drives the live preview sentence.
    Fieldsets: Identity | Course-load | Time-to-graduation | Requirements | Credentialing | Preview
--}}
<form
    method="POST"
    action="{{ route('admin.programs.update', $program) }}"
    x-data="{
        credits:     {{ (int) old('max_credits_per_semester', $program->max_credits_per_semester) }},
        semesters:   {{ (int) old('max_semesters_to_graduate', $program->max_semesters_to_graduate) }},
        threshold:   {{ (float) old('passing_threshold', $program->passing_threshold ?? 60) }},
        enforceYear: {{ old('enforce_year_sequence', $program->enforce_year_sequence) ? 'true' : 'false' }},
        get yearLabel() {
            return this.enforceYear
                ? '{{ addslashes(__('academics.rule_preview_year_blocked')) }}'
                : '{{ addslashes(__('academics.rule_preview_year_warned')) }}';
        },
        get previewSentence() {
            const tpl = '{{ addslashes(__('academics.rule_preview_sentence', [
                'credits'   => '__CREDITS__',
                'semesters' => '__SEMESTERS__',
                'threshold' => '__THRESHOLD__',
                'year_order'=> '__YEAR__',
            ])) }}';
            return tpl
                .replace('__CREDITS__',   this.credits   || '—')
                .replace('__SEMESTERS__', this.semesters || '—')
                .replace('__THRESHOLD__', this.threshold || 60)
                .replace('__YEAR__',      this.yearLabel);
        }
    }"
    novalidate
>
    @csrf
    @method('PUT')

    {{-- ═══ IDENTITY ══════════════════════════════════════════════════════════ --}}
    <x-card variant="panel" class="mb-4">
        <fieldset>
            <legend class="spims-title h5 mb-4">{{ __('academics.fieldset_identity') }}</legend>
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <x-field :label="__('academics.code')" name="code">
                        <input id="code" class="form-control" value="{{ $program->code }}" disabled>
                    </x-field>
                </div>
                <div class="col-12 col-md-8">
                    <x-field :label="__('academics.name')" name="name"
                             :error="$errors->first('name')" required>
                        <input id="name" name="name" type="text"
                               class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $program->name) }}"
                               required autocomplete="off">
                    </x-field>
                </div>
                <div class="col-12 col-md-6">
                    <x-field :label="__('academics.type')" name="type"
                             :error="$errors->first('type')" required>
                        <select id="type" name="type"
                                class="form-select @error('type') is-invalid @enderror" required>
                            @foreach($types as $pt)
                                @php $ptVal = $pt->value; @endphp
                                <option value="{{ $ptVal }}"
                                    @selected(old('type', $program->type->value) === $ptVal)>
                                    {{ __('program_type.' . $ptVal) }}
                                </option>
                            @endforeach
                        </select>
                    </x-field>
                </div>
                <div class="col-12 col-md-6">
                    <x-field :label="__('academics.level')" name="level"
                             :error="$errors->first('level')"
                             :hint="__('academics.level_hint')">
                        <input id="level" name="level" type="text"
                               class="form-control @error('level') is-invalid @enderror"
                               value="{{ old('level', $program->level) }}" maxlength="128">
                    </x-field>
                </div>
            </div>
        </fieldset>
    </x-card>

    {{-- ═══ COURSE LOAD ══════════════════════════════════════════════════════ --}}
    <x-card variant="quiet" class="mb-4">
        <fieldset>
            <legend class="spims-title h5 mb-4">{{ __('academics.fieldset_course_load') }}</legend>
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <x-field :label="__('academics.max_credits')" name="max_credits_per_semester"
                             :error="$errors->first('max_credits_per_semester')"
                             :hint="__('academics.max_credits_hint')" required>
                        <input id="max_credits_per_semester"
                               name="max_credits_per_semester"
                               type="number" min="1" step="1"
                               class="form-control @error('max_credits_per_semester') is-invalid @enderror"
                               value="{{ old('max_credits_per_semester', $program->max_credits_per_semester) }}"
                               x-model.number="credits" required>
                    </x-field>
                </div>
                <div class="col-12 col-md-4">
                    <x-field :label="__('academics.max_courses')" name="max_courses_per_semester"
                             :error="$errors->first('max_courses_per_semester')"
                             :hint="__('academics.max_courses_hint')" required>
                        <input id="max_courses_per_semester"
                               name="max_courses_per_semester"
                               type="number" min="1" step="1"
                               class="form-control @error('max_courses_per_semester') is-invalid @enderror"
                               value="{{ old('max_courses_per_semester', $program->max_courses_per_semester) }}"
                               required>
                    </x-field>
                </div>
                {{-- enforce_year_sequence labelled toggle --}}
                <div class="col-12">
                    <div class="spims-toggle-field d-flex align-items-start gap-3 p-3"
                         style="border-radius:var(--radius-sm); background:var(--color-surface); border:1px solid var(--color-surface-border);">
                        <div class="form-check form-switch mb-0 mt-1 flex-shrink-0">
                            <input type="hidden" name="enforce_year_sequence" value="0">
                            <input class="form-check-input" type="checkbox"
                                   id="enforce_year_sequence"
                                   name="enforce_year_sequence" value="1"
                                   role="switch"
                                   @checked(old('enforce_year_sequence', $program->enforce_year_sequence))
                                   x-model="enforceYear"
                                   aria-describedby="enforce-year-hint">
                        </div>
                        <div>
                            <label class="form-check-label fw-semibold d-block"
                                   for="enforce_year_sequence">
                                {{ __('academics.enforce_year_sequence') }}
                            </label>
                            <p id="enforce-year-hint" class="form-text mb-0">
                                {{ __('academics.enforce_year_sequence_hint') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </fieldset>
    </x-card>

    {{-- ═══ TIME TO GRADUATION ═════════════════════════════════════════════ --}}
    <x-card variant="quiet" class="mb-4">
        <fieldset>
            <legend class="spims-title h5 mb-4">{{ __('academics.fieldset_time_to_graduation') }}</legend>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <x-field :label="__('academics.max_semesters')" name="max_semesters_to_graduate"
                             :error="$errors->first('max_semesters_to_graduate')"
                             :hint="__('academics.max_semesters_hint')" required>
                        <input id="max_semesters_to_graduate"
                               name="max_semesters_to_graduate"
                               type="number" min="1" step="1"
                               class="form-control @error('max_semesters_to_graduate') is-invalid @enderror"
                               value="{{ old('max_semesters_to_graduate', $program->max_semesters_to_graduate) }}"
                               x-model.number="semesters" required>
                    </x-field>
                </div>
                <div class="col-12 col-md-6">
                    <x-field :label="__('academics.elective_credits')" name="elective_credits_required"
                             :error="$errors->first('elective_credits_required')">
                        <input id="elective_credits_required"
                               name="elective_credits_required"
                               type="number" min="0" step="1"
                               class="form-control @error('elective_credits_required') is-invalid @enderror"
                               value="{{ old('elective_credits_required', $program->elective_credits_required) }}">
                    </x-field>
                </div>
            </div>
        </fieldset>
    </x-card>

    {{-- ═══ REQUIREMENTS ════════════════════════════════════════════════════ --}}
    <x-card variant="quiet" class="mb-4">
        <fieldset>
            <legend class="spims-title h5 mb-4">{{ __('academics.fieldset_requirements') }}</legend>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <x-field :label="__('academics.passing_threshold')" name="passing_threshold"
                             :error="$errors->first('passing_threshold')"
                             :hint="__('academics.passing_threshold_hint')">
                        <input id="passing_threshold"
                               name="passing_threshold"
                               type="number" min="0" max="100" step="0.01"
                               class="form-control @error('passing_threshold') is-invalid @enderror"
                               value="{{ old('passing_threshold', $program->passing_threshold) }}"
                               x-model.number="threshold">
                    </x-field>
                </div>
                <div class="col-12">
                    <div class="spims-toggle-field d-flex align-items-start gap-3 p-3"
                         style="border-radius:var(--radius-sm); background:var(--color-surface); border:1px solid var(--color-surface-border);">
                        <div class="form-check form-switch mb-0 mt-1 flex-shrink-0">
                            <input type="hidden" name="require_all_courses_to_graduate" value="0">
                            <input class="form-check-input" type="checkbox"
                                   id="require_all_courses_to_graduate"
                                   name="require_all_courses_to_graduate" value="1"
                                   role="switch"
                                   @checked(old('require_all_courses_to_graduate', $program->require_all_courses_to_graduate))
                                   aria-describedby="require-all-hint">
                        </div>
                        <div>
                            <label class="form-check-label fw-semibold d-block"
                                   for="require_all_courses_to_graduate">
                                {{ __('academics.require_all_courses_to_graduate') }}
                            </label>
                            <p id="require-all-hint" class="form-text mb-0">
                                {{ __('academics.require_all_courses_to_graduate_hint') }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <x-field :label="__('academics.grading_scheme')" name="grading_scheme_id"
                             :error="$errors->first('grading_scheme_id')">
                        <select id="grading_scheme_id" name="grading_scheme_id" class="form-select">
                            <option value="">—</option>
                            @foreach($schemes as $scheme)
                                <option value="{{ $scheme->id }}"
                                    @selected(old('grading_scheme_id', $program->grading_scheme_id) === $scheme->id)>
                                    {{ $scheme->name }}
                                </option>
                            @endforeach
                        </select>
                    </x-field>
                </div>
            </div>
        </fieldset>
    </x-card>

    {{-- ═══ CREDENTIALING ════════════════════════════════════════════════════ --}}
    <x-card variant="bare" class="mb-4">
        <fieldset>
            <legend class="spims-title h5 mb-4">{{ __('academics.fieldset_credentialing') }}</legend>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <x-field :label="__('academics.signatory_name')" name="signatory_name"
                             :error="$errors->first('signatory_name')">
                        <input id="signatory_name" name="signatory_name" type="text"
                               class="form-control @error('signatory_name') is-invalid @enderror"
                               value="{{ old('signatory_name', $program->signatory_name) }}"
                               maxlength="255">
                    </x-field>
                </div>
                <div class="col-12 col-md-6">
                    <x-field :label="__('academics.signatory_title')" name="signatory_title"
                             :error="$errors->first('signatory_title')">
                        <input id="signatory_title" name="signatory_title" type="text"
                               class="form-control @error('signatory_title') is-invalid @enderror"
                               value="{{ old('signatory_title', $program->signatory_title) }}"
                               maxlength="255">
                    </x-field>
                </div>
                <div class="col-12">
                    <x-field :label="__('academics.certificate_template')" name="certificate_template"
                             :error="$errors->first('certificate_template')"
                             :hint="__('academics.certificate_template_hint')">
                        <input id="certificate_template" name="certificate_template" type="text"
                               class="form-control @error('certificate_template') is-invalid @enderror"
                               value="{{ old('certificate_template', $program->certificate_template) }}"
                               maxlength="255">
                    </x-field>
                </div>
                <div class="col-12">
                    <div class="spims-toggle-field d-flex align-items-start gap-3 p-3"
                         style="border-radius:var(--radius-sm); background:var(--color-surface); border:1px solid var(--color-surface-border);">
                        <div class="form-check form-switch mb-0 mt-1 flex-shrink-0">
                            <input type="hidden" name="issue_credential_on_completion" value="0">
                            <input class="form-check-input" type="checkbox"
                                   id="issue_credential_on_completion"
                                   name="issue_credential_on_completion" value="1"
                                   role="switch"
                                   @checked(old('issue_credential_on_completion', $program->issue_credential_on_completion))
                                   aria-describedby="credential-hint">
                        </div>
                        <div>
                            <label class="form-check-label fw-semibold d-block"
                                   for="issue_credential_on_completion">
                                {{ __('academics.issue_credential_on_completion') }}
                            </label>
                            <p id="credential-hint" class="form-text mb-0">
                                {{ __('academics.issue_credential_on_completion_hint') }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="form-check mt-2">
                        <input type="hidden" name="active" value="0">
                        <input type="checkbox" name="active" value="1"
                               class="form-check-input" id="program_active"
                               @checked(old('active', $program->active))>
                        <label for="program_active" class="form-check-label">
                            {{ __('academics.active') }}
                        </label>
                    </div>
                </div>
            </div>
        </fieldset>
    </x-card>

    {{-- ═══ LIVE RULE PREVIEW ════════════════════════════════════════════════ --}}
    <x-card variant="quiet" class="mb-4" id="rule-preview" aria-live="polite" aria-atomic="true">
        <p class="spims-title fw-semibold mb-2">
            <i class="bi bi-eye me-2" aria-hidden="true"></i>{{ __('academics.rule_preview_title') }}
        </p>
        <p class="spims-text-dim fst-italic mb-0" x-text="previewSentence"></p>
    </x-card>

    {{-- Form actions --}}
    <div class="d-flex flex-wrap gap-2 mb-5">
        <button type="submit" class="btn btn-primary">{{ __('ui.save_changes') }}</button>
        <a href="{{ route('admin.programs.show', $program) }}" class="btn btn-outline-secondary">
            {{ __('ui.cancel') }}
        </a>
    </div>
</form>

@include('admin.programs._standing')
@endsection
