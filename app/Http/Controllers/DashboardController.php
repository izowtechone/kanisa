<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\FinancialSetting;
use App\Models\FinancialTransaction;
use App\Models\Member;
use App\Models\Setting;
use App\Services\FinancialAmountFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, FinancialAmountFormatter $amounts): View
    {
        $systemName = Setting::getValue('system_name', 'KKKT CMS');
        $churchName = Setting::getValue('church_name');
        $memberStatistics = null;
        $communityStatistics = null;
        $sundayOfferingStatistics = null;
        $recentMembers = collect();

        if ($request->user()?->can('view members')) {
            $monthStart = now()->startOfMonth();
            $nextMonthStart = $monthStart->copy()->addMonth();

            $memberStatistics = Member::query()
                ->selectRaw(
                    'COUNT(*) AS total_members,
                    COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS active_members,
                    COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS inactive_members,
                    COALESCE(SUM(CASE WHEN gender = ? THEN 1 ELSE 0 END), 0) AS male_members,
                    COALESCE(SUM(CASE WHEN gender = ? THEN 1 ELSE 0 END), 0) AS female_members,
                    COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END), 0) AS new_members_this_month',
                    ['active', 'inactive', 'Male', 'Female', $monthStart, $nextMonthStart],
                )
                ->first();

            $recentMembers = Member::query()
                ->select(['id', 'membership_number', 'first_name', 'middle_name', 'last_name', 'created_at'])
                ->latest('created_at')
                ->limit(5)
                ->get();
        }

        if ($request->user()?->can('view communities')) {
            $communityStatistics = [
                'active_communities' => Community::query()->where('status', 'active')->count(),
                'unassigned_active_members' => Member::query()
                    ->where('status', 'active')
                    ->whereNull('community_id')
                    ->count(),
            ];
        }

        $sundayOfferingCurrency = null;

        if ($request->user()?->can('view Sunday offerings')) {
            $monthStart = now()->startOfMonth()->toDateString();
            $nextMonthStart = now()->startOfMonth()->addMonth()->toDateString();

            $sundayOfferingStatistics = FinancialTransaction::query()
                ->where('source', FinancialTransaction::SOURCE_SUNDAY_OFFERING)
                ->where('type', 'offering')
                ->where('transaction_date', '>=', $monthStart)
                ->where('transaction_date', '<', $nextMonthStart)
                ->selectRaw(
                    "COALESCE(SUM(CASE WHEN status = 'approved' AND reversal_of_id IS NULL THEN 1 ELSE 0 END), 0) AS approved_count,
                    COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_count,
                    COALESCE(SUM(CASE WHEN status IN ('approved', 'reversed') THEN amount * ledger_sign ELSE 0 END), 0) AS approved_amount",
                )
                ->first();

            $sundayOfferingCurrency = FinancialSetting::getValue(
                'currency',
                Setting::getValue('currency', 'TZS'),
            ) ?? 'TZS';
        }

        $sundayOfferingAmount = $sundayOfferingStatistics === null
            ? null
            : $amounts->format($sundayOfferingStatistics->approved_amount, $sundayOfferingCurrency);

        return view('dashboard', compact(
            'systemName',
            'churchName',
            'memberStatistics',
            'communityStatistics',
            'sundayOfferingStatistics',
            'sundayOfferingCurrency',
            'sundayOfferingAmount',
            'recentMembers',
        ));
    }
}
