<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\MemberAuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MemberLifecycleController extends Controller
{
    private const MAX_BULK_MEMBERS = 500;

    public function destroy(Request $request, Member $member): RedirectResponse
    {
        DB::transaction(function () use ($request, $member): void {
            $lockedMember = Member::query()
                ->whereKey($member->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedMember->delete();
            $this->recordAudit($lockedMember, 'deleted', $request);
        });

        return redirect()
            ->route('members.index')
            ->with('success', __('members.member_deleted'));
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $memberIds = $this->validatedMemberIds($request, includeTrashed: false);

        $deletedCount = DB::transaction(function () use ($memberIds, $request): int {
            $members = Member::query()
                ->whereKey($memberIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $this->ensureSelectedMembersAreAvailable($members->count(), count($memberIds));

            foreach ($members as $member) {
                $member->delete();
                $this->recordAudit($member, 'deleted', $request);
            }

            return $members->count();
        });

        return redirect()
            ->route('members.index')
            ->with('success', __('members.members_deleted', ['count' => $deletedCount]));
    }

    public function deleted(Request $request): View
    {
        abort_unless(
            $request->user()->can('delete members') || $request->user()->can('restore members'),
            403,
        );

        $members = Member::onlyTrashed()
            ->orderByDesc('deleted_at')
            ->orderByDesc('id')
            ->paginate(15);

        return view('members.deleted', [
            'members' => $members,
            'memberIds' => array_map('strval', $members->getCollection()->modelKeys()),
        ]);
    }

    public function restore(Request $request, Member $member): RedirectResponse
    {
        abort_unless($member->trashed(), 404);

        DB::transaction(function () use ($request, $member): void {
            $deletedMember = Member::onlyTrashed()
                ->whereKey($member->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $deletedMember->restore();
            $this->recordAudit($deletedMember, 'restored', $request);
        });

        return redirect()
            ->route('members.deleted.index')
            ->with('success', __('members.member_restored'));
    }

    public function bulkRestore(Request $request): RedirectResponse
    {
        $memberIds = $this->validatedMemberIds($request, includeTrashed: true);

        $restoredCount = DB::transaction(function () use ($memberIds, $request): int {
            $members = Member::onlyTrashed()
                ->whereKey($memberIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $this->ensureSelectedMembersAreAvailable($members->count(), count($memberIds));

            foreach ($members as $member) {
                $member->restore();
                $this->recordAudit($member, 'restored', $request);
            }

            return $members->count();
        });

        return redirect()
            ->route('members.deleted.index')
            ->with('success', __('members.members_restored', ['count' => $restoredCount]));
    }

    /**
     * @return list<int|string>
     */
    private function validatedMemberIds(Request $request, bool $includeTrashed): array
    {
        $existenceRule = Rule::exists('members', 'id');
        $existenceRule = $includeTrashed
            ? $existenceRule->whereNotNull('deleted_at')
            : $existenceRule->whereNull('deleted_at');

        return $request->validate([
            'member_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_BULK_MEMBERS],
            'member_ids.*' => ['required', 'integer', 'distinct', $existenceRule],
        ], [
            'member_ids.required' => __('members.select_members_required'),
            'member_ids.array' => __('members.invalid_member_selection'),
            'member_ids.min' => __('members.select_members_required'),
            'member_ids.max' => __('members.too_many_selected'),
            'member_ids.*.integer' => __('members.invalid_member_selection'),
            'member_ids.*.distinct' => __('members.duplicate_member_selection'),
            'member_ids.*.exists' => __('members.invalid_member_selection'),
        ])['member_ids'];
    }

    private function ensureSelectedMembersAreAvailable(int $foundCount, int $selectedCount): void
    {
        if ($foundCount !== $selectedCount) {
            throw ValidationException::withMessages([
                'member_ids' => __('members.selection_changed'),
            ]);
        }
    }

    private function recordAudit(Member $member, string $action, Request $request): void
    {
        MemberAuditLog::query()->create([
            'member_id' => $member->getKey(),
            'membership_number' => $member->membership_number,
            'member_name' => collect([
                $member->first_name,
                $member->middle_name,
                $member->last_name,
            ])->filter()->implode(' '),
            'action' => $action,
            'user_id' => $request->user()->getAuthIdentifier(),
            'actor_name' => $request->user()->name,
        ]);
    }
}
