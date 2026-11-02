<?php

namespace App\Http\Requests;

use App\Services\SmsSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveFinancialSmsSettingsRequest extends FormRequest
{
    private const array TEMPLATE_FIELDS = [
        'sms_template_offering_en',
        'sms_template_offering_sw',
        'sms_template_contribution_en',
        'sms_template_contribution_sw',
        'sms_template_sunday_offering_en',
        'sms_template_sunday_offering_sw',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can('manage SMS settings') ?? false;
    }

    public function rules(): array
    {
        return [
            'swala_enabled' => ['required', 'boolean'],
            'swala_test_mode' => ['required', 'boolean'],
            'sms_language' => ['required', Rule::in(['en', 'sw'])],
            'swala_sender_id' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9 ._-]+$/'],
            'swala_api_key' => ['nullable', 'string', 'max:200', 'regex:/^swl_(test|live)_\S+$/'],
            'swala_webhook_secret' => ['nullable', 'string', 'max:500'],
            'clear_swala_credentials' => ['sometimes', 'boolean'],
            'sms_auto_offering' => ['required', 'boolean'],
            'sms_auto_contribution' => ['required', 'boolean'],
            'sms_auto_other_income' => ['required', 'boolean'],
            ...array_fill_keys(self::TEMPLATE_FIELDS, ['required', 'string', 'max:500']),
        ];
    }

    public function messages(): array
    {
        return [
            'swala_api_key.regex' => __('finance.swala_api_key_format'),
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->boolean('clear_swala_credentials')
                    && ($this->filled('swala_api_key') || $this->filled('swala_webhook_secret'))) {
                    $validator->errors()->add('clear_swala_credentials', __('finance.clear_or_replace_credentials'));
                }

                $enabled = $this->boolean('swala_enabled');
                $testMode = $this->boolean('swala_test_mode');
                $senderId = $this->input('swala_sender_id') ?? SmsSettings::senderId();

                if ($enabled && ! $testMode && trim((string) $senderId) === '') {
                    $validator->errors()->add('swala_sender_id', __('finance.sender_id_required'));
                }

                $apiKey = $this->boolean('clear_swala_credentials')
                    ? ''
                    : ($this->input('swala_api_key') ?: SmsSettings::apiKey());

                if ($enabled && ! $testMode && trim((string) $apiKey) === '') {
                    $validator->errors()->add('swala_api_key', __('finance.credentials_required_live'));
                }

                foreach (self::TEMPLATE_FIELDS as $field) {
                    if ($validator->errors()->has($field) || ! $this->filled($field)) {
                        continue;
                    }

                    preg_match_all('/\{([^{}]+)\}/', (string) $this->input($field), $matches);
                    $allowedPlaceholders = str_starts_with($field, 'sms_template_sunday_offering_')
                        ? ['amount', 'receipt_number', 'church_name', 'currency', 'member_name']
                        : ['amount', 'receipt_number', 'church_name', 'currency'];

                    foreach ($matches[1] as $placeholder) {
                        if (! in_array($placeholder, $allowedPlaceholders, true)) {
                            $validator->errors()->add($field, __('finance.sms_template_placeholder_invalid'));

                            break;
                        }
                    }
                }
            },
        ];
    }
}
