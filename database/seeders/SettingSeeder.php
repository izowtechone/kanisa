<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'system_name' => [
                'value' => 'KKKT CMS',
                'group' => 'general',
            ],
            'church_name' => [
                'value' => 'Kanisa la Kiinjili la Kilutheri Tanzania',
                'group' => 'general',
            ],
            'system_language' => [
                'value' => 'sw',
                'group' => 'general',
            ],
            'system_email' => [
                'value' => '',
                'group' => 'contact',
            ],
            'system_phone' => [
                'value' => '',
                'group' => 'contact',
            ],
            'system_address' => [
                'value' => '',
                'group' => 'contact',
            ],
            'currency' => [
                'value' => 'TZS',
                'group' => 'general',
            ],
            'timezone' => [
                'value' => 'Africa/Dar_es_Salaam',
                'group' => 'general',
            ],
        ];

        foreach ($settings as $key => $setting) {
            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $setting['value'],
                    'group' => $setting['group'],
                ]
            );
        }

        Setting::firstOrCreate(
            ['key' => 'church_short_name'],
            [
                'value' => null,
                'group' => 'general',
            ],
        );

        Setting::firstOrCreate(
            ['key' => 'church_logo'],
            [
                'value' => null,
                'group' => 'general',
            ],
        );

        Setting::firstOrCreate(
            ['key' => 'community_code_prefix'],
            [
                'value' => 'JUM',
                'group' => 'general',
            ],
        );
    }
}
