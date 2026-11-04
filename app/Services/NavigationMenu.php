<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Builds the sidebar from the routes that exist today, filtered by the user's permissions.
 * Modules without routes (events, attendance, visitors, expenses ledger pages) are intentionally absent.
 */
class NavigationMenu
{
    /**
     * @return array<int, array{key: string, label: string, active: bool, items: array<int, array{label: string, url: string, icon: string, active: bool}>}>
     */
    public function for(User $user, Request $request): array
    {
        $groups = [];

        foreach ($this->definition() as $group) {
            $items = [];

            foreach ($group['items'] as $item) {
                if (! $this->allowed($user, $item['can'], $item['role'] ?? null)) {
                    continue;
                }

                $items[] = [
                    'label' => __($item['label']),
                    'url' => route($item['route'], $item['parameters'] ?? []),
                    'icon' => $item['icon'],
                    'active' => $this->isActive($request, $item),
                ];
            }

            if ($items === []) {
                continue;
            }

            $groups[] = [
                'key' => $group['key'],
                'label' => __($group['label']),
                'active' => collect($items)->contains('active', true),
                'items' => $items,
            ];
        }

        return $groups;
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function allowed(User $user, array $permissions, ?string $role): bool
    {
        if ($role !== null && ! $user->hasRole($role)) {
            return false;
        }

        if ($permissions === []) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isActive(Request $request, array $item): bool
    {
        if (! $request->routeIs(...$item['active'])) {
            return false;
        }

        $type = $request->query('type');
        $routeType = $request->route('type');
        $currentType = is_string($routeType) ? $routeType : (is_string($type) ? $type : null);

        return match ($item['type_scope'] ?? null) {
            'ledger' => ! in_array($currentType, ['other_income', 'expense'], true),
            'other_income' => $currentType === 'other_income',
            'expense' => $currentType === 'expense',
            default => true,
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function definition(): array
    {
        return [
            [
                'key' => 'dashboard',
                'label' => 'navigation.group_dashboard',
                'items' => [
                    ['label' => 'navigation.dashboard', 'route' => 'dashboard', 'icon' => 'layout-dashboard', 'active' => ['dashboard'], 'can' => []],
                ],
            ],
            [
                'key' => 'members',
                'label' => 'navigation.group_members',
                'items' => [
                    ['label' => 'navigation.members_list', 'route' => 'members.index', 'icon' => 'users', 'active' => ['members.index', 'members.edit', 'members.deleted.*', 'members.import.*'], 'can' => ['view members']],
                    ['label' => 'navigation.member_register', 'route' => 'members.create', 'icon' => 'user-plus', 'active' => ['members.create'], 'can' => ['create members']],
                    ['label' => 'navigation.communities', 'route' => 'communities.index', 'icon' => 'home', 'active' => ['communities.*'], 'can' => ['view communities']],
                    ['label' => 'navigation.departments', 'route' => 'departments.index', 'icon' => 'building', 'active' => ['departments.*'], 'can' => ['view departments']],
                    ['label' => 'navigation.groups', 'route' => 'groups.index', 'icon' => 'network', 'active' => ['groups.*'], 'can' => ['view groups']],
                ],
            ],
            [
                'key' => 'finance',
                'label' => 'navigation.group_finance',
                'items' => [
                    ['label' => 'navigation.finance_overview', 'route' => 'finance.index', 'icon' => 'wallet', 'active' => ['finance.index'], 'can' => ['view finances']],
                    ['label' => 'navigation.sunday_offerings', 'route' => 'finance.sunday-offerings.index', 'icon' => 'church', 'active' => ['finance.sunday-offerings.*'], 'can' => ['view Sunday offerings']],
                    ['label' => 'navigation.offering_categories', 'route' => 'finance.offering-categories.index', 'icon' => 'sliders', 'active' => ['finance.offering-categories.*'], 'can' => ['view offering categories', 'manage finance categories']],
                    ['label' => 'navigation.offerings_contributions', 'route' => 'finance.transactions.index', 'icon' => 'hand-coins', 'active' => ['finance.transactions.*', 'finance.envelopes.*', 'finance.imports.*', 'finance.categories.*'], 'can' => ['view finances'], 'type_scope' => 'ledger'],
                    ['label' => 'navigation.campaigns', 'route' => 'finance.campaigns.index', 'icon' => 'megaphone', 'active' => ['finance.campaigns.*'], 'can' => ['manage contributions']],
                    ['label' => 'navigation.other_income', 'route' => 'finance.transactions.index', 'parameters' => ['type' => 'other_income'], 'icon' => 'trending-up', 'active' => ['finance.transactions.*', 'finance.categories.*'], 'can' => ['view finances'], 'type_scope' => 'other_income'],
                    ['label' => 'navigation.expenses', 'route' => 'finance.transactions.index', 'parameters' => ['type' => 'expense'], 'icon' => 'trending-down', 'active' => ['finance.transactions.*', 'finance.categories.*'], 'can' => ['view finances'], 'type_scope' => 'expense'],
                    ['label' => 'navigation.receipts', 'route' => 'finance.receipts.index', 'icon' => 'receipt', 'active' => ['finance.receipts.index', 'finance.receipts.show'], 'can' => ['view receipts']],
                    ['label' => 'navigation.financial_reports', 'route' => 'finance.reports.index', 'icon' => 'bar-chart', 'active' => ['finance.reports.*'], 'can' => ['view financial reports']],
                    ['label' => 'navigation.receipt_settings', 'route' => 'finance.receipts.settings.edit', 'icon' => 'sliders', 'active' => ['finance.receipts.settings.*'], 'can' => [], 'role' => User::SUPER_ADMIN_ROLE],
                    ['label' => 'navigation.financial_settings', 'route' => 'finance.settings.edit', 'icon' => 'settings', 'active' => ['finance.settings.*'], 'can' => [], 'role' => User::SUPER_ADMIN_ROLE],
                ],
            ],
            [
                'key' => 'administration',
                'label' => 'navigation.group_administration',
                'items' => [
                    ['label' => 'users.navigation', 'route' => 'users.index', 'icon' => 'shield', 'active' => ['users.*'], 'can' => [], 'role' => User::SUPER_ADMIN_ROLE],
                    ['label' => 'navigation.sms_history', 'route' => 'finance.sms.index', 'icon' => 'message-square', 'active' => ['finance.sms.index'], 'can' => ['view SMS history']],
                    ['label' => 'navigation.sms_campaigns', 'route' => 'finance.sms.campaigns.index', 'icon' => 'megaphone', 'active' => ['finance.sms.campaigns.*'], 'can' => ['view SMS campaigns']],
                    ['label' => 'navigation.system_settings', 'route' => 'settings.edit', 'icon' => 'settings', 'active' => ['settings.*', 'finance.sms.settings.*'], 'can' => [], 'role' => User::SUPER_ADMIN_ROLE],
                ],
            ],
        ];
    }
}
