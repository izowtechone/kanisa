<?php

use App\Models\Setting;
use App\Models\User;

test('web app manifest is valid and installable', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['name'])->toBe('KKKT CMS')
        ->and($manifest['short_name'])->toBe('KKKT CMS')
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['start_url'])->toBe('/')
        ->and($manifest['orientation'])->toBe('portrait');

    $purposes = collect($manifest['icons'])->pluck('purpose')->all();
    expect($purposes)->toContain('any')->toContain('maskable');

    foreach ($manifest['icons'] as $icon) {
        $path = public_path(ltrim($icon['src'], '/'));
        [$width, $height] = getimagesize($path);

        expect(file_exists($path))->toBeTrue()
            ->and("{$width}x{$height}")->toBe($icon['sizes'])
            ->and(mime_content_type($path))->toBe($icon['type']);
    }
});

test('apple touch icon is a 180px png', function () {
    [$width, $height, $type] = getimagesize(public_path('icons/apple-touch-icon.png'));

    expect([$width, $height, $type])->toBe([180, 180, IMAGETYPE_PNG]);
});

test('offline page is bilingual and states that internet is required', function () {
    $html = file_get_contents(public_path('offline.html'));

    expect($html)->toContain('internet connection is required')
        ->toContain('huduma ya mtandao inahitajika', ignoreCase: true)
        ->not->toContain('http://');
});

test('service worker only handles safe static assets', function () {
    $worker = file_get_contents(public_path('sw.js'));

    expect($worker)->toContain("request.method !== 'GET'")
        ->toContain("'/build/'")
        ->toContain('OFFLINE_URL')
        ->not->toContain('localStorage')
        ->not->toContain('Authorization')
        ->not->toContain('csrf');
});

test('application layouts link the manifest and mobile app metadata', function (string $language) {
    Setting::setValue('system_language', $language);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('rel="manifest"', false)
        ->assertSee('manifest.webmanifest', false)
        ->assertSee('name="theme-color"', false)
        ->assertSee('rel="apple-touch-icon"', false)
        ->assertSee('data-pwa-install', false);
})->with(['en', 'sw']);

test('login page links the manifest but has no install control', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('rel="manifest"', false)
        ->assertDontSee('data-pwa-install', false);
});

test('install strings exist in both languages', function () {
    foreach (['install_app', 'install_ios_hint', 'install_manual_hint', 'offline_notice'] as $key) {
        expect(trans("pwa.$key", [], 'en'))->not->toBe("pwa.$key")
            ->and(trans("pwa.$key", [], 'sw'))->not->toBe("pwa.$key");
    }
});

test('authenticated pages are sent without public caching headers', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

    expect($response->headers->get('Cache-Control'))->toContain('private')
        ->not->toContain('public');
});
