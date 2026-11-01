<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignGroupMembersRequest;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use App\Models\Department;
use App\Models\Group;
use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $groups = Group::query()
            ->with([
                'department:id,code,name',
                'leader:id,membership_number,first_name,middle_name,last_name',
            ])
            ->withCount('members')
            ->when(
                filled($filters['search'] ?? null),
                function (Builder $query) use ($filters): void {
                    $search = '%'.trim($filters['search']).'%';
                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('groups.name', 'like', $search)
                            ->orWhere('groups.code', 'like', $search)
                            ->orWhereHas('department', function (Builder $departmentQuery) use ($search): void {
                                $departmentQuery->where('name', 'like', $search)
                                    ->orWhere('code', 'like', $search);
                            });
                    });
                },
            )
            ->when(
                isset($filters['department_id']),
                fn (Builder $query): Builder => $query->where('department_id', $filters['department_id']),
            )
            ->when(
                isset($filters['status']),
                fn (Builder $query): Builder => $query->where('is_active', $filters['status'] === 'active'),
            )
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('groups.index', [
            'groups' => $groups,
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'filters' => array_merge([
                'search' => '',
                'department_id' => '',
                'status' => '',
            ], $filters),
        ]);
    }

    public function create(): View
    {
        return view('groups.form', [
            'group' => null,
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'leaders' => $this->availableLeaders(),
        ]);
    }

    public function store(StoreGroupRequest $request): RedirectResponse
    {
        $group = Group::query()->create($request->validated());

        return redirect()
            ->route('groups.show', $group)
            ->with('success', __('groups.created'));
    }

    public function show(Group $group): View
    {
        $group->load([
            'department:id,code,name',
            'leader:id,membership_number,first_name,middle_name,last_name',
        ])->loadCount('members');

        return view('groups.show', compact('group'));
    }

    public function edit(Group $group): View
    {
        return view('groups.form', [
            'group' => $group,
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'leaders' => $this->availableLeaders(),
        ]);
    }

    public function update(UpdateGroupRequest $request, Group $group): RedirectResponse
    {
        $group->update($request->validated());

        return redirect()
            ->route('groups.show', $group)
            ->with('success', __('groups.updated'));
    }

    public function destroy(Group $group): RedirectResponse
    {
        $hasAssignments = DB::transaction(function () use ($group): bool {
            $lockedGroup = Group::query()->lockForUpdate()->findOrFail($group->id);
            $hasAssignments = DB::table('group_member')
                ->where('group_id', $lockedGroup->id)
                ->exists();

            if (! $hasAssignments) {
                $lockedGroup->delete();
            }

            return $hasAssignments;
        });

        if ($hasAssignments) {
            return back()->with('error', __('groups.delete_blocked'));
        }

        return redirect()
            ->route('groups.index')
            ->with('success', __('groups.deleted'));
    }

    public function members(Group $group): View
    {
        $members = $group->members()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate(20);

        $availableMembers = Member::query()
            ->select(['id', 'membership_number', 'first_name', 'middle_name', 'last_name', 'phone'])
            ->whereDoesntHave('groups', fn (Builder $query): Builder => $query->whereKey($group->id))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->get();

        return view('groups.members', compact('group', 'members', 'availableMembers'));
    }

    public function assignMembers(AssignGroupMembersRequest $request, Group $group): RedirectResponse
    {
        $now = now();
        $assignments = collect($request->validated('member_ids'))
            ->map(fn (int $memberId): array => [
                'group_id' => $group->id,
                'member_id' => $memberId,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        DB::transaction(function () use ($assignments): void {
            DB::table('group_member')->upsert(
                $assignments,
                ['group_id', 'member_id'],
                ['updated_at'],
            );
        });

        return back()->with('success', __('groups.members_assigned'));
    }

    public function removeMember(Group $group, Member $member): RedirectResponse
    {
        $group->members()->detach($member);

        return back()->with('success', __('groups.member_removed'));
    }

    /**
     * @return Collection<int, Member>
     */
    private function availableLeaders(): Collection
    {
        return Member::query()
            ->select(['id', 'membership_number', 'first_name', 'middle_name', 'last_name'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->get();
    }
}
