@extends('layouts.app')
@section('title', $thread->title)
@section('content')
<x-page-header :title="$thread->title" :eyebrow="$offering->course->code ?? null">
    <x-slot:actions>
        @if($offering ?? null)
            <a href="{{ route('discussions.board', $offering) }}" class="btn btn-outline-secondary btn-sm">{{ __('live.discussions') }}</a>
            @if($canGrade)
                <a href="{{ route('teach.discussions.index', $offering) }}" class="btn btn-outline-primary btn-sm">{{ __('teach.discussions_workspace') }}</a>
            @endif
        @endif
    </x-slot:actions>
</x-page-header>

@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@error('file_url')<div class="alert alert-danger">{{ $message }}</div>@enderror
@error('attachments')<div class="alert alert-danger">{{ $message }}</div>@enderror
@error('attachment')<div class="alert alert-danger">{{ $message }}</div>@enderror

@foreach($posts as $post)
    <x-card variant="panel" class="mb-2">
        <div class="small spims-text-dim">{{ $post->author->email }} · {{ $post->created_at }}</div>
        <div>{!! nl2br(e($post->body)) !!}</div>
        @if(!empty($post->attachments))
            <div class="mt-2">
                <div class="small spims-text-dim mb-1">{{ __('discussions.attachments') }}</div>
                <ul class="list-unstyled mb-0">
                    @foreach($post->attachments as $index => $attachment)
                        @php
                            $name = is_array($attachment) ? (string) ($attachment['name'] ?? '') : '';
                        @endphp
                        @if($name !== '')
                            <li>
                                <a href="{{ route('discussions.posts.attachment', [$post, $index]) }}">{{ $name }}</a>
                                <span class="small spims-text-dim">{{ __('discussions.download') }}</span>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </div>
        @endif
    </x-card>
@endforeach
{{ $posts->links() }}

<x-card variant="panel" tag="form" method="POST" action="{{ route('discussions.posts.store', $thread) }}" enctype="multipart/form-data" class="mt-3">
    @csrf
    <textarea name="body" class="form-control mb-2" rows="3" required></textarea>
    <label class="form-label" for="post-attachments">{{ __('discussions.attach_file') }}</label>
    <input id="post-attachments" type="file" name="attachments[]" class="form-control mb-3" multiple>
    <button class="btn btn-primary">{{ __('live.posted') }}</button>
</x-card>
@if(($canGrade ?? false) && ($offering ?? null))
    @include('discussions.partials.grade-form', [
        'thread' => $thread,
        'offering' => $offering,
        'students' => $students,
        'canGrade' => $canGrade,
    ])
@endif
@endsection
