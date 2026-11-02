<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

it('does not seed a default administrator without explicit credentials', function () {
    config(['services.initial_admin' => [
        'name' => null,
        'email' => null,
        'password' => null,
    ]]);

    expect(fn () => app(DatabaseSeeder::class)->run())
        ->toThrow(RuntimeException::class, 'Set INITIAL_ADMIN_NAME');

    $this->assertDatabaseCount('users', 0);
});

it('creates one configured super administrator without resetting its password on reseed', function () {
    config(['services.initial_admin' => [
        'name' => 'Church Administrator',
        'email' => 'admin@example.org',
        'password' => 'A-unique-secure-password',
    ]]);

    app(DatabaseSeeder::class)->run();

    $administrator = User::query()->where('email', 'admin@example.org')->firstOrFail();
    $existingPasswordHash = $administrator->password;

    expect($administrator->name)->toBe('Church Administrator');
    expect($administrator->hasRole('Super Admin'))->toBeTrue();
    expect(Hash::check('A-unique-secure-password', $existingPasswordHash))->toBeTrue();

    config(['services.initial_admin.password' => 'A-different-secure-password']);

    app(DatabaseSeeder::class)->run();

    $administrator->refresh();

    $this->assertDatabaseCount('users', 1);
    expect($administrator->hasRole('Super Admin'))->toBeTrue();
    expect(Hash::check('A-unique-secure-password', $administrator->password))->toBeTrue();
});
