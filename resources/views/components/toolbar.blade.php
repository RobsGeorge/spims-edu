<div {{ $attributes->merge(['class' => 'spims-toolbar']) }}>
    @isset($start)
        <div class="spims-toolbar-start">{{ $start }}</div>
    @endisset
    @isset($end)
        <div class="spims-toolbar-end">{{ $end }}</div>
    @endisset
</div>
