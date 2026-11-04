<?php

namespace App\Http\Requests;

use App\Services\AppSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveChurchSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('section')) {
            return;
        }

        $section = match (true) {
            $this->exists('church_short_name') || $this->exists('system_language') || $this->exists('community_code_prefix') => 'legacy',
            collect(['church_name', 'church_address', 'church_phone', 'church_email'])
                ->contains(fn (string $key): bool => $this->exists($key)) => 'church',
            $this->hasFile('church_logo') || $this->boolean('remove_church_logo') => 'logo',
            $this->hasFile('church_favicon') || $this->boolean('remove_church_favicon') => 'favicon',
            default => 'legacy',
        };

        $this->merge(['section' => $section]);
    }

    public function rules(): array
    {
        $section = $this->input('section');

        return [
            'section' => ['required', Rule::in(['general', 'church', 'logo', 'favicon', 'legacy'])],
            'system_name' => $section === 'general'
                ? ['required', 'string', 'max:100']
                : ['sometimes', 'string', 'max:100'],
            'system_language' => in_array($section, ['general', 'legacy'], true)
                ? ['required', Rule::in(AppSettings::LOCALES)]
                : ['sometimes', Rule::in(AppSettings::LOCALES)],
            'timezone' => $section === 'general'
                ? ['required', Rule::in(AppSettings::TIMEZONES)]
                : ['sometimes', Rule::in(AppSettings::TIMEZONES)],
            'currency' => $section === 'general'
                ? ['required', Rule::in(AppSettings::CURRENCIES)]
                : ['sometimes', Rule::in(AppSettings::CURRENCIES)],
            'date_format' => $section === 'general'
                ? ['required', Rule::in(AppSettings::DATE_FORMATS)]
                : ['sometimes', Rule::in(AppSettings::DATE_FORMATS)],
            'time_format' => $section === 'general'
                ? ['required', Rule::in(AppSettings::TIME_FORMATS)]
                : ['sometimes', Rule::in(AppSettings::TIME_FORMATS)],
            'community_code_prefix' => in_array($section, ['general', 'legacy'], true)
                ? ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$/']
                : ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$/'],
            'church_name' => $section === 'church'
                ? ['required', 'string', 'max:255']
                : ['sometimes', 'string', 'max:255'],
            'church_short_name' => in_array($section, ['church', 'legacy'], true)
                ? ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$/']
                : ['sometimes', 'string', 'max:80', 'regex:/^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$/'],
            'church_address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'church_phone' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[0-9+().\-\s]{6,30}$/'],
            'church_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'church_logo' => in_array($section, ['logo', 'legacy'], true)
                ? ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048']
                : ['prohibited'],
            'remove_church_logo' => in_array($section, ['logo', 'legacy'], true)
                ? ['sometimes', 'boolean']
                : ['prohibited'],
            'church_favicon' => $section === 'favicon'
                ? ['nullable', 'file', 'mimes:ico,png', 'extensions:ico,png', 'max:512']
                : ['prohibited'],
            'remove_church_favicon' => $section === 'favicon'
                ? ['sometimes', 'boolean']
                : ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('section') === 'logo' && $this->boolean('remove_church_logo') && $this->hasFile('church_logo')) {
                    $validator->errors()->add('church_logo', __('settings.choose_file_action'));
                }

                if ($this->input('section') === 'favicon' && $this->boolean('remove_church_favicon') && $this->hasFile('church_favicon')) {
                    $validator->errors()->add('church_favicon', __('settings.choose_file_action'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'system_name' => __('settings.system_name'),
            'system_language' => __('settings.system_language'),
            'timezone' => __('settings.timezone'),
            'currency' => __('settings.currency'),
            'date_format' => __('settings.date_format'),
            'time_format' => __('settings.time_format'),
            'community_code_prefix' => __('communities.code_prefix'),
            'church_name' => __('settings.church_name'),
            'church_short_name' => __('settings.church_short_name'),
            'church_address' => __('settings.church_address'),
            'church_phone' => __('settings.church_phone'),
            'church_email' => __('settings.church_email'),
            'church_logo' => __('settings.church_logo'),
            'church_favicon' => __('settings.church_favicon'),
        ];
    }
}
