@if($canGrade)
    <x-card variant="panel" tag="section" class="mt-4" aria-labelledby="discussion-grade-heading">
            <h2 id="discussion-grade-heading" class="h6 mb-3">{{ __('discussions.grade_heading') }}</h2>
            <p class="small spims-text-dim mb-3">{{ __('discussions.grade_help') }}</p>

            @if($thread->relationLoaded('grades') && $thread->grades->isNotEmpty())
                <ul class="list-unstyled mb-3">
                    @foreach($thread->grades as $grade)
                        <li class="border rounded-3 p-2 mb-2">
                            <strong>{{ $grade->student?->first_name }} {{ $grade->student?->last_name }}</strong>
                            <span class="small spims-text-dim">{{ $grade->student?->email }}</span>
                            <div class="small">
                                {{ __('discussions.score') }}: {{ $grade->final_score }}
                                @if($grade->overridden)
                                    <x-status-badge status="info" :label="__('discussions.overridden')" />
                                @endif
                            </div>
                            @if($grade->feedback)
                                <div class="small spims-text-dim">{{ $grade->feedback }}</div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('teach.discussions.grade', [$offering, $thread]) }}" class="row g-2">
                @csrf
                <div class="col-md-5">
                    <label class="form-label" for="discussion-grade-student-{{ $thread->id }}">{{ __('discussions.student') }}</label>
                    <select name="student_id" id="discussion-grade-student-{{ $thread->id }}" class="form-select" required>
                        <option value="">{{ __('discussions.student_placeholder') }}</option>
                        @foreach($students as $student)
                            <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }} — {{ $student->email }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="discussion-grade-score-{{ $thread->id }}">{{ __('discussions.score') }}</label>
                    <input type="number" name="score" id="discussion-grade-score-{{ $thread->id }}" class="form-control" min="0" max="100" step="0.01" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="discussion-grade-feedback-{{ $thread->id }}">{{ __('discussions.feedback') }}</label>
                    <input type="text" name="feedback" id="discussion-grade-feedback-{{ $thread->id }}" class="form-control">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100">{{ __('discussions.submit_grade') }}</button>
                </div>
            </form>
    </x-card>
@endif
