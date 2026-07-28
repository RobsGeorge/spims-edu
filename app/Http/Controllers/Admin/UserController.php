<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\UserAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()->with('roles')->orderBy('created_at', 'desc')->paginate(20);

        return view('admin.users.index', [
            'users' => $users,
            'assignableRoles' => RoleType::cases(),
        ]);
    }

    public function store(Request $request, UserAdminService $service): RedirectResponse
    {
        $data = $request->validate([
            'email' => 'required|email|unique:users,email',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'password' => 'required|string|min:8',
            'roles' => 'array',
            'roles.*' => 'string',
            'is_reviewer' => 'boolean',
        ]);

        $service->createUser($request->user(), $data);

        return back()->with('status', __('auth.user_created'));
    }

    public function suspend(Request $request, User $user, UserAdminService $service): RedirectResponse
    {
        $service->suspend($request->user(), $user);

        return back()->with('status', __('auth.user_suspended'));
    }
}
