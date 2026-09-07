<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleType;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\PeopleDirectoryService;
use App\Services\Admin\UserAdminService;
use App\Services\Enrollment\EnrollmentService;
use App\Support\AuthorizeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(
        Request $request,
        AuthorizeService $authorize,
        PeopleDirectoryService $directory,
    ): View {
        $actor = $request->user();
        $filters = $request->only(['q', 'role', 'status', 'locale']);

        return view('admin.users.index', [
            'users' => $directory->paginate($actor, $filters),
            'filters' => $filters,
            'assignableRoles' => $actor ? $authorize->assignableRoles($actor) : [],
            'capabilities' => $this->capabilities($authorize, $actor),
            'roleOptions' => $this->filterableRoles(),
            'statusOptions' => UserStatus::cases(),
            'localeOptions' => ['ar', 'en', 'fr'],
        ]);
    }

    public function show(
        Request $request,
        User $user,
        AuthorizeService $authorize,
        PeopleDirectoryService $directory,
        EnrollmentService $enrollment,
    ): View {
        $actor = $request->user();
        $dossier = $directory->dossier($actor, $user);

        return view('admin.users.show', array_merge($dossier, [
            'person' => $user,
            'assignableRoles' => $actor ? $authorize->assignableRoles($actor) : [],
            'capabilities' => $this->capabilities($authorize, $actor),
            'localeOptions' => ['ar' => 'العربية', 'en' => 'English', 'fr' => 'Français'],
            'hasFinancialHold' => $enrollment->hasFinancialHold($user),
            'canImpersonateTarget' => $this->canImpersonateTarget($authorize, $actor, $user),
            'canMutateTarget' => ! $user->isSeededSuperAdmin(),
            'isSelf' => $actor !== null && $actor->is($user),
        ]));
    }

    public function store(Request $request, UserAdminService $service): RedirectResponse
    {
        $data = $request->validate([
            'email' => 'required|email|unique:users,email',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:40',
            'password' => 'required|string|min:8',
            'preferred_locale' => 'nullable|in:ar,en,fr',
            'roles' => 'array',
            'roles.*' => 'string',
            'is_reviewer' => 'boolean',
        ]);

        $service->createUser($request->user(), $data);

        return back()->with('status', __('auth.user_created'));
    }

    public function update(Request $request, User $user, UserAdminService $service): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:40',
            'preferred_locale' => ['required', Rule::in(['ar', 'en', 'fr'])],
            'is_reviewer' => 'boolean',
        ]);

        $service->update($request->user(), $user, $data);

        return back()->with('status', __('people.updated'));
    }

    public function suspend(Request $request, User $user, UserAdminService $service): RedirectResponse
    {
        $service->suspend($request->user(), $user);

        return back()->with('status', __('auth.user_suspended'));
    }

    public function unsuspend(Request $request, User $user, UserAdminService $service): RedirectResponse
    {
        $service->unsuspend($request->user(), $user);

        return back()->with('status', __('people.unsuspended'));
    }

    public function activate(Request $request, User $user, UserAdminService $service): RedirectResponse
    {
        $service->forceActivate($request->user(), $user);

        return back()->with('status', __('people.activated'));
    }

    public function assignRole(Request $request, User $user, UserAdminService $service): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', 'string', Rule::in(array_map(fn (RoleType $role) => $role->value, RoleType::cases()))],
        ]);

        $service->assignRole($request->user(), $user, RoleType::from($data['role']));

        return back()->with('status', __('people.role_assigned'));
    }

    public function revokeRole(Request $request, User $user, string $role, UserAdminService $service): RedirectResponse
    {
        $service->revokeRole($request->user(), $user, RoleType::from($role));

        return back()->with('status', __('people.role_revoked'));
    }

    public function passwordReset(Request $request, User $user, UserAdminService $service): RedirectResponse
    {
        $otp = $service->issuePasswordReset($request->user(), $user);

        $redirect = back()->with('status', __('people.password_reset_issued'));

        if (app()->environment(['local', 'testing'])) {
            $redirect->with('dev_otp', $otp);
        }

        return $redirect;
    }

    public function revokeSessions(Request $request, User $user, UserAdminService $service): RedirectResponse
    {
        $count = $service->revokeIdentitySessions($request->user(), $user);

        return back()->with('status', __('people.sessions_revoked', ['count' => $count]));
    }

    /**
     * @return array{manage: bool, unsuspend: bool, reset_password: bool, impersonate: bool, assign_roles: bool, enrollment_override: bool}
     */
    private function capabilities(AuthorizeService $authorize, ?User $actor): array
    {
        return [
            'manage' => $authorize->allows($actor, 'users.manage'),
            'unsuspend' => $authorize->allows($actor, 'users.unsuspend'),
            'reset_password' => $authorize->allows($actor, 'users.reset_password'),
            'impersonate' => $authorize->allows($actor, 'users.impersonate'),
            'assign_roles' => $authorize->allows($actor, 'roles.assign'),
            'enrollment_override' => $authorize->allows($actor, 'enrollment.override'),
        ];
    }

    private function canImpersonateTarget(AuthorizeService $authorize, ?User $actor, User $target): bool
    {
        return $actor !== null
            && $authorize->allows($actor, 'users.impersonate')
            && ! $target->isSuperAdmin()
            && ! $actor->is($target)
            && $target->status === UserStatus::Active;
    }

    /**
     * @return list<RoleType>
     */
    private function filterableRoles(): array
    {
        return RoleType::cases();
    }
}
