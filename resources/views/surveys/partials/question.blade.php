@php
    $kind = $question->kind->value;
    $options = $question->options ?? [];
    $field = 'answers['.$question->id.']';
    $current = $value;
    if ($kind === 'MULTI' && ! is_array($current) && $current !== null && $current !== '') {
        $current = [$current];
    }
    $scaleMin = 1;
    $scaleMax = 5;
    if (isset($options['min'], $options['max'])) {
        $scaleMin = (int) $options['min'];
        $scaleMax = (int) $options['max'];
    } elseif (is_array($options) && $options !== [] && array_is_list($options)) {
        $numeric = array_map('intval', $options);
        $scaleMin = min($numeric);
        $scaleMax = max($numeric);
    }
@endphp
<x-card variant="quiet" tag="fieldset" class="mb-3" :disabled="$disabled">
    <legend class="h6 mb-2">
        {{ $question->position }}. {{ $question->prompt }}
        @if($question->required)
            <span class="small spims-text-dim">&middot; {{ __('feedback.required') }}</span>
        @endif
        <span class="visually-hidden">{{ __('feedback.kind_'.$kind) }}</span>
    </legend>

    @if($kind === 'TEXT')
        <textarea
            name="{{ $field }}"
            class="form-control"
            rows="3"
            @required($question->required && ! $disabled)
            @disabled($disabled)
        >{{ is_array($current) ? '' : $current }}</textarea>
    @elseif($kind === 'SINGLE')
        @foreach($options as $option)
            @if(! is_string($option) && ! is_numeric($option))
                @continue
            @endif
            <label class="form-check">
                <input
                    type="radio"
                    name="{{ $field }}"
                    value="{{ $option }}"
                    class="form-check-input"
                    @checked((string) $current === (string) $option)
                    @required($question->required && ! $disabled)
                    @disabled($disabled)
                >
                <span class="form-check-label">{{ $option }}</span>
            </label>
        @endforeach
    @elseif($kind === 'MULTI')
        @php $picked = array_map('strval', is_array($current) ? $current : []); @endphp
        @foreach($options as $option)
            @if(! is_string($option) && ! is_numeric($option))
                @continue
            @endif
            <label class="form-check">
                <input
                    type="checkbox"
                    name="{{ $field }}[]"
                    value="{{ $option }}"
                    class="form-check-input"
                    @checked(in_array((string) $option, $picked, true))
                    @disabled($disabled)
                >
                <span class="form-check-label">{{ $option }}</span>
            </label>
        @endforeach
    @elseif($kind === 'SCALE')
        <div class="d-flex flex-wrap gap-3">
            @for($n = $scaleMin; $n <= $scaleMax; $n++)
                <label class="form-check">
                    <input
                        type="radio"
                        name="{{ $field }}"
                        value="{{ $n }}"
                        class="form-check-input"
                        @checked((string) $current === (string) $n)
                        @required($question->required && ! $disabled)
                        @disabled($disabled)
                    >
                    <span class="form-check-label">{{ $n }}</span>
                </label>
            @endfor
        </div>
    @endif
</x-card>
