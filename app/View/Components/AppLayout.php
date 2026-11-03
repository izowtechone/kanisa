<?php

namespace App\View\Components;

use App\Services\AppSettings;
use App\Services\NavigationMenu;
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
        $settings = app(AppSettings::class);

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
            request()->routeIs('communities.create') => __('communities.create_title'),
            request()->routeIs('communities.edit') => __('communities.edit_title'),
            request()->routeIs('communities.show') => __('communities.details_title'),
            request()->routeIs('communities.*') => __('communities.title'),
            request()->routeIs('finance.sunday-offerings.create') => __('finance.record_sunday_offering'),
            request()->routeIs('finance.sunday-offerings.show') => __('finance.sunday_offering_details'),
            request()->routeIs('finance.sunday-offerings.*') => __('finance.sunday_offerings_title'),
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
            request()->routeIs('settings.*') => __('settings.title'),
            request()->routeIs('profile.*') => __('dashboard.profile'),
            default => config('app.name', 'KKKT CMS'),
        };

        return view('layouts.app', [
            'systemName' => $settings->systemName(),
            'churchName' => $settings->churchName(),
            'pageTitle' => $pageTitle,
            'navigation' => request()->user()
                ? app(NavigationMenu::class)->for(request()->user(), request())
                : [],
        ]);
    }
}
