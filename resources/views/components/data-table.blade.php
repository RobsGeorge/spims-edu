@props([
    'columns',
    'rows',
])

<div {{ $attributes }}>
    {{-- Desktop: standard scrollable table --}}
    <div class="spims-dt-wrap spims-table-wrap d-none d-md-block">
        <table class="spims-dt-table">
            <thead>
                <tr>
                    @foreach ($columns as $col)
                        <th
                            class="spims-dt-th{{ !empty($col['numeric']) ? ' spims-dt-th--numeric' : '' }}"
                            scope="col"
                        >{{ $col['label'] }}</th>
                    @endforeach
                    @isset($actions)
                        <th class="spims-dt-th" scope="col"></th>
                    @endisset
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr class="spims-dt-row">
                        @foreach ($columns as $col)
                            <td class="spims-dt-td{{ !empty($col['numeric']) ? ' spims-dt-td--numeric' : '' }}">
                                {{ is_array($row) ? ($row[$col['key']] ?? '') : ($row->{$col['key']} ?? '') }}
                            </td>
                        @endforeach
                        @isset($actions)
                            <td class="spims-dt-td">{{ $actions }}</td>
                        @endisset
                    </tr>
                @empty
                    <tr>
                        <td
                            class="spims-dt-td text-center"
                            colspan="{{ count($columns) + (isset($actions) ? 1 : 0) }}"
                        >{{ __('ui.no_results') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile: stacked labelled cards --}}
    <div class="spims-dt-stack-list d-md-none">
        @forelse ($rows as $row)
            <div class="spims-dt-stack-item">
                @foreach ($columns as $col)
                    <div class="spims-dt-stack-row">
                        <span class="spims-dt-stack-label">{{ $col['label'] }}</span>
                        <span class="spims-dt-stack-value{{ !empty($col['numeric']) ? ' spims-dt-td--numeric' : '' }}">
                            {{ is_array($row) ? ($row[$col['key']] ?? '') : ($row->{$col['key']} ?? '') }}
                        </span>
                    </div>
                @endforeach
                @isset($actions)
                    <div class="mt-2">{{ $actions }}</div>
                @endisset
            </div>
        @empty
            <x-empty-state :title="__('ui.no_results')" />
        @endforelse
    </div>
</div>
