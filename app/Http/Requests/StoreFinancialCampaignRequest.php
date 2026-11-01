<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFinancialCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage contributions') ?? false;
    }

    public function rules(): array
    {
        $campaign = $this->route('campaign');

        return [
            'code' => [
                'required',
                'string',
                'max:40',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('financial_campaigns', 'code')->ignore($campaign),
            ],
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('financial_campaigns', 'name')->ignore($campaign),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->filled('starts_on') && $this->filled('ends_on')
                    && $this->input('ends_on') < $this->input('starts_on')) {
                    $validator->errors()->add('ends_on', __('finance.campaign_end_before_start'));
                }
            },
        ];
    }
}
