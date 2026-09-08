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
            <tr>
                <td>
                    <strong>{{ $enrollment->student->first_name }} {{ $enrollment->student->last_name }}</strong>
                    <div class="small spims-text-dim">{{ $enrollment->student->email }}</div>
                </td>
                @foreach($components as $component)
                    @php $score = $scores->get($component->id)['score'] ?? null; @endphp
                    <td>
                        @if(!empty($gradeUrls[$component->id]))
                            <a href="{{ $gradeUrls[$component->id] }}">{{ $score !== null ? $score : __('assessment.no_score') }}</a>
                        @else
                            {{ $score !== null ? $score : __('assessment.no_score') }}
                        @endif
                    </td>
                @endforeach
                <td>{{ $computed['percent'] }}</td>
                <td>{{ $computed['letter'] ?? $enrollment->final_letter }}</td>
                <td><x-status-badge :status="$enrollment->status->value" :label="$enrollment->status->value" /></td>
                <td><x-status-badge :status="$enrollment->grade_status->value" :label="$enrollment->grade_status->value" /></td>
            </tr>
        @empty
            <tr><td colspan="{{ 5 + $components->count() }}"><x-empty-state :title="__('teach.empty_roster')" icon="bi-people" /></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
