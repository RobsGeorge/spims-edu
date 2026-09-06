@extends('layouts.app')
@section('title', __('completion.preview'))
@section('content')
<h1 class="spims-title mb-3">{{ __('completion.preview') }}</h1>
<iframe title="{{ __('completion.preview') }}" class="w-100 border-0 shadow-sm" style="min-height: 32rem" srcdoc="{{ $html }}"></iframe>
@endsection
