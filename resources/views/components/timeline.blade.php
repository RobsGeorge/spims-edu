@props([
    'items' => [],
])

<ol {{ $attributes->merge(['class' => 'spims-timeline']) }}>
    @foreach ($items as $item)
        <li class="spims-timeline-item">
            <span class="spims-timeline-dot" aria-hidden="true"></span>
            <div class="spims-timeline-content">
                @if (!empty($item['time']))
                    <time class="spims-timeline-time d-block">{{ $item['time'] }}</time>
                @endif
                @if (!empty($item['title']))
                    <strong class="d-block">{{ $item['title'] }}</strong>
                @endif
                @if (!empty($item['body']))
                    <p class="mb-0 spims-text-dim">{{ $item['body'] }}</p>
                @endif
            </div>
        </li>
    @endforeach
    {{ $slot }}
</ol>
