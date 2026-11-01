<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\Department;
use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $departments = Department::query()
            ->with(['leader:id,membership_number,first_name,middle_name,last_name'])
            ->withCount('groups')
            ->when(
                filled($filters['search'] ?? null),
                function (Builder $query) use ($filters): void {
                    $search = '%'.trim($filters['search']).'%';
                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('name', 'like', $search)
                            ->orWhere('code', 'like', $search);
                    });
                },
            )
            ->when(
                isset($filters['status']),
                fn (Builder $query): Builder => $query->where('is_active', $filters['status'] === 'active'),
            )
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('departments.index', [
            'departments' => $departments,
            'filters' => array_merge(['search' => '', 'status' => ''], $filters),
        ]);
    }

    public function create(): View
    {
        return view('departments.form', [
            'department' => null,
            'leaders' => $this->availableLeaders(),
        ]);
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $department = Department::query()->create($request->validated());

        return redirect()
            ->route('departments.show', $department)
            ->with('success', __('departments.created'));
    }

    public function show(Department $department): View
    {
        $department->load('leader:id,membership_number,first_name,middle_name,last_name');
        $groups = $department->groups()
            ->with(['leader:id,membership_number,first_name,middle_name,last_name'])
            ->withCount('members')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        return view('departments.show', compact('department', 'groups'));
    }

    public function edit(Department $department): View
    {
        return view('departments.form', [
            'department' => $department,
            'leaders' => $this->availableLeaders(),
        ]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update($request->validated());

        return redirect()
            ->route('departments.show', $department)
            ->with('success', __('departments.updated'));
    }

    public function destroy(Department $department): RedirectResponse
    {
        $hasGroups = DB::transaction(function () use ($department): bool {
            $lockedDepartment = Department::query()->lockForUpdate()->findOrFail($department->id);
            $hasGroups = $lockedDepartment->groups()->exists();

            if (! $hasGroups) {
                $lockedDepartment->delete();
            }

            return $hasGroups;
        });

        if ($hasGroups) {
            return back()->with('error', __('departments.delete_blocked'));
        }

        return redirect()
            ->route('departments.index')
            ->with('success', __('departments.deleted'));
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
