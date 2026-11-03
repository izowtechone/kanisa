<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReverseFinancialTransactionRequest;
use App\Http\Requests\StoreFinancialTransactionRequest;
use App\Http\Requests\VoidFinancialTransactionRequest;
use App\Models\FinanceCategory;
use App\Models\FinancialCampaign;
use App\Models\FinancialTransaction;
use App\Models\Member;
use App\Services\FinancialTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FinancialTransactionController extends Controller
{
    public function __construct(
        private readonly FinancialTransactionService $transactions,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::in(FinancialTransaction::TYPES)],
            'status' => ['nullable', Rule::in(FinancialTransaction::STATUSES)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $records = FinancialTransaction::query()
            ->with([
                'category:id,name,name_en,name_sw',
                'campaign:id,name',
                'member:id,first_name,middle_name,last_name,membership_number',
            ])
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('transaction_number', 'like', '%'.$search.'%')
                        ->orWhere('receipt_number', 'like', '%'.$search.'%')
                        ->orWhere('envelope_code', 'like', '%'.$search.'%')
                        ->orWhere('member_number', 'like', '%'.$search.'%')
                        ->orWhere('member_name', 'like', '%'.$search.'%')
                        ->orWhereHas('member', function ($memberQuery) use ($search): void {
                            $memberQuery->where(function ($nameQuery) use ($search): void {
                                $nameQuery->where('first_name', 'like', '%'.$search.'%')
                                    ->orWhere('middle_name', 'like', '%'.$search.'%')
                                    ->orWhere('last_name', 'like', '%'.$search.'%')
                                    ->orWhere('membership_number', 'like', '%'.$search.'%');
                            });
                        });
                });
            })
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('finance.transactions.index', [
            'transactions' => $records,
            'filters' => $filters,
            'types' => FinancialTransaction::TYPES,
            'statuses' => FinancialTransaction::STATUSES,
        ]);
    }

    public function create(string $type): View
    {
        $permission = $this->permissionForType($type);
        abort_unless(Auth::user()?->can($permission), 403);

        return view('finance.transactions.form', [
            'type' => $type,
            'idempotencyKey' => (string) Str::uuid(),
            'categories' => FinanceCategory::query()
                ->where('type', $type)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'name_en', 'name_sw']),
            'campaigns' => $type === 'contribution'
                ? FinancialCampaign::query()->where('status', 'active')->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }

    public function store(StoreFinancialTransactionRequest $request, string $type): RedirectResponse
    {
        $attributes = Arr::except($request->validated(), ['member_number']);
        $attributes['type'] = $type;

        if ($request->filled('member_number')) {
            $attributes['member_id'] = Member::query()
                ->where('membership_number', trim((string) $request->input('member_number')))
                ->where('status', 'active')
                ->value('id');
        }

        $transaction = $this->transactions->create($attributes, $request->user());

        return redirect()
            ->route('finance.transactions.show', $transaction)
            ->with('success', __('finance.transaction_created_pending'));
    }

    public function show(FinancialTransaction $transaction): View
    {
        return view('finance.transactions.show', [
            'transaction' => $transaction->load([
                'category:id,name,name_en,name_sw',
                'campaign:id,name',
                'member:id,first_name,middle_name,last_name,membership_number',
                'creator:id,name',
                'approver:id,name',
                'reverser:id,name',
                'reversalOf:id,transaction_number',
                'auditEvents.user:id,name',
                'smsLog',
            ]),
        ]);
    }

    public function approve(FinancialTransaction $transaction): RedirectResponse
    {
        $this->transactions->approve($transaction, Auth::user());

        return back()->with('success', __('finance.transaction_approved'));
    }

    public function void(VoidFinancialTransactionRequest $request, FinancialTransaction $transaction): RedirectResponse
    {
        $this->transactions->void($transaction, Auth::user(), $request->validated('reason'));

        return back()->with('success', __('finance.transaction_voided'));
    }

    public function reverse(ReverseFinancialTransactionRequest $request, FinancialTransaction $transaction): RedirectResponse
    {
        $reversal = $this->transactions->reverse($transaction, Auth::user(), $request->validated('reason'));

        return redirect()
            ->route('finance.transactions.show', $reversal)
            ->with('success', __('finance.transaction_reversed'));
    }

    public function envelopeCreate(): View
    {
        abort_unless(
            Auth::user()?->can('record envelope offerings') || Auth::user()?->can('manage offerings'),
            403,
        );

        return view('finance.envelopes.create', [
            'idempotencyKey' => (string) Str::uuid(),
            'categories' => FinanceCategory::query()
                ->whereIn('type', ['offering', 'tithe'])
                ->where('is_active', true)
                ->orderBy('type')
                ->orderBy('name')
                ->get(['id', 'type', 'name', 'name_en', 'name_sw']),
        ]);
    }

    public function lookupEnvelope(Request $request): JsonResponse
    {
        abort_unless(
            Auth::user()?->can('record envelope offerings') || Auth::user()?->can('manage offerings'),
            403,
        );

        $validated = $request->validate([
            'envelope_code' => ['required', 'string', 'max:255'],
        ]);
        $member = Member::query()
            ->where('envelope_number', trim($validated['envelope_code']))
            ->where('status', 'active')
            ->first(['id', 'membership_number', 'first_name', 'middle_name', 'last_name']);

        abort_if($member === null, 404, __('finance.envelope_member_not_found'));

        return response()->json([
            'membership_number' => $member->membership_number,
            'name' => trim(implode(' ', array_filter([
                $member->first_name,
                $member->middle_name,
                $member->last_name,
            ]))),
        ]);
    }

    public function storeEnvelope(StoreFinancialTransactionRequest $request): RedirectResponse
    {
        $attributes = $request->validated();
        $attributes['member_id'] = Member::query()
            ->where('envelope_number', trim($attributes['envelope_code']))
            ->where('status', 'active')
            ->value('id');

        $transaction = $this->transactions->create($attributes, $request->user());

        return redirect()
            ->route('finance.transactions.show', $transaction)
            ->with('success', __('finance.transaction_created_pending'));
    }

    private function permissionForType(string $type): string
    {
        return match ($type) {
            'offering' => 'manage offerings',
            'tithe' => 'manage tithes',
            'contribution' => 'manage contributions',
            'other_income' => 'manage other income',
            'expense' => 'manage expenses',
            default => abort(404),
        };
    }
}
