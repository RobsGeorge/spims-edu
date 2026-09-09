@php $locked = $locked ?? false; @endphp

<div class="table-responsive spims-table-wrap">
    <table class="table table-bordered align-middle gradebook-grid" data-gradebook-grid="1">
        <caption class="visually-hidden">{{ __('assessment.gradebook_grid') }}</caption>
        <thead>
            <tr>
                <th scope="col">{{ __('assessment.student') }}</th>
                @foreach($components as $component)
                    <th scope="col">
                        @if(!empty($gradeUrls[$component->id]))
                            <a href="{{ $gradeUrls[$component->id] }}">{{ $component->name }}</a>
                        @else
                            {{ $component->name }}
                        @endif
                        <div class="small spims-text-dim">{{ $component->weight_percent }}% · {{ __('assessment.kind_'.$component->kind->value) }}</div>
                    </th>
                @endforeach
                <th scope="col">{{ __('assessment.final_percent') }}</th>
                <th scope="col">{{ __('assessment.letter') }}</th>
                <th scope="col">{{ __('teach.enrollment') }}</th>
                <th scope="col">{{ __('teach.grade_status') }}</th>
            </tr>
        </thead>
        <tbody>
        @forelse($enrollments as $enrollment)
            @php
                $computed = $enrollment->computed ?? ['percent' => null, 'components' => [], 'letter' => $enrollment->final_letter];
                $scores = collect($computed['components'])->keyBy('id');
            @endphp
            <tr data-student-id="{{ $enrollment->student_id }}">
                <td>
                    <strong>{{ $enrollment->student->first_name }} {{ $enrollment->student->last_name }}</strong>
                    <div class="small spims-text-dim">{{ $enrollment->student->email }}</div>
                </td>
                @foreach($components as $component)
                    @php $score = $scores->get($component->id)['score'] ?? null; @endphp
                    <td data-component-id="{{ $component->id }}">
                        @if(!$locked)
                            {{-- Inline editable cell using Alpine.js --}}
                            <div
                                x-data="{
                                    editing: false,
                                    saving: false,
                                    value: '{{ $score !== null ? number_format((float)$score, 2, '.', '') : '' }}',
                                    original: '{{ $score !== null ? number_format((float)$score, 2, '.', '') : '' }}',
                                    error: '',
                                    save() {
                                        this.saving = true;
                                        this.error = '';
                                        fetch('{{ route('admin.gradebook.cell', $offering) }}', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json',
                                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                'Accept': 'application/json',
                                            },
                                            body: JSON.stringify({
                                                student_id: '{{ $enrollment->student_id }}',
                                                component_id: '{{ $component->id }}',
                                                score: parseFloat(this.value) || 0,
                                            }),
                                        })
                                        .then(r => r.json().then(d => ({ ok: r.ok, data: d })))
                                        .then(({ ok, data }) => {
                                            if (!ok) {
                                                this.error = data.message || '{{ __('assessment.cell_score_error') }}';
                                            } else {
                                                this.original = this.value;
                                                this.editing = false;
                                                const row = this.$el.closest('tr');
                                                const pctCell = row.querySelector('[data-final-percent]');
                                                const ltCell = row.querySelector('[data-final-letter]');
                                                if (pctCell) pctCell.textContent = data.percent ?? '';
                                                if (ltCell) ltCell.textContent = data.letter ?? '';
                                            }
                                        })
                                        .catch(() => { this.error = '{{ __('assessment.cell_score_error') }}'; })
                                        .finally(() => { this.saving = false; });
                                    },
                                    cancel() {
                                        this.value = this.original;
                                        this.editing = false;
                                        this.error = '';
                                    }
                                }"
                                class="gradebook-cell"
                            >
                                <span
                                    x-show="!editing"
                                    @click="editing = true"
                                    role="button"
                                    tabindex="0"
                                    @keydown.enter="editing = true"
                                    @keydown.space.prevent="editing = true"
                                    :aria-label="'{{ addslashes(__('assessment.cell_edit_aria', ['component' => $component->name, 'student' => $enrollment->student->first_name.' '.$enrollment->student->last_name])) }}'"
                                    class="gradebook-cell-display"
                                >
                                    <span x-text="original || '{{ __('assessment.no_score') }}'"></span>
                                </span>
                                <div x-show="editing" x-cloak class="d-flex align-items-center gap-1 flex-wrap">
                                    <input
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        x-model="value"
                                        @keydown.enter="save()"
                                        @keydown.escape="cancel()"
                                        class="form-control form-control-sm"
                                        style="width:5rem;"
                                        :aria-label="'{{ __('assessment.cell_score_input') }}'"
                                        :disabled="saving"
                                        x-ref="input"
                                        x-init="$watch('editing', v => v && $nextTick(() => $refs.input && $refs.input.focus()))"
                                    >
                                    <button
                                        @click="save()"
                                        :disabled="saving"
                                        class="btn btn-sm btn-primary"
                                        :aria-label="'{{ __('ui.save') }}'"
                                    ><i class="bi bi-check" aria-hidden="true"></i></button>
                                    <button
                                        @click="cancel()"
                                        :disabled="saving"
                                        class="btn btn-sm btn-outline-secondary"
                                        :aria-label="'{{ __('ui.cancel') }}'"
                                    ><i class="bi bi-x" aria-hidden="true"></i></button>
                                </div>
                                <p x-show="error" x-text="error" class="text-danger mb-0 small" role="alert"></p>
                            </div>
                        @else
                            {{-- Read-only when locked --}}
                            {{ $score !== null ? number_format((float)$score, 2, '.', '') : __('assessment.no_score') }}
                        @endif
                    </td>
                @endforeach
                <td data-final-percent>{{ $computed['percent'] }}</td>
                <td data-final-letter>{{ $computed['letter'] ?? $enrollment->final_letter }}</td>
                <td>
                    <x-status-badge :status="$enrollment->status->value" />
                </td>
                <td>
                    <x-status-badge :status="$enrollment->grade_status->value" />
                </td>
            </tr>
        @empty
            <tr><td colspan="{{ 5 + $components->count() }}"><x-empty-state :title="__('teach.empty_roster')" icon="bi-people" /></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
