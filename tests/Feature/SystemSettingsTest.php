<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\AppSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

function systemSettingsTestUser(array $permissions = ['manage settings']): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return $user;
}

it('renders categorized settings for authorized administrators without exposing them to other users', function () {
    $administrator = systemSettingsTestUser();

    $this->actingAs($administrator)
        ->get(route('settings.edit'))
        ->assertOk()
        ->assertSee(__('settings.general'))
        ->assertSee(__('settings.church_information'))
        ->assertSee(__('settings.logo_settings'))
        ->assertSee(__('settings.favicon_settings'))
        ->assertSee('name="section" value="general"', false)
        ->assertSee('name="section" value="church"', false);

    $unauthorized = systemSettingsTestUser(['view finances']);

    $this->actingAs($unauthorized)
        ->get(route('settings.edit'))
        ->assertForbidden();
});

it('renders settings categories in English and Kiswahili', function () {
    $administrator = systemSettingsTestUser();
    $this->actingAs($administrator);

    Setting::setValue('system_language', 'en', 'general');
    $this->get(route('settings.edit'))
        ->assertOk()
        ->assertSee('General settings')
        ->assertSee('Church information')
        ->assertSee('Church address');

    Setting::setValue('system_language', 'sw', 'general');
    $this->get(route('settings.edit'))
        ->assertOk()
        ->assertSee('Mipangilio ya jumla')
        ->assertSee('Taarifa za kanisa')
        ->assertSee('Anuani ya kanisa');
});

it('saves general settings and makes them available through AppSettings', function () {
    $administrator = systemSettingsTestUser();

    $this->actingAs($administrator)
        ->put(route('settings.update'), [
            'section' => 'general',
            'system_name' => 'KKKT Dodoma',
            'system_language' => 'en',
            'timezone' => 'Africa/Dar_es_Salaam',
            'currency' => 'TZS',
            'date_format' => 'd/m/Y',
            'time_format' => 'H:i',
            'community_code_prefix' => 'DOD',
        ])
        ->assertRedirect(route('settings.edit').'#general-settings')
        ->assertSessionHas('success', __('settings.settings_saved'));

    $this->assertDatabaseHas('settings', [
        'key' => 'system_name',
        'value' => 'KKKT Dodoma',
        'group' => 'general',
    ]);

    expect(app(AppSettings::class)->systemName())->toBe('KKKT Dodoma')
        ->and(app(AppSettings::class)->locale())->toBe('en')
        ->and(app(AppSettings::class)->timezone())->toBe('Africa/Dar_es_Salaam')
        ->and(app(AppSettings::class)->currency())->toBe('TZS')
        ->and(app(AppSettings::class)->dateFormat())->toBe('d/m/Y')
        ->and(app(AppSettings::class)->timeFormat())->toBe('H:i')
        ->and(Setting::getValue('community_code_prefix'))->toBe('DOD');

    $this->actingAs($administrator)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('KKKT Dodoma');
});

it('saves official church information using the settings consumed by church documents', function () {
    $administrator = systemSettingsTestUser();

    $this->actingAs($administrator)
        ->put(route('settings.update'), [
            'section' => 'church',
            'church_name' => 'KKKT Usharika wa Dodoma',
            'church_short_name' => 'Dodoma-North',
            'church_address' => 'Sokoine Road, Dodoma',
            'church_phone' => '+255 26 232 1000',
            'church_email' => 'office@example.org',
        ])
        ->assertRedirect(route('settings.edit').'#church-information');

    expect(Setting::getValue('church_name'))->toBe('KKKT Usharika wa Dodoma')
        ->and(Setting::getValue('church_short_name'))->toBe('DODOMA-NORTH')
        ->and(Setting::getValue('system_address'))->toBe('Sokoine Road, Dodoma')
        ->and(Setting::getValue('system_phone'))->toBe('+255 26 232 1000')
        ->and(Setting::getValue('system_email'))->toBe('office@example.org')
        ->and(app(AppSettings::class)->churchName())->toBe('KKKT Usharika wa Dodoma')
        ->and(app(AppSettings::class)->churchAddress())->toBe('Sokoine Road, Dodoma')
        ->and(app(AppSettings::class)->churchPhone())->toBe('+255 26 232 1000')
        ->and(app(AppSettings::class)->churchEmail())->toBe('office@example.org');

    $this->actingAs($administrator)
        ->get(route('settings.edit'))
        ->assertOk()
        ->assertSee('KKKT Usharika wa Dodoma');
});

it('uploads and replaces church logos while removing only the previous managed file', function () {
    Storage::fake('public');
    $administrator = systemSettingsTestUser();

    $this->actingAs($administrator)
        ->put(route('settings.update'), [
            'section' => 'logo',
            'church_logo' => UploadedFile::fake()->image('church.png'),
        ])
        ->assertRedirect(route('settings.edit').'#logo-settings');

    $previousLogo = Setting::getValue('church_logo');
    Storage::disk('public')->assertExists($previousLogo);
    $this->assertDatabaseHas('settings', ['key' => 'church_logo', 'value' => $previousLogo]);

    $this->actingAs($administrator)
        ->put(route('settings.update'), [
            'section' => 'logo',
            'church_logo' => UploadedFile::fake()->image('replacement.webp'),
        ])
        ->assertRedirect(route('settings.edit').'#logo-settings');

    $replacementLogo = Setting::getValue('church_logo');
    expect($replacementLogo)->not->toBe($previousLogo);
    Storage::disk('public')->assertMissing($previousLogo);
    Storage::disk('public')->assertExists($replacementLogo);

    $this->actingAs($administrator)
        ->get(route('settings.edit'))
        ->assertOk()
        ->assertSee(Storage::disk('public')->url($replacementLogo), false);
});

it('removes the church logo and favicon safely and reflects favicon changes in the browser layout', function () {
    Storage::fake('public');
    $administrator = systemSettingsTestUser();

    $this->actingAs($administrator)->put(route('settings.update'), [
        'section' => 'logo',
        'church_logo' => UploadedFile::fake()->image('church.png'),
    ]);
    $logoPath = Setting::getValue('church_logo');

    $this->actingAs($administrator)->put(route('settings.update'), [
        'section' => 'favicon',
        'church_favicon' => UploadedFile::fake()->image('favicon.png'),
    ])->assertRedirect(route('settings.edit').'#favicon-settings');
    $faviconPath = Setting::getValue('church_favicon');

    Storage::disk('public')->assertExists($logoPath);
    Storage::disk('public')->assertExists($faviconPath);

    $this->actingAs($administrator)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('rel="icon"', false)
        ->assertSee('type="image/png"', false)
        ->assertSee(Storage::disk('public')->url($faviconPath), false);

    $this->actingAs($administrator)->put(route('settings.update'), [
        'section' => 'logo',
        'remove_church_logo' => '1',
    ]);
    $this->actingAs($administrator)->put(route('settings.update'), [
        'section' => 'favicon',
        'remove_church_favicon' => '1',
    ]);

    expect(Setting::getValue('church_logo'))->toBeNull()
        ->and(Setting::getValue('church_favicon'))->toBeNull()
        ->and(app(AppSettings::class)->customFaviconUrl())->toBeNull()
        ->and(app(AppSettings::class)->faviconUrl())->toContain('/favicon.svg');
    Storage::disk('public')->assertMissing($logoPath);
    Storage::disk('public')->assertMissing($faviconPath);

    $this->actingAs($administrator)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('href="'.asset('favicon.svg').'"', false)
        ->assertSee('type="image/svg+xml"', false);
});

it('accepts ICO favicons and publishes the correct browser icon type', function () {
    Storage::fake('public');
    $administrator = systemSettingsTestUser();
    $favicon = UploadedFile::fake()->createWithContent(
        'church-icon.ico',
        hex2bin('000001000100010100000100200030000000160000002800000001000000020000000100200000000000040000000000000000000000000000000000000000000000000000000000000000ffffff0000000000'),
    );

    $this->actingAs($administrator)
        ->put(route('settings.update'), [
            'section' => 'favicon',
            'church_favicon' => $favicon,
        ])
        ->assertRedirect(route('settings.edit').'#favicon-settings');

    $faviconPath = Setting::getValue('church_favicon');
    Storage::disk('public')->assertExists($faviconPath);
    expect(app(AppSettings::class)->faviconMimeType())->toBe('image/x-icon');

    $this->actingAs($administrator)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('type="image/x-icon"', false)
        ->assertSee(Storage::disk('public')->url($faviconPath), false);
});

it('rejects invalid branding files and conflicting replace and remove actions', function () {
    Storage::fake('public');
    $administrator = systemSettingsTestUser();

    $this->actingAs($administrator)
        ->from(route('settings.edit'))
        ->put(route('settings.update'), [
            'section' => 'logo',
            'church_logo' => UploadedFile::fake()->create('church.svg', 10, 'image/svg+xml'),
        ])
        ->assertSessionHasErrors('church_logo');

    $this->actingAs($administrator)
        ->from(route('settings.edit'))
        ->put(route('settings.update'), [
            'section' => 'favicon',
            'church_favicon' => UploadedFile::fake()->create('favicon.svg', 10, 'image/svg+xml'),
        ])
        ->assertSessionHasErrors('church_favicon');

    $this->actingAs($administrator)
        ->from(route('settings.edit'))
        ->put(route('settings.update'), [
            'section' => 'logo',
            'church_logo' => UploadedFile::fake()->image('church.png'),
            'remove_church_logo' => '1',
        ])
        ->assertSessionHasErrors('church_logo');

    $this->assertDatabaseMissing('settings', ['key' => 'church_logo']);
    $this->assertDatabaseMissing('settings', ['key' => 'church_favicon']);
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('does not delete files outside the managed branding directories', function () {
    Storage::fake('public');
    Storage::disk('public')->put('other-assets/logo.png', 'preserve');
    Setting::setValue('church_logo', 'other-assets/logo.png', 'branding');
    $administrator = systemSettingsTestUser();

    $this->actingAs($administrator)
        ->put(route('settings.update'), [
            'section' => 'logo',
            'remove_church_logo' => '1',
        ])
        ->assertRedirect(route('settings.edit').'#logo-settings');

    expect(Setting::getValue('church_logo'))->toBeNull();
    Storage::disk('public')->assertExists('other-assets/logo.png');
});

it('rejects logo and favicon files that exceed their size limits', function () {
    Storage::fake('public');
    $administrator = systemSettingsTestUser();

    $this->actingAs($administrator)
        ->from(route('settings.edit'))
        ->put(route('settings.update'), [
            'section' => 'logo',
            'church_logo' => UploadedFile::fake()->image('large-logo.png')->size(2049),
        ])
        ->assertSessionHasErrors('church_logo');

    $this->actingAs($administrator)
        ->from(route('settings.edit'))
        ->put(route('settings.update'), [
            'section' => 'favicon',
            'church_favicon' => UploadedFile::fake()->image('large-favicon.png')->size(513),
        ])
        ->assertSessionHasErrors('church_favicon');

    $this->assertDatabaseMissing('settings', ['key' => 'church_logo']);
    $this->assertDatabaseMissing('settings', ['key' => 'church_favicon']);
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('validates church contact details and protects settings updates by permission', function () {
    $administrator = systemSettingsTestUser();
    $unauthorized = systemSettingsTestUser(['view finances']);

    $this->actingAs($administrator)
        ->from(route('settings.edit'))
        ->put(route('settings.update'), [
            'section' => 'church',
            'church_name' => 'KKKT Dodoma',
            'church_short_name' => 'DOD',
            'church_email' => 'not-an-email',
            'church_phone' => 'phone-number',
        ])
        ->assertSessionHasErrors(['church_email', 'church_phone']);

    $this->actingAs($unauthorized)
        ->put(route('settings.update'), [
            'section' => 'church',
            'church_name' => 'Unauthorized update',
            'church_short_name' => 'BAD',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('settings', ['key' => 'church_name', 'value' => 'Unauthorized update']);
});
