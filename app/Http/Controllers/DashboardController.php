<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $systemName = Setting::getValue('system_name', 'KKKT CMS');
        $churchName = Setting::getValue('church_name');
        $memberStatistics = null;
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

        return view('dashboard', compact(
            'systemName',
            'churchName',
            'memberStatistics',
            'recentMembers',
        ));
    }
}
