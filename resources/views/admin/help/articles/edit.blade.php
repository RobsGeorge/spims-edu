@extends('layouts.app')

@section('title', $article ? __('help.admin_edit_article') : __('help.admin_new_article'))

@section('content')
@php
    $formAction = $article
        ? route('admin.help.articles.update', $article)
        : route('admin.help.articles.store');
@endphp

<x-page-header
    :title="$article ? __('help.admin_edit_article') : __('help.admin_new_article')"
    :subtitle="$article?->slug"
>
    <x-slot:actions>
        @if($previewUrl)
            <a class="btn btn-outline-secondary btn-sm" href="{{ $previewUrl }}" target="_blank" rel="noopener">
                {{ __('help.admin_preview_reader') }}
            </a>
        @endif
    </x-slot:actions>
</x-page-header>

@include('admin.help._nav', ['helpAdminTab' => 'articles'])

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if(count(array_intersect($missingLocales, ['ar', 'fr'])) > 0)
    <div class="alert alert-warning">
        {{ __('help.admin_missing_locales', ['locales' => implode(', ', array_intersect($missingLocales, ['ar', 'fr']))]) }}
    </div>
@endif

<form method="POST" action="{{ $formAction }}" id="help-article-form">
    @csrf
    @if($article)
        @method('PUT')
    @endif

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label">{{ __('help.admin_slug') }}</label>
                <input name="slug" class="form-control" value="{{ old('slug', $article?->slug) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('help.admin_category') }}</label>
                <select name="category_id" class="form-select" required>
                    @foreach($categories as $category)
                        @php
                            $label = __('help.categories.'.$category->slug);
                            if ($label === 'help.categories.'.$category->slug) {
                                $label = $category->slug;
                            }
                        @endphp
                        <option value="{{ $category->id }}" @selected(old('category_id', $article?->category_id) === $category->id)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ __('help.admin_sort_order') }}</label>
                <input type="number" name="sort_order" class="form-control" min="0"
                       value="{{ old('sort_order', $article?->sort_order ?? 0) }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ __('help.admin_status') }}</label>
                <div class="form-control-plaintext">
                    {{ $article ? __('help.status_'.$article->status->value) : __('help.status_draft') }}
                </div>
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_public" value="1" id="isPublic"
                           @checked(old('is_public', $article?->is_public))>
                    <label class="form-check-label" for="isPublic">{{ __('help.admin_is_public') }}</label>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">{{ __('help.admin_audiences') }}</label>
                <p class="small text-muted-theme mb-2">{{ __('help.admin_audiences_hint') }}</p>
                <div class="d-flex flex-wrap gap-3">
                    @foreach($roles as $role)
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" name="audiences[]" value="{{ $role->value }}"
                                   @checked(in_array($role->value, $selectedAudiences, true))>
                            <span class="form-check-label">{{ __('help.audience_'.$role->value) }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <ul class="nav nav-tabs" role="tablist">
                @foreach($locales as $i => $locale)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $i === 0 ? 'active' : '' }}"
                                id="tab-{{ $locale }}"
                                data-bs-toggle="tab"
                                data-bs-target="#pane-{{ $locale }}"
                                type="button"
                                role="tab">
                            {{ strtoupper($locale) }}
                            @if(in_array($locale, $missingLocales, true))
                                <span class="badge text-bg-warning ms-1">!</span>
                            @endif
                        </button>
                    </li>
                @endforeach
            </ul>
            <div class="tab-content pt-3">
                @foreach($locales as $i => $locale)
                    @php $loc = $localeMap[$locale]; @endphp
                    <div class="tab-pane fade {{ $i === 0 ? 'show active' : '' }}" id="pane-{{ $locale }}" role="tabpanel">
                        <div class="mb-3">
                            <label class="form-label">{{ __('help.admin_title_field') }}</label>
                            <input name="locales[{{ $locale }}][title]" class="form-control" value="{{ $loc['title'] }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('help.admin_summary') }}</label>
                            <textarea name="locales[{{ $locale }}][summary]" class="form-control" rows="2">{{ $loc['summary'] }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('help.admin_body') }}</label>
                            <textarea name="locales[{{ $locale }}][body_markdown]"
                                      id="body-{{ $locale }}"
                                      class="form-control font-monospace help-md-body"
                                      rows="12"
                                      data-locale="{{ $locale }}">{{ $loc['body_markdown'] }}</textarea>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong class="small">{{ __('help.admin_preview') }} ({{ strtoupper($locale) }})</strong>
                            <button type="button" class="btn btn-sm btn-outline-secondary help-md-preview-btn" data-locale="{{ $locale }}">
                                {{ __('help.admin_preview') }}
                            </button>
                        </div>
                        <div id="preview-{{ $locale }}" class="border rounded p-3 bg-body-tertiary help-md-preview" style="min-height: 4rem;"></div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <button type="submit" name="intent" value="draft" class="btn btn-outline-primary">{{ __('help.admin_save_draft') }}</button>
        <button type="submit" name="intent" value="publish" class="btn btn-primary">{{ __('help.admin_publish') }}</button>
        @if($article)
            <button type="submit" name="intent" value="archive" class="btn btn-outline-secondary">{{ __('help.admin_archive') }}</button>
        @endif
        <a class="btn btn-link" href="{{ route('admin.help.articles.index') }}">{{ __('help.back_to_index') }}</a>
    </div>
</form>

@if($article)
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h6">{{ __('help.admin_media') }}</h2>
        @if($article->media->isEmpty())
            <p class="text-muted-theme small">{{ __('help.admin_media_empty') }}</p>
        @else
            <ul class="list-group list-group-flush mb-3">
                @foreach($article->media as $media)
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <code class="small">{{ $media->path }}</code>
                            @if($media->alt)
                                <div class="small text-muted-theme">{{ $media->alt }}</div>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('admin.help.articles.media.destroy', [$article, $media]) }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">{{ __('help.admin_delete') }}</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('admin.help.articles.media.store', $article) }}" enctype="multipart/form-data" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-5">
                <label class="form-label">{{ __('help.admin_media_upload') }}</label>
                <input type="file" name="file" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('help.admin_media_alt') }}</label>
                <input type="text" name="alt" class="form-control">
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-primary">{{ __('help.admin_media_upload') }}</button>
            </div>
        </form>

        <hr class="my-3">
        <p class="small text-muted-theme mb-2">Upload API (<code>help-media</code>) then attach:</p>
        <div class="row g-2 align-items-end" id="help-api-upload">
            <div class="col-md-5">
                <input type="file" id="help-api-file" class="form-control">
            </div>
            <div class="col-md-4">
                <input type="text" id="help-api-alt" class="form-control" placeholder="{{ __('help.admin_media_alt') }}">
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-outline-secondary" id="help-api-upload-btn">API upload</button>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.help.articles.media.attach', $article) }}" id="help-attach-form" class="d-none">
            @csrf
            <input type="hidden" name="path" id="help-attach-path">
            <input type="hidden" name="alt" id="help-attach-alt">
        </form>
    </div>
</div>
@endif

<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const previewUrl = @json(route('admin.help.preview-markdown'));

    document.querySelectorAll('.help-md-preview-btn').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const locale = btn.getAttribute('data-locale');
            const body = document.getElementById('body-' + locale)?.value || '';
            const target = document.getElementById('preview-' + locale);
            if (!target) return;
            target.innerHTML = '…';
            try {
                const res = await fetch(previewUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf || '',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ body_markdown: body })
                });
                const data = await res.json();
                target.innerHTML = data.html || '';
            } catch (e) {
                target.textContent = 'Preview failed';
            }
        });
    });

    const apiBtn = document.getElementById('help-api-upload-btn');
    if (apiBtn) {
        apiBtn.addEventListener('click', async function () {
            const fileInput = document.getElementById('help-api-file');
            const file = fileInput?.files?.[0];
            if (!file) return;
            const form = new FormData();
            form.append('file', file);
            form.append('prefix', 'help-media');
            try {
                const res = await fetch(@json(route('api.uploads.store')), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf || '',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: form
                });
                if (!res.ok) throw new Error('upload failed');
                const data = await res.json();
                document.getElementById('help-attach-path').value = data.path;
                document.getElementById('help-attach-alt').value = document.getElementById('help-api-alt')?.value || '';
                document.getElementById('help-attach-form').submit();
            } catch (e) {
                alert('Upload failed');
            }
        });
    }
})();
</script>
@endsection
