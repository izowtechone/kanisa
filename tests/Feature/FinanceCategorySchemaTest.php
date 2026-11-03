<?php

use App\Models\FinanceCategory;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\Services\FinancialReportService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

function financeSchemaTestUser(array $permissions = ['view finances', 'view financial reports']): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return $user;
}

function financeSchemaTestCategory(User $creator, string $code, array $attributes = []): FinanceCategory
{
    return FinanceCategory::query()->create(array_merge([
        'type' => 'offering',
        'code' => $code,
        'name' => $code,
        'created_by' => $creator->id,
    ], $attributes));
}

function financeSchemaTestTransaction(User $creator, FinanceCategory $category, string $amount, int $sequence): FinancialTransaction
{
    return FinancialTransaction::query()->create([
        'transaction_number' => 'KKKT-TXN-2026-'.str_pad((string) (900000 + $sequence), 6, '0', STR_PAD_LEFT),
        'receipt_number' => 'KKKT-RCPT-2026-'.str_pad((string) (900000 + $sequence), 6, '0', STR_PAD_LEFT),
        'idempotency_key' => (string) Str::uuid(),
        'payload_hash' => hash('sha256', 'finance-schema-'.$sequence),
        'type' => $category->type,
        'category_id' => $category->id,
        'created_by' => $creator->id,
        'amount' => $amount,
        'currency' => 'TZS',
        'payment_method' => 'cash',
        'status' => 'approved',
        'transaction_date' => '2026-11-02',
    ]);
}

it('has the bilingual category columns the finance queries depend on', function () {
    expect(Schema::hasColumns('finance_categories', ['id', 'name', 'name_en', 'name_sw']))->toBeTrue();
});

it('loads the finance dashboard with bilingual categories', function () {
    $user = financeSchemaTestUser();
    financeSchemaTestCategory($user, 'OFFERING-A', ['name_en' => 'Thanksgiving', 'name_sw' => 'Shukrani']);

    $this->actingAs($user)->get(route('finance.index'))->assertOk();
});

it('aggregates report totals per category and type using the bilingual columns', function () {
    $user = financeSchemaTestUser();
    $thanks = financeSchemaTestCategory($user, 'OFFERING-A', ['name_en' => 'Thanksgiving', 'name_sw' => 'Shukrani']);
    $legacy = financeSchemaTestCategory($user, 'OFFERING-B', ['name' => 'AHADI']);
    $other = financeSchemaTestCategory($user, 'INCOME-A', ['type' => 'other_income', 'name' => 'Rent']);
    financeSchemaTestTransaction($user, $thanks, '100.00', 1);
    financeSchemaTestTransaction($user, $thanks, '50.00', 2);
    financeSchemaTestTransaction($user, $legacy, '30.00', 3);
    financeSchemaTestTransaction($user, $other, '20.00', 4);

    $rows = app(FinancialReportService::class)
        ->summary(['from' => '2026-11-01', 'to' => '2026-11-30'])['byCategory']
        ->keyBy('id');

    expect($rows)->toHaveCount(3)
        ->and((float) $rows[$thanks->id]->total)->toBe(150.0)
        ->and($rows[$thanks->id]->type)->toBe('offering')
        ->and($rows[$thanks->id]->name_sw)->toBe('Shukrani')
        ->and((float) $rows[$legacy->id]->total)->toBe(30.0)
        ->and((float) $rows[$other->id]->total)->toBe(20.0)
        ->and($rows[$other->id]->type)->toBe('other_income');
});

it('shows category names in English and Kiswahili on the report', function () {
    $user = financeSchemaTestUser();
    $category = financeSchemaTestCategory($user, 'OFFERING-A', ['name_en' => 'Thanksgiving', 'name_sw' => 'Shukrani']);
    financeSchemaTestTransaction($user, $category, '100.00', 1);
    $query = ['from' => '2026-11-01', 'to' => '2026-11-30'];

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->get(route('finance.reports.index', $query))->assertOk()->assertSee('Thanksgiving');

    $this->withSession(['locale' => 'sw'])
        ->get(route('finance.reports.index', $query))->assertOk()->assertSee('Shukrani');
});

it('falls back to the stored name for categories without translations', function () {
    $user = financeSchemaTestUser();
    $category = financeSchemaTestCategory($user, 'OFFERING-B', ['name' => 'AHADI']);
    financeSchemaTestTransaction($user, $category, '30.00', 1);

    expect($category->localizedName('en'))->toBe('AHADI')
        ->and($category->localizedName('sw'))->toBe('AHADI');

    $this->actingAs($user)
        ->withSession(['locale' => 'sw'])
        ->get(route('finance.reports.index', ['from' => '2026-11-01', 'to' => '2026-11-30']))
        ->assertOk()
        ->assertSee('AHADI');
});

it('keeps historical transactions linked after a category is renamed', function () {
    $user = financeSchemaTestUser(['view finances', 'view financial reports']);
    $category = financeSchemaTestCategory($user, 'OFFERING-B', ['name' => 'AHADI']);
    $transaction = financeSchemaTestTransaction($user, $category, '30.00', 1);

    $category->update(['name_en' => 'Pledge', 'name_sw' => 'Ahadi']);

    expect($transaction->fresh()->category->localizedName('sw'))->toBe('Ahadi')
        ->and($transaction->fresh()->category_id)->toBe($category->id)
        ->and($transaction->fresh()->amount)->toBe('30.00');
});
