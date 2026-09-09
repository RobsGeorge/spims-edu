@extends('layouts.app')

@section('title', __('system_docs.publish_title'))

@section('content')
<div class="hub-page animate-in" style="max-width:720px;margin-inline:auto;">
    <div class="mb-3">
        <a href="{{ route('superadmin.index') }}" class="text-decoration-none spims-text-dim">
            @include('partials.superadmin-entry-tag', ['class' => 'me-1']) {{ __('superadmin.title') }}
        </a>
    </div>

    <x-page-header
        :title="__('system_docs.publish_title')"
        :subtitle="__('system_docs.publish_desc')"
    />

    @if(session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    <x-card variant="panel" class="mb-4">
        <div class="p-3 p-md-4">
            <p class="mb-3">
                <strong>{{ __('system_docs.publish_status_label') }}:</strong>
                {{ $guestPublished ? __('system_docs.publish_status_on') : __('system_docs.publish_status_off') }}
            </p>
            <p class="spims-text-dim small mb-4">{{ __('system_docs.publish_scope_note') }}</p>

            <form method="POST" action="{{ route('superadmin.system-docs.publish.update') }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="guest_published" value="0">
                <div class="form-check mb-3">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="guest_published"
                        id="guest_published"
                        value="1"
                        @checked($guestPublished)
                    >
                    <label class="form-check-label" for="guest_published">
                        {{ __('system_docs.publish_checkbox') }}
                    </label>
                </div>
                <button type="submit" class="btn btn-primary">
                    {{ __('system_docs.publish_save') }}
                </button>
            </form>
        </div>
    </x-card>

    <x-card variant="quiet" class="mb-3">
        <div class="p-3">
            <h2 class="h6">{{ __('system_docs.audience_client') }}</h2>
            <p class="spims-text-dim small mb-2">{{ __('system_docs.publish_client_note') }}</p>
            <ul class="mb-0">
                @foreach($clientPages as $page)
                    <li>
                        <a href="{{ route('system-docs.show', $page['slug']) }}">{{ $page['title'] }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    </x-card>

    <x-card variant="bare">
        <div class="p-3">
            <h2 class="h6">{{ __('system_docs.audience_technical') }}</h2>
            <p class="spims-text-dim small mb-2">{{ __('system_docs.publish_technical_note') }}</p>
            <ul class="mb-0">
                @foreach($technicalPages as $page)
                    <li>
                        <a href="{{ route('system-docs.show', $page['slug']) }}">{{ $page['title'] }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    </x-card>
</div>
@endsection
