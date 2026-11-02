<?php

use App\Models\Setting;
use App\Services\AppSettings;
use Illuminate\Support\Facades\DB;

it('serves repeated setting reads from one cached query', function () {
    Setting::setValue('church_name', 'KKKT Dodoma');
    Setting::flushCache();

    DB::enableQueryLog();
    Setting::getValue('church_name');
    Setting::getValue('church_name');
    Setting::getValue('system_name', 'fallback');
    $settingQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from "settings"') || str_contains($query['query'], 'from `settings`'));

    expect($settingQueries)->toHaveCount(1)
        ->and(Setting::getValue('system_name', 'fallback'))->toBe('fallback');
});

it('invalidates the cached settings when a setting is saved', function () {
    Setting::setValue('church_name', 'Old name');
    expect(Setting::getValue('church_name'))->toBe('Old name');

    Setting::setValue('church_name', 'New name');

    expect(Setting::getValue('church_name'))->toBe('New name')
        ->and(app(AppSettings::class)->churchName())->toBe('New name');
});

it('invalidates the cached settings when a setting is deleted', function () {
    Setting::setValue('church_name', 'KKKT Dodoma');
    expect(Setting::getValue('church_name'))->toBe('KKKT Dodoma');

    Setting::query()->where('key', 'church_name')->get()->each->delete();

    expect(Setting::getValue('church_name'))->toBeNull();
});
