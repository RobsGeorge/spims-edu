@extends('layouts.app')
@section('title', __('ui.nav_my_applications'))
@section('content')
<x-page-header :title="__('ui.nav_my_applications')" :subtitle="__('admissions.my_applications_subtitle')" />

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<x-card variant="panel" class="mb-4">
    <h2 class="h6">{{ __('admissions.start_application') }}</h2>
    <ul class="mb-0">
    @foreach($programs as $program)
        @foreach($program->applicationForms as $form)
            <li><a href="{{ route('applications.create', $form) }}">{{ $program->code }} — {{ $form->name }}</a></li>
        @endforeach
    @endforeach
    </ul>
</x-card>

@if($applications->isEmpty())
    <x-empty-state :title="__('admissions.no_applications')" />
@else
    <x-card variant="panel">
        <div class="table-responsive spims-table-wrap">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('academics.code') }}</th>
                        <th>{{ __('ui.status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($applications as $application)
                    <tr>
                        <td>{{ $application->program->code }}</td>
                        <td>
                            <x-status-badge :status="$application->status->badgeTone()" :label="$application->status->value" />
                        </td>
                        <td class="text-end">
                            @if($application->status->isWithdrawable())
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#withdraw-{{ $application->id }}">
                                    {{ __('admissions.withdraw') }}
                                </button>
                                <form method="POST" action="{{ route('applications.withdraw', $application) }}" id="withdraw-form-{{ $application->id }}">
                                    @csrf
                                </form>
                                <x-confirm-dialog
                                    id="withdraw-{{ $application->id }}"
                                    :title="__('admissions.withdraw_confirm_title')"
                                    :message="__('admissions.withdraw_confirm_body')"
                                    tone="danger"
                                >
                                    <x-slot:confirm>
                                        <button type="submit" form="withdraw-form-{{ $application->id }}" class="btn btn-danger">{{ __('admissions.withdraw') }}</button>
                                    </x-slot:confirm>
                                </x-confirm-dialog>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
    {{ $applications->links() }}
@endif
@endsection
