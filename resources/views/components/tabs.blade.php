@props([
    'id',
    'items',
])

@php
    $firstKey = $items[0]['key'] ?? '';
@endphp

<div
    x-data="{
        tab: new URLSearchParams(window.location.search).get('tab') || @js($firstKey),
        setTab(key) {
            this.tab = key;
            const url = new URL(window.location.href);
            url.searchParams.set('tab', key);
            history.replaceState(null, '', url);
        }
    }"
    id="{{ $id }}"
    {{ $attributes }}
>
    <ul class="spims-tabs-list" role="tablist">
        @foreach ($items as $item)
            <li role="presentation">
                <button
                    type="button"
                    class="spims-tab-btn"
                    role="tab"
                    :aria-selected="tab === @js($item['key'])"
                    :tabindex="tab === @js($item['key']) ? 0 : -1"
                    x-on:click="setTab(@js($item['key']))"
                    id="{{ $id }}-tab-{{ $item['key'] }}"
                    aria-controls="{{ $id }}-panel-{{ $item['key'] }}"
                >
                    @if (!empty($item['icon']))
                        <x-icon :name="$item['icon']" size="sm" />
                    @endif
                    {{ $item['label'] }}
                </button>
            </li>
        @endforeach
    </ul>
    {{ $slot }}
</div>
