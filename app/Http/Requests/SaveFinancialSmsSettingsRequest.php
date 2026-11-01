<?php

namespace App\Http\Requests;

use App\Models\FinancialSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveFinancialSmsSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage SMS settings') ?? false;
    }

    public function rules(): array
    {
        return [
            'beem_enabled' => ['required', 'boolean'],
            'beem_test_mode' => ['required', 'boolean'],
            'beem_language' => ['required', Rule::in(['en', 'sw'])],
            'beem_sender_id' => ['nullable', 'string', 'max:11', 'regex:/^[A-Za-z0-9 ]+$/'],
            'beem_api_key' => ['nullable', 'string', 'max:500'],
            'beem_api_secret' => ['nullable', 'string', 'max:500'],
            'clear_beem_credentials' => ['sometimes', 'boolean'],
            'beem_auto_offering' => ['required', 'boolean'],
            'beem_auto_contribution' => ['required', 'boolean'],
            'beem_auto_other_income' => ['required', 'boolean'],
            'beem_low_credit_threshold' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'beem_template_offering_en' => ['required', 'string', 'max:500'],
            'beem_template_offering_sw' => ['required', 'string', 'max:500'],
            'beem_template_contribution_en' => ['required', 'string', 'max:500'],
            'beem_template_contribution_sw' => ['required', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->boolean('clear_beem_credentials')
                    && ($this->filled('beem_api_key') || $this->filled('beem_api_secret'))) {
                    $validator->errors()->add('clear_beem_credentials', __('finance.clear_or_replace_credentials'));
                }

                if ($this->filled('beem_api_key') !== $this->filled('beem_api_secret')) {
                    $validator->errors()->add('beem_api_secret', __('finance.credentials_must_be_paired'));
                }

                $enabled = $this->boolean('beem_enabled');
                $testMode = $this->boolean('beem_test_mode');
                $senderId = $this->input('beem_sender_id') ?? FinancialSetting::getValue('beem_sender_id');

                if ($enabled && trim((string) $senderId) === '') {
                    $validator->errors()->add('beem_sender_id', __('finance.sender_id_required'));
                }

                $apiKey = $this->boolean('clear_beem_credentials')
                    ? ''
                    : ($this->input('beem_api_key') ?? FinancialSetting::getValue('beem_api_key'));
                $apiSecret = $this->boolean('clear_beem_credentials')
                    ? ''
                    : ($this->input('beem_api_secret') ?? FinancialSetting::getValue('beem_api_secret'));

                if ($enabled && ! $testMode
                    && (trim((string) $apiKey) === '' || trim((string) $apiSecret) === '')) {
                    $validator->errors()->add('beem_api_key', __('finance.credentials_required_live'));
                }

                foreach ([
                    'beem_template_offering_en',
                    'beem_template_offering_sw',
                    'beem_template_contribution_en',
                    'beem_template_contribution_sw',
                ] as $field) {
                    if ($validator->errors()->has($field) || ! $this->filled($field)) {
                        continue;
                    }

                    preg_match_all('/\{([^{}]+)\}/', (string) $this->input($field), $matches);

                    foreach ($matches[1] as $placeholder) {
                        if (! in_array($placeholder, ['amount', 'receipt_number', 'church_name', 'currency'], true)) {
                            $validator->errors()->add($field, __('finance.sms_template_placeholder_invalid'));

                            break;
                        }
                    }
                }
            },
        ];
    }
}
