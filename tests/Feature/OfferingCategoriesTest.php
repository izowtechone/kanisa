<?php

use App\Models\FinanceCategory;
use App\Models\FinancialTransaction;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

function offeringCategoriesTestUser(array $permissions = []): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return $user;
}

function offeringCategoriesTestCategory(User $creator, array $attributes = []): FinanceCategory
{
    return FinanceCategory::query()->create(array_merge([
        'type' => 'offering',
        'code' => 'OFFERING-GENERAL',
        'name' => 'General Offering',
        'name_en' => 'General Offering',
        'name_sw' => 'Sadaka ya Jumla',
        'description' => 'Regular congregational giving.',
        'created_by' => $creator->id,
    ], $attributes));
}

function offeringCategoriesTestPayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'OFFERING-MISSION',
        'name_en' => 'Mission Offering',
        'name_sw' => 'Sadaka ya Misheni',
        'description' => 'Support for the church mission.',
    ], $overrides);
}

it('creates and edits an offering category with bilingual names', function () {
    $user = offeringCategoriesTestUser(['create offering categories', 'edit offering categories']);

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->post(route('finance.offering-categories.store'), offeringCategoriesTestPayload())
        ->assertRedirect(route('finance.offering-categories.index'))
        ->assertSessionHas('success', __('finance.offering_category_created'));

    $category = FinanceCategory::query()->where('code', 'OFFERING-MISSION')->firstOrFail();
    $this->assertDatabaseHas('finance_categories', [
        'id' => $category->id,
        'type' => 'offering',
        'name' => 'Mission Offering',
        'name_en' => 'Mission Offering',
        'name_sw' => 'Sadaka ya Misheni',
        'description' => 'Support for the church mission.',
    ]);

    $this->put(route('finance.offering-categories.update', $category), offeringCategoriesTestPayload([
        'code' => 'OFFERING-MISSION',
        'name_en' => 'World Mission Offering',
        'name_sw' => 'Sadaka ya Misheni Duniani',
    ]))
        ->assertRedirect(route('finance.offering-categories.index'))
        ->assertSessionHas('success', __('finance.offering_category_updated'));

    expect($category->fresh()->localizedName('sw'))->toBe('Sadaka ya Misheni Duniani');
    $this->assertDatabaseHas('finance_categories', [
        'id' => $category->id,
        'name' => 'World Mission Offering',
        'name_en' => 'World Mission Offering',
        'name_sw' => 'Sadaka ya Misheni Duniani',
    ]);
});

it('rejects duplicate offering category codes and names', function () {
    $user = offeringCategoriesTestUser(['create offering categories']);
    offeringCategoriesTestCategory($user);

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->from(route('finance.offering-categories.create'))
        ->post(route('finance.offering-categories.store'), offeringCategoriesTestPayload([
            'code' => 'OFFERING-GENERAL',
            'name_en' => 'Special Offering',
            'name_sw' => 'Sadaka Maalum',
        ]))
        ->assertSessionHasErrors([
            'code' => __('validation.unique', [
                'attribute' => __('finance.category_code', [], 'en'),
            ], 'en'),
        ]);

    $this->withSession(['locale' => 'sw'])
        ->post(route('finance.offering-categories.store'), offeringCategoriesTestPayload([
            'code' => 'OFFERING-UNIQUE',
            'name_en' => 'General Offering',
            'name_sw' => 'Sadaka ya Jumla',
        ]))
        ->assertSessionHasErrors([
            'name_en' => __('validation.unique', [
                'attribute' => __('finance.category_name_en', [], 'sw'),
            ], 'sw'),
            'name_sw' => __('validation.unique', [
                'attribute' => __('finance.category_name_sw', [], 'sw'),
            ], 'sw'),
        ]);

    $this->assertDatabaseCount('finance_categories', 1);
});

it('shows searchable offering categories and requires view permission', function () {
    $user = offeringCategoriesTestUser(['view offering categories']);
    $category = offeringCategoriesTestCategory($user);
    offeringCategoriesTestCategory($user, [
        'code' => 'OFFERING-OTHER',
        'name' => 'Other Offering',
        'name_en' => 'Other Offering',
        'name_sw' => 'Sadaka Nyingine',
    ]);

    $this->actingAs($user)
        ->withSession(['locale' => 'sw'])
        ->get(route('finance.offering-categories.index', ['search' => 'General']))
        ->assertOk()
        ->assertSee($category->code)
        ->assertSee('Sadaka ya Jumla')
        ->assertSee($category->description)
        ->assertSee('Vikundi vya sadaka')
        ->assertDontSee('OFFERING-OTHER')
        ->assertDontSee(route('finance.offering-categories.create'));

    $unauthorized = offeringCategoriesTestUser(['view finances']);
    $this->actingAs($unauthorized)
        ->get(route('finance.offering-categories.index'))
        ->assertForbidden();
});

it('renders offering category management labels in English and Kiswahili', function () {
    $user = offeringCategoriesTestUser([
        'view offering categories',
        'create offering categories',
    ]);

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->get(route('finance.offering-categories.create'))
        ->assertOk()
        ->assertSee('Add offering category')
        ->assertSee('Category name in Kiswahili')
        ->assertSee('Category name in English');

    $this->withSession(['locale' => 'sw'])
        ->get(route('finance.offering-categories.create'))
        ->assertOk()
        ->assertSee('Ongeza kikundi cha sadaka')
        ->assertSee('Jina la kikundi kwa Kiswahili')
        ->assertSee('Jina la kikundi kwa Kiingereza');
});

it('redirects existing offering category links to the bilingual manager', function () {
    $user = offeringCategoriesTestUser([
        'view finances',
        'view offering categories',
        'manage finance categories',
        'create offering categories',
        'edit offering categories',
    ]);
    $category = offeringCategoriesTestCategory($user);

    $this->actingAs($user)
        ->get(route('finance.categories.index', 'offering'))
        ->assertRedirect(route('finance.offering-categories.index'));

    $this->get(route('finance.categories.create', 'offering'))
        ->assertRedirect(route('finance.offering-categories.create'));

    $this->get(route('finance.categories.edit', ['type' => 'offering', 'category' => $category]))
        ->assertRedirect(route('finance.offering-categories.edit', $category));
});

it('requires separate create, edit, and activation permissions', function () {
    $viewer = offeringCategoriesTestUser(['view offering categories']);
    $category = offeringCategoriesTestCategory($viewer);

    $this->actingAs($viewer)
        ->post(route('finance.offering-categories.store'), offeringCategoriesTestPayload())
        ->assertForbidden();

    $this->put(route('finance.offering-categories.update', $category), offeringCategoriesTestPayload([
        'code' => $category->code,
        'name_en' => 'Changed Name',
        'name_sw' => 'Jina Jipya',
    ]))
        ->assertForbidden();

    $this->patch(route('finance.offering-categories.toggle', $category))
        ->assertForbidden();

    $this->assertDatabaseCount('finance_categories', 1);
});

it('localizes active categories in all offering entry forms', function () {
    $user = offeringCategoriesTestUser([
        'create Sunday offerings',
        'view finances',
        'manage offerings',
        'record envelope offerings',
        'import envelope offerings',
    ]);
    $activeCategory = offeringCategoriesTestCategory($user);
    offeringCategoriesTestCategory($user, [
        'code' => 'OFFERING-INACTIVE',
        'name' => 'Inactive Offering',
        'name_en' => 'Inactive Offering',
        'name_sw' => 'Sadaka Isiyotumika',
        'is_active' => false,
    ]);

    $this->actingAs($user)
        ->withSession(['locale' => 'sw'])
        ->get(route('finance.sunday-offerings.create'))
        ->assertOk()
        ->assertSee('Sadaka ya Jumla')
        ->assertDontSee('General Offering')
        ->assertDontSee('Sadaka Isiyotumika');

    $this->withSession(['locale' => 'en'])
        ->get(route('finance.transactions.create', 'offering'))
        ->assertOk()
        ->assertSee('General Offering')
        ->assertDontSee('Sadaka ya Jumla')
        ->assertDontSee('Inactive Offering');

    $this->withSession(['locale' => 'sw'])
        ->get(route('finance.envelopes.create'))
        ->assertOk()
        ->assertSee('Sadaka ya Jumla')
        ->assertDontSee('Sadaka Isiyotumika');

    $this->withSession(['locale' => 'en'])
        ->get(route('finance.imports.create'))
        ->assertOk()
        ->assertSee('General Offering')
        ->assertDontSee('Inactive Offering');

    expect($activeCategory->localizedName('en'))->toBe('General Offering')
        ->and($activeCategory->localizedName('sw'))->toBe('Sadaka ya Jumla');
});

it('rejects inactive categories when submitting a Sunday Offering', function () {
    $user = offeringCategoriesTestUser(['create Sunday offerings']);
    $category = offeringCategoriesTestCategory($user, ['is_active' => false]);
    $member = Member::query()->create([
        'membership_number' => 'KKKT-OFFERING-0001',
        'envelope_number' => '00301',
        'first_name' => 'Asha',
        'middle_name' => null,
        'last_name' => 'Mushi',
        'gender' => 'Female',
        'date_of_birth' => '1990-05-10',
        'phone' => '0712345678',
        'email' => null,
        'address' => null,
        'membership_date' => '2020-01-01',
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->from(route('finance.sunday-offerings.create'))
        ->post(route('finance.sunday-offerings.store'), [
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->id,
            'category_id' => $category->id,
            'amount' => '125.00',
            'payment_method' => 'cash',
            'transaction_date' => now()->toDateString(),
        ])
        ->assertSessionHasErrors([
            'category_id' => __('finance.category_type_mismatch'),
        ]);

    $this->assertDatabaseCount('financial_transactions', 0);
});

it('deactivates categories without changing historical transaction relationships', function () {
    $user = offeringCategoriesTestUser(['toggle offering categories', 'view offering categories']);
    $category = offeringCategoriesTestCategory($user, [
        'name_en' => null,
        'name_sw' => null,
    ]);
    $transaction = FinancialTransaction::query()->create([
        'transaction_number' => 'KKKT-TXN-2026-009901',
        'receipt_number' => 'KKKT-RCPT-2026-009901',
        'idempotency_key' => (string) Str::uuid(),
        'payload_hash' => hash('sha256', 'offering-category-history'),
        'type' => 'offering',
        'category_id' => $category->id,
        'created_by' => $user->id,
        'amount' => '125.00',
        'currency' => 'TZS',
        'payment_method' => 'cash',
        'status' => 'approved',
        'transaction_date' => now()->toDateString(),
    ]);

    $this->actingAs($user)
        ->patch(route('finance.offering-categories.toggle', $category))
        ->assertRedirect()
        ->assertSessionHas('success', __('finance.offering_category_status_updated'));

    expect($category->fresh()->is_active)->toBeFalse()
        ->and($transaction->fresh()->category_id)->toBe($category->id)
        ->and($transaction->fresh()->category->localizedName('en'))->toBe('General Offering')
        ->and($transaction->fresh()->category->localizedName('sw'))->toBe('General Offering');
    $this->assertModelExists($category->fresh());
});

it('escapes category names and descriptions in the management page', function () {
    $user = offeringCategoriesTestUser(['view offering categories']);
    offeringCategoriesTestCategory($user, [
        'name' => '<script>alert(1)</script>',
        'name_en' => '<script>alert(1)</script>',
        'name_sw' => '<script>alert(1)</script>',
        'description' => '<img src=x onerror=alert(1)>',
    ]);

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->get(route('finance.offering-categories.index'))
        ->assertOk()
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});
