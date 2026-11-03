<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFinanceCategoryRequest;
use App\Models\FinanceCategory;
use App\Models\FinancialCampaign;
use App\Models\FinancialSetting;
use App\Models\FinancialTransaction;
use App\Models\Setting;
use App\Services\FinancialCsvSanitizer;
use App\Services\FinancialReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    public function __construct(
        private readonly FinancialReportService $reports,
        private readonly FinancialCsvSanitizer $csvSanitizer,
    ) {}

    public function index(): View
    {
        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        return view('finance.index', [
            'summary' => $this->reports->summary(['from' => $from, 'to' => $to]),
            'currency' => FinancialSetting::getValue('currency', Setting::getValue('currency', 'TZS')),
            'recentTransactions' => FinancialTransaction::query()
                ->with(['category:id,name,name_en,name_sw', 'member:id,first_name,middle_name,last_name,membership_number'])
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->limit(8)
                ->get(),
            'types' => FinancialTransaction::TYPES,
        ]);
    }

    public function categories(string $type): View|RedirectResponse
    {
        $this->authorizeType($type);

        if ($type === 'offering') {
            return redirect()->route('finance.offering-categories.index');
        }

        return view('finance.categories.index', [
            'type' => $type,
            'categories' => FinanceCategory::query()
                ->where('type', $type)
                ->withCount('transactions')
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function createCategory(string $type): View|RedirectResponse
    {
        $this->authorizeType($type);

        if ($type === 'offering') {
            return redirect()->route('finance.offering-categories.create');
        }

        return view('finance.categories.form', [
            'type' => $type,
            'category' => new FinanceCategory,
        ]);
    }

    public function storeCategory(StoreFinanceCategoryRequest $request, string $type): RedirectResponse
    {
        $this->authorizeType($type);

        if ($type === 'offering') {
            return redirect()->route('finance.offering-categories.index');
        }

        FinanceCategory::query()->create([
            ...$request->validated(),
            'type' => $type,
            'created_by' => Auth::id(),
        ]);

        return redirect()
            ->route('finance.categories.index', $type)
            ->with('success', __('finance.category_created'));
    }

    public function editCategory(string $type, FinanceCategory $category): View|RedirectResponse
    {
        $this->authorizeType($type);
        $this->ensureCategoryType($category, $type);

        if ($type === 'offering') {
            return redirect()->route('finance.offering-categories.edit', $category);
        }

        return view('finance.categories.form', compact('type', 'category'));
    }

    public function updateCategory(
        StoreFinanceCategoryRequest $request,
        string $type,
        FinanceCategory $category,
    ): RedirectResponse {
        $this->authorizeType($type);
        $this->ensureCategoryType($category, $type);

        if ($type === 'offering') {
            return redirect()->route('finance.offering-categories.index');
        }

        $category->update($request->validated());

        return redirect()
            ->route('finance.categories.index', $type)
            ->with('success', __('finance.category_updated'));
    }

    public function toggleCategory(string $type, FinanceCategory $category): RedirectResponse
    {
        $this->authorizeType($type);
        $this->ensureCategoryType($category, $type);

        if ($type === 'offering') {
            return redirect()->route('finance.offering-categories.index');
        }

        $category->update(['is_active' => ! $category->is_active]);

        return back()->with('success', __('finance.category_status_updated'));
    }

    public function report(Request $request): View
    {
        $filters = $this->reportFilters($request);

        return view('finance.reports.index', [
            ...$this->reports->summary($filters),
            'currency' => FinancialSetting::getValue('currency', Setting::getValue('currency', 'TZS')),
            'filters' => $filters,
            'categories' => FinanceCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'name_en', 'name_sw', 'type']),
            'campaigns' => FinancialCampaign::query()->orderBy('name')->get(['id', 'name', 'status']),
            'types' => FinancialTransaction::TYPES,
        ]);
    }

    public function exportReport(Request $request): StreamedResponse
    {
        $filters = $this->reportFilters($request);
        $transactions = FinancialTransaction::query()
            ->with([
                'category:id,name,name_en,name_sw',
                'campaign:id,name',
                'member:id,first_name,middle_name,last_name,membership_number',
            ])
            ->whereIn('financial_transactions.status', ['approved', 'reversed'])
            ->whereBetween('financial_transactions.transaction_date', [$filters['from'], $filters['to']])
            ->when($filters['category_id'], fn ($query) => $query->where('financial_transactions.category_id', $filters['category_id']))
            ->when($filters['campaign_id'], fn ($query) => $query->where('financial_transactions.campaign_id', $filters['campaign_id']))
            ->when($filters['type'], fn ($query) => $query->where('financial_transactions.type', $filters['type']))
            ->when($filters['payment_method'], fn ($query) => $query->where('financial_transactions.payment_method', $filters['payment_method']))
            ->orderBy('id');

        return response()->streamDownload(function () use ($transactions): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                throw new \RuntimeException('Unable to open the report output stream.');
            }

            fputcsv($output, [
                __('finance.transaction_number'),
                __('finance.receipt_number'),
                __('finance.type'),
                __('finance.category'),
                __('finance.campaign'),
                __('finance.member'),
                __('finance.payee'),
                __('finance.transaction_date'),
                __('finance.amount'),
                __('finance.currency'),
                __('finance.payment_method'),
                __('finance.status'),
            ], ',', '"', '');

            foreach ($transactions->lazyById(500) as $transaction) {
                $row = [
                    $transaction->transaction_number,
                    $transaction->receipt_number,
                    __('finance.types.'.$transaction->type),
                    $transaction->category?->localizedName(),
                    $transaction->campaign?->name,
                    $transaction->is_anonymous ? __('finance.anonymous') : $transaction->member_number,
                    $transaction->payee,
                    $transaction->transaction_date->toDateString(),
                    $transaction->amount,
                    $transaction->currency,
                    __('finance.payment_methods.'.$transaction->payment_method),
                    __('finance.statuses.'.$transaction->status),
                ];

                fputcsv($output, array_map(
                    fn (?string $value): string => $this->csvSanitizer->escape($value),
                    array_map(static fn (mixed $value): ?string => $value === null ? null : (string) $value, $row),
                ), ',', '"', '');
            }

            fclose($output);
        }, 'financial-report-'.$filters['from'].'-'.$filters['to'].'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{from: string, to: string, category_id: int|null, type: string|null}
     */
    private function reportFilters(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'category_id' => ['nullable', 'integer', 'exists:finance_categories,id'],
            'type' => ['nullable', Rule::in(FinancialTransaction::TYPES)],
            'period' => ['nullable', Rule::in(['daily', 'weekly', 'monthly', 'quarterly', 'annually', 'custom'])],
            'payment_method' => ['nullable', Rule::in(['cash', 'bank_transfer', 'mobile_money', 'cheque', 'other'])],
            'campaign_id' => ['nullable', 'integer', 'exists:financial_campaigns,id'],
        ]);

        $period = $validated['period']
            ?? (isset($validated['from']) || isset($validated['to']) ? 'custom' : 'monthly');
        $today = now();
        [$from, $to] = match ($period) {
            'daily' => [$today->toDateString(), $today->toDateString()],
            'weekly' => [$today->copy()->startOfWeek()->toDateString(), $today->copy()->endOfWeek()->toDateString()],
            'monthly' => [$today->copy()->startOfMonth()->toDateString(), $today->toDateString()],
            'quarterly' => [$today->copy()->startOfQuarter()->toDateString(), $today->toDateString()],
            'annually' => [$today->copy()->startOfYear()->toDateString(), $today->toDateString()],
            default => [
                $validated['from'] ?? $today->copy()->startOfMonth()->toDateString(),
                $validated['to'] ?? $today->toDateString(),
            ],
        };

        $filters = [
            'from' => $from,
            'to' => $to,
            'period' => $period,
            'category_id' => isset($validated['category_id']) ? (int) $validated['category_id'] : null,
            'campaign_id' => isset($validated['campaign_id']) ? (int) $validated['campaign_id'] : null,
            'type' => $validated['type'] ?? null,
            'payment_method' => $validated['payment_method'] ?? null,
        ];

        if ($filters['campaign_id'] !== null && $filters['type'] !== null && $filters['type'] !== 'contribution') {
            throw ValidationException::withMessages([
                'campaign_id' => __('finance.campaign_filter_requires_contribution'),
            ]);
        }

        if ($filters['to'] < $filters['from']) {
            throw ValidationException::withMessages([
                'to' => __('finance.invalid_report_period'),
            ]);
        }

        return $filters;
    }

    private function authorizeType(string $type): void
    {
        abort_unless(in_array($type, FinancialTransaction::TYPES, true), 404);
    }

    private function ensureCategoryType(FinanceCategory $category, string $type): void
    {
        abort_unless($category->type === $type, 404);
    }
}
