<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommunityRequest;
use App\Http\Requests\UpdateCommunityRequest;
use App\Models\Community;
use App\Models\Member;
use App\Services\CommunityCodeGenerator;
use App\Services\MemberSearchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CommunityController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $communities = Community::query()
            ->with(['leader:id,membership_number,first_name,middle_name,last_name'])
            ->withCount('members')
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
                fn (Builder $query): Builder => $query->where('status', $filters['status']),
            )
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('communities.index', [
            'communities' => $communities,
            'filters' => array_merge(['search' => '', 'status' => ''], $filters),
        ]);
    }

    public function create(Request $request): View
    {
        return view('communities.form', [
            'community' => null,
            'leader' => $this->selectedLeader($request),
        ]);
    }

    public function store(StoreCommunityRequest $request, CommunityCodeGenerator $codes): RedirectResponse
    {
        $attributes = $request->validated();
        $community = DB::transaction(function () use ($attributes, $codes): Community {
            $this->lockActiveLeader($attributes['leader_member_id'] ?? null);

            return Community::query()->create([
                ...$attributes,
                'code' => $codes->next(),
                'status' => 'active',
            ]);
        });

        return redirect()
            ->route('communities.show', $community)
            ->with('success', __('communities.created'));
    }

    public function show(Community $community): View
    {
        $community->load('leader:id,membership_number,first_name,middle_name,last_name');
        $members = $community->members()
            ->select(['id', 'community_id', 'membership_number', 'first_name', 'middle_name', 'last_name', 'phone', 'status'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('communities.show', compact('community', 'members'));
    }

    public function edit(Request $request, Community $community): View
    {
        $community->load('leader:id,membership_number,first_name,middle_name,last_name');

        return view('communities.form', [
            'community' => $community,
            'leader' => $this->selectedLeader($request, $community->leader),
        ]);
    }

    public function update(UpdateCommunityRequest $request, Community $community): RedirectResponse
    {
        $attributes = $request->validated();
        DB::transaction(function () use ($attributes, $community): void {
            $this->lockActiveLeader($attributes['leader_member_id'] ?? null);
            Community::query()->lockForUpdate()->findOrFail($community->id)->update($attributes);
        });

        return redirect()
            ->route('communities.show', $community)
            ->with('success', __('communities.updated'));
    }

    public function toggle(Community $community): RedirectResponse
    {
        DB::transaction(function () use ($community): void {
            $lockedCommunity = Community::query()->lockForUpdate()->findOrFail($community->id);
            $lockedCommunity->update([
                'status' => $lockedCommunity->status === 'active' ? 'inactive' : 'active',
            ]);
        });

        return back()->with('success', __('communities.status_updated'));
    }

    public function destroy(Community $community): RedirectResponse
    {
        $hasMembers = DB::transaction(function () use ($community): bool {
            $lockedCommunity = Community::query()->lockForUpdate()->findOrFail($community->id);
            $hasMembers = Member::withTrashed()
                ->where('community_id', $lockedCommunity->id)
                ->lockForUpdate()
                ->exists();

            if (! $hasMembers) {
                $lockedCommunity->delete();
            }

            return $hasMembers;
        });

        if ($hasMembers) {
            return back()->with('error', __('communities.delete_blocked'));
        }

        return redirect()
            ->route('communities.index')
            ->with('success', __('communities.deleted'));
    }

    public function lookup(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('assign members to communities'), 403);

        $validated = $request->validate([
            'search' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $search = '%'.trim($validated['search']).'%';
        $communities = Community::active()
            ->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', $search)
                    ->orWhere('code', 'like', $search);
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'code', 'name']);

        return response()->json([
            'data' => $communities,
        ]);
    }

    public function searchLeaders(Request $request, MemberSearchService $members): JsonResponse
    {
        abort_unless(
            $request->user()?->can('create communities')
                || $request->user()?->can('edit communities'),
            403,
        );

        $validated = $request->validate([
            'search' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        return response()->json([
            'data' => $members->searchActive($validated['search'])->map(fn (Member $member): array => [
                'id' => $member->id,
                'name' => trim(implode(' ', array_filter([
                    $member->first_name,
                    $member->middle_name,
                    $member->last_name,
                ]))),
                'membership_number' => $member->membership_number,
            ]),
        ]);
    }

    private function lockActiveLeader(?int $memberId): void
    {
        if ($memberId === null) {
            return;
        }

        $leader = Member::query()
            ->whereKey($memberId)
            ->where('status', 'active')
            ->lockForUpdate()
            ->first();

        if ($leader === null) {
            throw ValidationException::withMessages([
                'leader_member_id' => __('communities.leader_not_active'),
            ]);
        }
    }

    private function selectedLeader(Request $request, ?Member $fallback = null): ?Member
    {
        $oldInput = $request->session()->getOldInput();

        if (! array_key_exists('leader_member_id', $oldInput)) {
            return $fallback;
        }

        $memberId = filter_var($oldInput['leader_member_id'], FILTER_VALIDATE_INT);

        return $memberId === false
            ? null
            : Member::query()->where('status', 'active')->find($memberId);
    }
}
