<?php

use App\Models\Setting;
use App\Models\User;

test('authenticated layout renders the page loader with the translated status text', function (string $language, string $message) {
    Setting::setValue('system_language', $language);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('id="kkkt-page-loader"', false)
        ->assertSee('id="kkkt-page-status"', false)
        ->assertSee('data-message="'.$message.'"', false);
})->with([
    'english' => ['en', 'Loading...'],
    'kiswahili' => ['sw', 'Inapakia taarifa...'],
]);

test('guest layout renders the page loader', function () {
    Setting::setValue('system_language', 'sw');

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('id="kkkt-page-loader"', false)
        ->assertSee('data-message="Inapakia taarifa..."', false);
});

test('loading translations exist in both languages', function () {
    foreach (['loading', 'saving', 'failed', 'no_results'] as $key) {
        expect(trans("loading.$key", [], 'en'))->not->toBe("loading.$key")
            ->and(trans("loading.$key", [], 'sw'))->not->toBe("loading.$key");
    }
});

test('loader stylesheet uses the brand colour and respects reduced motion', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('.kkkt-loader')
        ->toContain('#25b09b')
        ->toContain('prefers-reduced-motion: reduce');
});
