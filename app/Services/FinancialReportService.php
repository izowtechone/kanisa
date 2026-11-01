<?php

namespace App\Services;

use App\Models\FinancialTransaction;
use Illuminate\Database\Eloquent\Builder;

class FinancialReportService
{
    /**
     * @param  array{from: string, to: string, category_id?: int|null, campaign_id?: int|null, type?: string|null, payment_method?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function summary(array $filters): array
    {
        $query = $this->filteredQuery($filters);
        $totals = (clone $query)
            ->selectRaw("COALESCE(SUM(CASE WHEN type <> 'expense' THEN amount * ledger_sign ELSE 0 END), 0) as income")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'expense' THEN amount * ledger_sign ELSE 0 END), 0) as expenses")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'expense' THEN amount * ledger_sign * -1 ELSE amount * ledger_sign END), 0) as net")
            ->first();

        $transactions = (clone $query)
            ->with(['category:id,name', 'member:id,first_name,middle_name,last_name,membership_number'])
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        $byCategory = (clone $query)
            ->join('finance_categories', 'financial_transactions.category_id', '=', 'finance_categories.id')
            ->selectRaw('finance_categories.id, finance_categories.name, financial_transactions.type')
            ->selectRaw('SUM(financial_transactions.amount * financial_transactions.ledger_sign) as total')
            ->groupBy('finance_categories.id', 'finance_categories.name', 'financial_transactions.type')
            ->orderBy('finance_categories.name')
            ->get();

        $byPaymentMethod = (clone $query)
            ->selectRaw('payment_method, SUM(amount * ledger_sign) as total')
            ->groupBy('payment_method')
            ->orderBy('payment_method')
            ->get();

        $byCampaign = (clone $query)
            ->join('financial_campaigns', 'financial_transactions.campaign_id', '=', 'financial_campaigns.id')
            ->where('financial_transactions.type', 'contribution')
            ->selectRaw('financial_campaigns.id, financial_campaigns.name')
            ->selectRaw('SUM(financial_transactions.amount * financial_transactions.ledger_sign) as total')
            ->groupBy('financial_campaigns.id', 'financial_campaigns.name')
            ->orderBy('financial_campaigns.name')
            ->get();

        return [
            'income' => (string) $totals->income,
            'expenses' => (string) $totals->expenses,
            'net' => (string) $totals->net,
            'transactions' => $transactions,
            'byCategory' => $byCategory,
            'byPaymentMethod' => $byPaymentMethod,
            'byCampaign' => $byCampaign,
        ];
    }

    /**
     * @param  array{from: string, to: string, category_id?: int|null, campaign_id?: int|null, type?: string|null, payment_method?: string|null}  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        return FinancialTransaction::query()
            ->whereIn('financial_transactions.status', ['approved', 'reversed'])
            ->whereBetween('financial_transactions.transaction_date', [$filters['from'], $filters['to']])
            ->when(
                $filters['category_id'] ?? null,
                fn (Builder $query, int $categoryId): Builder => $query->where('financial_transactions.category_id', $categoryId),
            )
            ->when(
                $filters['campaign_id'] ?? null,
                fn (Builder $query, int $campaignId): Builder => $query->where('financial_transactions.campaign_id', $campaignId),
            )
            ->when(
                $filters['type'] ?? null,
                fn (Builder $query, string $type): Builder => $query->where('financial_transactions.type', $type),
            )
            ->when(
                $filters['payment_method'] ?? null,
                fn (Builder $query, string $paymentMethod): Builder => $query->where('financial_transactions.payment_method', $paymentMethod),
            );
    }
}
