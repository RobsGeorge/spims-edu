@extends('layouts.app')

@section('title', __('demo.guide_title'))

@section('content')
<div class="app-content px-3 px-md-4 py-4">
    <x-page-header :title="__('demo.guide_title')" :subtitle="__('demo.guide_subtitle')" />

    <div class="row g-4">
        {{-- Student role --}}
        <div class="col-12 col-md-4">
            <x-card variant="panel">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <x-icon name="student" size="lg" />
                    <h2 class="spims-title mb-0" style="font-size: var(--text-lg);">{{ __('demo.role_student') }}</h2>
                </div>
                <p class="spims-text-dim" style="font-size: var(--text-sm);">{{ __('demo.role_student_desc') }}</p>

                <ul class="list-unstyled mb-0" style="display: flex; flex-direction: column; gap: var(--space-2);">
                    @foreach($guideLinks['student'] as $link)
                        <li>
                            <a href="{{ $link['url'] }}"
                               class="d-flex align-items-center gap-2"
                               style="color: var(--color-link); font-size: var(--text-sm); min-height: 44px; padding-block: var(--space-2);">
                                <i class="bi bi-arrow-right-circle" aria-hidden="true"></i>
                                {{ __($link['label_key']) }}
                            </a>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-3 pt-3" style="border-top: 1px solid var(--color-hairline);">
                    <form method="POST" action="{{ route('demo.login') }}">
                        @csrf
                        <input type="hidden" name="role" value="student">
                        <button type="submit" class="btn btn-primary w-100">
                            <x-icon name="student" />
                            {{ __('demo.login_as', ['role' => __('demo.role_student')]) }}
                        </button>
                    </form>
                </div>
            </x-card>
        </div>

        {{-- Instructor role --}}
        <div class="col-12 col-md-4">
            <x-card variant="panel">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <x-icon name="course" size="lg" />
                    <h2 class="spims-title mb-0" style="font-size: var(--text-lg);">{{ __('demo.role_instructor') }}</h2>
                </div>
                <p class="spims-text-dim" style="font-size: var(--text-sm);">{{ __('demo.role_instructor_desc') }}</p>

                <ul class="list-unstyled mb-0" style="display: flex; flex-direction: column; gap: var(--space-2);">
                    @foreach($guideLinks['instructor'] as $link)
                        <li>
                            <a href="{{ $link['url'] }}"
                               class="d-flex align-items-center gap-2"
                               style="color: var(--color-link); font-size: var(--text-sm); min-height: 44px; padding-block: var(--space-2);">
                                <i class="bi bi-arrow-right-circle" aria-hidden="true"></i>
                                {{ __($link['label_key']) }}
                            </a>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-3 pt-3" style="border-top: 1px solid var(--color-hairline);">
                    <form method="POST" action="{{ route('demo.login') }}">
                        @csrf
                        <input type="hidden" name="role" value="instructor">
                        <button type="submit" class="btn btn-primary w-100">
                            <x-icon name="course" />
                            {{ __('demo.login_as', ['role' => __('demo.role_instructor')]) }}
                        </button>
                    </form>
                </div>
            </x-card>
        </div>

        {{-- Admin role --}}
        <div class="col-12 col-md-4">
            <x-card variant="panel">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <x-icon name="settings" size="lg" />
                    <h2 class="spims-title mb-0" style="font-size: var(--text-lg);">{{ __('demo.role_admin') }}</h2>
                </div>
                <p class="spims-text-dim" style="font-size: var(--text-sm);">{{ __('demo.role_admin_desc') }}</p>

                <ul class="list-unstyled mb-0" style="display: flex; flex-direction: column; gap: var(--space-2);">
                    @foreach($guideLinks['admin'] as $link)
                        <li>
                            <a href="{{ $link['url'] }}"
                               class="d-flex align-items-center gap-2"
                               style="color: var(--color-link); font-size: var(--text-sm); min-height: 44px; padding-block: var(--space-2);">
                                <i class="bi bi-arrow-right-circle" aria-hidden="true"></i>
                                {{ __($link['label_key']) }}
                            </a>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-3 pt-3" style="border-top: 1px solid var(--color-hairline);">
                    <form method="POST" action="{{ route('demo.login') }}">
                        @csrf
                        <input type="hidden" name="role" value="admin">
                        <button type="submit" class="btn btn-primary w-100">
                            <x-icon name="settings" />
                            {{ __('demo.login_as', ['role' => __('demo.role_admin')]) }}
                        </button>
                    </form>
                </div>
            </x-card>
        </div>
    </div>

    <div class="mt-4">
        <x-card variant="quiet">
            <p class="mb-0 spims-text-dim" style="font-size: var(--text-sm);">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                {{ __('demo.guide_note') }}
            </p>
        </x-card>
    </div>
</div>
@endsection
