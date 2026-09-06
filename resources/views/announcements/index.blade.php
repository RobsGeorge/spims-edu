@extends('layouts.app')
@section('title', __('communications.inbox_title'))
@section('content')
<x-page-header :title="__('communications.inbox_title')" :subtitle="__('communications.inbox_sub')" />

@forelse($announcements as $announcement)
    <article class="border rounded-3 p-3 mb-2">
        <a href="{{ route('announcements.show', $announcement) }}" class="text-decoration-none">
            <h2 class="h6 mb-1">{{ $announcement->title }}</h2>
        </a>
        <p class="mb-0 text-muted-theme">{{ \Illuminate\Support\Str::limit($announcement->localizedBody(), 160) }}</p>
    </article>
@empty
    <x-empty-state :title="__('communications.inbox_empty')" icon="bi-megaphone" />
@endforelse
@endsection
