@extends('layouts.app')
@section('title', $item->title.' — '.$offering->course->code)
@section('content')
<div class="learn-shell animate-in">
    <x-page-header :title="$item->title">
        <x-slot:actions>
            <a href="{{ route('learn.week', [$offering, $activeWeek]) }}" class="btn btn-outline-secondary btn-sm">{{ __('learn.week', ['number' => $activeWeek->number]) }}</a>
        </x-slot:actions>
    </x-page-header>
    <p class="spims-text-dim mb-3"><x-badge :value="$item->type" /></p>

    @include('offerings.partials.student-preview-banner')
    @if(session('status'))<div class="alert alert-success academic-alert">{{ session('status') }}</div>@endif
    @error('learn')<div class="alert alert-danger academic-alert">{{ $message }}</div>@enderror

    <div class="row">
        @include('learn.partials.week-nav')
        <div class="col-lg-9">
            <x-card variant="panel" class="mb-3">
                @include('learn.partials.item-media', ['item' => $item])
            </x-card>

            @if(empty($studentPreview) && in_array($item->type->value, ['VIDEO', 'READING', 'TEXT', 'FILE'], true))
                @if($completed)
                    <span class="badge text-bg-success">{{ __('learn.already_complete') }}</span>
                @else
                    <form method="POST" action="{{ route('learn.item.complete', [$offering, $item]) }}" class="academic-form">
                        @csrf
                        <button class="btn btn-primary">{{ __('learn.mark_complete') }}</button>
                    </form>
                @endif
            @endif

            @php
                $sortedItems = $activeWeek->items->sortBy('order')->values();
                $idx = $sortedItems->search(fn($i) => $i->id === $item->id);
                $prevItem = $idx > 0 ? $sortedItems[$idx - 1] : null;
                $nextItem = $idx < $sortedItems->count() - 1 ? $sortedItems[$idx + 1] : null;
            @endphp
            <div class="d-flex justify-content-between mt-3 gap-2 flex-wrap">
                @if($prevItem)
                    <a href="{{ route('learn.item', [$offering, $prevItem]) }}" class="btn btn-outline-secondary btn-sm">{{ __('learn.prev_item') }}</a>
                @else
                    <span class="btn btn-outline-secondary btn-sm disabled" aria-disabled="true">{{ __('learn.prev_item') }}</span>
                @endif
                @if($nextItem)
                    <a href="{{ route('learn.item', [$offering, $nextItem]) }}" class="btn btn-outline-secondary btn-sm">{{ __('learn.next_item') }}</a>
                @else
                    <span class="btn btn-outline-secondary btn-sm disabled" aria-disabled="true">{{ __('learn.next_item') }}</span>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
