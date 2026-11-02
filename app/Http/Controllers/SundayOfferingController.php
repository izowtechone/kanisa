<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSundayOfferingRequest;
use App\Models\FinanceCategory;
use App\Models\FinancialSetting;
use App\Models\FinancialTransaction;
use App\Models\Member;
use App\Models\Setting;
use App\Services\FinancialTransactionService;
use App\Services\MemberSearchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SundayOfferingController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(FinancialTransaction::STATUSES)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $filters = array_merge([
            'search' => '',
            'status' => '',
            'from' => '',
            'to' => '',
        ], $validated);

        $transactions = FinancialTransaction::query()
            ->where('source', FinancialTransaction::SOURCE_SUNDAY_OFFERING)
            ->where('type', 'offering')
            ->with([
                'category:id,name',
                'member:id,membership_number,first_name,middle_name,last_name,community_id',
                'member.community:id,name,code',
                'smsLog:id,financial_transaction_id,status',
            ])
            ->when(
                filled($filters['search']),
                function (Builder $query) use ($filters): void {
                    $search = '%'.trim($filters['search']).'%';
                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('transaction_number', 'like', $search)
                            ->orWhere('receipt_number', 'like', $search)
                            ->orWhere('member_number', 'like', $search)
                            ->orWhere('member_name', 'like', $search)
                            ->orWhere('envelope_code', 'like', $search)
                            ->orWhereHas('member', function (Builder $memberQuery) use ($search): void {
                                $memberQuery->where('envelope_number', 'like', $search);
                            })
                            ->orWhere('reference_number', 'like', $search);
                    });
                },
            )
            ->when(
                filled($filters['status']),
                fn (Builder $query): Builder => $query->where('status', $filters['status']),
            )
            ->when(
                filled($filters['from']),
                fn (Builder $query): Builder => $query->whereDate('transaction_date', '>=', $filters['from']),
            )
            ->when(
                filled($filters['to']),
                fn (Builder $query): Builder => $query->whereDate('transaction_date', '<=', $filters['to']),
            )
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('finance.sunday-offerings.index', compact('transactions', 'filters'));
    }

    public function create(Request $request): View
    {
        $memberId = filter_var($request->session()->getOldInput('member_id'), FILTER_VALIDATE_INT);
        $selectedMember = $memberId === false
            ? null
            : Member::query()
                ->where('status', 'active')
                ->with('community:id,code,name')
                ->find($memberId);

        return view('finance.sunday-offerings.create', [
            'categories' => FinanceCategory::query()
                ->where('type', 'offering')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'idempotencyKey' => (string) Str::uuid(),
            'selectedMember' => $selectedMember,
            'currency' => FinancialSetting::getValue(
                'currency',
                Setting::getValue('currency', 'TZS'),
            ),
        ]);
    }

    public function searchMembers(Request $request, MemberSearchService $members): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        return response()->json([
            'data' => $members->searchActive($validated['search'], includeCommunity: true)
                ->map(fn (Member $member): array => [
                    'id' => $member->id,
                    'name' => trim(implode(' ', array_filter([
                        $member->first_name,
                        $member->middle_name,
                        $member->last_name,
                    ]))),
                    'membership_number' => $member->membership_number,
                    'envelope_number' => $member->envelope_number,
                    'phone' => $member->phone,
                    'community_name' => $member->community?->name,
                ]),
        ]);
    }

    public function store(
        StoreSundayOfferingRequest $request,
        FinancialTransactionService $transactions,
    ): RedirectResponse {
        $transaction = $transactions->create([
            ...$request->validated(),
            'type' => 'offering',
        ], $request->user(), 'sunday_offering');

        return redirect()
            ->route('finance.sunday-offerings.show', $transaction)
            ->with('success', __('finance.sunday_offering_approval_pending'));
    }

    public function show(Request $request, FinancialTransaction $transaction): View
    {
        abort_unless(
            $transaction->source === FinancialTransaction::SOURCE_SUNDAY_OFFERING
                && $request->user()?->can('view Sunday offerings'),
            404,
        );

        $transaction->load([
            'category:id,name',
            'member:id,first_name,middle_name,last_name,membership_number',
            'member.community:id,name,code',
            'creator:id,name',
            'approver:id,name',
            'smsLog',
        ]);

        return view('finance.sunday-offerings.show', compact('transaction'));
    }
}
