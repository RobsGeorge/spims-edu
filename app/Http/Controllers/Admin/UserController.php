<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleType;
use App\Enums\StudentProgramStatus;
use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Models\StudentProgram;
use App\Models\User;
use App\Services\Admin\UserAdminService;
use App\Services\Enrollment\EnrollmentService;
use App\Support\AuthorizeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    public function show(Request $request, User $user, EnrollmentService $enrollments, AuthorizeService $authorize): View
    {
        $user->load('roles');

        return view('admin.users.show', [
            'user' => $user,
            'held' => $enrollments->hasManualFinancialHold($user),
            'offerings' => CourseOffering::query()
                ->with(['course', 'semester'])
                ->latest()
                ->get(),
            'programs' => StudentProgram::query()
                ->with('program')
                ->where('student_id', $user->id)
                ->where('status', StudentProgramStatus::Active)
                ->get(),
            'assignableRoles' => array_values(array_filter(
                RoleType::cases(),
                fn (RoleType $role) => $authorize->canAssignRole($request->user(), $role)
            )),
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

        $data['is_reviewer'] = $request->boolean('is_reviewer');
        $service->createUser($request->user(), $data);

        return back()->with('status', __('auth.user_created'));
    }

    public function update(Request $request, User $user, UserAdminService $service): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:40',
            'preferred_locale' => 'required|in:ar,en,fr',
            'country_code' => 'nullable|string|max:10',
            'date_of_birth' => 'nullable|date',
            'notify_email' => 'sometimes|boolean',
            'is_reviewer' => 'sometimes|boolean',
        ]);

        $data['notify_email'] = $request->boolean('notify_email');
        $data['is_reviewer'] = $request->boolean('is_reviewer');

        $service->updateUser($request->user(), $user, $data);

        return redirect()->route('admin.users.show', $user)->with('status', __('auth.user_updated'));
    }

    public function assignRole(Request $request, User $user, UserAdminService $service): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::enum(RoleType::class)],
        ]);

        $service->assignRole($request->user(), $user, RoleType::from($data['role']));

        return back()->with('status', __('auth.role_assigned'));
    }

    public function removeRole(Request $request, User $user, string $role, UserAdminService $service): RedirectResponse
    {
        $resolved = RoleType::tryFrom($role);
        abort_unless($resolved instanceof RoleType, 404);

        $service->removeRole($request->user(), $user, $resolved);

        return back()->with('status', __('auth.role_removed'));
    }

    public function suspend(Request $request, User $user, UserAdminService $service): RedirectResponse
    {
        $service->suspend($request->user(), $user);

        return back()->with('status', __('auth.user_suspended'));
    }
}
