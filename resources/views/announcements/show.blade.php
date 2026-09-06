@extends('layouts.app')
@section('title', $announcement->title)
@section('content')
<x-page-header :title="$announcement->title" :subtitle="__('communications.detail_title')" />

<article class="app-card p-4">
    <p class="mb-0">{{ $announcement->localizedBody() }}</p>
    @if($announcement->offering?->course)
        <p class="small text-muted-theme mt-3 mb-0">{{ $announcement->offering->course->code }} — {{ $announcement->offering->course->title }}</p>
    @endif
</article>
@endsection
