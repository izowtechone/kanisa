<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Models\Member;
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
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('members.index', [
            'members' => $members,
            'memberIds' => array_map('strval', $members->getCollection()->modelKeys()),
            'totalMembers' => Member::query()->count(),
            'activeMembers' => Member::query()->where('status', 'active')->count(),
            'inactiveMembers' => Member::query()->where('status', 'inactive')->count(),
            'filters' => $filters,
        ]);
    }

    public function create(MembershipNumberGenerator $membershipNumbers): View
    {
        return view('members.create', [
            'member' => null,
            'membershipNumberPreview' => $membershipNumbers->preview(),
        ]);
    }

    public function store(
        StoreMemberRequest $request,
        MembershipNumberGenerator $membershipNumbers,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            DB::transaction(function () use ($validated, $membershipNumbers): void {
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

    public function edit(Member $member, MembershipNumberGenerator $membershipNumbers): View
    {
        return view('members.create', [
            'member' => $member,
            'membershipNumberPreview' => $membershipNumbers->preview(),
        ]);
    }

    public function update(UpdateMemberRequest $request, Member $member): RedirectResponse
    {
        try {
            $member->update($request->validated());
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
        $members = $this->filteredMembers($this->validatedFilters($request));
        $headers = [
            'membership_number',
            'first_name',
            'middle_name',
            'last_name',
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
     * @return array{search: string|null, gender: string|null, status: string|null}
     */
    private function validatedFilters(Request $request): array
    {
        return array_merge([
            'search' => null,
            'gender' => null,
            'status' => null,
        ], $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', Rule::in(['Male', 'Female'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]));
    }

    /**
     * @param  array{search: string|null, gender: string|null, status: string|null}  $filters
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
                        ->orWhere('envelope_number', 'like', $like);
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
            );
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
