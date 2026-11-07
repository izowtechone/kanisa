<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreManagedUserRequest;
use App\Http\Requests\UpdateManagedUserPermissionsRequest;
use App\Http\Requests\UpdateManagedUserRequest;
use App\Models\LoginSecurityEvent;
use App\Models\Member;
use App\Models\User;
use App\Services\UserInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    public function __construct(private UserInvitationService $invitations) {}

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
        $users->getCollection()->each(fn (User $user) => $this->localizeRoles($user->roles));

        return view('users.index', [
            'users' => $users,
            'filters' => $filters,
            'roles' => $this->localizeRoles(
                Role::query()->where('guard_name', 'web')->orderBy('name')->get(['id', 'name']),
            ),
        ]);
    }

    public function create(Request $request): View
    {
        return view('users.create', [
            'roles' => $this->localizeRoles(
                $this->availableRoles($request->user(), excludeAdministrativeRoles: true),
            ),
            'members' => Member::query()
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'membership_number', 'first_name', 'middle_name', 'last_name']),
        ]);
    }

    public function store(StoreManagedUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $this->invitations->ensureReady();
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->whereIn('id', $validated['role_ids'])
            ->with('permissions')
            ->get();
        $this->authorizeRoleAssignment($request->user(), $roles);

        $user = DB::transaction(function () use ($validated, $roles): User {
            $user = User::query()->create([
                ...Arr::only($validated, ['name', 'email', 'phone', 'member_id', 'status']),
                'password' => Str::random(64),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $user->syncRoles($roles);

            return $user;
        });

        $invitation = $this->invitations->send($user, $request->user());

        return redirect()->route('users.show', $user)
            ->with('success', __('users.created'))
            ->with('invitation_status', $invitation->status);
    }

    public function show(User $managedUser): View
    {
        $trackingAvailable = User::supportsInvitationTracking();
        $managedUser->load(['roles.permissions', 'member', 'permissions']);

        if ($trackingAvailable) {
            $managedUser->load('passwordResetInitiator');
        }

        $this->localizeRoles($managedUser->roles);

        return view('users.show', [
            'managedUser' => $managedUser,
            'invitationTrackingAvailable' => $trackingAvailable,
            'invitationLog' => $trackingAvailable
                ? $managedUser->smsLogs()
                    ->where('notification_type', 'user_invitation')
                    ->orderByDesc('id')
                    ->first()
                : null,
            'permissions' => Permission::query()
                ->where('guard_name', 'web')
                ->whereNotIn('name', User::USER_ADMIN_PERMISSIONS)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function edit(Request $request, User $managedUser): View
    {
        $managedUser->load(['roles', 'member']);
        $this->localizeRoles($managedUser->roles);

        return view('users.edit', [
            'managedUser' => $managedUser,
            'roles' => $this->localizeRoles($this->availableRoles($request->user())),
            'members' => Member::query()
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'membership_number', 'first_name', 'middle_name', 'last_name']),
        ]);
    }

    public function update(UpdateManagedUserRequest $request, User $managedUser): RedirectResponse
    {
        $validated = $request->validated();
        $previousPhoneVerifiedAt = $managedUser->phone_verified_at;
        $previousPhone = $managedUser->phone;
        $roles = null;

        if ($request->exists('role_ids')) {
            $roles = Role::query()
                ->where('guard_name', 'web')
                ->whereIn('id', $validated['role_ids'] ?? [])
                ->with('permissions')
                ->get();
            $this->authorizeRoleAssignment($request->user(), $roles);
        }

        DB::transaction(function () use ($validated, $managedUser, $roles): void {
            $managedUser = User::query()->lockForUpdate()->findOrFail($managedUser->id);
            $this->protectLastActiveSuperAdmin($managedUser, $managedUser->status, $roles);
            $phoneChanged = ($validated['phone'] ?? null) !== $managedUser->phone;
            $managedUser->fill(Arr::only($validated, ['name', 'email', 'phone', 'member_id']));
            $managedUser->forceFill([
                'phone_verified_at' => $phoneChanged || ! ($validated['phone_verified'] ?? false)
                    || blank($validated['phone'] ?? null)
                    ? null
                    : ($managedUser->phone_verified_at ?? now()),
            ])->save();

            if ($roles !== null) {
                $managedUser->syncRoles($roles);
            }
        });

        $managedUser->refresh();

        if ($previousPhone !== $managedUser->phone
            || ($previousPhoneVerifiedAt !== null) !== ($managedUser->phone_verified_at !== null)) {
            LoginSecurityEvent::record('phone_verification_changed', $managedUser, $request, [
                'actor_id' => $request->user()->id,
                'verified' => $managedUser->phone_verified_at !== null,
                'phone_changed' => $previousPhone !== $managedUser->phone,
            ]);
        }

        return redirect()->route('users.show', $managedUser)
            ->with('success', __('users.updated'));
    }

    public function updateStatus(Request $request, User $managedUser): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:active,inactive,suspended'],
        ]);

        DB::transaction(function () use ($managedUser, $validated): void {
            $managedUser = User::query()->lockForUpdate()->findOrFail($managedUser->id);
            $this->protectLastActiveSuperAdmin($managedUser, $validated['status'], null);
            $managedUser->forceFill(['status' => $validated['status']])->save();
        });

        return redirect()->route('users.show', $managedUser)
            ->with('success', __('users.status_updated'));
    }

    public function sendPasswordReset(Request $request, User $managedUser): RedirectResponse
    {
        abort_if($managedUser->is($request->user()), 403);

        $log = $this->invitations->send($managedUser, $request->user());

        return redirect()->route('users.show', $managedUser)
            ->with('success', __('users.password_reset_requested'))
            ->with('invitation_status', $log->status);
    }

    public function retryInvitation(Request $request, User $managedUser): RedirectResponse
    {
        abort_if($managedUser->is($request->user()), 403);
        $this->invitations->ensureReady();

        $latestLog = $managedUser->smsLogs()
            ->where('notification_type', 'user_invitation')
            ->orderByDesc('id')
            ->first();

        abort_unless($latestLog !== null
            && in_array($latestLog->status, ['failed', 'not_configured', 'no_phone'], true), 409);

        $log = $this->invitations->send($managedUser, $request->user());

        return redirect()->route('users.show', $managedUser)
            ->with('success', __('users.invitation_retry_requested'))
            ->with('invitation_status', $log->status);
    }

    public function destroy(Request $request, User $managedUser): RedirectResponse
    {
        abort_if($managedUser->is($request->user()), 403);

        DB::transaction(function () use ($managedUser): void {
            $managedUser = User::query()->lockForUpdate()->findOrFail($managedUser->id);
            $this->protectLastActiveSuperAdmin($managedUser, 'inactive', null);
            $managedUser->delete();
        });

        return redirect()->route('users.index')->with('success', __('users.deleted'));
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
    private function availableRoles(User $actor, bool $excludeAdministrativeRoles = false): Collection
    {
        if (! $actor->isSuperAdmin()) {
            return collect();
        }

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->with('permissions')
            ->orderBy('name')
            ->get();

        if ($excludeAdministrativeRoles) {
            return $roles
                ->reject(fn (Role $role): bool => $role->name === User::SUPER_ADMIN_ROLE
                    || $role->permissions->pluck('name')->intersect(User::USER_ADMIN_PERMISSIONS)->isNotEmpty())
                ->values();
        }

        return $roles;
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
     * @param  Collection<int, Role>  $roles
     * @return Collection<int, Role>
     */
    private function localizeRoles(Collection $roles): Collection
    {
        return $roles->each(function (Role $role): void {
            $translationKey = 'users.role_names.'.$role->name;
            $role->setAttribute('display_name', Lang::has($translationKey) ? __($translationKey) : $role->name);
        });
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
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id'])
            ->count();

        if ($activeSuperAdmins <= 1) {
            throw ValidationException::withMessages([
                'status' => __('users.last_active_super_admin'),
            ]);
        }
    }
}
