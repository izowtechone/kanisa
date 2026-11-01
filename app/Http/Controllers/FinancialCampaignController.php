<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFinancialCampaignRequest;
use App\Models\FinancialCampaign;
use App\Models\FinancialSetting;
use App\Models\FinancialTransaction;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FinancialCampaignController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'closed'])],
        ]);
        $campaigns = FinancialCampaign::query()
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($nestedQuery) use ($search): void {
                    $nestedQuery->where('name', 'like', '%'.$search.'%')
                        ->orWhere('code', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $totals = FinancialTransaction::query()
            ->selectRaw('campaign_id, SUM(amount * ledger_sign) as total_collected')
            ->whereIn('campaign_id', $campaigns->getCollection()->pluck('id'))
            ->where('type', 'contribution')
            ->whereIn('status', ['approved', 'reversed'])
            ->groupBy('campaign_id')
            ->pluck('total_collected', 'campaign_id');

        return view('finance.campaigns.index', [
            'campaigns' => $campaigns,
            'totals' => $totals,
            'filters' => $filters,
            'currency' => FinancialSetting::getValue('currency', Setting::getValue('currency', 'TZS')),
        ]);
    }

    public function create(): View
    {
        return view('finance.campaigns.form', [
            'campaign' => new FinancialCampaign,
        ]);
    }

    public function store(StoreFinancialCampaignRequest $request): RedirectResponse
    {
        $campaign = FinancialCampaign::query()->create([
            ...$request->validated(),
            'created_by' => Auth::id(),
            'status' => 'active',
        ]);

        return redirect()
            ->route('finance.campaigns.show', $campaign)
            ->with('success', __('finance.campaign_created'));
    }

    public function show(FinancialCampaign $campaign): View
    {
        $transactions = $campaign->transactions()
            ->with(['member:id,membership_number,first_name,middle_name,last_name'])
            ->where('type', 'contribution')
            ->whereIn('status', ['approved', 'reversed'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(25);

        $totalCollected = $campaign->transactions()
            ->where('type', 'contribution')
            ->whereIn('status', ['approved', 'reversed'])
            ->selectRaw('COALESCE(SUM(amount * ledger_sign), 0) as total')
            ->value('total');

        return view('finance.campaigns.show', [
            'campaign' => $campaign,
            'transactions' => $transactions,
            'totalCollected' => (string) $totalCollected,
            'currency' => FinancialSetting::getValue('currency', Setting::getValue('currency', 'TZS')),
        ]);
    }

    public function edit(FinancialCampaign $campaign): View
    {
        return view('finance.campaigns.form', compact('campaign'));
    }

    public function update(StoreFinancialCampaignRequest $request, FinancialCampaign $campaign): RedirectResponse
    {
        $campaign->update($request->validated());

        return redirect()
            ->route('finance.campaigns.show', $campaign)
            ->with('success', __('finance.campaign_updated'));
    }

    public function toggle(FinancialCampaign $campaign): RedirectResponse
    {
        $campaign->update([
            'status' => $campaign->status === 'active' ? 'closed' : 'active',
        ]);

        return back()->with('success', __('finance.campaign_status_updated'));
    }
}
