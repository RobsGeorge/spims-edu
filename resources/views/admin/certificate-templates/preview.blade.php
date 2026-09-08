@extends('layouts.app')
@section('title', __('completion.preview'))
@section('content')
<x-page-header :title="__('completion.preview')" />
<iframe title="{{ __('completion.preview') }}" class="w-100 border-0 shadow-sm" style="min-height: 32rem" srcdoc="{{ $html }}"></iframe>
@endsection
