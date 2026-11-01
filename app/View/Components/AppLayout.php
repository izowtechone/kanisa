<?php

namespace App\View\Components;

use App\Models\Setting;
use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    public function __construct(public ?string $title = null) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        $settings = Setting::query()
            ->whereIn('key', ['system_name', 'church_name'])
            ->pluck('value', 'key');

        $pageTitle = $this->title ?? match (true) {
            request()->routeIs('dashboard') => __('dashboard.title'),
            request()->routeIs('members.deleted.*') => __('members.deleted_title'),
            request()->routeIs('members.create') => __('members.form_create_title'),
            request()->routeIs('members.edit') => __('members.form_edit_title'),
            request()->routeIs('members.import.*') => __('members.bulk_title'),
            request()->routeIs('members.*') => __('members.title'),
            request()->routeIs('departments.create') => __('departments.create_title'),
            request()->routeIs('departments.edit') => __('departments.edit_title'),
            request()->routeIs('departments.*') => __('departments.title'),
            request()->routeIs('groups.create') => __('groups.create_title'),
            request()->routeIs('groups.edit') => __('groups.edit_title'),
            request()->routeIs('groups.members.*') => __('groups.members_title'),
            request()->routeIs('groups.*') => __('groups.title'),
            request()->routeIs('finance.categories.*') => __('finance.categories_title'),
            request()->routeIs('finance.campaigns.create') => __('finance.campaign_create_title'),
            request()->routeIs('finance.campaigns.edit') => __('finance.campaign_edit_title'),
            request()->routeIs('finance.campaigns.*') => __('finance.campaigns_title'),
            request()->routeIs('finance.transactions.create') => __('finance.transaction_create_title'),
            request()->routeIs('finance.transactions.*') => __('finance.transactions_title'),
            request()->routeIs('finance.envelopes.*') => __('finance.envelopes_title'),
            request()->routeIs('finance.imports.*') => __('finance.import_title'),
            request()->routeIs('finance.receipts.settings.*') => __('finance.receipt_settings_title'),
            request()->routeIs('finance.receipts.*') => __('finance.receipts_title'),
            request()->routeIs('finance.reports.*') => __('finance.reports_title'),
            request()->routeIs('finance.settings.*') => __('finance.settings_title'),
            request()->routeIs('finance.sms.settings.*') => __('finance.sms_settings_title'),
            request()->routeIs('finance.sms.*') => __('finance.sms_title'),
            request()->routeIs('finance.*') => __('finance.title'),
            request()->routeIs('receipts.verify') => __('finance.receipt_verification_title'),
            request()->routeIs('settings.*') => __('members.settings_title'),
            request()->routeIs('profile.*') => __('dashboard.profile'),
            default => config('app.name', 'KKKT CMS'),
        };

        return view('layouts.app', [
            'systemName' => $settings->get('system_name') ?: config('app.name', 'KKKT CMS'),
            'churchName' => $settings->get('church_name'),
            'pageTitle' => $pageTitle,
        ]);
    }
}
