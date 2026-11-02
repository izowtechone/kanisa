<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreManagedUserRequest;
use App\Http\Requests\UpdateManagedUserPermissionsRequest;
use App\Http\Requests\UpdateManagedUserRequest;
use App\Models\Member;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive,suspended'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
        ]);

        $users = User::query()
            ->with(['roles:id,name', 'member:id,first_name,last_name'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['role_id'] ?? null, fn ($query, int $roleId) => $query->whereHas('roles', fn ($roles) => $roles->whereKey($roleId)))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'filters' => $filters,
            'roles' => Role::query()->where('guard_name', 'web')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): View
    {
        return view('users.create', [
            'roles' => $this->availableRoles($request->user()),
            'members' => Member::query()
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'membership_number', 'first_name', 'middle_name', 'last_name']),
        ]);
    }

    public function store(StoreManagedUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $roles = collect();

        if (! empty($validated['role_ids'])) {
            abort_unless($request->user()->can('assign roles'), 403);
            $roles = Role::query()
                ->where('guard_name', 'web')
                ->whereIn('id', $validated['role_ids'])
                ->with('permissions')
                ->get();
            $this->authorizeRoleAssignment($request->user(), $roles);
        }

        $user = DB::transaction(function () use ($validated, $roles): User {
            $user = User::query()->create([
                ...Arr::only($validated, ['name', 'email', 'phone', 'member_id', 'status']),
                'password' => Str::random(64),
            ]);

            if ($roles->isNotEmpty()) {
                $user->syncRoles($roles);
            }

            return $user;
        });

        event(new Registered($user));
        $resetStatus = Password::sendResetLink(['email' => $user->email]);

        if ($resetStatus !== Password::RESET_LINK_SENT) {
            return redirect()->route('users.show', $user)
                ->withErrors(['invitation' => __('users.invitation_not_sent')]);
        }

        return redirect()->route('users.show', $user)
            ->with('success', __('users.created_invitation_sent'));
    }

    public function show(User $managedUser): View
    {
        $managedUser->load(['roles.permissions', 'member', 'permissions']);

        return view('users.show', [
            'managedUser' => $managedUser,
            'permissions' => Permission::query()
                ->where('guard_name', 'web')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function edit(Request $request, User $managedUser): View
    {
        $managedUser->load(['roles', 'member']);

        return view('users.edit', [
            'managedUser' => $managedUser,
            'roles' => $this->availableRoles($request->user()),
            'members' => Member::query()
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'membership_number', 'first_name', 'middle_name', 'last_name']),
        ]);
    }

    public function update(UpdateManagedUserRequest $request, User $managedUser): RedirectResponse
    {
        $validated = $request->validated();
        $roles = null;

        if ($request->exists('role_ids')) {
            abort_unless($request->user()->can('assign roles'), 403);
            $roles = Role::query()
                ->where('guard_name', 'web')
                ->whereIn('id', $validated['role_ids'] ?? [])
                ->with('permissions')
                ->get();
            $this->authorizeRoleAssignment($request->user(), $roles);
            $this->protectLastActiveSuperAdmin($managedUser, 'active', $roles);
        }

        $this->protectLastActiveSuperAdmin(
            $managedUser,
            $managedUser->status,
            $roles,
        );

        DB::transaction(function () use ($validated, $managedUser, $roles): void {
            $managedUser->fill(Arr::only($validated, ['name', 'email', 'phone', 'member_id']))->save();

            if ($roles !== null) {
                $managedUser->syncRoles($roles);
            }
        });

        return redirect()->route('users.show', $managedUser)
            ->with('success', __('users.updated'));
    }

    public function updateStatus(Request $request, User $managedUser): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:active,inactive,suspended'],
        ]);

        $this->protectLastActiveSuperAdmin($managedUser, $validated['status'], null);

        $managedUser->forceFill(['status' => $validated['status']])->save();

        return redirect()->route('users.show', $managedUser)
            ->with('success', __('users.status_updated'));
    }

    public function sendPasswordReset(User $managedUser): RedirectResponse
    {
        $status = Password::sendResetLink(['email' => $managedUser->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->withErrors(['password_reset' => __('users.password_reset_not_sent')]);
        }

        return back()->with('success', __('users.password_reset_sent'));
    }

    public function updatePermissions(
        UpdateManagedUserPermissionsRequest $request,
        User $managedUser,
    ): RedirectResponse {
        $permissionIds = array_map('intval', (array) $request->validated('permission_ids', []));
        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('id', $permissionIds)
            ->get();

        $managedUser->syncPermissions($permissions);

        return redirect()->route('users.show', $managedUser)
            ->with('success', __('users.permissions_updated'));
    }

    /**
     * @return Collection<int, Role>
     */
    private function availableRoles(User $actor): Collection
    {
        if (! $actor->can('assign roles')) {
            return collect();
        }

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->with('permissions')
            ->orderBy('name')
            ->get();

        if ($actor->hasRole(User::SUPER_ADMIN_ROLE)) {
            return $roles;
        }

        $allowedPermissions = $actor->getAllPermissions()->pluck('name');

        return $roles
            ->reject(fn (Role $role): bool => $role->name === User::SUPER_ADMIN_ROLE)
            ->filter(fn (Role $role): bool => $role->permissions->pluck('name')->diff($allowedPermissions)->isEmpty())
            ->values();
    }

    /**
     * @param  Collection<int, Role>  $roles
     */
    private function authorizeRoleAssignment(User $actor, Collection $roles): void
    {
        if ($actor->hasRole(User::SUPER_ADMIN_ROLE)) {
            return;
        }

        if ($roles->contains(fn (Role $role): bool => $role->name === User::SUPER_ADMIN_ROLE)) {
            throw ValidationException::withMessages([
                'role_ids' => __('users.super_admin_assignment_denied'),
            ]);
        }

        $requestedPermissions = $roles->flatMap(
            fn (Role $role) => $role->permissions->pluck('name'),
        )->unique();

        if ($requestedPermissions->diff($actor->getAllPermissions()->pluck('name'))->isNotEmpty()) {
            throw ValidationException::withMessages([
                'role_ids' => __('users.permission_escalation_denied'),
            ]);
        }
    }

    /**
     * @param  Collection<int, Role>|null  $roles
     */
    private function protectLastActiveSuperAdmin(User $target, string $nextStatus, ?Collection $roles): void
    {
        $willRemainSuperAdmin = $roles === null
            ? $target->hasRole(User::SUPER_ADMIN_ROLE)
            : $roles->contains(fn (Role $role): bool => $role->name === User::SUPER_ADMIN_ROLE);

        if ($target->status !== 'active'
            || ! $target->hasRole(User::SUPER_ADMIN_ROLE)
            || $nextStatus === 'active' && $willRemainSuperAdmin) {
            return;
        }

        $activeSuperAdmins = User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('name', User::SUPER_ADMIN_ROLE))
            ->count();

        if ($activeSuperAdmins <= 1) {
            throw ValidationException::withMessages([
                'status' => __('users.last_active_super_admin'),
            ]);
        }
    }
}
