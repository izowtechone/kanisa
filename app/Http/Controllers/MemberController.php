<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Models\Community;
use App\Models\Member;
use App\Models\Setting;
use App\Services\MembershipNumberGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->validatedFilters($request);
        $members = $this->filteredMembers($filters)
            ->with('community:id,name,code')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('members.index', [
            'members' => $members,
            'memberIds' => array_map('strval', $members->getCollection()->modelKeys()),
            'totalMembers' => Member::query()->count(),
            'activeMembers' => Member::query()->where('status', 'active')->count(),
            'inactiveMembers' => Member::query()->where('status', 'inactive')->count(),
            'communities' => Community::query()->orderBy('name')->get(['id', 'name', 'code']),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request, MembershipNumberGenerator $membershipNumbers): View
    {
        return view('members.create', [
            'member' => null,
            'membershipNumberPreview' => $membershipNumbers->preview(),
            'selectedCommunity' => $this->selectedCommunity($request),
            'systemLanguage' => Setting::getValue('system_language', config('app.locale', 'sw')),
        ]);
    }

    public function store(
        StoreMemberRequest $request,
        MembershipNumberGenerator $membershipNumbers,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            DB::transaction(function () use ($validated, $membershipNumbers): void {
                $this->lockCommunityForAssignment($validated['community_id'] ?? null);

                Member::query()->create([
                    ...$validated,
                    'membership_number' => $membershipNumbers->generate(),
                ]);
            });
        } catch (QueryException $exception) {
            if (! $this->isEnvelopeNumberConflict($exception)) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'envelope_number' => __('members.envelope_number_taken'),
            ]);
        }

        return redirect()
            ->route('members.index')
            ->with('success', __('members.success_created'));
    }

    public function edit(
        Request $request,
        Member $member,
        MembershipNumberGenerator $membershipNumbers,
    ): View {
        $member->load('community:id,name,code,status');
        $oldInput = $request->session()->getOldInput();

        return view('members.create', [
            'member' => $member,
            'membershipNumberPreview' => $membershipNumbers->preview(),
            'selectedCommunity' => array_key_exists('community_id', $oldInput)
                ? $this->selectedCommunity($request)
                : $member->community,
            'systemLanguage' => Setting::getValue('system_language', config('app.locale', 'sw')),
        ]);
    }

    public function update(UpdateMemberRequest $request, Member $member): RedirectResponse
    {
        try {
            DB::transaction(function () use ($member, $request): void {
                $lockedMember = Member::query()->lockForUpdate()->findOrFail($member->id);
                $communityId = $request->validated('community_id');

                $this->lockCommunityForAssignment(
                    $communityId,
                    $lockedMember->community_id,
                );

                $lockedMember->update($request->validated());
            });
        } catch (QueryException $exception) {
            if (! $this->isEnvelopeNumberConflict($exception)) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'envelope_number' => __('members.envelope_number_taken'),
            ]);
        }

        return redirect()
            ->route('members.index')
            ->with('success', __('members.success_updated'));
    }

    public function export(Request $request): StreamedResponse
    {
        $members = $this->filteredMembers($this->validatedFilters($request))
            ->with('community:id,name');
        $headers = [
            'membership_number',
            'first_name',
            'middle_name',
            'last_name',
            'community',
            'envelope_number',
            'gender',
            'phone',
            'email',
            'membership_date',
            'status',
        ];

        return response()->streamDownload(function () use ($members, $headers): void {
            $stream = fopen('php://output', 'wb');

            if ($stream === false) {
                throw new \RuntimeException('Unable to create the member export stream.');
            }

            fputcsv($stream, $headers, ',', '"', '');

            foreach ($members->lazyById(500) as $member) {
                $values = [
                    $member->membership_number,
                    $member->first_name,
                    $member->middle_name,
                    $member->last_name,
                    $member->community?->name,
                    $member->envelope_number,
                    $member->gender,
                    $member->phone,
                    $member->email,
                    $member->membership_date?->toDateString(),
                    $member->status,
                ];

                fputcsv(
                    $stream,
                    array_map(fn (?string $value): string => $this->safeCsvValue($value ?? ''), $values),
                    ',',
                    '"',
                    '',
                );
            }

            fclose($stream);
        }, 'members.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{search: string|null, gender: string|null, status: string|null, community_id: int|null}
     */
    private function validatedFilters(Request $request): array
    {
        $filters = array_merge([
            'search' => null,
            'gender' => null,
            'status' => null,
            'community_id' => null,
        ], $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', Rule::in(['Male', 'Female'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'community_id' => ['nullable', 'integer', Rule::exists('communities', 'id')->whereNull('deleted_at')],
        ]));

        $filters['community_id'] = $filters['community_id'] === null
            ? null
            : (int) $filters['community_id'];

        return $filters;
    }

    /**
     * @param  array{search: string|null, gender: string|null, status: string|null, community_id: int|null}  $filters
     */
    private function filteredMembers(array $filters): Builder
    {
        $query = Member::query();

        if ($filters['search'] !== null && trim($filters['search']) !== '') {
            $terms = preg_split('/\s+/', trim($filters['search'])) ?: [];

            foreach ($terms as $term) {
                $like = '%'.$term.'%';
                $query->where(function (Builder $query) use ($like): void {
                    $query->where('first_name', 'like', $like)
                        ->orWhere('middle_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('membership_number', 'like', $like)
                        ->orWhere('envelope_number', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhereHas('community', function (Builder $communityQuery) use ($like): void {
                            $communityQuery->where('name', 'like', $like)
                                ->orWhere('code', 'like', $like);
                        });
                });
            }
        }

        return $query
            ->when(
                $filters['gender'] !== null,
                fn (Builder $query): Builder => $query->where('gender', $filters['gender']),
            )
            ->when(
                $filters['status'] !== null,
                fn (Builder $query): Builder => $query->where('status', $filters['status']),
            )
            ->when(
                $filters['community_id'] !== null,
                fn (Builder $query): Builder => $query->where('community_id', $filters['community_id']),
            );
    }

    private function selectedCommunity(Request $request): ?Community
    {
        if (! $request->user()?->can('assign members to communities')) {
            return null;
        }

        $communityId = (int) $request->old('community_id', $request->input('community_id', 0));

        return $communityId > 0
            ? Community::query()->find($communityId)
            : null;
    }

    private function lockCommunityForAssignment(?int $communityId, ?int $existingCommunityId = null): void
    {
        if ($communityId === null) {
            return;
        }

        $community = Community::query()
            ->lockForUpdate()
            ->find($communityId);

        if ($community === null
            || ($community->status !== 'active' && $community->id !== $existingCommunityId)) {
            throw ValidationException::withMessages([
                'community_id' => __('communities.inactive_assignment'),
            ]);
        }
    }

    private function safeCsvValue(string $value): string
    {
        if (preg_match('/^\s*[=+\-@]/u', $value)) {
            return "'".$value;
        }

        return $value;
    }

    private function isEnvelopeNumberConflict(QueryException $exception): bool
    {
        $driver = DB::connection()->getDriverName();
        $sqlState = (string) $exception->getCode();
        $errorCode = (int) ($exception->errorInfo[1] ?? 0);
        $message = mb_strtolower($exception->getMessage());

        return match ($driver) {
            'mysql' => $sqlState === '23000'
                && $errorCode === 1062
                && str_contains($message, 'envelope_number'),
            'pgsql' => $sqlState === '23505' && str_contains($message, 'envelope_number'),
            'sqlite' => in_array($errorCode, [19, 1555, 2067], true)
                && str_contains($message, 'members.envelope_number'),
            default => false,
        };
    }
}
