@extends('layouts.app')

@section('title', __('ui.nav_users'))

@section('content')
<h1 class="spims-title mb-3">{{ __('ui.nav_users') }}</h1>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h6">{{ __('ui.create_user') }}</h2>
        <form method="POST" action="{{ route('admin.users.store') }}" class="row g-2">
            @csrf
            <div class="col-md-3"><input name="first_name" class="form-control" placeholder="{{ __('ui.first_name') }}" required></div>
            <div class="col-md-3"><input name="last_name" class="form-control" placeholder="{{ __('ui.last_name') }}" required></div>
            <div class="col-md-3"><input name="email" type="email" class="form-control" placeholder="{{ __('ui.email') }}" required></div>
            <div class="col-md-3"><input name="password" type="password" class="form-control" placeholder="{{ __('ui.password') }}" required></div>
            <div class="col-12">
                @foreach($assignableRoles as $role)
                    <label class="me-3"><input type="checkbox" name="roles[]" value="{{ $role->value }}"> {{ $role->value }}</label>
                @endforeach
            </div>
            <div class="col-12"><button class="btn btn-primary">{{ __('ui.create_user') }}</button></div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>{{ __('ui.email') }}</th><th>{{ __('ui.name') }}</th><th>{{ __('ui.roles') }}</th><th>{{ __('ui.status') }}</th><th></th></tr></thead>
            <tbody>
            @foreach($users as $user)
                <tr>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->first_name }} {{ $user->last_name }}</td>
                    <td>{{ $user->roleTypes()->pluck('value')->join(', ') }}</td>
                    <td>{{ $user->status->value }}</td>
                    <td>
                        @if($user->status->value !== 'SUSPENDED')
                        <form method="POST" action="{{ route('admin.users.suspend', $user) }}">@csrf<button class="btn btn-sm btn-outline-danger">{{ __('ui.suspend') }}</button></form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
{{ $users->links() }}
@endsection
