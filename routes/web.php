
<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\FinancialCampaignController;
use App\Http\Controllers\FinancialImportController;
use App\Http\Controllers\FinancialReceiptController;
use App\Http\Controllers\FinancialSettingsController;
use App\Http\Controllers\FinancialSmsController;
use App\Http\Controllers\FinancialTransactionController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberImportController;
use App\Http\Controllers\MemberLifecycleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SystemSettingsController;
use Illuminate\Support\Facades\Route;

// Wageni wanaelekezwa kwenye ukurasa wa kuingia
Route::get('/', function () {
    return redirect()->route('login');
});

// Kurasa za mfumo zinalindwa kwa uthibitisho wa kuingia
Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::get('/members', [MemberController::class, 'index'])
        ->middleware('can:view members')
        ->name('members.index');

    Route::get('/members/deleted', [MemberLifecycleController::class, 'deleted'])
        ->name('members.deleted.index');

    Route::post('/members/deleted/bulk-restore', [MemberLifecycleController::class, 'bulkRestore'])
        ->middleware('can:restore members')
        ->name('members.deleted.bulk-restore');

    Route::post('/members/deleted/{member}/restore', [MemberLifecycleController::class, 'restore'])
        ->withTrashed()
        ->middleware('can:restore members')
        ->name('members.restore');

    Route::post('/members/bulk-delete', [MemberLifecycleController::class, 'bulkDestroy'])
        ->middleware('can:delete members')
        ->name('members.bulk-destroy');

    Route::delete('/members/{member}', [MemberLifecycleController::class, 'destroy'])
        ->middleware('can:delete members')
        ->name('members.destroy');

    Route::get('/members/export', [MemberController::class, 'export'])
        ->middleware('can:export members')
        ->name('members.export');

    Route::get('/members/create', [MemberController::class, 'create'])
        ->middleware('can:create members')
        ->name('members.create');

    Route::post('/members', [MemberController::class, 'store'])
        ->middleware('can:create members')
        ->name('members.store');

    Route::get('/members/{member}/edit', [MemberController::class, 'edit'])
        ->middleware('can:edit members')
        ->name('members.edit');

    Route::put('/members/{member}', [MemberController::class, 'update'])
        ->middleware('can:edit members')
        ->name('members.update');

    Route::get('/members/bulk-upload', [MemberImportController::class, 'create'])
        ->middleware('can:import members')
        ->name('members.import.create');

    Route::get('/members/import-template/{format}', [MemberImportController::class, 'template'])
        ->whereIn('format', ['csv', 'xlsx'])
        ->middleware('can:import members')
        ->name('members.import.template');

    Route::post('/members/imports', [MemberImportController::class, 'store'])
        ->middleware('can:import members')
        ->name('members.import.store');

    Route::get('/members/imports/{memberImport:uuid}', [MemberImportController::class, 'show'])
        ->middleware('can:import members')
        ->name('members.import.show');

    Route::post('/members/imports/{memberImport:uuid}/confirm', [MemberImportController::class, 'confirm'])
        ->middleware('can:import members')
        ->name('members.import.confirm');

    Route::get('/members/imports/{memberImport:uuid}/report', [MemberImportController::class, 'report'])
        ->middleware('can:import members')
        ->name('members.import.report');

    Route::get('/departments', [DepartmentController::class, 'index'])
        ->middleware('can:view departments')
        ->name('departments.index');

    Route::get('/departments/create', [DepartmentController::class, 'create'])
        ->middleware('can:create departments')
        ->name('departments.create');

    Route::post('/departments', [DepartmentController::class, 'store'])
        ->name('departments.store');

    Route::get('/departments/{department}/edit', [DepartmentController::class, 'edit'])
        ->middleware('can:edit departments')
        ->name('departments.edit');

    Route::get('/departments/{department}', [DepartmentController::class, 'show'])
        ->middleware('can:view departments')
        ->name('departments.show');

    Route::put('/departments/{department}', [DepartmentController::class, 'update'])
        ->name('departments.update');

    Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])
        ->middleware('can:delete departments')
        ->name('departments.destroy');

    Route::get('/groups', [GroupController::class, 'index'])
        ->middleware('can:view groups')
        ->name('groups.index');

    Route::get('/groups/create', [GroupController::class, 'create'])
        ->middleware('can:create groups')
        ->name('groups.create');

    Route::post('/groups', [GroupController::class, 'store'])
        ->name('groups.store');

    Route::get('/groups/{group}/members', [GroupController::class, 'members'])
        ->middleware('can:view groups')
        ->name('groups.members.index');

    Route::post('/groups/{group}/members', [GroupController::class, 'assignMembers'])
        ->name('groups.members.assign');

    Route::delete('/groups/{group}/members/{member}', [GroupController::class, 'removeMember'])
        ->scopeBindings()
        ->middleware('can:assign group members')
        ->name('groups.members.remove');

    Route::get('/groups/{group}/edit', [GroupController::class, 'edit'])
        ->middleware('can:edit groups')
        ->name('groups.edit');

    Route::get('/groups/{group}', [GroupController::class, 'show'])
        ->middleware('can:view groups')
        ->name('groups.show');

    Route::put('/groups/{group}', [GroupController::class, 'update'])
        ->name('groups.update');

    Route::delete('/groups/{group}', [GroupController::class, 'destroy'])
        ->middleware('can:delete groups')
        ->name('groups.destroy');

    Route::get('/finance', [FinanceController::class, 'index'])
        ->middleware('can:view finances')
        ->name('finance.index');

    Route::get('/finance/campaigns', [FinancialCampaignController::class, 'index'])
        ->middleware('can:manage contributions')
        ->name('finance.campaigns.index');

    Route::get('/finance/campaigns/create', [FinancialCampaignController::class, 'create'])
        ->middleware('can:manage contributions')
        ->name('finance.campaigns.create');

    Route::post('/finance/campaigns', [FinancialCampaignController::class, 'store'])
        ->middleware('can:manage contributions')
        ->name('finance.campaigns.store');

    Route::get('/finance/campaigns/{campaign}/edit', [FinancialCampaignController::class, 'edit'])
        ->middleware('can:manage contributions')
        ->name('finance.campaigns.edit');

    Route::put('/finance/campaigns/{campaign}', [FinancialCampaignController::class, 'update'])
        ->middleware('can:manage contributions')
        ->name('finance.campaigns.update');

    Route::patch('/finance/campaigns/{campaign}/toggle', [FinancialCampaignController::class, 'toggle'])
        ->middleware('can:manage contributions')
        ->name('finance.campaigns.toggle');

    Route::get('/finance/campaigns/{campaign}', [FinancialCampaignController::class, 'show'])
        ->middleware('can:manage contributions')
        ->name('finance.campaigns.show');

    Route::get('/finance/transactions', [FinancialTransactionController::class, 'index'])
        ->middleware('can:view finances')
        ->name('finance.transactions.index');

    Route::get('/finance/transactions/create/{type}', [FinancialTransactionController::class, 'create'])
        ->whereIn('type', ['offering', 'tithe', 'contribution', 'other_income', 'expense'])
        ->middleware('can:view finances')
        ->name('finance.transactions.create');

    Route::post('/finance/transactions/{type}', [FinancialTransactionController::class, 'store'])
        ->whereIn('type', ['offering', 'tithe', 'contribution', 'other_income', 'expense'])
        ->middleware('can:view finances')
        ->name('finance.transactions.store');

    Route::post('/finance/transactions/{transaction}/approve', [FinancialTransactionController::class, 'approve'])
        ->middleware('can:approve financial transactions')
        ->name('finance.transactions.approve');

    Route::post('/finance/transactions/{transaction}/void', [FinancialTransactionController::class, 'void'])
        ->middleware('can:void financial transactions')
        ->name('finance.transactions.void');

    Route::post('/finance/transactions/{transaction}/reverse', [FinancialTransactionController::class, 'reverse'])
        ->middleware('can:reverse financial transactions')
        ->name('finance.transactions.reverse');

    Route::get('/finance/transactions/{transaction}', [FinancialTransactionController::class, 'show'])
        ->middleware('can:view finances')
        ->name('finance.transactions.show');

    Route::get('/finance/envelopes', [FinancialTransactionController::class, 'envelopeCreate'])
        ->middleware('can:view finances')
        ->name('finance.envelopes.create');

    Route::get('/finance/envelopes/lookup', [FinancialTransactionController::class, 'lookupEnvelope'])
        ->middleware('can:view finances')
        ->name('finance.envelopes.lookup');

    Route::post('/finance/envelopes', [FinancialTransactionController::class, 'storeEnvelope'])
        ->middleware('can:view finances')
        ->name('finance.envelopes.store');

    Route::get('/finance/categories/{type}', [FinanceController::class, 'categories'])
        ->whereIn('type', ['offering', 'tithe', 'contribution', 'other_income', 'expense'])
        ->middleware('can:view finances')
        ->name('finance.categories.index');

    Route::get('/finance/categories/{type}/create', [FinanceController::class, 'createCategory'])
        ->whereIn('type', ['offering', 'tithe', 'contribution', 'other_income', 'expense'])
        ->middleware('can:manage finance categories')
        ->name('finance.categories.create');

    Route::post('/finance/categories/{type}', [FinanceController::class, 'storeCategory'])
        ->whereIn('type', ['offering', 'tithe', 'contribution', 'other_income', 'expense'])
        ->middleware('can:manage finance categories')
        ->name('finance.categories.store');

    Route::get('/finance/categories/{type}/{category}/edit', [FinanceController::class, 'editCategory'])
        ->whereIn('type', ['offering', 'tithe', 'contribution', 'other_income', 'expense'])
        ->middleware('can:manage finance categories')
        ->name('finance.categories.edit');

    Route::put('/finance/categories/{type}/{category}', [FinanceController::class, 'updateCategory'])
        ->whereIn('type', ['offering', 'tithe', 'contribution', 'other_income', 'expense'])
        ->middleware('can:manage finance categories')
        ->name('finance.categories.update');

    Route::patch('/finance/categories/{type}/{category}/toggle', [FinanceController::class, 'toggleCategory'])
        ->whereIn('type', ['offering', 'tithe', 'contribution', 'other_income', 'expense'])
        ->middleware('can:manage finance categories')
        ->name('finance.categories.toggle');

    Route::get('/finance/receipts', [FinancialReceiptController::class, 'index'])
        ->middleware('can:view receipts')
        ->name('finance.receipts.index');

    Route::get('/finance/receipts/settings', [FinancialSettingsController::class, 'editReceipts'])
        ->middleware('can:manage receipts')
        ->name('finance.receipts.settings.edit');

    Route::put('/finance/receipts/settings', [FinancialSettingsController::class, 'updateReceipts'])
        ->middleware('can:manage receipts')
        ->name('finance.receipts.settings.update');

    Route::get('/finance/receipts/{receiptNumber}', [FinancialReceiptController::class, 'show'])
        ->middleware('can:view receipts')
        ->name('finance.receipts.show');

    Route::get('/finance/reports', [FinanceController::class, 'report'])
        ->middleware('can:view financial reports')
        ->name('finance.reports.index');

    Route::get('/finance/reports/export', [FinanceController::class, 'exportReport'])
        ->middleware('can:export financial reports')
        ->name('finance.reports.export');

    Route::get('/finance/envelope-imports/template/{format}', [FinancialImportController::class, 'template'])
        ->whereIn('format', ['csv', 'xlsx'])
        ->middleware('can:import envelope offerings')
        ->name('finance.imports.template');

    Route::get('/finance/envelope-imports/create', [FinancialImportController::class, 'create'])
        ->middleware('can:import envelope offerings')
        ->name('finance.imports.create');

    Route::post('/finance/envelope-imports', [FinancialImportController::class, 'store'])
        ->middleware('can:import envelope offerings')
        ->name('finance.imports.store');

    Route::get('/finance/envelope-imports/{financialImport:uuid}', [FinancialImportController::class, 'show'])
        ->middleware('can:import envelope offerings')
        ->name('finance.imports.show');

    Route::post('/finance/envelope-imports/{financialImport:uuid}/confirm', [FinancialImportController::class, 'confirm'])
        ->middleware('can:import envelope offerings')
        ->name('finance.imports.confirm');

    Route::get('/finance/envelope-imports/{financialImport:uuid}/report', [FinancialImportController::class, 'report'])
        ->middleware('can:import envelope offerings')
        ->name('finance.imports.report');

    Route::get('/finance/settings', [FinancialSettingsController::class, 'edit'])
        ->middleware('can:manage financial settings')
        ->name('finance.settings.edit');

    Route::put('/finance/settings', [FinancialSettingsController::class, 'update'])
        ->middleware('can:manage financial settings')
        ->name('finance.settings.update');

    Route::get('/finance/sms/settings', [FinancialSettingsController::class, 'editSms'])
        ->middleware('can:manage SMS settings')
        ->name('finance.sms.settings.edit');

    Route::put('/finance/sms/settings', [FinancialSettingsController::class, 'updateSms'])
        ->middleware('can:manage SMS settings')
        ->name('finance.sms.settings.update');

    Route::get('/finance/sms', [FinancialSmsController::class, 'index'])
        ->middleware('can:view SMS history')
        ->name('finance.sms.index');

    Route::post('/finance/sms/{smsLog}/retry', [FinancialSmsController::class, 'retry'])
        ->middleware('can:retry failed SMS')
        ->name('finance.sms.retry');

    Route::post('/finance/sms/balance', [FinancialSmsController::class, 'balance'])
        ->middleware('can:manage SMS settings')
        ->name('finance.sms.balance');

    Route::post('/finance/sms/connection-test', [FinancialSettingsController::class, 'testSmsConnection'])
        ->middleware('can:manage SMS settings')
        ->name('finance.sms.connection-test');

    Route::post('/finance/sms/test', [FinancialSmsController::class, 'sendTest'])
        ->middleware('can:send test SMS')
        ->name('finance.sms.test');

    Route::post('/finance/sms/{smsLog}/delivery-report', [FinancialSmsController::class, 'checkDelivery'])
        ->middleware('can:view SMS history')
        ->name('finance.sms.delivery-report');

    Route::get('/settings', [SystemSettingsController::class, 'edit'])
        ->name('settings.edit');

    Route::put('/settings', [SystemSettingsController::class, 'update'])
        ->middleware('can:manage settings')
        ->name('settings.update');
});

Route::get('/receipts/verify/{receiptNumber}', [FinancialReceiptController::class, 'verify'])
    ->middleware('throttle:30,1')
    ->name('receipts.verify');

// Kurasa za wasifu zinalindwa
Route::middleware('auth')->group(function () {

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');

});

require __DIR__.'/auth.php';
